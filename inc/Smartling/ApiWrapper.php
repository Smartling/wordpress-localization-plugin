<?php

namespace Smartling;

use DateTime;
use DateTimeZone;
use Smartling\API\FileApiExtended;
use Smartling\Exception\SmartlingDbException;
use Smartling\Exception\SmartlingFileDownloadException;
use Smartling\Exception\SmartlingFileUploadException;
use Smartling\Exception\SmartlingNetworkException;
use Smartling\Helpers\ArrayHelper;
use Smartling\Helpers\DateTimeHelper;
use Smartling\Helpers\FileHelper;
use Smartling\Helpers\LogContextMixinHelper;
use Smartling\Helpers\LoggerSafeTrait;
use Smartling\Helpers\RuntimeCacheHelper;
use Smartling\Helpers\TestRunHelper;
use Smartling\Jobs\JobEntityWithBatchUid;
use Smartling\Settings\ConfigurationProfileEntity;
use Smartling\Settings\SettingsManager;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Vendor\Smartling\AuditLog\AuditLogApi;
use Smartling\Vendor\Smartling\AuditLog\Params\CreateRecordParameters;
use Smartling\Vendor\Smartling\AuthApi\AuthTokenProvider;
use Smartling\Vendor\Smartling\Batch\BatchApi;
use Smartling\Vendor\Smartling\Batch\Params\CreateBatchParameters;
use Smartling\Vendor\Smartling\DistributedLockService\DistributedLockServiceApi;
use Smartling\Vendor\Smartling\Exceptions\SmartlingApiException;
use Smartling\Vendor\Smartling\File\FileApi;
use Smartling\Vendor\Smartling\File\Params\DownloadFileParameters;
use Smartling\Vendor\Smartling\File\Params\UploadFileParameters;
use Smartling\Vendor\Smartling\Jobs\JobsApi;
use Smartling\Vendor\Smartling\Jobs\JobStatus;
use Smartling\Vendor\Smartling\Jobs\Params\CreateJobParameters;
use Smartling\Vendor\Smartling\Jobs\Params\ListJobsParameters;
use Smartling\Vendor\Smartling\Jobs\Params\UpdateJobParameters;
use Smartling\Vendor\Smartling\ProgressTracker\Params\RecordParameters;
use Smartling\Vendor\Smartling\ProgressTracker\ProgressTrackerApi;
use Smartling\Vendor\Smartling\Project\ProjectApi;

class ApiWrapper implements ApiWrapperInterface
{
    use LoggerSafeTrait;

    private const ADDITIONAL_HEADERS = ['X-SL-UseSecondaryDB' => 'true'];

    private SettingsManager $settings;
    private string $pluginName;
    private string $pluginVersion;

    public function getCache(): RuntimeCacheHelper
    {
        return RuntimeCacheHelper::getInstance();
    }

    public function __construct(SettingsManager $manager, string $pluginName, string $pluginVersion)
    {
        $this->settings = $manager;
        $this->pluginName = $pluginName;
        $this->pluginVersion = $pluginVersion;
    }

    /**
     * @throws SmartlingDbException
     */
    private function getConfigurationProfile(SubmissionEntity $submission): ConfigurationProfileEntity
    {
        $profile = $this->settings->getSingleSettingsProfile($submission->getSourceBlogId());
        if (TestRunHelper::isTestRunBlog($submission->getTargetBlogId())) {
            $profile->setRetrievalType(ConfigurationProfileEntity::RETRIEVAL_TYPE_PSEUDO);
        }

        LogContextMixinHelper::addToContext('projectId', $profile->getProjectId());

        return $profile;
    }

    private function getAuthProvider(ConfigurationProfileEntity $profile): AuthTokenProvider
    {
        $cacheKey = 'profile.auth-provider.' . $profile->getId();
        $authProvider = $this->getCache()->get($cacheKey);

        if (false === $authProvider) {
            AuthTokenProvider::setCurrentClientId($this->pluginName);
            AuthTokenProvider::setCurrentClientVersion($this->pluginVersion);
            $authProvider = AuthTokenProvider::create(
                $profile->getUserIdentifier(),
                $profile->getSecretKey(),
                $this->getLogger()
            );
            $this->getCache()->set($cacheKey, $authProvider);
        }

        return $authProvider;
    }

