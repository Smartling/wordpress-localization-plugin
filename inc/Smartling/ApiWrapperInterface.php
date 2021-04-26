<?php

namespace Smartling;

use JetBrains\PhpStorm\ArrayShape;
use Smartling\Exception\SmartlingFileDownloadException;
use Smartling\Exception\SmartlingFileUploadException;
use Smartling\Exception\SmartlingNetworkException;
use Smartling\Exceptions\SmartlingApiException;
use Smartling\Jobs\JobInformationEntity;
use Smartling\Settings\ConfigurationProfileEntity;
use Smartling\Submissions\SubmissionEntity;

interface ApiWrapperInterface
{
    public const CREATE_BATCH_RESPONSE = ['batchUid' => 'string'];
    public const CREATE_JOB_RESPONSE = [
        'translationJobUid' => 'string',
        'jobName' => 'string',
        'jobNumber' => 'string',
        'targetLocaleIds' => 'string[]',
        'callbackMethod' => 'string',
        'callbackUrl' => 'string',
        'createdByUserUid' => 'string',
        'createdDate' => 'string',
        'description' => 'string',
        'dueDate' => 'string',
        'firstCompletedDate' => 'string',
        'jobStatus' => 'string',
        'lastCompletedDate' => 'string',
        'modifiedByUserUid' => 'string',
        'modifiedDate' => 'string',
        'referenceNumber' => 'string',
        'customFields' => [],
    ];

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
     */
    public function testConnection(ConfigurationProfileEntity $profile);

    /**
     * @param SubmissionEntity $entity
     * @param string $xmlString
     * @param string $filename
     * @param array $smartlingLocaleList
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
     * @throws SmartlingNetworkException
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
     * @throws SmartlingApiException
     */
    #[ArrayShape(self::CREATE_JOB_RESPONSE)]
    public function createJob(ConfigurationProfileEntity $profile, array $params): array;

    /**
     * @param ConfigurationProfileEntity $profile
     * @param                            $jobId
     * @param                            $name
     * @param                            $description
     * @param                            $dueDate
     *
     * @return array
     * @throws SmartlingApiException
     */
    public function updateJob(ConfigurationProfileEntity $profile, $jobId, $name, $description, $dueDate);

    /**
     * @throws SmartlingApiException
     */
    #[ArrayShape(self::CREATE_BATCH_RESPONSE)]
    public function createBatch(ConfigurationProfileEntity $profile, string $jobUid, bool $authorize = false): array;

    /**
     * @param ConfigurationProfileEntity $profile
     * @param                                                $batchUid
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
     * @param string $space
     * @param string $object
     * @param string $record
     */
    public function deleteNotificationRecord(ConfigurationProfileEntity $profile, $space, $object, $record);

    /**
     * @param ConfigurationProfileEntity $profile
     * @param string $space
     * @param string $object
     * @param string $record
     * @param array $data
     * @param int $ttl
     *
     * @return array
     */
    public function setNotificationRecord(ConfigurationProfileEntity $profile, $space, $object, $record, $data = [], $ttl = 30);

    /**
     * @throws SmartlingApiException
     */
    public function retrieveBatchForBucketJob(ConfigurationProfileEntity $profile, bool $authorize): string;

    /**
     * @param \Exception $e
     * @return bool
     */
    public function isUnrecoverable(\Exception $e);
}
