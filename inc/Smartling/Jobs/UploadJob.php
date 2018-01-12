<?php

namespace Smartling\Jobs;

use Smartling\Base\ExportedAPI;
use Smartling\Helpers\ArrayHelper;
use Smartling\MonologWrapper\MonologWrapper;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;

/**
 * Class UploadJob
 * @package Smartling\Jobs
 */
class UploadJob extends JobAbstract
{

    const JOB_HOOK_NAME = 'smartling-upload-task';

    /**
     * UploadJob constructor.
     */
    public function __construct(SubmissionManager $submissionManager, $workerTTL = self::WORKER_DEFAULT_TTL) {
        parent::__construct(MonologWrapper::getLogger(get_called_class()), $submissionManager, $workerTTL);
    }

    /**
     * @return string
     */
    public function getJobHookName()
    {
        return self::JOB_HOOK_NAME;
    }

    /**
     * @inheritdoc
     */
    public function run()
    {
        $this->getLogger()->info('Started UploadJob.');

        do {
            $entities = $this->getSubmissionManager()->find(
                [
                    'status'    => [SubmissionEntity::SUBMISSION_STATUS_NEW],
                    'is_locked' => [0],
                ], 1);

            if (0 === count($entities)) {
                break;
            }

            $entity = ArrayHelper::first($entities);

            $this->getLogger()->info(
                vsprintf(
                    'Cron Job triggers content upload for submission id = \'%s\' with status = \'%s\' for entity = \'%s\', blog = \'%s\', id = \'%s\', targetBlog = \'%s\', locale = \'%s\'.',
                    [
                        $entity->getId(),
                        $entity->getStatus(),
                        $entity->getContentType(),
                        $entity->getSourceBlogId(),
                        $entity->getSourceId(),
                        $entity->getTargetBlogId(),
                        $entity->getTargetLocale(),
                    ]
                )
            );

            do_action(ExportedAPI::ACTION_SMARTLING_SEND_FILE_FOR_TRANSLATION, $entity);

        } while (0 < count($entities));

        $this->getLogger()->info('Finished UploadJob.');
    }


}
