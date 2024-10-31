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
    /**
     * Default cache time (1 day)
     * @var int
     */
    public static $cacheExpireSec = 84600;

    /**
     * @var Cache
     */
    private $cache;

    /**
     * @var ContentHelper
     */
    private $contentHelper;

    /**
     * @return Cache
     */
    public function getCache()
    {
        return $this->cache;
    }

    /**
     * @param Cache $cache
     */
    public function setCache($cache)
    {
        $this->cache = $cache;
    }

    /**
     * @return ContentHelper
     */
    public function getContentHelper()
    {
        return $this->contentHelper;
    }

    /**
     * @param ContentHelper $contentHelper
     */
    public function setContentHelper($contentHelper)
    {
        $this->contentHelper = $contentHelper;
    }

    private function getCacheKeyByFieldName($fieldName)
    {
        return vsprintf('acf-field-type-cache-%s', [$fieldName]);
    }

    /**
     * AcfTypeDetector constructor.
     *
     * @param ContentHelper $contentHelper
     * @param Cache         $cache
     */
    public function __construct(ContentHelper $contentHelper, Cache $cache)
    {
        $this->setCache($cache);
        $this->setContentHelper($contentHelper);
    }

    /**
     * @param string           $fieldName
     * @param SubmissionEntity $submission
     *
     * @return false|string
     */
    private function getFieldKeyFieldName($fieldName, SubmissionEntity $submission)
    {
        if (false === $fieldKey = $this->getCache()->get($this->getCacheKeyByFieldName($fieldName))) {
            $sourceMeta = $this->getContentHelper()->readSourceMetadata($submission);
            return $this->getFieldKeyFieldNameByMetaFields($fieldName, $sourceMeta);
        }
        return $fieldKey;
    }

    private function getFieldKeyFieldNameByMetaFields($fieldName, array $metadata)
    {
        if (false === $fieldKey = $this->getCache()->get($this->getCacheKeyByFieldName($fieldName))) {
            $_realFieldName = preg_replace('#^meta\/#ius', '', $fieldName);
            if (array_key_exists('_' . $_realFieldName, $metadata)) {
                $fieldKey = $metadata['_' . $_realFieldName];
                $this->getCache()->set($this->getCacheKeyByFieldName($fieldName), $fieldKey, static::$cacheExpireSec);
            } else {
                return false;
            }
        }

        return $fieldKey;
    }

    private function getProcessorByFieldKey($key, $fieldName)
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

    private function getAcfProcessor(string $field, string $key): MetaFieldProcessorInterface|bool
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
