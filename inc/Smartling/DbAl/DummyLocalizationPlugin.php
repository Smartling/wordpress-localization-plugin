<?php

namespace Smartling\DbAl;

use Psr\Log\LoggerInterface;
use Smartling\MonologWrapper\MonologWrapper;
use Smartling\Submissions\SubmissionEntity;

/**
 * Class DummyLocalizationPlugin
 * @package Smartling\DbAl
 */
class DummyLocalizationPlugin implements LocalizationPluginProxyInterface
{

    /**
     * @return LoggerInterface
     */
    public function getLogger()
    {
        return MonologWrapper::getLogger(__CLASS__);
    }

    /**
     * Retrieves locale from site option
     * @return array
     */
    public function getLocales()
    {
        return [];
    }

    /**
     * Retrieves locale from site option
     *
     * @param integer $blogId
     *
     * @return string
     */
    public function getBlogLocaleById($blogId)
    {
        return '';
    }

    /**
     * Retrieves blog ids linked to given blog
     *
     * @param integer $blogId
     *
     * @return array
     */
    public function getLinkedBlogIdsByBlogId($blogId)
    {
        return [];
    }

    /**
     * Returns linked content
     *
     * @param int    $sourceBlogId
     * @param int    $sourceContentId
     * @param string $contentType
     *
     * @return array
     * ( <blog_id> => <content_id> )
     */
    public function getLinkedObjects($sourceBlogId, $sourceContentId, $contentType)
    {
        return [];
    }

    /**
     * @param SubmissionEntity $submission
     *
     * @return bool
     */
    public function linkObjects(SubmissionEntity $submission)
    {
        return true;
    }

    /**
     * @param SubmissionEntity $submission
     *
     * @return bool
     */
    public function unlinkObjects(SubmissionEntity $submission)
    {
        return true;
    }

    /**
     * @return string
     */
    public function getBlogLanguageById($blogId)
    {
        return '';
    }

    /**
     * @param string $locale
     *
     * @return string mixed
     */
    public function getBlogNameByLocale($locale)
    {
        return '';
    }
}