    public function acquireLock(ConfigurationProfileEntity $profile, string $key, int $ttlSeconds): \DateTime
    {
        return DistributedLockServiceApi::create($this->getAuthProvider($profile), $profile->getProjectId(), $this->getLogger())
            ->acquireLock("{$profile->getProjectId()}-$key", $ttlSeconds);
    }

    public function renewLock(ConfigurationProfileEntity $profile, string $key, int $ttlSeconds): \DateTime
    {
        return DistributedLockServiceApi::create($this->getAuthProvider($profile), $profile->getProjectId(), $this->getLogger())
            ->renewLock("{$profile->getProjectId()}-$key", $ttlSeconds);
    }

    public function releaseLock(ConfigurationProfileEntity $profile, string $key): void
    {
        DistributedLockServiceApi::create($this->getAuthProvider($profile), $profile->getProjectId(), $this->getLogger())
            ->releaseLock("{$profile->getProjectId()}-$key");
    }

    public function createAuditLogRecord(ConfigurationProfileEntity $profile, string $actionType, string $description, array $clientData, ?JobEntityWithBatchUid $jobInfo = null, ?bool $isAuthorize = null): void
    {
        $record = new CreateRecordParameters();
        $record->setActionType($actionType);
        if ($jobInfo !== null) {
            $job = $jobInfo->getJobInformationEntity();
            $record->setTranslationJobUid($job->getJobUid());
            $record->setTranslationJobName($job->getJobName());
            $record->setBatchUid($jobInfo->getBatchUid());
        }
        if ($isAuthorize !== null) {
            $record->setTranslationJobAuthorize($isAuthorize);
        }
        $record->setClientUserId(wp_get_current_user()->ID);
        $record->setDescription($description);
        foreach ($clientData as $key => $value) {
            $record->setClientData($key, $value);
        }

        AuditLogApi::create($this->getAuthProvider($profile), $profile->getProjectId(), $this->getLogger())
            ->createProjectLevelLogRecord($record);
    }

    /**
     * @throws SmartlingFileDownloadException
     */
    public function downloadFile(SubmissionEntity $entity): string
    {
        try {
            $profile = $this->getConfigurationProfile($entity);

            $api = $this->getFileApi($profile);

            $this->getLogger()
                ->info(vsprintf(
                           'Starting file \'%s\' download for entity = \'%s\', blog = \'%s\', id = \'%s\', locale = \'%s\'.',
                           [
                               $entity->getFileUri(),
                               $entity->getContentType(),
                               $entity->getSourceBlogId(),
                               $entity->getSourceId(),
                               $entity->getTargetLocale(),
                           ]
                       ));

            $params = new DownloadFileParameters();

            $params->setRetrievalType($profile->getRetrievalType());

            return $api->downloadFile(
                $entity->getFileUri(),
                $this->getSmartlingLocaleBySubmission($entity),
                $params
            );

        } catch (\Exception $e) {
            $this->getLogger()
                ->error($e->getMessage());
            throw new SmartlingFileDownloadException($e->getMessage(), $e->getCode(), $e);

        }
    }

    private function setTranslationStatusToEntity(SubmissionEntity $entity, int $completedStrings, int $authorizedStrings, int $totalStringCount, int $excludedStringCount): SubmissionEntity
    {
        $entity->setApprovedStringCount($completedStrings + $authorizedStrings);
        $entity->setCompletedStringCount($completedStrings);
        $entity->setTotalStringCount($totalStringCount);
        $entity->setExcludedStringCount($excludedStringCount);

        return $entity;
    }

