<?php

namespace Smartling\Jobs;

use Psr\Log\LoggerInterface;
use Smartling\Bootstrap;
use Smartling\Helpers\DiagnosticsHelper;
use Smartling\Submissions\SubmissionManager;
use Smartling\WP\WPHookInterface;
use Smartling\WP\WPInstallableInterface;

abstract class JobAbstract implements WPHookInterface, JobInterface, WPInstallableInterface
{
    /**
     * @var string
     */
    private $jobRunInterval = '';

    /**
     * @var string
     */
    private $jobHookName = '';

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var SubmissionManager
     */
    private $submissionManager;

    /**
     * @return string
     */
    public function getJobRunInterval()
    {
        return $this->jobRunInterval;
    }

    /**
     * @param int $jobRunInterval
     */
    public function setJobRunInterval($jobRunInterval)
    {
        $this->jobRunInterval = $jobRunInterval;
    }

    /**
     * @return string
     */
    public function getJobHookName()
    {
        return $this->jobHookName;
    }

    /**
     * @param string $jobHookName
     */
    public function setJobHookName($jobHookName)
    {
        $this->jobHookName = $jobHookName;
    }

    /**
     * @return LoggerInterface
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * @param LoggerInterface $logger
     */
    public function setLogger($logger)
    {
        $this->logger = $logger;
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
     * JobAbstract constructor.
     *
     * @param LoggerInterface   $logger
     * @param SubmissionManager $submissionManager
     */
    public function __construct(LoggerInterface $logger, SubmissionManager $submissionManager)
    {
        $this->setLogger($logger);
        $this->setSubmissionManager($submissionManager);
    }

    public function install()
    {
        wp_schedule_event(time(), $this->getJobRunInterval(), $this->getJobHookName());
    }

    /**
     * uninstalls scheduled event
     */
    public function uninstall()
    {
        wp_clear_scheduled_hook($this->getJobHookName());
    }

    public function activate()
    {
        $this->install();
    }

    public function deactivate()
    {
        $this->uninstall();
    }


    /**
     *
     */
    public function cronProxyMethod()
    {
        die('rrr');
    }

    /**
     * @inheritdoc
     */
    public function register()
    {
        if (!DiagnosticsHelper::isBlocked()) {
            add_action($this->getJobHookName(), [ $this, 'cronProxyMethod' ]);
        }
    }


}