<?php

namespace Smartling\Helpers;

use JetBrains\PhpStorm\ExpectedValues;
use Smartling\Base\ExportedAPI;
use Smartling\Extensions\Acf\AcfDynamicSupport;
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
    use LoggerSafeTrait;

    public const ARRAY_DIVIDER = '/';

    private const FILTER_STRATEGY_UPLOAD = 'upload';

    private const FILTER_STRATEGY_DOWNLOAD = 'download';

    public function __construct(
        private AcfDynamicSupport $acfDynamicSupport,
        private ContentSerializationHelper $contentSerializationHelper,
        private SettingsManager $settingsManager,
        private WordpressFunctionProxyHelper $wordpressFunctionProxyHelper,
    ) {
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

    public function structurizeArray(array $flatArray, string $divider = self::ARRAY_DIVIDER): array
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

    private function processArrayElement(string $currentPath, mixed $elementValue, string $divider): array
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

    public function prepareSourceData(array $sourceArray): array
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

                $value = $this->wordpressFunctionProxyHelper->maybe_unserialize($value);
            }
            unset ($value);
        }

        return $sourceArray;
    }

    public function removeIgnoringFields(SubmissionEntity $submission, array $data): array
    {
        return $this->structurizeArray(
            $this->removeFields(
                $this->flattenArray(
                    $this->prepareSourceData($data)
                ),
                $this->contentSerializationHelper->prepareFieldProcessorValues($submission)['ignore'],
                $this->settingsManager->getSingleSettingsProfile($submission->getSourceBlogId())->getFilterFieldNameRegExp()),
        );
    }

    public function processStringsBeforeEncoding(
        SubmissionEntity $submission,
        array $data,
        #[ExpectedValues([self::FILTER_STRATEGY_UPLOAD, self::FILTER_STRATEGY_DOWNLOAD])]
        string $strategy = self::FILTER_STRATEGY_UPLOAD
    ): array
    {
        $data = $this->prepareSourceData($data);
        if ($strategy === self::FILTER_STRATEGY_UPLOAD) {
            $data = $this->acfDynamicSupport->removePreTranslationFields($data);
        }

        $settings = $this->contentSerializationHelper->prepareFieldProcessorValues($submission);

        return $this->passConnectionProfileFilters(
            $this->passFieldProcessorsBeforeSendFilters(
                $submission,
                $this->removeFields(
                    $this->flattenArray($data),
                    $settings['ignore'],
                    $this->settingsManager->getSingleSettingsProfile($submission->getSourceBlogId())->getFilterFieldNameRegExp(),
                )
            ),
            $strategy,
            $this->settingsManager->getSingleSettingsProfile($submission->getSourceBlogId())->getFilterFieldNameRegExp(),
            $settings,
        );
    }

    public function processStringsAfterDecoding(array $data): array
    {
        return $this->structurizeArray($data);
    }

    public function applyTranslatedValues(SubmissionEntity $submission, array $originalValues, array $translatedValues, bool $applyFilters = true): array
    {
        $originalValues = $this->flattenArray($this->prepareSourceData($originalValues));
        $translatedValues = $this->flattenArray($this->prepareSourceData($translatedValues));
        $result = $originalValues;

        if (true === $applyFilters) {
            $result = array_merge(
                [],
                $this->filterArray(
                    $originalValues,
                    $submission,
                    self::FILTER_STRATEGY_DOWNLOAD,
                    $this->contentSerializationHelper->prepareFieldProcessorValues($submission),
                ),
            );
        }

        $result = array_merge($result, $translatedValues);

        return $this->structurizeArray($result);
    }

    private function filterArray(array $array, SubmissionEntity $submission, string $strategy, array $settings): array
    {
        return $this->passConnectionProfileFilters(
            $this->passFieldProcessorsFilters(
                $submission,
                $this->removeFields(
                    $array,
                    $settings['ignore'],
                    $this->settingsManager->getSingleSettingsProfile($submission->getSourceBlogId())->getFilterFieldNameRegExp(),
                ),
            ),
            $strategy,
            $this->settingsManager->getSingleSettingsProfile($submission->getSourceBlogId())->getFilterFieldNameRegExp(),
            $this->contentSerializationHelper->prepareFieldProcessorValues($submission),
        );
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

    public function passConnectionProfileFilters(array $data, string $strategy, bool $removeAsRegExp, array $settings): array
    {
        if (self::FILTER_STRATEGY_UPLOAD === $strategy) {
            $data = $this->removeValuesByRegExp(
                $this->removeFields($data, $settings['copy']['name'], $removeAsRegExp),
                $settings['copy']['regexp'],
            );
        }

        return $this->removeEmptyFields($data);
    }

    public function removeFields(array $fields, array $remove, bool $removeAsRegExp): array
    {
        $result = [];
        if ([] === $remove) {
            return $fields;
        }

        if ($removeAsRegExp) {
            return $this->removeFieldsRegExp($fields, $remove);
        }

        $pattern = '#/(' . implode('|', $remove) . ')$#us';

        foreach ($fields as $key => $value) {
            if (1 === preg_match($pattern, $key)) {
                $this->getLogger()->debug(sprintf('Removed fieldName="%s" because of configuration', $key));
                continue;
            }

            $result[$key] = $value;
        }

        return $result;
    }

    private function removeFieldsRegExp(array $fields, array $remove): array
    {
        $result = [];

        foreach ($fields as $key => $value) {
            foreach ($remove as $regex) {
                $parts = explode('/', $key);
                $userPart = array_pop($parts);
                if (0 !== preg_match("/$regex/", $userPart)) {
                    $debugMessage = "Removed fieldName=\"$key\" because of configuration (matches regex=\"$regex\").";
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
                $this->getLogger()->debug(sprintf('Removed empty fieldName="%s"', $key));
                continue;
            }
            $rebuild[$key] = $value;
        }

        return $rebuild;
    }

    public function removeValuesByRegExp(array $array, array $list): array
    {
        $rebuild = [];
        foreach ($array as $key => $value) {
            foreach ($list as $item) {
                if (preg_match("/$item/us", $value)) {
                    $this->getLogger()->debug(sprintf('Removed field by value: filedName="%s" fieldValue="%s" filter="%s"', $key, $value, $item));
                    continue 2;
                }
            }
            $rebuild[$key] = $value;
        }

        return $rebuild;
    }
}
