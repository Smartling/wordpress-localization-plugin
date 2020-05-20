<?php

namespace Smartling;

use DateTimeZone;
use Psr\Log\LoggerInterface;
use Smartling\AuthApi\AuthTokenProvider;
use Smartling\Batch\BatchApi;
use Smartling\Batch\Params\CreateBatchParameters;
use Smartling\Exception\SmartlingDbException;
use Smartling\Exception\SmartlingFileDownloadException;
use Smartling\Exception\SmartlingFileUploadException;
use Smartling\Exception\SmartlingNetworkException;
use Smartling\Exceptions\SmartlingApiException;
use Smartling\File\FileApi;
use Smartling\File\Params\DownloadFileParameters;
use Smartling\File\Params\UploadFileParameters;
use Smartling\Helpers\ArrayHelper;
use Smartling\Helpers\DateTimeHelper;
use Smartling\Helpers\FileHelper;
use Smartling\Helpers\LogContextMixinHelper;
use Smartling\Helpers\RuntimeCacheHelper;
use Smartling\Jobs\JobsApi;
use Smartling\Jobs\JobStatus;
use Smartling\Jobs\Params\CreateJobParameters;
use Smartling\Jobs\Params\ListJobsParameters;
use Smartling\Jobs\Params\UpdateJobParameters;
use Smartling\MonologWrapper\MonologWrapper;
use Smartling\ProgressTracker\Params\RecordParameters;
use Smartling\ProgressTracker\ProgressTrackerApi;
use Smartling\Project\ProjectApi;
use Smartling\Settings\ConfigurationProfileEntity;
use Smartling\Settings\SettingsManager;
use Smartling\Submissions\SubmissionEntity;

/**
 * Class ApiWrapper
 * @package Smartling
 */
class ApiWrapper implements ApiWrapperInterface
{

    /**
     * @var SettingsManager
     */
    private $settings;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var string
     */
    private $pluginName = '';

    /**
     * @var string
     */
    private $pluginVersion = '';

    /**
     * @return string
     */
    public function getPluginName()
    {
        return $this->pluginName;
    }

    /**
     * @param string $pluginName
     */
    public function setPluginName($pluginName)
    {
        $this->pluginName = $pluginName;
    }

    /**
     * @return string
     */
    public function getPluginVersion()
    {
        return $this->pluginVersion;
    }

    /**
     * @param string $pluginVersion
     */
    public function setPluginVersion($pluginVersion)
    {
        $this->pluginVersion = $pluginVersion;
    }

    /**
     * @return SettingsManager
     */
    public function getSettings()
    {
        return $this->settings;
    }

    /**
     * @param SettingsManager $settings
     */
    public function setSettings($settings)
    {
        $this->settings = $settings;
    }

    /**
     * @return LoggerInterface
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * @return RuntimeCacheHelper
     */
    public function getCache()
    {
        return RuntimeCacheHelper::getInstance();
    }

    /**
     * ApiWrapper constructor.
     *
     * @param SettingsManager $manager
     * @param string          $pluginName
     * @param string          $pluginVersion
     */
    public function __construct(SettingsManager $manager, $pluginName, $pluginVersion)
    {
        $this->logger = MonologWrapper::getLogger(get_called_class());
        $this->setSettings($manager);
        $this->setPluginName($pluginName);
        $this->setPluginVersion($pluginVersion);
    }

    /**
     * @param SubmissionEntity $submission
     *
     * @return ConfigurationProfileEntity
     * @throws SmartlingDbException
     */
    private function getConfigurationProfile(SubmissionEntity $submission)
    {
        $profile = $this->getSettings()->getSingleSettingsProfile($submission->getSourceBlogId());
        LogContextMixinHelper::addToContext('projectId', $profile->getProjectId());

        return $profile;
    }

