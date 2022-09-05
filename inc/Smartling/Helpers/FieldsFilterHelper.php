<?php

namespace Smartling\Helpers;

use JetBrains\PhpStorm\ExpectedValues;
use Smartling\Base\ExportedAPI;
use Smartling\Bootstrap;
use Smartling\Extensions\Acf\AcfDynamicSupport;
use Smartling\MonologWrapper\MonologWrapper;
use Smartling\Services\GlobalSettingsManager;
use Smartling\Settings\SettingsManager;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Vendor\Psr\Log\LoggerInterface;

/**
 * Filtering goes in 3 steps.
 * 1. Convert data into flat array
 * 2. Filter data with fields processors (detect image, related content)
 * 3. Filter data with profile settings filters (exclude, copy)
 * Class FieldsFilterHelper
 * @package Smartling\Helpers
 */
class FieldsFilterHelper
{
    const ARRAY_DIVIDER = '/';

    const FILTER_STRATEGY_UPLOAD = 'upload';

    const FILTER_STRATEGY_DOWNLOAD = 'download';

    private $acfDynamicSupport;
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var SettingsManager
     */
    private $settingsManager;

    /**
     * @return LoggerInterface
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * @return SettingsManager
     */
    public function getSettingsManager()
    {
        return $this->settingsManager;
    }

    /**
     * @param SettingsManager $settingsManager
     */
    public function setSettingsManager($settingsManager)
    {
        $this->settingsManager = $settingsManager;
    }

    /**
     * @param SettingsManager $settingsManager
     * @param AcfDynamicSupport $acfDynamicSupport
     */
    public function __construct(SettingsManager $settingsManager, AcfDynamicSupport $acfDynamicSupport)
    {
        $this->acfDynamicSupport = $acfDynamicSupport;
        $this->logger = MonologWrapper::getLogger(get_called_class());
        $this->setSettingsManager($settingsManager);
    }

    public function flattenArray(array $array, string $base = '', string $divider = self::ARRAY_DIVIDER): array
    {
        $output = [];
        foreach ($array as $key => $element) {
            $path = '' === $base ? $key : implode($divider, [$base, $key]);
            $output = array_merge($output, $this->processArrayElement($path, $element, $divider));
        }

        return $output;
    }

    /**
     * @param array  $flatArray
     * @param string $divider
     *
     * @return array
     */
    public function structurizeArray(array $flatArray, $divider = self::ARRAY_DIVIDER)
    {
        $output = [];

        foreach ($flatArray as $key => $element) {
            $pathElements = explode($divider, $key);
            $pointer = &$output;
            for ($i = 0; $i < (count($pathElements) - 1); $i++) {
                if (!isset($pointer[$pathElements[$i]])) {
                    $pointer[$pathElements[$i]] = [];
                }
                $pointer = &$pointer[$pathElements[$i]];
            }
            $pointer[end($pathElements)] = $element;
        }

        return $output;
    }

    /**
     * @param string $currentPath
     * @param mixed  $elementValue
     * @param string $divider
     *
     * @return array
     */
    private function processArrayElement($currentPath, $elementValue, $divider)
    {
        $valueType = gettype($elementValue);
        $result = [];
        switch ($valueType) {
            case 'array':
                $result = $this->flattenArray($elementValue, $currentPath, $divider);
                break;
            case 'NULL':
            case 'boolean':
            case 'string':
            case 'integer':
            case 'double':
                $result[$currentPath] = (string)$elementValue;
                break;
            case 'unknown type':
            case 'resource':
            case 'object':
            default:
                $message = vsprintf(
                    'Unsupported type \'%s\' found in scope for translation. Skipped. Contents: \'%s\'.',
                    [$valueType, var_export($elementValue, true)]
                );
                $this->getLogger()->warning($message);
        }

        return $result;
    }

    /**
     * @param array $sourceArray
     *
     * @return array
     */
    public function prepareSourceData(array $sourceArray)
    {
        if (array_key_exists('meta', $sourceArray) && is_array($sourceArray['meta'])) {
            $metadata = &$sourceArray['meta'];
            foreach ($metadata as $key => & $value) {
                if (is_array($value) && array_key_exists('entity', $metadata[$key]) &&
                    array_key_exists('meta', $metadata[$key])
                ) {
                    // nested object detected
                    $value = $this->prepareSourceData($metadata[$key]);
                }

                $value = maybe_unserialize($value);
            }
            unset ($value);
        }

        return $sourceArray;
    }

