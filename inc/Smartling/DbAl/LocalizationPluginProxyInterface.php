<?php

namespace Smartling\DbAl;

use Psr\Log\LoggerInterface;
use Smartling\Helpers\SiteHelper;
use Smartling\Submissions\SubmissionEntity;

/**
 * Interface LocalizationPluginProxyInterface
 *
 * @package Smartling\DbAl
 */
interface LocalizationPluginProxyInterface {

	/**
	 * Constructor
	 *
	 * @param LoggerInterface $logger
	 * @param SiteHelper      $helper
	 * @param array           $ml_plugin_statuses
	 */
	function __construct ( LoggerInterface $logger, SiteHelper $helper, array $ml_plugin_statuses );

	/**
	 * @return LoggerInterface
	 */
	function getLogger();

	/**
	 * Retrieves locale from site option
	 *
	 * @return array
	 */
	function getLocales ();

	/**
	 * Retrieves locale from site option
	 *
	 * @param integer $blogId
	 *
	 * @return string
	 */
	function getBlogLocaleById ( $blogId );

	/**
	 * Retrieves blog ids linked to given blog
	 *
	 * @param integer $blogId
	 *
	 * @return array
	 */
	function getLinkedBlogIdsByBlogId ( $blogId );

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
	function getLinkedObjects ( $sourceBlogId, $sourceContentId, $contentType );

	/**
	 * @param SubmissionEntity $submission
	 *
	 * @return bool
	 */
	function linkObjects ( SubmissionEntity $submission );

	/**
	 * @param SubmissionEntity $submission
	 *
	 * @return bool
	 */
	function  unlinkObjects ( SubmissionEntity $submission );
}