    /**
     * @param ConfigurationProfileEntity $profile
     *
     * @return AuthTokenProvider
     */
    private function getAuthProvider(ConfigurationProfileEntity $profile)
    {
        $cacheKey = 'profile.auth-provider.' . $profile->getId();
        $authProvider = $this->getCache()->get($cacheKey);

        if (false === $authProvider) {
            AuthTokenProvider::setCurrentClientId('wordpress-connector');
            AuthTokenProvider::setCurrentClientVersion($this->getPluginVersion());
            $authProvider = AuthTokenProvider::create(
                $profile->getUserIdentifier(),
                $profile->getSecretKey(),
                $this->getLogger()
            );
            $this->getCache()->set($cacheKey, $authProvider);
        }

        return $authProvider;
    }

    /**
     * @param SubmissionEntity $entity
     *
     * @return string
     * @throws SmartlingFileDownloadException
     */
    public function downloadFile(SubmissionEntity $entity)
    {
        try {
            $profile = $this->getConfigurationProfile($entity);

            $api = FileApi::create(
                $this->getAuthProvider($profile),
                $profile->getProjectId(),
                $this->getLogger()
            );

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

            $result = $api->downloadFile(
                $entity->getFileUri(),
                $this->getSmartlingLocaleBySubmission($entity),
                $params
            );

            return $result;

        } catch (\Exception $e) {
            $this->getLogger()
                ->error($e->getMessage());
            throw new SmartlingFileDownloadException($e->getMessage(), $e->getCode(), $e);

        }
    }

    /**
     * @param SubmissionEntity $entity
     * @param int              $completedStrings
     * @param int              $authorizedStrings
     * @param int              $totalStringCount
     * @param int              $excludedStringCount
     *
     * @return SubmissionEntity
     */
    private function setTranslationStatusToEntity(SubmissionEntity $entity, $completedStrings, $authorizedStrings, $totalStringCount, $excludedStringCount)
    {
        $entity->setApprovedStringCount($completedStrings + $authorizedStrings);
        $entity->setCompletedStringCount($completedStrings);
        $entity->setTotalStringCount($totalStringCount);
        $entity->setExcludedStringCount($excludedStringCount);

        return $entity;
    }

    /**
     * @param SubmissionEntity $entity
     *
     * @return SubmissionEntity
     * @throws SmartlingNetworkException
     */
    public function getStatus(SubmissionEntity $entity)
    {
        try {
            $locale = $this->getSmartlingLocaleBySubmission($entity);

            $profile = $this->getConfigurationProfile($entity);

            $api = FileApi::create(
                $this->getAuthProvider($profile),
                $profile->getProjectId(),
                $this->getLogger()
            );

            $data = $api->getStatus($entity->getFileUri(), $locale);

            $entity = $this
                ->setTranslationStatusToEntity($entity,
                                               $data['completedStringCount'],
                                               $data['authorizedStringCount'],
                                               $data['totalStringCount'],
                                               $data['excludedStringCount']
                );

            $entity->setWordCount($data['totalWordCount']);

            return $entity;

        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            throw new SmartlingNetworkException($e->getMessage());

        }
    }

    /**
     * @param ConfigurationProfileEntity $profile
     *
     * @return bool
     * @throws SmartlingNetworkException
     */
    public function testConnection(ConfigurationProfileEntity $profile)
    {
        try {
            $api = FileApi::create(
                $this->getAuthProvider($profile),
                $profile->getProjectId(),
                $this->getLogger()
            );

            $api->getList();

            return true;
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            throw new SmartlingNetworkException($e->getMessage());
        }
    }

    private function getSmartlingLocaleBySubmission(SubmissionEntity $entity)
    {
        $profile = $this->getSettings()->getSingleSettingsProfile($entity->getSourceBlogId());

        return $this->getSettings()->getSmartlingLocaleIdBySettingsProfile($profile, $entity->getTargetBlogId());
    }

    /**
     * {@inheritdoc}
     */
    public function uploadContent(SubmissionEntity $entity, $xmlString = '', $filename = '', array $smartlingLocaleList = [])
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

            $params = new UploadFileParameters('wordpress-connector', $this->getPluginVersion());
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

    /**
     * @inheritdoc
     */
    public function getSupportedLocales(ConfigurationProfileEntity $profile)
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