    /**
     * @param SubmissionEntity $submission
     * @param array            $data
     *
     * @return array
     */
    public function removeIgnoringFields(SubmissionEntity $submission, array $data)
    {
        $settingsManager = $this->getSettingsManager();
        ContentSerializationHelper::prepareFieldProcessorValues($settingsManager, $submission);
        $profile = $settingsManager->getSingleSettingsProfile($submission->getSourceBlogId());

        $data = $this->prepareSourceData($data);
        $data = $this->flattenArray($data);

        $settings = self::getFieldProcessingParams();
        $data = $this->removeFields($data, $settings['ignore'], $profile->getFilterFieldNameRegExp());
        $data = $this->structurizeArray($data);
        return $data;
    }

    public function processStringsBeforeEncoding(
        SubmissionEntity $submission,
        array $data,
        #[ExpectedValues([self::FILTER_STRATEGY_UPLOAD, self::FILTER_STRATEGY_DOWNLOAD])]
        string $strategy = self::FILTER_STRATEGY_UPLOAD
    ): array
    {
        $settingsManager = $this->getSettingsManager();
        ContentSerializationHelper::prepareFieldProcessorValues($settingsManager, $submission);
        $profile = $settingsManager->getSingleSettingsProfile($submission->getSourceBlogId());
        $data = $this->prepareSourceData($data);
        if ($strategy === self::FILTER_STRATEGY_UPLOAD) {
            $data = $this->acfDynamicSupport->removePreTranslationFields($data);
        }
        $data = $this->flattenArray($data);

        $settings = self::getFieldProcessingParams();
        $removeAsRegExp = $profile->getFilterFieldNameRegExp();
        $data = $this->removeFields($data, $settings['ignore'], $removeAsRegExp);

        $data = $this->passFieldProcessorsBeforeSendFilters($submission, $data);
        $data = $this->passConnectionProfileFilters($data, $strategy, $removeAsRegExp);

        return $data;
    }

    /**
     * @param array $data
     *
     * @return array
     */
    public function processStringsAfterDecoding(array $data)
    {
        return $this->structurizeArray($data);
    }

    public function filterValues(SubmissionEntity $submission, array $data, $strategy = self::FILTER_STRATEGY_UPLOAD)
    {
        return $this->processStringsAfterDecoding($this->processStringsBeforeEncoding($submission, $data, $strategy));
    }


    /**
     * Is used while downloading
     *
     * @param SubmissionEntity $submission
     * @param array            $originalValues
     * @param array            $translatedValues
     * @param bool             $applyFilters
     *
     * @return array
     */
    public function applyTranslatedValues(SubmissionEntity $submission, array $originalValues, array $translatedValues, $applyFilters = true)
    {
        $originalValues = $this->prepareSourceData($originalValues);
        $originalValues = $this->flattenArray($originalValues);

        $translatedValues = $this->prepareSourceData($translatedValues);
        $translatedValues = $this->flattenArray($translatedValues);

        $result = [];

        if (true === $applyFilters) {
            ContentSerializationHelper::prepareFieldProcessorValues($this->getSettingsManager(), $submission);

            $filteredOriginalValues = $this->filterArray($originalValues, $submission, self::FILTER_STRATEGY_DOWNLOAD);
            $result = array_merge($result, $filteredOriginalValues);
        } else {
            $result = $originalValues;
        }

        $result = array_merge($result, $translatedValues);

        return $this->structurizeArray($result);
    }

    /**
     * @param array            $array
     * @param SubmissionEntity $submission
     * @param string           $strategy
     *
     * @return array
     */
    private function filterArray(array $array, SubmissionEntity $submission, $strategy)
    {
        $settings = self::getFieldProcessingParams();
        $removeAsRegex = $this->getSettingsManager()->getSingleSettingsProfile($submission->getSourceBlogId())
            ->getFilterFieldNameRegExp();
        $array = $this->removeFields($array, $settings['ignore'], $removeAsRegex);

        $array = $this->passFieldProcessorsFilters($submission, $array);
        $array = $this->passConnectionProfileFilters($array, $strategy, $removeAsRegex);
        return $array;
    }

