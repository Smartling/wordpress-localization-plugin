<?php

namespace Smartling\Extensions\Acf;

use Smartling\Bootstrap;
use Smartling\Helpers\Cache;
use Smartling\Helpers\ContentHelper;
use Smartling\Helpers\MetaFieldProcessor\CustomFieldFilterHandler;
use Smartling\Helpers\MetaFieldProcessor\MetaFieldProcessorInterface;
use Smartling\MonologWrapper\MonologWrapper;
use Smartling\Submissions\SubmissionEntity;

class AcfTypeDetector
{
    public const ACF_FIELD_GROUP_REGEX = '#(field|group)_([0-9a-f]){13}#';

    private const CACHE_EXPIRE_SEC = 84600;

    private function getCacheKeyByFieldName(string $fieldName): string
    {
        return "acf-field-type-cache-$fieldName";
    }

    public function __construct(private ContentHelper $contentHelper, private Cache $cache)
    {
    }

    /**
     * @param string           $fieldName
     * @param SubmissionEntity $submission
     *
     * @return false|string
     */
    private function getFieldKeyFieldName($fieldName, SubmissionEntity $submission)
    {
        if (false === $fieldKey = $this->cache->get($this->getCacheKeyByFieldName($fieldName))) {
            $sourceMeta = $this->contentHelper->readSourceMetadata($submission);
            return $this->getFieldKeyFieldNameByMetaFields($fieldName, $sourceMeta);
        }
        return $fieldKey;
    }

    private function getFieldKeyFieldNameByMetaFields($fieldName, array $metadata)
    {
        if (false === $fieldKey = $this->cache->get($this->getCacheKeyByFieldName($fieldName))) {
            $matches = [];
            $_realFieldName = preg_match('#^(?:meta/)?([^/]+)#i', $fieldName, $matches) ? $matches[1] : $fieldName;
            if (array_key_exists('_' . $_realFieldName, $metadata)) {
                $fieldKey = $metadata['_' . $_realFieldName];
                $this->cache->set($this->getCacheKeyByFieldName($fieldName), $fieldKey, self::CACHE_EXPIRE_SEC);
            } else {
                return false;
            }
        }

        return $fieldKey;
    }

    public function getProcessorByFieldKey($key, $fieldName)
    {
        if (!array_key_exists($key, AcfDynamicSupport::$acfReverseDefinitionAction)) {
            MonologWrapper::getLogger(__CLASS__)
                ->info(vsprintf('No definition found for field \'%s\', key \'%s\'', [$fieldName, $key]));

            return false;
        }
        $conf = AcfDynamicSupport::$acfReverseDefinitionAction[$key];
        $config = array_merge($conf, ['pattern' => vsprintf('^%s$', [$fieldName])]);

        return CustomFieldFilterHandler::getProcessor(Bootstrap::getContainer(), $config);
    }

    public function getProcessor($field, SubmissionEntity $submission)
    {
        return $this->getAcfProcessor($field, $this->getFieldKeyFieldName($field, $submission));
    }

    public function getProcessorByMetaFields($field, array $metaFields)
    {
        return $this->getAcfProcessor($field, $this->getFieldKeyFieldNameByMetaFields($field, $metaFields));
    }

    /**
     * @param string $field
     * @param array $fields
     * @return bool|MetaFieldProcessorInterface
     */
    public function getProcessorForGutenberg($field, array $fields)
    {
        $parts = explode('/', $field);
        $lastPart = end($parts);
        if ($lastPart !== false && strpos($lastPart, '_') !== 0) {
            $parts[count($parts) - 1] = "_$lastPart";
            $acfField = implode('/', $parts);
            if (array_key_exists($acfField, $fields) && is_string($fields[$acfField])) {
                return $this->getAcfProcessor($acfField, $fields[$acfField]);
            }
        }

        return false;
    }

    private function getAcfProcessor($field, $key)
    {
        $matches = [];
        preg_match_all(self::ACF_FIELD_GROUP_REGEX, $key, $matches);
        $fieldKey = array_pop($matches[0]);
        if ($fieldKey !== null) {
            return $this->getProcessorByFieldKey($fieldKey, $field);
        }

        return false;
    }
}
