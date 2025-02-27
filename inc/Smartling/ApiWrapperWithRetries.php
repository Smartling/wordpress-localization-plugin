<?php

namespace Smartling;

use DateTime;
use Smartling\Helpers\LoggerSafeTrait;
use Smartling\Jobs\JobEntity;
use Smartling\Jobs\JobEntityWithBatchUid;
use Smartling\Jobs\JobEntityWithStatus;
use Smartling\Settings\ConfigurationProfileEntity;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Vendor\Jralph\Retry\Command;
use Smartling\Vendor\Jralph\Retry\Retry;
use Smartling\Vendor\Jralph\Retry\RetryException;

class ApiWrapperWithRetries implements ApiWrapperInterface {
    use LoggerSafeTrait;

    public const int RETRY_ATTEMPTS = 4;
    private const int RETRY_WAIT_SECONDS = 1;

    public function __construct(private ApiWrapperInterface $base, private int $retryWaitSeconds = 1)
    {
    }

    public function acquireLock(ConfigurationProfileEntity $profile, string $key, int $ttlSeconds): \DateTime
    {
        return $this->withRetry(function () use ($profile, $key, $ttlSeconds) {
            return $this->base->acquireLock($profile, $key, $ttlSeconds);
        });
    }

    public function renewLock(ConfigurationProfileEntity $profile, string $key, int $ttlSeconds): \DateTime
    {
        return $this->withRetry(function () use ($profile, $key, $ttlSeconds) {
            return $this->base->renewLock($profile, $key, $ttlSeconds);
        });
    }

    public function releaseLock(ConfigurationProfileEntity $profile, string $key): void
    {
        $this->withRetry(function () use ($profile, $key) {
            $this->base->releaseLock($profile, $key);
        });
    }

    public function createAuditLogRecord(ConfigurationProfileEntity $profile, string $actionType, string $description, array $clientData, ?JobEntity $job = null, ?bool $isAuthorize = null): void
    {
        $this->withRetry(function () use ($profile, $job, $actionType, $isAuthorize, $clientData, $description) {
            $this->base->createAuditLogRecord($profile, $actionType, $description, $clientData, $job, $isAuthorize);
        });
    }

    public function downloadFile(SubmissionEntity $entity): string
    {
        return $this->withRetry(function () use ($entity) {
            return $this->base->downloadFile($entity);
        });
    }

    public function getStatus(SubmissionEntity $entity): SubmissionEntity
    {
        return $this->withRetry(function () use ($entity) {
            return $this->base->getStatus($entity);
        });
    }

    public function uploadContent(SubmissionEntity $entity, string $xmlString, string $batchUid, array $smartlingLocaleList): bool {
        return $this->withRetry(function () use ($batchUid, $entity, $xmlString, $smartlingLocaleList) {
            return $this->base->uploadContent($entity, $xmlString, $batchUid, $smartlingLocaleList);
        });
    }

    public function getSupportedLocales(ConfigurationProfileEntity $profile): array
    {
        return $this->withRetry(function () use ($profile) {
            return $this->base->getSupportedLocales($profile);
        });
    }

    public function getAccountUid(ConfigurationProfileEntity $profile): string
    {
        return $this->withRetry(function () use ($profile) {
            return $this->base->getAccountUid($profile);
        });
    }

    public function lastModified(SubmissionEntity $submission): array
    {
        return $this->withRetry(function () use ($submission) {
            return $this->base->lastModified($submission);
        });
    }

    public function getStatusForAllLocales(array $submissions): array
    {
        return $this->withRetry(function () use ($submissions) {
            return $this->base->getStatusForAllLocales($submissions);
        });
    }

    public function deleteFile(SubmissionEntity $submission): void
    {
        $this->withRetry(function () use ($submission) {
            $this->base->deleteFile($submission);
        });
    }

    public function listJobs(ConfigurationProfileEntity $profile, ?string $name = null, array $statuses = []): array
    {
        return $this->withRetry(function () use ($profile, $name, $statuses) {
            return $this->base->listJobs($profile, $name, $statuses);
        });
    }

    public function createJob(ConfigurationProfileEntity $profile, array $params): array
    {
        return $this->withRetry(function () use ($profile, $params) {
            return $this->base->createJob($profile, $params);
        });
    }

    public function updateJob(ConfigurationProfileEntity $profile, string $jobId, string $name, ?string $description = null, ?DateTime $dueDate = null): array
    {
        return $this->withRetry(function () use ($profile, $jobId, $name, $description, $dueDate) {
            return $this->base->updateJob($profile, $jobId, $name, $description, $dueDate);
        });
    }

    public function cancelBatchFile(ConfigurationProfileEntity $profile, string $batchUid, string $fileUri): void
    {
        $this->withRetry(function () use ($profile, $batchUid, $fileUri) {
            $this->base->cancelBatchFile($profile, $batchUid, $fileUri);
        });
    }

    public function createBatch(ConfigurationProfileEntity $profile, string $jobUid, array $fileUris, bool $authorize = false): string
    {
        return $this->withRetry(function () use ($profile, $jobUid, $authorize, $fileUris) {
            return $this->base->createBatch($profile, $jobUid, $fileUris, $authorize);
        });
    }

    public function registerBatchFile(ConfigurationProfileEntity $profile, string $batchUid, string $fileUri): void
    {
        $this->withRetry(function () use ($profile, $batchUid, $fileUri) {
            $this->base->registerBatchFile($profile, $batchUid, $fileUri);
        });
    }

    public function findLastJobByFileUri(ConfigurationProfileEntity $profile, string $fileUri): ?JobEntityWithStatus
    {
        return $this->withRetry(function () use ($profile, $fileUri) {
            return $this->base->findLastJobByFileUri($profile, $fileUri);
        });
    }

    public function getOrCreateJobInfoForDailyBucketJob(ConfigurationProfileEntity $profile, array $fileUris): JobEntityWithBatchUid
    {
        return $this->withRetry(function () use ($profile, $fileUris) {
            return $this->base->getOrCreateJobInfoForDailyBucketJob($profile, $fileUris);
        });
    }

    public function getProgressToken(ConfigurationProfileEntity $profile): array
    {
        return $this->withRetry(function () use ($profile) {
            return $this->base->getProgressToken($profile);
        });
    }

    public function deleteNotificationRecord(ConfigurationProfileEntity $profile, string $space, string $object, string $record): void
    {
        $this->withRetry(function () use ($profile, $space, $object, $record) {
            $this->base->deleteNotificationRecord($profile, $space, $object, $record);
        });
    }

    public function setNotificationRecord(ConfigurationProfileEntity $profile, string $space, string $object, string $record, array $data = [], int $ttl = 30): void
    {
        $this->withRetry(function () use ($profile, $space, $object, $record, $data, $ttl) {
            $this->base->setNotificationRecord($profile, $space, $object, $record, $data, $ttl);
        });
    }

    public function testConnection(ConfigurationProfileEntity $profile): bool
    {
        return $this->base->testConnection($profile); // No retrying
    }

    public function isUnrecoverable(\Exception $e): bool
    {
        return $this->base->isUnrecoverable($e);
    }

    public function withRetry(callable $command)
    {
        try {
            return (new Retry(new Command($command)))
                ->attempts(self::RETRY_ATTEMPTS)
                ->wait($this->retryWaitSeconds)
                ->onlyIf(function ($attempt, $error) {
                    return ($error instanceof \Exception && !$this->isUnrecoverable($error));
                })
                ->run();
        } catch (RetryException $e) {
            throw $e->getPrevious();
        }
    }
}