    public function passFieldProcessorsBeforeSendFilters(SubmissionEntity $submission, array $data): array
    {
        foreach ($data as $stringName => &$value) {
            /* @var mixed $value */
            $value = apply_filters(
                ExportedAPI::FILTER_SMARTLING_METADATA_PROCESS_BEFORE_TRANSLATION,
                $submission,
                $stringName,
                $value,
                $data
            );
        }
        unset($value);

        return $data;
    }

    public function passFieldProcessorsFilters(SubmissionEntity $submission, array $data): array
    {
        foreach ($data as $stringName => &$value) {
            if (StringHelper::isNullOrEmpty($value)) {
                continue;
            }
            try {
                /* @var mixed $value */
                $value = apply_filters(ExportedAPI::FILTER_SMARTLING_METADATA_FIELD_PROCESS, $stringName, $value, $submission);
            } catch (\Exception $e) {
                $message = vsprintf(
                    'An error happened while processing field=\'%s\' with value=\'%s\' of submission=\'%s\'. Message:%s',
                    [
                        $stringName,
                        $value,
                        $submission->getId(),
                        $e->getMessage(),
                    ]
                );
                $this->getLogger()->error($message);
            }
        }
        unset($value);

        return $data;
    }

    /**
     * @param array $data
     * @param string $strategy
     * @param bool $removeAsRegExp
     * @return array
     */
    public function passConnectionProfileFilters(array $data, $strategy, $removeAsRegExp)
    {
        $settings = self::getFieldProcessingParams();

        if (self::FILTER_STRATEGY_UPLOAD === $strategy) {
            $data = $this->removeFields($data, $settings['copy']['name'], $removeAsRegExp);
            $data = $this->removeValuesByRegExp($data, $settings['copy']['regexp']);
        }
        $data = $this->removeEmptyFields($data);

        return $data;
    }

    /**
     * @return mixed
     */
    private static function getFieldProcessingParams()
    {
        return Bootstrap::getContainer()->getParameter('field.processor');
    }

    /**
     * @param string[] $fields
     * @param string[] $remove
     * @param bool $removeAsRegExp
     * @return string[]
     */
    public function removeFields(array $fields, array $remove, $removeAsRegExp)
    {
        $result = [];
        if ([] === $remove) {
            return $fields;
        }

        if ($removeAsRegExp) {
            return $this->removeFieldsRegExp($fields, $remove);
        }

        $pattern = '#\/(' . implode('|', $remove) . ')$#us';

        foreach ($fields as $key => $value) {
            if (1 === preg_match($pattern, $key)) {
                $debugMessage = vsprintf('Removed field by name \'%s\' because of configuration.', [$key]);
                $this->getLogger()->debug($debugMessage);
                continue;
            }

            $result[$key] = $value;
        }

        return $result;
    }

    private function removeFieldsRegExp(array $fields, array $remove) {
        $result = [];

        foreach ($fields as $key => $value) {
            foreach ($remove as $regex) {
                $parts = explode('/', $key);
                $userPart = array_pop($parts);
                if (0 !== preg_match("/$regex/", $userPart)) {
                    $debugMessage = "Removed field by name '$key' because of configuration (matches regex '$regex').";
                    $this->getLogger()->debug($debugMessage);
                    continue 2;
                }
            }

            $result[$key] = $value;
        }

        return $result;
    }

    public function removeEmptyFields(array $array): array
    {
        $rebuild = [];
        foreach ($array as $key => $value) {
            if (StringHelper::isNullOrEmpty((string)$value)) {
                $debugMessage = vsprintf('Removed empty field \'%s\'.', [$key]);
                $this->getLogger()->debug($debugMessage);
                continue;
            }
            $rebuild[$key] = $value;
        }

        return $rebuild;
    }

    /**
     * @param $array
     * @param $list
     *
     * @return array
     */
    public function removeValuesByRegExp($array, $list)
    {
        $rebuild = [];
        foreach ($array as $key => $value) {
            foreach ($list as $item) {
                if (preg_match("/{$item}/us", $value)) {
                    $debugMessage = vsprintf('Removed field by value: filedName:\'%s\' fieldValue:\'%s\' filter:\'%s\'.',
                                             [$key, $value, $item]);
                    $this->getLogger()->debug($debugMessage);
                    continue 2;
                }
            }
            $rebuild[$key] = $value;
        }

        return $rebuild;
    }
}