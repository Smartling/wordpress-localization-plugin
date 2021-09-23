<?php

namespace Smartling\Jobs;

use Smartling\ApiWrapperInterface;
use Smartling\Exceptions\SmartlingApiException;
use Smartling\Helpers\ArrayHelper;
use Smartling\Helpers\DiagnosticsHelper;
use Smartling\Helpers\LoggerSafeTrait;
use Smartling\Helpers\OptionHelper;
use Smartling\Settings\SettingsManager;
use Smartling\Submissions\SubmissionManager;
use Smartling\WP\WPHookInterface;
use Smartling\WP\WPInstallableInterface;

abstract class JobAbstract implements WPHookInterface, JobInterface, WPInstallableInterface
{
    use LoggerSafeTrait;

    protected ApiWrapperInterface $api;
    protected string $jobRunInterval;
    protected SettingsManager $settingsManager;
    protected SubmissionManager $submissionManager;
    protected int $workerTTL;

    public function __construct(
        ApiWrapperInterface $api,
        SettingsManager $settingsManager,
        SubmissionManager $submissionManager,
        string $jobRunInterval,
        int $workerTTL
    ) {
        $this->api = $api;
        $this->jobRunInterval = $jobRunInterval;
        $this->settingsManager = $settingsManager;
        $this->submissionManager = $submissionManager;
        $this->workerTTL = $workerTTL;
    }

    private function getInstalledCrons(): array
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

    private function isJobHookInstalled(): bool
    {
        return in_array($this->getJobHookName(), $this->getInstalledCrons(), true);
    }

    public function install(): void
    {
        if (!$this->isJobHookInstalled()) {
            $this->getLogger()
                ->warning(vsprintf('The \'%s\' cron hook isn\'t installed. Installing...', [$this->getJobHookName()]));
            wp_schedule_event(time(), $this->jobRunInterval, $this->getJobHookName());
        }
    }

    public function uninstall(): void
    {
        wp_clear_scheduled_hook($this->getJobHookName());
    }

    public function activate(): void
    {
        $this->install();
    }

    public function deactivate(): void
    {
        $this->uninstall();
    }

    protected function getCronFlagName(): string
    {
        return 'smartling_cron_flag_' . $this->getJobHookName();
    }

    /**
     * @throws SmartlingApiException
     */
    public function placeLockFlag(bool $renew = false): void
    {
        $profile = ArrayHelper::first($this->settingsManager->getActiveProfiles());
        if ($profile === false) {
            $this->getLogger()->debug('No active profiles, skipping ' . $this->getJobHookName() . ' run');
            return;
        }
        $flagName = $this->getCronFlagName();
        $newFlagValue = time() + $this->workerTTL;

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
                $this->workerTTL,
            ]
        ));

        if ($renew) {
            $this->api->renewLock($profile, $flagName, $this->workerTTL);
        } else {
            $this->api->acquireLock($profile, $flagName, $this->workerTTL);
        }
    }

    public function dropLockFlag(): void
    {
        $profile = ArrayHelper::first($this->settingsManager->getActiveProfiles());
        if ($profile === false) {
            $this->getLogger()->debug('No active profiles when trying to drop lock flag for ' . $this->getJobHookName());
            return;
        }
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
        try {
            $this->api->releaseLock($profile, $flagName);
        } catch (SmartlingApiException $e) {
            $this->getLogger()->debug("Failed to remove lock for {$this->getJobHookName()}: {$e->getMessage()}");
        }
    }

    public function runCronJob(): void
    {
        $this->getLogger()->debug(
            vsprintf(
                'Checking if allowed to run \'%s\' cron (TTL=%s)',
                [
                    $this->getJobHookName(),
                    $this->workerTTL,
                ]
            )
        );

        $this->tryRunJob();
    }

    private function tryRunJob(): void
    {
        try {
            $this->placeLockFlag();
        } catch (SmartlingApiException $e) {
            $errorMessage = $e->getErrors()[0]['message'] ?? $e->getMessage();
            $this->getLogger()->debug("Failed to place lock flag: $errorMessage");
            return;
        }
        try {
            $this->run();
        } catch (\Exception $exception) {
            $this->getLogger()->warning(vsprintf(
                'Cron job %s execution failed: %s',
                [$this->getJobHookName(), $exception->getMessage()])
            );
        } finally {
            $this->dropLockFlag();
        }
    }

    public function register(): void
    {
        $this->install();
        if (!DiagnosticsHelper::isBlocked()) {
            add_action($this->getJobHookName(), [$this, 'runCronJob']);
        }
    }
}
