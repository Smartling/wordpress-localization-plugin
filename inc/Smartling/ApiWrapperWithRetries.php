<?php

namespace Smartling;

use DateTime;
use ReflectionMethod;
use Smartling\Jobs\JobEntityWithBatchUid;
use Smartling\Jobs\JobEntityWithStatus;
use Smartling\Settings\ConfigurationProfileEntity;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Vendor\Jralph\Retry\Command;
use Smartling\Vendor\Jralph\Retry\Retry;
use Smartling\Vendor\Jralph\Retry\RetryException;

class ApiWrapperWithRetries extends ApiWrapper {
    public function acquireLock(ConfigurationProfileEntity $profile, string $key, int $ttlSeconds): \DateTime
    {
        return $this->conditionalRetry(__FUNCTION__, func_get_args());
    }

    public function renewLock(ConfigurationProfileEntity $profile, string $key, int $ttlSeconds): \DateTime
    {
        return $this->conditionalRetry(__FUNCTION__, func_get_args());
    }

    public function releaseLock(ConfigurationProfileEntity $profile, string $key): void
    {
        $this->conditionalRetry(__FUNCTION__, func_get_args());
    }

    public function createAuditLogRecord(ConfigurationProfileEntity $profile, string $actionType, string $description, array $clientData, ?JobEntityWithBatchUid $jobInfo = null, ?bool $isAuthorize = null): void
    {
        $this->conditionalRetry(__FUNCTION__, func_get_args());
    }

    public function downloadFile(SubmissionEntity $entity): string
    {
        return $this->conditionalRetry(__FUNCTION__, func_get_args());
    }

    public function getStatus(SubmissionEntity $entity): SubmissionEntity
    {
        return $this->conditionalRetry(__FUNCTION__, func_get_args());
    }

    public function uploadContent(SubmissionEntity $entity, string $xmlString = '', string $filename = '', array $smartlingLocaleList = []): bool
    {
        return $this->conditionalRetry(__FUNCTION__, func_get_args());
    }

    public function getSupportedLocales(ConfigurationProfileEntity $profile): array
    {
        return $this->conditionalRetry(__FUNCTION__, func_get_args());
    }

    public function getAccountUid(ConfigurationProfileEntity $profile): string
    {
        return $this->conditionalRetry(__FUNCTION__, func_get_args());
    }

    public function lastModified(SubmissionEntity $submission): array
    {
        return $this->conditionalRetry(__FUNCTION__, func_get_args());
    }

    public function getStatusForAllLocales(array $submissions): array
    {
        return $this->conditionalRetry(__FUNCTION__, func_get_args());
    }

    public function deleteFile(SubmissionEntity $submission): void
    {
        $this->conditionalRetry(__FUNCTION__, func_get_args());
    }

    public function listJobs(ConfigurationProfileEntity $profile, ?string $name = null, array $statuses = []): array
    {
        return $this->conditionalRetry(__FUNCTION__, func_get_args());
    }

    public function createJob(ConfigurationProfileEntity $profile, array $params): array
    {
        return $this->conditionalRetry(__FUNCTION__, func_get_args());
    }

    public function updateJob(ConfigurationProfileEntity $profile, string $jobId, string $name, ?string $description = null, ?DateTime $dueDate = null): array
    {
        return $this->conditionalRetry(__FUNCTION__, func_get_args());
    }

    public function createBatch(ConfigurationProfileEntity $profile, string $jobUid, bool $authorize = false): array
    {
        return $this->conditionalRetry(__FUNCTION__, func_get_args());
    }

    public function retrieveBatch(ConfigurationProfileEntity $profile, string $jobId, bool $authorize = true, array $updateJob = []): string
    {
        return $this->conditionalRetry(__FUNCTION__, func_get_args());
    }

    public function executeBatch(ConfigurationProfileEntity $profile, string $batchUid): void
    {
        $this->conditionalRetry(__FUNCTION__, func_get_args());
    }

    public function findLastJobByFileUri(ConfigurationProfileEntity $profile, string $fileUri): ?JobEntityWithStatus
    {
        return $this->conditionalRetry(__FUNCTION__, func_get_args());
    }

    public function retrieveJobInfoForDailyBucketJob(ConfigurationProfileEntity $profile, bool $authorize): JobEntityWithBatchUid
    {
        return $this->conditionalRetry(__FUNCTION__, func_get_args());
    }

    public function getProgressToken(ConfigurationProfileEntity $profile): array
    {
        return $this->conditionalRetry(__FUNCTION__, func_get_args());
    }

    public function deleteNotificationRecord(ConfigurationProfileEntity $profile, string $space, string $object, string $record): void
    {
        $this->conditionalRetry(__FUNCTION__, func_get_args());
    }

    public function setNotificationRecord(ConfigurationProfileEntity $profile, string $space, string $object, string $record, array $data = [], int $ttl = 30): void
    {
        $this->conditionalRetry(__FUNCTION__, func_get_args());
    }

    public function testConnection(ConfigurationProfileEntity $profile): bool
    {
        return $this->conditionalRetry(__FUNCTION__, func_get_args());
    }

    public function conditionalRetry(string $name, array $arguments): mixed
    {
        foreach ((new ReflectionMethod(parent::class, $name))->getAttributes() as $attribute) {
            if ($attribute->getName() === \Smartling\Retry::class) {
                $retryParameters = $attribute->newInstance();
                assert($retryParameters instanceof \Smartling\Retry);
                try {
                    return (new Retry(new Command(function () use ($name, $arguments) {return parent::$name(...$arguments);})))
                        ->attempts($retryParameters->retryAttempts)
                        ->wait($retryParameters->retryTimeoutSeconds)
                        ->onlyIf(function ($attempt, $error) {
                            return ($error instanceof \Exception && !$this->isUnrecoverable($error));
                        })
                        ->run();
                } catch (RetryException $e) {
                    throw $e->getPrevious();
                }
            }
        }

        return parent::$name(...$arguments);
    }
}
