<?php

namespace Smartling\Jobs;

use Psr\Log\LoggerInterface;
use Smartling\Bootstrap;
use Smartling\DbAl\SmartlingToCMSDatabaseAccessWrapperInterface;
use Smartling\Helpers\DiagnosticsHelper;
use Smartling\Helpers\OptionHelper;
use Smartling\Helpers\QueryBuilder\Condition\Condition;
use Smartling\Helpers\QueryBuilder\Condition\ConditionBlock;
use Smartling\Helpers\QueryBuilder\Condition\ConditionBuilder;
use Smartling\Helpers\QueryBuilder\QueryBuilder;
use Smartling\Helpers\QueryBuilder\TransactionManager;
use Smartling\Helpers\SimpleStorageHelper;
use Smartling\MonologWrapper\MonologWrapper;
use Smartling\Submissions\SubmissionManager;
use Smartling\WP\WPHookInterface;
use Smartling\WP\WPInstallableInterface;

abstract class JobAbstract implements WPHookInterface, JobInterface, WPInstallableInterface
{
    const SOURCE_USER = 'user';
    /**
     * The default TTL for workers in seconds (5 minutes)
     */
    const WORKER_DEFAULT_TTL = 300;

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

    private $transactionManager;

    public function getJobRunInterval(): string
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
     * @param SubmissionManager $submissionManager
     * @param TransactionManager $transactionManager
     * @param int $workerTTL
     */
    public function __construct(
        SubmissionManager $submissionManager,
        TransactionManager $transactionManager,
        $workerTTL = self::WORKER_DEFAULT_TTL
    ) {
        $this->logger = MonologWrapper::getLogger(get_called_class());
        $this->setSubmissionManager($submissionManager);
        $this->setWorkerTTL($workerTTL);
        $this->transactionManager = $transactionManager;
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

    public function install(): void
    {
        if (!$this->isJobHookInstalled()) {
            $this->getLogger()
                ->warning(vsprintf('The \'%s\' cron hook isn\'t installed. Installing...', [$this->getJobHookName()]));
            wp_schedule_event(time(), $this->getJobRunInterval(), $this->getJobHookName());
        }
    }

    public function uninstall(): void
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

    /**
     * @param bool $renew
     */
    public function placeLockFlag($renew = false)
    {
        $flagName = $this->getCronFlagName();
        $newFlagValue = time() + $this->getWorkerTTL();

        if (true === $renew) {
            $msgTemplate = 'Renewing flag \'%s\' for cron job \'%s\' with value \'%s\' (TTL=%s)';
        } else {
            $msgTemplate = 'Placing flag \'%s\' for cron job \'%s\' with value \'%s\' (TTL=%s)';
        }

        $this->getLogger()->debug(vsprintf(
            $msgTemplate,
            [
                $flagName,
                $this->getJobHookName(),
                $newFlagValue,
                $this->getWorkerTTL(),
            ]
        ));

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

    /**
     * @param string $source
     */
    public function runCronJob($source = '')
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

        $this->tryRunJob($source);
    }

    /**
     * @param string $source
     */
    private function tryRunJob($source)
    {
        $this->transactionManager->setAutocommit(0);
        $allowedToRun = false;
        $exception = null;
        $message = null;
        try {
            $this->transactionManager->transactionStart();
            $result = $this->transactionManager->executeSelectForUpdate($this->buildSelectQuery());
            if (null !== $result) {
                $currentTS = time();
                $flagTS = (0 < count($result)) ? (int)$result[0]['ts'] : 0;

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
                $allowedToRun = $currentTS > $flagTS;
                if ($allowedToRun) {
                    $this->placeLockFlag();
                } else {
                    $message = vsprintf(
                        'Cron \'%s\' is not allowed to run. TTL=%s has not expired yet. Expecting TTL expiration in %s seconds.',
                        [
                            $this->getJobHookName(),
                            $this->getWorkerTTL(),
                            $flagTS - time(),
                        ]
                    );
                    $this->getLogger()->debug($message);
                }
            }
        } catch (\Exception $exception) {
            $this->getLogger()->warn(vsprintf(
                'Cron job %s execution failed: %s',
                [$this->getJobHookName(), $exception->getMessage()])
            );
            $message = $exception->getMessage();
        } finally {
            $this->transactionManager->transactionCommit();
            $this->transactionManager->setAutocommit(1);
            if ($allowedToRun) {
                $this->run();
                $this->dropLockFlag();
            } elseif ($source === self::SOURCE_USER && $message !== null) {
                if ($exception !== null) {
                    throw $exception;
                }

                throw new \RuntimeException($message);
            }
        }
    }

    public function register(): void
    {
        $this->install();
        if (!DiagnosticsHelper::isBlocked()) {
            add_action($this->getJobHookName(), [$this, 'runCronJob']);
        }
    }

    /**
     * @return string
     */
    public function buildSelectQuery()
    {
        $block = ConditionBlock::getConditionBlock();
        $block->addCondition(
            Condition::getCondition(
                ConditionBuilder::CONDITION_SIGN_EQ,
                'meta_key',
                [$this->getCronFlagName()]
            )
        );
        return QueryBuilder::buildSelectQuery(
            Bootstrap::getContainer()->get('site.db')->completeTableName('sitemeta'),
            [
                ['meta_value' => 'ts'],
            ],
            $block
        );
    }
}