    public function getAccountUid(ConfigurationProfileEntity $profile)
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

    /**
     * @param SubmissionEntity $submission
     *
     * @return array mixed
     * @throws SmartlingNetworkException
     */
    public function lastModified(SubmissionEntity $submission)
    {
        try {
            $profile = $this->getConfigurationProfile($submission);

            $api = FileApi::create(
                $this->getAuthProvider($profile),
                $profile->getProjectId(),
                $this->getLogger()
            );

            $data = $api->lastModified($submission->getFileUri());


            $output = [];

            foreach ($data['items'] as $descriptor) {
                $output[$descriptor['localeId']] = $descriptor['lastModified'];
            }

            return $output;

        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            throw new SmartlingNetworkException($e->getMessage());
        }
    }

    /**
     * @param SubmissionEntity[] $submissions
     *
     * @return Submissions\SubmissionEntity[]
     * @throws SmartlingNetworkException
     */
    public function getStatusForAllLocales(array $submissions)
    {
        try {
            /**
             * @var SubmissionEntity $submission
             */
            $submission = ArrayHelper::first($submissions);
            $profile = $this->getConfigurationProfile($submission);

            $api = FileApi::create(
                $this->getAuthProvider($profile),
                $profile->getProjectId(),
                $this->getLogger()
            );

            $data = $api->getStatusForAllLocales($submission->getFileUri());

            $totalWordCount = $data['totalWordCount'];

            unset($submission);

            foreach ($data['items'] as $descriptor) {
                $localeId = $descriptor['localeId'];

                if (array_key_exists($localeId, $submissions)) {
                    $completedStrings = $descriptor['completedStringCount'];
                    $authorizedStrings = $descriptor['authorizedStringCount'];
                    $totalStringCount = $data['totalStringCount'];
                    $excludedStringCount = $descriptor['excludedStringCount'];

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
                        /* nothing to do, removing from array to skip useless download */
                        $submissions[$localeId]->setStatus(SubmissionEntity::SUBMISSION_STATUS_COMPLETED);
                    }

                    if (100 > $newProgress && 100 === $currentProgress) {
                        /* just move from Completed to In Progress */
                        $submissions[$localeId]->setStatus(SubmissionEntity::SUBMISSION_STATUS_IN_PROGRESS);
                    }

                    if (100 === $newProgress && 100 > $currentProgress) {
                        /* just move from Completed to In Progress */
                        $submissions[$localeId]->setStatus(SubmissionEntity::SUBMISSION_STATUS_COMPLETED);
                    }

                    if (100 > $newProgress && 100 > $currentProgress) {
                        /* just move from Completed to In Progress */
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

    /**
     * @param SubmissionEntity $submission
     */
    public function deleteFile(SubmissionEntity $submission)
    {
        try {
            $profile = $this->getConfigurationProfile($submission);
            $api = FileApi::create(
                $this->getAuthProvider($profile),
                $profile->getProjectId(),
                $this->getLogger()
            );
            $api->deleteFile($submission->getFileUri());
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
        }
    }

    /**
     * @param ConfigurationProfileEntity $profile
     *
     * @return JobsApi
     */
    private function getJobsApi(ConfigurationProfileEntity $profile)
    {
        return JobsApi::create($this->getAuthProvider($profile), $profile->getProjectId(), $this->getLogger());
    }

    /**
     * @param ConfigurationProfileEntity $profile
     *
     * @return BatchApi
     */
    private function getBatchApi(ConfigurationProfileEntity $profile)
    {
        return BatchApi::create($this->getAuthProvider($profile), $profile->getProjectId(), $this->getLogger());
    }

    /**
     * @param ConfigurationProfileEntity $profile
     * @param null                       $name
     * @param array                      $statuses
     *
     * @return array
     */
    public function listJobs(ConfigurationProfileEntity $profile, $name = null, array $statuses = [])
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

    /**
     * {@inheritdoc}
     */
    public function createJob(ConfigurationProfileEntity $profile, array $params)
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

    /**
     * {@inheritdoc}
     */
    public function updateJob(ConfigurationProfileEntity $profile, $jobId, $name, $description = null, $dueDate = null)
    {
        $params = new UpdateJobParameters();
        $params->setName($name);

        if (!empty($description)) {
            $params->setDescription($description);
        }

        if (!empty($dueDate)) {
            $params->setDueDate($dueDate);
        }

        return $this->getJobsApi($profile)->updateJob($jobId, $params);
    }

    /**
     * @param ConfigurationProfileEntity $profile
     * @param                                                $jobId
     * @param bool                                           $authorize
     *
     * @return array
     * @throws SmartlingApiException
     */
    public function createBatch(ConfigurationProfileEntity $profile, $jobId, $authorize = false)
    {
        $createBatchParameters = new CreateBatchParameters();
        $createBatchParameters->setTranslationJobUid($jobId);
        $createBatchParameters->setAuthorize($authorize);

        return $this->getBatchApi($profile)->createBatch($createBatchParameters);
    }

    /**
     * {@inheritdoc}
     */
    public function executeBatch(ConfigurationProfileEntity $profile, $batchUid)
    {
        $this->getBatchApi($profile)->executeBatch($batchUid);
    }

    /**
     * Returns batch uid for a given job.
     *
     * @param       $profile
     * @param       $jobId
     * @param bool  $authorize
     * @param array $updateJob
     *
     * @return string
     * @throws SmartlingApiException
     */
    public function retrieveBatch(ConfigurationProfileEntity $profile, $jobId, $authorize = true, $updateJob = [])
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

                $this->getLogger()->debug(vsprintf('Updated job "%s": "%s"', [$jobId, json_encode($updateJob)]));
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

    /**
     * Returns batch uid for a daily bucket job.
     *
     * @param ConfigurationProfileEntity $profile
     * @param                                                $authorize
     *
     * @return string|null
     * @throws \Exception
     */
    public function retrieveBatchForBucketJob(ConfigurationProfileEntity $profile, $authorize)
    {
        $getName = function ($suffix = '') {
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
                $this->getLogger()->error("Queueing file upload into the bucket job failed: can't find/create job.");

                return null;
            }

            $result = $this->createBatch($profile, $jobId, $authorize);

            return $result['batchUid'];
        } catch (SmartlingApiException $e) {
            $this->getLogger()->error(vsprintf('Can\'t create batch for a daily job "%s". Error: %s', [
                $jobName,
                $e->formatErrors(),
            ]));

            throw $e;
        }
    }

    /**
     * @inheritdoc
     */
    public function getProgressToken(ConfigurationProfileEntity $profile)
    {
        try {
            $accountUid = $this->getAccountUid($profile);

            $progressApi = ProgressTrackerApi::create(
                $this->getAuthProvider($profile),
                $profile->getProjectId(),
                $this->getLogger()
            );

            $token = $progressApi->getToken($accountUid);

            $token = array_merge($token, [
                'accountUid' => $accountUid,
                'projectId'  => $profile->getProjectId(),
            ]);

            return $token;
        } catch (\Exception $e) {
            $this->getLogger()->error(
                vsprintf(
                    'Can\'t get progress token for project id "%s". Error: %s',
                    [$profile->getProjectId(), $e->formatErrors()]
                )
            );

            throw $e;
        }
    }

    /**
     * @inheritdoc
     */
    public function deleteNotificationRecord(ConfigurationProfileEntity $profile, $space, $object, $record)
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
                            $e->formatErrors(),
                        ]
                    ));
        }
    }

    /**
     * @inheritdoc
     */
    public function setNotificationRecord(ConfigurationProfileEntity $profile, $space, $object, $recordId, $data = [], $ttl = 30)
    {
        try {
            $progressApi = ProgressTrackerApi::create(
                $this->getAuthProvider($profile),
                $profile->getProjectId(),
                $this->getLogger()
            );

            $params = new RecordParameters();
            $params->setTtl((int)$ttl);
            $params->setData($data);

            $progressApi->updateRecord($space, $object, $recordId, $params);
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
                            $e->formatErrors(),
                        ]
                    ));
        }
    }
}