    public function getStatus(SubmissionEntity $entity): SubmissionEntity
    {
        try {
            $data = $this->getFileApi($this->getConfigurationProfile($entity), true)
                ->getStatus($entity->getFileUri(), $this->getSmartlingLocaleBySubmission($entity));

            $entity = $this
                ->setTranslationStatusToEntity($entity,
                    (int)$data['completedStringCount'],
                    (int) $data['authorizedStringCount'],
                    (int) $data['totalStringCount'],
                    (int)$data['excludedStringCount'],
                );

            $entity->setWordCount($data['totalWordCount']);

            return $entity;
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            throw new SmartlingNetworkException($e->getMessage());
        }
    }

    public function testConnection(ConfigurationProfileEntity $profile): bool
    {
        try {
            $api = $this->getFileApi($profile);

            $api->getList();

            return true;
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            throw new SmartlingNetworkException($e->getMessage());
        }
    }

    private function getSmartlingLocaleBySubmission(SubmissionEntity $entity): string
    {
        return $this->settings->getSmartlingLocaleBySubmission($entity);
    }

    public function uploadContent(SubmissionEntity $entity, string $xmlString = '', string $filename = '', array $smartlingLocaleList = []): bool
    {
        $this->getLogger()
            ->info(vsprintf(
                       'Starting file \'%s\' upload for entity = \'%s\', blog = \'%s\', id = \'%s\', locales:%s.',
                       [
                           $entity->getFileUri(),
                           $entity->getContentType(),
                           $entity->getSourceBlogId(),
                           $entity->getSourceId(),
                           implode(',', $smartlingLocaleList),
                       ]
                   ));
        try {
            $profile = $this->getConfigurationProfile($entity);

            $api = $this->getBatchApi($profile);

            $params = new UploadFileParameters('wordpress-connector', $this->pluginVersion);
            $params->setLocalesToApprove($smartlingLocaleList);

            if (FileHelper::testFile($filename)) {
                $api->uploadBatchFile(
                    $filename,
                    $entity->getFileUri(),
                    'xml',
                    $entity->getBatchUid(),
                    $params);

                $message = vsprintf(
                    'Smartling uploaded "%s" for locales:%s.',
                    [
                        $entity->getFileUri(),
                        implode(',', $smartlingLocaleList),
                    ]
                );

                $this->logger->info($message);
            } else {
                $msg = vsprintf('File "%s" should exist if required for upload.', [$filename]);
                throw new \InvalidArgumentException($msg);
            }

            return true;
        } catch (\Exception $e) {
            throw new SmartlingFileUploadException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function getSupportedLocales(ConfigurationProfileEntity $profile): array
    {
        $supportedLocales = [];
        try {
            $api = ProjectApi::create(
                $this->getAuthProvider($profile),
                $profile->getProjectId(),
                $this->getLogger()
            );

            $locales = $api->getProjectDetails();

            foreach ($locales['targetLocales'] as $locale) {
                $supportedLocales[$locale['localeId']] = $locale['description'];
            }
        } catch (\Exception $e) {
            $message = vsprintf('Response has error messages. Message:\'%s\'.', [$e->getMessage()]);
            $this->logger->error($message);
        }

        return $supportedLocales;
    }

    public function getAccountUid(ConfigurationProfileEntity $profile): string
    {
        try {
            $api = ProjectApi::create(
                $this->getAuthProvider($profile),
                $profile->getProjectId(),
                $this->getLogger()
            );

            $details = $api->getProjectDetails();

            return $details['accountUid'];
        } catch (\Exception $e) {
            $message = vsprintf('Response has error messages. Message:\'%s\'.', [$e->getMessage()]);
            $this->logger->error($message);
            throw $e;
        }
    }

    public function lastModified(SubmissionEntity $submission): array
    {
        $output = [];
        try {
            foreach ($this->getFileApi($this->getConfigurationProfile($submission), true)
                ->lastModified($submission->getFileUri())['items'] as $descriptor) {
                $output[$descriptor['localeId']] = $descriptor['lastModified'];
            }
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            throw new SmartlingNetworkException($e->getMessage());
        }

        return $output;
    }

    public function getStatusForAllLocales(array $submissions): array
    {
        $submission = ArrayHelper::first($submissions);
        if (!$submission instanceof SubmissionEntity) {
            throw new \InvalidArgumentException('$submissions should be an array of ' . SubmissionEntity::class);
        }
        try {
            $data = $this->getFileApi($this->getConfigurationProfile($submission), true)
                ->getStatusForAllLocales($submission->getFileUri());

            $totalWordCount = $data['totalWordCount'];

            unset($submission);

            foreach ($data['items'] as $descriptor) {
                $localeId = $descriptor['localeId'];

                if (array_key_exists($localeId, $submissions)) {
                    $completedStrings = (int)$descriptor['completedStringCount'];
                    $authorizedStrings = (int)$descriptor['authorizedStringCount'];
                    $totalStringCount = (int)$data['totalStringCount'];
                    $excludedStringCount = (int)$descriptor['excludedStringCount'];

                    $currentProgress = $submissions[$localeId]->getCompletionPercentage(); // current progress value 0..100

                    $submission = $this->setTranslationStatusToEntity(
                        $submissions[$localeId],
                        $completedStrings,
                        $authorizedStrings,
                        $totalStringCount,
                        $excludedStringCount);
                    $submission->setWordCount($totalWordCount);

                    $newProgress = $submissions[$localeId]->getCompletionPercentage(); // current progress value 0..100

                    if (100 === $newProgress && 100 === $currentProgress) {
                        $submissions[$localeId]->setStatus(SubmissionEntity::SUBMISSION_STATUS_COMPLETED);
                    }

                    if (100 > $newProgress && 100 === $currentProgress) {
                        $submissions[$localeId]->setStatus(SubmissionEntity::SUBMISSION_STATUS_IN_PROGRESS);
                    }

                    if (100 === $newProgress && 100 > $currentProgress) {
                        $submissions[$localeId]->setStatus(SubmissionEntity::SUBMISSION_STATUS_COMPLETED);
                    }

                    if (100 > $newProgress && 100 > $currentProgress) {
                        $submissions[$localeId]->setStatus(SubmissionEntity::SUBMISSION_STATUS_IN_PROGRESS);
                    }
                }
            }

            return $submissions;
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            throw new SmartlingNetworkException($e->getMessage());
        }
    }

    public function deleteFile(SubmissionEntity $submission): void
    {
        try {
            $profile = $this->getConfigurationProfile($submission);
            $api = $this->getFileApi($profile);
            $api->deleteFile($submission->getFileUri());
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
        }
    }

    private function getJobsApi(ConfigurationProfileEntity $profile): JobsApi
    {
        return JobsApi::create($this->getAuthProvider($profile), $profile->getProjectId(), $this->getLogger());
    }

    private function getBatchApi(ConfigurationProfileEntity $profile): BatchApi
    {
        return BatchApi::create($this->getAuthProvider($profile), $profile->getProjectId(), $this->getLogger());
    }

    public function listJobs(ConfigurationProfileEntity $profile, ?string $name = null, array $statuses = []): array
    {
        $params = new ListJobsParameters();

        if (!empty($name)) {
            $params->setName($name);
        }

        if (!empty($statuses)) {
            $params->setStatuses($statuses);
        }

        return $this->getJobsApi($profile)->listJobs($params);
    }

    public function createJob(ConfigurationProfileEntity $profile, array $params): array
    {
        $param = new CreateJobParameters();

        if (!empty($params['dueDate'])) {
            $param->setDueDate($params['dueDate']);
        }

        if (!empty($params['name'])) {
            $param->setName($params['name']);
        }

        if (!empty($params['locales'])) {
            $param->setTargetLocales($params['locales']);
        }

        if (!empty($params['description'])) {
            $param->setDescription($params['description']);
        }

        return $this->getJobsApi($profile)->createJob($param);
    }

    public function updateJob(ConfigurationProfileEntity $profile, string $jobId, string $name, ?string $description = null, ?DateTime $dueDate = null): array
    {
        $params = new UpdateJobParameters();
        $params->setName($name);

        if (!empty($description)) {
            $params->setDescription($description);
        }

        if ($dueDate !== null) {
            $params->setDueDate($dueDate);
        }

        return $this->getJobsApi($profile)->updateJob($jobId, $params);
    }

    public function createBatch(ConfigurationProfileEntity $profile, string $jobUid, bool $authorize = false): array
    {
        $createBatchParameters = new CreateBatchParameters();
        $createBatchParameters->setTranslationJobUid($jobUid);
        $createBatchParameters->setAuthorize($authorize);

        return $this->getBatchApi($profile)->createBatch($createBatchParameters);
    }

    public function executeBatch(ConfigurationProfileEntity $profile, string $batchUid): void
    {
        $this->getBatchApi($profile)->executeBatch($batchUid);
    }
    public function retrieveBatch(ConfigurationProfileEntity $profile, string $jobId, bool $authorize = true, array $updateJob = []): string
    {
        if ($authorize) {
            $this->getLogger()->debug(vsprintf('Job \'%s\' should be authorized once upload is finished.', [$jobId]));
        }

        try {
            if (!empty($updateJob['name'])) {
                $description = empty($updateJob['description']) ? null : $updateJob['description'];
                $dueDate = null;

                if (!empty($updateJob['dueDate']['date']) && !empty($updateJob['dueDate']['timezone'])) {
                    $dueDate = \DateTime::createFromFormat(DateTimeHelper::DATE_TIME_FORMAT_JOB, $updateJob['dueDate']['date'], new DateTimeZone($updateJob['dueDate']['timezone']));
                    $dueDate->setTimeZone(new DateTimeZone('UTC'));
                }

                $this->updateJob($profile, $jobId, $updateJob['name'], $description, $dueDate);

                $this->getLogger()->debug(vsprintf('Updated job "%s": "%s"', [$jobId, json_encode($updateJob, JSON_THROW_ON_ERROR)]));
            }

            $result = $this->createBatch($profile, $jobId, $authorize);

            $this->getLogger()->debug(vsprintf('Created batch "%s" for job "%s"', [$result['batchUid'], $jobId]));

            return $result['batchUid'];
        } catch (SmartlingApiException $e) {
            $this
                ->getLogger()
                ->error(
                    vsprintf(
                        'Can\'t create batch for a job "%s".\nProfile: %s\nError:%s.\nTrace:%s',
                        [
                            $jobId,
                            base64_encode(serialize($profile->toArraySafe())),
                            $e->formatErrors(),
                            $e->getTraceAsString()
                        ]
                    )
                );
            throw $e;

        } catch (\Exception $e) {
            $this
                ->getLogger()
                ->error(
                    vsprintf(
                        'Can\'t create batch for a job "%s".\nProfile: %s\nError:%s.\nTrace:%s',
                        [
                            $jobId,
                            base64_encode(serialize($profile->toArraySafe())),
                            $e->getMessage(),
                            $e->getTraceAsString()
                        ]
                    )
                );
            throw $e;
        }
    }

    public function retrieveJobInfoForDailyBucketJob(ConfigurationProfileEntity $profile, bool $authorize): JobEntityWithBatchUid
    {
        $getName = static function ($suffix = '') {
            $date = date('m/d/Y');
            $name = "Daily Bucket Job $date";

            return $name . $suffix;
        };

        $jobName = $getName();
        $jobId = null;

        try {
            $response = $this->listJobs($profile, $jobName, [
                JobStatus::AWAITING_AUTHORIZATION,
                JobStatus::IN_PROGRESS,
                JobStatus::COMPLETED,
            ]);

            // Try to find the latest created bucket job.
            if (!empty($response['items'])) {
                $jobId = $response['items'][0]['translationJobUid'];
            }

            // If there is no existing bucket job then create new one.
            if (empty($jobId)) {
                try {
                    $result = $this->createJob($profile, [
                        'name'        => $jobName,
                        'description' => 'Bucket job: contains updated content.',
                    ]);
                } catch (SmartlingApiException $e) {
                    // If there is a CLOSED bucket job then we have to
                    // come up with new job name in order to avoid
                    // "Job name is already taken" error.
                    $jobName = $getName(' ' . date('H:i:s'));
                    $result = $this->createJob($profile, [
                        'name'        => $jobName,
                        'description' => 'Bucket job: contains updated content.',
                    ]);
                }

                $jobId = $result['translationJobUid'];
            }

            if (empty($jobId)) {
                throw new \RuntimeException('Queueing file upload into the bucket job failed: can\'t find/create job.');
            }

            $result = $this->createBatch($profile, $jobId, $authorize);

            return new JobEntityWithBatchUid($result['batchUid'], $jobName, $jobId, $profile->getProjectId());
        } catch (SmartlingApiException $e) {
            $this->getLogger()->error(vsprintf('Can\'t create batch for a daily job "%s". Error: %s', [
                $jobName,
                $e->formatErrors(),
            ]));

            throw $e;
        }
    }

    public function getProgressToken(ConfigurationProfileEntity $profile): array
    {
        try {
            $accountUid = $this->getAccountUid($profile);

            $progressApi = ProgressTrackerApi::create(
                $this->getAuthProvider($profile),
                $profile->getProjectId(),
                $this->getLogger()
            );

            $token = $progressApi->getToken($accountUid);

            return array_merge($token, [
                'accountUid' => $accountUid,
                'projectId'  => $profile->getProjectId(),
            ]);
        } catch (\Exception $e) {
            $this->getLogger()->error(
                vsprintf(
                    'Can\'t get progress token for project id "%s". Error: %s',
                    [$profile->getProjectId(), $this->formatError($e)]
                )
            );

            throw $e;
        }
    }

    public function deleteNotificationRecord(ConfigurationProfileEntity $profile, string $space, string $object, string $record): void
    {
        try {
            $progressApi = ProgressTrackerApi::create(
                $this->getAuthProvider($profile),
                $profile->getProjectId(),
                $this->getLogger()
            );

            $progressApi->deleteRecord($space, $object, $record);
        } catch (\Exception $e) {
            $this
                ->getLogger()
                ->error(
                    vsprintf(
                        'Error occurred while deleting notification for space="%s" object="%s" record="%s". Error: %s',
                        [
                            $space,
                            $object,
                            $record,
                            $this->formatError($e),
                        ]
                    ));
        }
    }

    public function setNotificationRecord(ConfigurationProfileEntity $profile, string $space, string $object, string $record, array $data = [], int $ttl = 30): void
    {
        try {
            $progressApi = ProgressTrackerApi::create(
                $this->getAuthProvider($profile),
                $profile->getProjectId(),
                $this->getLogger()
            );

            $params = new RecordParameters();
            $params->setTtl($ttl);
            $params->setData($data);

            $progressApi->updateRecord($space, $object, $record, $params);
        } catch (\Exception $e) {
            $this
                ->getLogger()
                ->error(
                    vsprintf(
                        'Error occurred while setting notification for space="%s" object="%s" data="%s" ttl="%s". Error: %s',
                        [
                            $space,
                            $object,
                            var_export($data, true),
                            $ttl,
                            $this->formatError($e),
                        ]
                    ));
        }
    }

    public function isUnrecoverable(\Exception $e): bool {
        switch (get_class($e)) {
            case SmartlingApiException::class:
                foreach ($e->getErrors() as $error) {
                    if ($error['key'] === 'forbidden') {
                        return true;
                    }
                }
                break;
            case SmartlingNetworkException::class:
                return strpos($e->getMessage(), 'file.not.found') !== false;
        }

        return false;
    }

    public function getFileApi(ConfigurationProfileEntity $profile, $withAdditionalHeaders = false): FileApi
    {
        $auth = $this->getAuthProvider($profile);
        $projectId = $profile->getProjectId();

        return $withAdditionalHeaders ? new FileApiExtended($auth, $projectId, self::ADDITIONAL_HEADERS) : FileApi::create($auth, $projectId, $this->logger);
    }

    private function formatError(\Exception $e): string
    {
        return $e instanceof SmartlingApiException ? $e->formatErrors() : $e->getMessage();
    }
}
