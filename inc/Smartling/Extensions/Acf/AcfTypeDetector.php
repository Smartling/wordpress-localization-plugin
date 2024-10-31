<?php

namespace Smartling\Extensions\Acf;

use Smartling\Bootstrap;
use Smartling\Helpers\Cache;
use Smartling\Helpers\ContentHelper;
use Smartling\Helpers\LoggerSafeTrait;
use Smartling\Helpers\MetaFieldProcessor\CustomFieldFilterHandler;
use Smartling\Helpers\MetaFieldProcessor\MetaFieldProcessorInterface;
use Smartling\Submissions\SubmissionEntity;

class AcfTypeDetector
{
    use LoggerSafeTrait;
    public const ACF_FIELD_GROUP_REGEX = '#(field|group)_([0-9a-f]){13}#';
    private const CACHE_EXPIRE_SEC = 84600;

    private function getCacheKeyByFieldName(string $fieldName): string
    {
        return 'acf-field-type-cache-'. $fieldName;
    }

    public function __construct(private ContentHelper $contentHelper, private Cache $cache) {}

    private function getFieldKeyFieldName(string $fieldName, SubmissionEntity $submission): ?string
    {
        if (false === $fieldKey = $this->cache->get($this->getCacheKeyByFieldName($fieldName))) {
            $sourceMeta = $this->contentHelper->readSourceMetadata($submission);
            return $this->getFieldKeyFieldNameByMetaFields($fieldName, $sourceMeta);
        }

        return is_string($fieldKey) ? $fieldKey : null;
    }

    private function getFieldKeyFieldNameByMetaFields(string $fieldName, array $metadata): ?string
    {
        if (false === $fieldKey = $this->cache->get($this->getCacheKeyByFieldName($fieldName))) {
            $_realFieldName = preg_replace('#^meta/#', '', $fieldName);
            if (array_key_exists('_' . $_realFieldName, $metadata)) {
                $fieldKey = $metadata['_' . $_realFieldName];
                $this->cache->set($this->getCacheKeyByFieldName($fieldName), $fieldKey, self::CACHE_EXPIRE_SEC);
            } else {
                return null;
            }
        }

        return is_string($fieldKey) ? $fieldKey : null;
    }

    private function getProcessorByFieldKey(string $key, string $fieldName): ?MetaFieldProcessorInterface
    {
        if (!array_key_exists($key, AcfDynamicSupport::$acfReverseDefinitionAction)) {
            $this->getLogger()->info(vsprintf('No definition found for field \'%s\', key \'%s\'', [$fieldName, $key]));

            return null;
        }

        return CustomFieldFilterHandler::getProcessor(Bootstrap::getContainer(), array_merge(
            AcfDynamicSupport::$acfReverseDefinitionAction[$key],
            ['pattern' => vsprintf('^%s$', [$fieldName])],
        ));
    }

    public function getProcessor(string $field, SubmissionEntity $submission): ?MetaFieldProcessorInterface
    {
        return $this->getAcfProcessor($field, $this->getFieldKeyFieldName($field, $submission));
    }

    public function getProcessorByMetaFields(string $field, array $metaFields): ?MetaFieldProcessorInterface
    {
        return $this->getAcfProcessor($field, $this->getFieldKeyFieldNameByMetaFields($field, $metaFields));
    }

    public function getProcessorForGutenberg(string $field, array $fields): ?MetaFieldProcessorInterface
    {
        $parts = explode('/', $field);
        $lastPart = end($parts);
        if ($lastPart !== false && !str_starts_with($lastPart, '_')) {
            $parts[count($parts) - 1] = "_$lastPart";
            $acfField = implode('/', $parts);
            if (array_key_exists($acfField, $fields) && is_string($fields[$acfField])) {
                return $this->getAcfProcessor($acfField, $fields[$acfField]);
            }
        }

        return null;
    }

    private function getAcfProcessor(string $field, string $key): ?MetaFieldProcessorInterface
    {
        $matches = [];
        preg_match_all(self::ACF_FIELD_GROUP_REGEX, $key, $matches);
        $fieldKey = array_pop($matches[0]);
        if ($fieldKey !== null) {
            return $this->getProcessorByFieldKey($fieldKey, $field);
        }

        return null;
    }
}
