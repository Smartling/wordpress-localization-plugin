<?php

namespace Smartling;

use Smartling\Exception\SmartlingFileDownloadException;
use Smartling\Exception\SmartlingFileUploadException;
use Smartling\Exception\SmartlingNetworkException;
use Smartling\Settings\ConfigurationProfileEntity;
use Smartling\Submissions\SubmissionEntity;

/**
 * Interface ApiWrapperInterface
 *
 * @package Smartling
 */
interface ApiWrapperInterface
{

    /**
     * @param SubmissionEntity $entity
     *
     * @return string
     * @throws SmartlingFileDownloadException
     */
    public function downloadFile(SubmissionEntity $entity);

    /**
     * @param SubmissionEntity $entity
     *
     * @return SubmissionEntity
     * @throws SmartlingFileDownloadException
     * @throws SmartlingNetworkException
     */
    public function getStatus(SubmissionEntity $entity);

    /**
     * @param ConfigurationProfileEntity $profile
     *
     * @return bool
     * @internal param string $locale
     *
     */
    public function testConnection(ConfigurationProfileEntity $profile);

    /**
     * @param SubmissionEntity $entity
     * @param string           $xmlString
     *
     * @param string           $filename
     *
     * @return bool
     * @throws SmartlingFileUploadException
     */
    public function uploadContent(SubmissionEntity $entity, $xmlString = '', $filename = '');

    /**
     * @param ConfigurationProfileEntity $profile
     *
     * @return array
     */
    public function getSupportedLocales(ConfigurationProfileEntity $profile);

    /**
     * @param SubmissionEntity $submission
     *
     * @return array mixed
     */
    public function lastModified(SubmissionEntity $submission);

    /**
     * @param SubmissionEntity[] $submissions
     *
     * @return SubmissionEntity[]
     */
    public function getStatusForAllLocales(array $submissions);
}