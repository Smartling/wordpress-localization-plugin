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
	 * @param string           $filename
	 *
	 * @return bool
	 * @throws SmartlingFileUploadException
	 */
	function uploadContent ( SubmissionEntity $entity, $xmlString = '', $filename = '' );

	/**
	 * Sets up the reference to API SDK
	 */
	function setApi ();

	/**
	 * @return array
	 */
	function getSupportedLocales ();
}
