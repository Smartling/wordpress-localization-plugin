<?php

namespace Smartling;

use DateTime;
use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\ExpectedValues;
use Smartling\Exception\SmartlingFileDownloadException;
use Smartling\Exception\SmartlingFileUploadException;
use Smartling\Exception\SmartlingNetworkException;
use Smartling\Jobs\JobEntityWithBatchUid;
use Smartling\Jobs\JobEntityWithStatus;
use Smartling\Settings\ConfigurationProfileEntity;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Vendor\Smartling\AuditLog\Params\CreateRecordParameters;
use Smartling\Vendor\Smartling\Exceptions\SmartlingApiException;
use Smartling\Vendor\Smartling\Jobs\JobStatus;

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
    public const DAILY_BUCKET_JOB_NAME_PREFIX = "Daily Bucket Job";
    public const JOB_STATUSES_FOR_DAILY_BUCKET_JOB = [
        JobStatus::AWAITING_AUTHORIZATION,
        JobStatus::IN_PROGRESS,
        JobStatus::COMPLETED,
    ];

    /**
     * @throws SmartlingApiException
     */
    public function acquireLock(ConfigurationProfileEntity $profile, string $key, int $ttlSeconds): \DateTime;

    /**
     * @throws SmartlingApiException
     */
    public function renewLock(ConfigurationProfileEntity $profile, string $key, int $ttlSeconds): \DateTime;

    /**
     * @throws SmartlingApiException
     */
    public function releaseLock(ConfigurationProfileEntity $profile, string $key): void;

    public function createAuditLogRecord(
        ConfigurationProfileEntity $profile,
        #[ExpectedValues(valuesFromClass: CreateRecordParameters::class)]
        string $actionType,
        string $description,
        array $clientData,
        ?JobEntityWithBatchUid $jobInfo = null,
        ?bool $isAuthorize = null
    ): void;

    /**
     * @throws SmartlingFileDownloadException
     */
    public function downloadFile(SubmissionEntity $entity): string;

    /**
     * @throws SmartlingFileDownloadException
     * @throws SmartlingNetworkException
     */
    public function getStatus(SubmissionEntity $entity): SubmissionEntity;

    /**
     * @throws SmartlingNetworkException
     */
    public function testConnection(ConfigurationProfileEntity $profile): bool;

    /**
     * @throws SmartlingFileUploadException
     */
    public function uploadContent(SubmissionEntity $entity, string $xmlString = '', string $filename = '', array $smartlingLocaleList = []): bool;

    public function getSupportedLocales(ConfigurationProfileEntity $profile): array;

    public function getAccountUid(ConfigurationProfileEntity $profile): string;

    /**
     * @throws SmartlingNetworkException
     */
    public function lastModified(SubmissionEntity $submission): array;

    /**
     * @param SubmissionEntity[] $submissions
     * @return SubmissionEntity[]
     * @throws SmartlingNetworkException
     */
    public function getStatusForAllLocales(array $submissions): array;

    public function deleteFile(SubmissionEntity $submission): void;

    public function listJobs(ConfigurationProfileEntity $profile, ?string $name = null, array $statuses = []): array;

    /**
     * @throws SmartlingApiException
     */
    #[ArrayShape(self::CREATE_JOB_RESPONSE)]
    public function createJob(ConfigurationProfileEntity $profile, array $params): array;

    public function findJobByFileUri(ConfigurationProfileEntity $profile, string $fileUri): ?JobEntityWithStatus;

    /**
     * @throws SmartlingApiException
     */
    public function updateJob(ConfigurationProfileEntity $profile, string $jobId, string $name, ?string $description = null, ?DateTime $dueDate = null): array;

    /**
     * @throws SmartlingApiException
     */
    #[ArrayShape(self::CREATE_BATCH_RESPONSE)]
    public function createBatch(ConfigurationProfileEntity $profile, string $jobUid, bool $authorize = false): array;

    /**
     * @return string batch uid for a given job
     * @throws SmartlingApiException
     */
    public function retrieveBatch(ConfigurationProfileEntity $profile, string $jobId, bool $authorize = true, array $updateJob = []): string;

    /**
     * @throws SmartlingApiException
     */
    public function executeBatch(ConfigurationProfileEntity $profile, string $batchUid): void;

    public function getProgressToken(ConfigurationProfileEntity $profile): array;

    public function deleteNotificationRecord(ConfigurationProfileEntity $profile, string $space, string $object, string $record): void;

    public function setNotificationRecord(ConfigurationProfileEntity $profile, string $space, string $object, string $record, array $data = [], int $ttl = 30): void;

    /**
     * @throws SmartlingApiException
     */
    public function retrieveJobInfoForDailyBucketJob(ConfigurationProfileEntity $profile, bool $authorize): JobEntityWithBatchUid;

    public function isUnrecoverable(\Exception $e): bool;
}
