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
     * @param array            $smartlingLocaleList
     *
     * @return bool
     * @throws SmartlingFileUploadException
     */
    public function uploadContent(SubmissionEntity $entity, $xmlString = '', $filename = '', array $smartlingLocaleList = []);

    /**
     * @param ConfigurationProfileEntity $profile
     *
     * @return array
     */
    public function getSupportedLocales(ConfigurationProfileEntity $profile);

    /**
     * @param ConfigurationProfileEntity $profile
     *
     * @return mixed
     */
    public function getAccountUid(ConfigurationProfileEntity $profile);

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

    /**
     * @param SubmissionEntity $submission
     */
    public function deleteFile(SubmissionEntity $submission);

    /**
     * @param ConfigurationProfileEntity $profile
     *
     * @return array
     */
    public function listJobs(ConfigurationProfileEntity $profile);

    /**
     * @param ConfigurationProfileEntity $profile
     * @param array                      $params
     *
     * @return array
     * @throws \Exception
     */
    public function createJob(ConfigurationProfileEntity $profile, array $params);

    /**
     * @param ConfigurationProfileEntity $profile
     * @param $jobId
     * @param $name
     * @param $description
     * @param $dueDate
     *
     * @return array
     * @throws SmartlingApiException
     */
    public function updateJob(ConfigurationProfileEntity $profile, $jobId, $name, $description, $dueDate);

    /**
     * @param \Smartling\Settings\ConfigurationProfileEntity $profile
     * @param $jobId
     * @param bool $authorize
     *
     * @return array
     * @throws SmartlingApiException
     */
    public function createBatch(ConfigurationProfileEntity $profile, $jobId, $authorize = false);

    /**
     * @param \Smartling\Settings\ConfigurationProfileEntity $profile
     * @param $batchUid
     *
     * @throws SmartlingApiException
     */
    public function executeBatch(ConfigurationProfileEntity $profile, $batchUid);

    /**
     * @param ConfigurationProfileEntity $profile
     *
     * @return mixed
     */
    public function getProgressToken(ConfigurationProfileEntity $profile);

    /**
     * @param ConfigurationProfileEntity $profile
     * @param string                     $space
     * @param string                     $object
     * @param string                     $record
     */
    public function deleteNotificationRecord(ConfigurationProfileEntity $profile, $space, $object, $record);

    /**
     * @param ConfigurationProfileEntity $profile
     * @param string                     $space
     * @param string                     $object
     * @param array                      $data
     * @param int                        $ttl
     */
    public function createNotificationRecord(ConfigurationProfileEntity $profile, $space, $object, $data = [], $ttl = 30);
}