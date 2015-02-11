<?php

namespace Smartling;

use Smartling\Exception\SmartlingFileDownloadException;
use Smartling\Exception\SmartlingFileUploadException;
use Smartling\Exception\SmartlingNetworkException;
use Smartling\SDK\SmartlingAPI;
use Smartling\Submissions\SubmissionEntity;

/**
 * Interface ApiWrapperInterface
 *
 * @package Smartling
 */
interface ApiWrapperInterface {

	/**
	 * @param SmartlingAPI $api
	 */
	function setApi ( SmartlingAPI $api );

	/**
	 * @param SubmissionEntity $entity
	 *
	 * @return string
	 * @throws SmartlingFileDownloadException
	 */
	function downloadFile ( SubmissionEntity $entity );

	/**
	 * @param SubmissionEntity $entity
	 *
	 * @return SubmissionEntity
	 * @throws SmartlingFileDownloadException
	 * @throws SmartlingNetworkException
	 */
	function getStatus ( SubmissionEntity $entity );

	/**
	 * @param string $locale
	 *
	 * @return bool
	 */
	function testConnection ( $locale );

	/**
	 * @param SubmissionEntity $entity
	 * @param string           $xmlString
	 *
	 * @param bool             $is_stream
	 * @param string           $filename
	 *
	 * @return bool
	 * @throws SmartlingFileUploadException
	 */
	public function uploadContent ( SubmissionEntity $entity, $xmlString = '', $is_stream = false, $filename = '');
}
