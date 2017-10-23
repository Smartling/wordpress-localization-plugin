<?php

namespace Smartling\Jobs;

use Psr\Log\LoggerInterface;
use Smartling\Helpers\DiagnosticsHelper;
use Smartling\Helpers\OptionHelper;
use Smartling\Helpers\Parsers\IntegerParser;
use Smartling\Helpers\SimpleStorageHelper;
use Smartling\Submissions\SubmissionManager;
use Smartling\WP\WPHookInterface;
use Smartling\WP\WPInstallableInterface;

abstract class JobAbstract implements WPHookInterface, JobInterface, WPInstallableInterface
{
    /**
     * The default TTL for workers in seconds (20 minutes)
     */
    const WORKER_DEFAULT_TTL = 1200;

    /**
     * @var int
     */
    private $workerTTL = 0;

    /**
     * @var string
     */
    private $jobRunInterval = '';

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
     * @return LoggerInterface
     */
    protected function getLogger()
    {
        return $this->logger;
    }

    /**
     * @param LoggerInterface $logger
     */
    protected function setLogger($logger)
    {
        $this->logger = $logger;
    }

    /**
     * @return SubmissionManager
     */
    protected function getSubmissionManager()
    {
        return $this->submissionManager;
    }

    /**
     * @param SubmissionManager $submissionManager
     */
    protected function setSubmissionManager($submissionManager)
    {
        $this->submissionManager = $submissionManager;
    }

    /**
     * @return int
     */
    public function getWorkerTTL()
    {
        if (0 === $this->workerTTL) {
            $this->setWorkerTTL(self::WORKER_DEFAULT_TTL);
        }

        return $this->workerTTL;
    }

    /**
     * @param int $value
     */
    public function setWorkerTTL($value)
    {
        $this->workerTTL = $value;
    }


    /**
     * JobAbstract constructor.
     *
     * @param LoggerInterface   $logger
     * @param SubmissionManager $submissionManager
     * @param int               $workerTTL
     */
    public function __construct(LoggerInterface $logger, SubmissionManager $submissionManager, $workerTTL = self::WORKER_DEFAULT_TTL)
    {
        $this->setLogger($logger);
        $this->setSubmissionManager($submissionManager);
        $this->setWorkerTTL($workerTTL);
    }

    private function getInstalledCrons()
    {
        $val = OptionHelper::get('cron', []);
        $keys = [];
        foreach ($val as $eventsList) {
            if (is_array($eventsList)) {
                $keys = array_merge($keys, array_keys($eventsList));
            }
        }

        return $keys;
    }

    private function isJobHookInstalled()
    {
        return in_array($this->getJobHookName(), $this->getInstalledCrons(), true);
    }

    public function install()
    {
        if (!$this->isJobHookInstalled()) {
            $this->getLogger()
                ->warning(vsprintf('The \'%s\' cron hook isn\'t installed. Installing...', [$this->getJobHookName()]));
            wp_schedule_event(time(), $this->getJobRunInterval(), $this->getJobHookName());
        }
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

    protected function getCronFlagName()
    {
        return 'smartling_cron_flag_' . $this->getJobHookName();
    }

    protected function getFlagValue()
    {
        return IntegerParser::integerOrDefault(SimpleStorageHelper::get($this->getCronFlagName(), 0), 0);
    }

    protected function checkCanRun()
    {
        $currentTS = time();
        $flagTS = $this->getFlagValue();

        $this->getLogger()->debug(
            vsprintf(
                'Checking flag \'%s\' for cron job \'%s\'. Stored value=\'%s\'',
                [
                    $this->getCronFlagName(),
                    $this->getJobHookName(),
                    $flagTS,
                ]
            )
        );

        return $currentTS > $flagTS;
    }

    public function placeLockFlag()
    {
        $flagName = $this->getCronFlagName();
        $newFlagValue = time() + $this->getWorkerTTL();

        $this->getLogger()->debug(
            vsprintf(
                'Placing flag \'%s\' for cron job \'%s\' with value \'%s\' (TTL=%s)',
                [
                    $flagName,
                    $this->getJobHookName(),
                    $newFlagValue,
                    $this->getWorkerTTL(),
                ]
            )
        );

        SimpleStorageHelper::set($flagName, $newFlagValue);
    }

    public function dropLockFlag()
    {
        $flagName = $this->getCronFlagName();
        $this->getLogger()->debug(
            vsprintf(
                'Dropping flag \'%s\' for cron job \'%s\'.',
                [
                    $flagName,
                    $this->getJobHookName(),
                ]
            )
        );
        SimpleStorageHelper::drop($this->getCronFlagName());
    }

    public function runCronJob()
    {
        $this->getLogger()->debug(
            vsprintf(
                'Checking if allowed to run \'%s\' cron (TTL=%s)',
                [
                    $this->getJobHookName(),
                    $this->getWorkerTTL(),
                ]
            )
        );

        if ($this->checkCanRun()) {
            $this->placeLockFlag();
            $this->run();
            $this->dropLockFlag();
        } else {
            $this->getLogger()->debug(
                vsprintf(
                    'Cron \'%s\' is not allowed to run. TTL=%s has not expired yet. Expecting TTL expiration in %s seconds.',
                    [
                        $this->getJobHookName(),
                        $this->getWorkerTTL(),
                        $this->getFlagValue() - time(),
                    ]
                )
            );
        }
    }

    /**
     * @inheritdoc
     */
    public function register()
    {
        $this->install();
        if (!DiagnosticsHelper::isBlocked()) {
            add_action($this->getJobHookName(), [$this, 'runCronJob']);
        }
    }


}