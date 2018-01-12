<?php

namespace Smartling\Helpers;


use Psr\Log\LoggerInterface;
use Smartling\Base\ExportedAPI;
use Smartling\Bootstrap;
use Smartling\MonologWrapper\MonologWrapper;
use Smartling\Settings\SettingsManager;
use Smartling\Submissions\SubmissionEntity;

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
     * @param LoggerInterface $logger
     */
    public function setLogger($logger)
    {
        $this->logger = $logger;
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
     * FieldsFilterHelper constructor.
     *
     * @param SettingsManager $settingsManager
     */
    public function __construct(SettingsManager $settingsManager)
    {
        $logger = MonologWrapper::getLogger(get_called_class());
        $this->setLogger($logger);
        $this->setSettingsManager($settingsManager);
    }

    /**
     * @param array  $array
     * @param string $base
     * @param string $divider
     *
     * @return array
     */
    protected function flatternArray(array $array, $base = '', $divider = self::ARRAY_DIVIDER)
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
    protected function structurizeArray(array $flatArray, $divider = self::ARRAY_DIVIDER)
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
                $result = self::flatternArray($elementValue, $currentPath, $divider);
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
            /**
             * @var array $metadata
             */
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
        ContentSerializationHelper::prepareFieldProcessorValues($this->getSettingsManager(), $submission);
        $data = $this->prepareSourceData($data);
        $data = $this->flatternArray($data);

        $settings = self::getFieldProcessingParams();
        $data = $this->removeFields($data, $settings['ignore']);
        $data = $this->structurizeArray($data);
        return $data;
    }

    /**
     * @param SubmissionEntity $submission
     * @param array            $data
     *
     * @return mixed
     */
    public function processStringsBeforeEncoding(SubmissionEntity $submission, array $data, $strategy = self::FILTER_STRATEGY_UPLOAD)
    {
        ContentSerializationHelper::prepareFieldProcessorValues($this->getSettingsManager(), $submission);
        $data = $this->prepareSourceData($data);
        $data = $this->flatternArray($data);

        $settings = self::getFieldProcessingParams();
        $data = $this->removeFields($data, $settings['ignore']);

        $data = $this->passFieldProcessorsBeforeSendFilters($submission, $data);
        $data = $this->passConnectionProfileFilters($data, $strategy);

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
        $originalValues = $this->flatternArray($originalValues);

        $translatedValues = $this->prepareSourceData($translatedValues);
        $translatedValues = $this->flatternArray($translatedValues);


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
        $array = $this->removeFields($array, $settings['ignore']);

        $array = $this->passFieldProcessorsFilters($submission, $array);
        $array = $this->passConnectionProfileFilters($array, $strategy);
        return $array;
    }

    /**
     * @param SubmissionEntity $submission
     * @param array            $data
     *
     * @return array
     */
    public function passFieldProcessorsBeforeSendFilters(SubmissionEntity $submission, array $data)
    {
        foreach ($data as $stringName => & $stringValue) {
            $stringValue = apply_filters(
                ExportedAPI::FILTER_SMARTLING_METADATA_PROCESS_BEFORE_TRANSLATION,
                $submission,
                $stringName,
                $stringValue,
                $data
            );
        }
        unset($stringValue);

        return $data;
    }

    /**
     * @param SubmissionEntity $submission
     * @param array            $data
     *
     * @return array
     */
    public function passFieldProcessorsFilters(SubmissionEntity $submission, array $data)
    {
        foreach ($data as $stringName => & $stringValue) {
            if (StringHelper::isNullOrEmpty($stringValue)) {
                continue;
            }
            try {
                $stringValue = apply_filters(ExportedAPI::FILTER_SMARTLING_METADATA_FIELD_PROCESS, $stringName, $stringValue, $submission);
            } catch (\Exception $e) {
                $message = vsprintf(
                    'An error happened while processing field=\'%s\' with value=\'%s\' of submission=\'%s\'. Message:%s',
                    [
                        $stringName,
                        $stringValue,
                        $submission->getId(),
                        $e->getMessage(),
                    ]
                );
                $this->getLogger()->error($message);
            }
        }
        unset($stringValue);

        return $data;
    }

    public function passConnectionProfileFilters(array $data, $strategy)
    {
        $settings = self::getFieldProcessingParams();


        if (self::FILTER_STRATEGY_UPLOAD === $strategy) {
            $data = $this->removeFields($data, $settings['copy']['name']);
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
     * @param array $array
     * @param array $list
     *
     * @return array
     */
    public function removeFields($array, array $list)
    {
        $rebuild = [];
        if ([] === $list) {
            return $array;
        }
        $pattern = '#\/(' . implode('|', $list) . ')$#us';

        foreach ($array as $key => $value) {
            if (1 === preg_match($pattern, $key)) {
                $debugMessage = vsprintf('Removed field by name \'%s\' because of configuration.', [$key]);
                $this->getLogger()->debug($debugMessage);
                continue;
            } else {
                $rebuild[$key] = $value;
            }
        }

        return $rebuild;
    }

    /**
     * @param $array
     *
     * @return array
     */
    public function removeEmptyFields(array $array)
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