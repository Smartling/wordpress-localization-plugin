<?php

namespace Smartling\Services;

use Psr\Log\LoggerInterface;
use Smartling\ApiWrapperInterface;
use Smartling\MonologWrapper\MonologWrapper;
use Smartling\Settings\SettingsManager;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;
use Smartling\WP\WPHookInterface;

/**
 * Class BlogRemovalHandler
 * @package Smartling\Services
 */
class BlogRemovalHandler implements WPHookInterface
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var ApiWrapperInterface
     */
    private $apiWrapper;

    /**
     * @var SettingsManager
     */
    private $settingsManager;

    /**
     * @var SubmissionManager
     */
    private $submissionManager;

    /**
     * BlogRemovalHandler constructor.
     */
    public function __construct() {
        $this->logger = MonologWrapper::getLogger(get_called_class());
    }

    /**
     * @return LoggerInterface
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * @return ApiWrapperInterface
     */
    public function getApiWrapper()
    {
        return $this->apiWrapper;
    }

    /**
     * @param ApiWrapperInterface $apiWrapper
     */
    public function setApiWrapper($apiWrapper)
    {
        $this->apiWrapper = $apiWrapper;
    }

    /**
     * @return SettingsManager
     */
    public function getSettingsManager()
    {
        return $this->settingsManager;
    }

    /**
     * @param SettingsManager $settingsManager
     */
    public function setSettingsManager($settingsManager)
    {
        $this->settingsManager = $settingsManager;
    }

    /**
     * @return SubmissionManager
     */
    public function getSubmissionManager()
    {
        return $this->submissionManager;
    }

    /**
     * @param SubmissionManager $submissionManager
     */
    public function setSubmissionManager($submissionManager)
    {
        $this->submissionManager = $submissionManager;
    }

    /**
     * Registers wp hook handlers. Invoked by wordpress.
     * @return void
     */
    public function register()
    {
        add_action('delete_blog', [$this, 'blogRemovalHandler']);
    }

    /**
     * At this time blog does not exists anymore
     * We need to remove all related submissions if any
     * And cleanup all
     *
     * @param $blogId
     */
    public function blogRemovalHandler($blogId)
    {
        $submissions = $this->getSubmissions($blogId);

        if (0 < count($submissions)) {
            $this->getLogger()->debug(
                vsprintf(
                    'While deleting blog id=%d found %d translations.', [$blogId, count($submissions)]
                )
            );

            foreach ($submissions as $submission)
            {
                $this->getLogger()->debug(
                    vsprintf(
                        'Deleting submission id=%d that references deleted blog %d.', [$submission->getId(), $blogId]
                    )
                );
                $this->getSubmissionManager()->delete($submission);

                if ('' !== $submission->getStateFieldFileUri() && 0 === $this->getSubmissionCountByFileUri($submission->getFileUri())) {
                    $this->getLogger()->debug(
                        vsprintf(
                            'File %s is not in use and will be deleted', [$submission->getFileUri()]
                        )
                    );
                    $this->getApiWrapper()->deleteFile($submission);
                }
            }
        }
    }

    private function getSubmissions($targetBlogId)
    {
        return $this->getSubmissionManager()->find([SubmissionEntity::FIELD_TARGET_BLOG_ID => $targetBlogId]);
    }

    private function getSubmissionCountByFileUri($fileUri)
    {
        return count($this->getSubmissionManager()->find([SubmissionEntity::FIELD_FILE_URI => $fileUri]));
    }

}