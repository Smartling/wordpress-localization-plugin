<?php

namespace Smartling\Jobs;

use Smartling\Base\ExportedAPI;
use Smartling\Submissions\SubmissionEntity;

/**
 * Class UploadJob
 * @package Smartling\Jobs
 */
class UploadJob extends JobAbstract
{
    /**
     * @return string
     */
    public function getJobHookName()
    {
        return 'smartling-upload-task';
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

            do_action(ExportedAPI::ACTION_SMARTLING_SEND_FILE_FOR_TRANSLATION, $entity);
        }
        $this->getLogger()->info('Finished UploadJob.');
    }


}