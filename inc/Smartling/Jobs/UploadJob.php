<?php

namespace Smartling\Jobs;

use Psr\Log\LoggerInterface;
use Smartling\Base\SmartlingCore;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;

/**
 * Class UploadJob
 * @package Smartling\Jobs
 */
class UploadJob extends JobAbstract
{

    /**
     * UploadJob constructor.
     *
     * @param LoggerInterface   $logger
     * @param SubmissionManager $submissionManager
     */
    public function __construct(LoggerInterface $logger, SubmissionManager $submissionManager)
    {
        parent::__construct($logger, $submissionManager);
        $this->setJobHookName('smartling-upload-task');
    }

    /**
     * @inheritdoc
     */
    public function run()
    {
        $this->getLogger()->info('Started UploadJob.');

        $entities = $this->getSubmissionManager()->find(['status' => [SubmissionEntity::SUBMISSION_STATUS_NEW]]);

        $this->getLogger()->info(vsprintf('Found %s submissions.', [count($entities)]));
        foreach ($entities as $entity) {
            $this->getLogger()->info(
                vsprintf(
                    'Cron Job triggers content upload for submission id = \'%s\' with status = \'%s\' for entity = \'%s\', blog = \'%s\', id = \'%s\', targetBlog = \'%s\', locale = \'%s\'',
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

            do_action(SmartlingCore::ACTION_SMARTLING_SEND_FILE_FOR_TRANSLATION, $entity);
        }
        $this->getLogger()->info('Finished UploadJob.');
    }
}