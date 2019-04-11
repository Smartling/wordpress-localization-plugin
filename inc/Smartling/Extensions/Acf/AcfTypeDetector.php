<?php

namespace Smartling\Extensions\Acf;

use Smartling\Bootstrap;
use Smartling\Helpers\Cache;
use Smartling\Helpers\ContentHelper;
use Smartling\Helpers\MetaFieldProcessor\CustomFieldFilterHandler;
use Smartling\MonologWrapper\MonologWrapper;
use Smartling\Submissions\SubmissionEntity;

class AcfTypeDetector
{
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
            $_realFieldName = preg_replace('#^meta\/#ius', '', $fieldName);
            if (array_key_exists('_' . $_realFieldName, $sourceMeta)) {
                $fieldKey = $sourceMeta['_' . $_realFieldName];
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
                ->warning(vsprintf('No definition found for field \'%s\', key \'%s\'', [$fieldName, $key]));

            return false;
        }
        $conf = AcfDynamicSupport::$acfReverseDefinitionAction[$key];
        $config = array_merge($conf, ['pattern' => vsprintf('^%s$', [$fieldName])]);

        return CustomFieldFilterHandler::getProcessor(Bootstrap::getContainer(), $config);
    }


    public function getProcessor($field, SubmissionEntity $submission)
    {
        $key = $this->getFieldKeyFieldName($field, $submission);
        $mathes = [];
        $pattern = '#(field|group)_([0-9a-f]){13}#ius';
        preg_match_all($pattern, $key, $mathes);
        $key = end($mathes[0]);
        if (false !== $key) {
            return $this->getProcessorByFieldKey($key, $field);
        }

        return false;
    }

}