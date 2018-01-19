<?php

namespace Smartling\DbAl;

use Psr\Log\LoggerInterface;
use Smartling\Helpers\SiteHelper;
use Smartling\Submissions\SubmissionEntity;

/**
 * Interface LocalizationPluginProxyInterface
 * @package Smartling\DbAl
 */
interface LocalizationPluginProxyInterface
{

    /**
     * @return LoggerInterface
     */
    public function getLogger();

    /**
     * Retrieves locale from site option
     * @return array
     */
    public function getLocales();

    /**
     * Retrieves locale from site option
     *
     * @param integer $blogId
     *
     * @return string
     */
    public function getBlogLocaleById($blogId);

    /**
     * Retrieves blog ids linked to given blog
     *
     * @param integer $blogId
     *
     * @return array
     */
    public function getLinkedBlogIdsByBlogId($blogId);

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
    public function getLinkedObjects($sourceBlogId, $sourceContentId, $contentType);

    /**
     * @param SubmissionEntity $submission
     *
     * @return bool
     */
    public function linkObjects(SubmissionEntity $submission);

    /**
     * @param SubmissionEntity $submission
     *
     * @return bool
     */
    public function unlinkObjects(SubmissionEntity $submission);

    /**
     * @return string
     */
    public function getBlogLanguageById($blogId);

    /**
     * @param string $locale
     *
     * @return string mixed
     */
    public function getBlogNameByLocale($locale);
}