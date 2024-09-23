<?php

namespace Smartling\Jobs;

use Smartling\ApiWrapperInterface;
use Smartling\Exception\EntityNotFoundException;
use Smartling\Helpers\ArrayHelper;
use Smartling\Helpers\Cache;
use Smartling\Helpers\DiagnosticsHelper;
use Smartling\Helpers\LoggerSafeTrait;
use Smartling\Helpers\OptionHelper;
use Smartling\Helpers\SimpleStorageHelper;
use Smartling\Settings\ConfigurationProfileEntity;
use Smartling\Settings\SettingsManager;
use Smartling\Submissions\SubmissionManager;
use Smartling\Vendor\Smartling\Exceptions\SmartlingApiException;
use Smartling\WP\WPHookInterface;
use Smartling\WP\WPInstallableInterface;

abstract class JobAbstract implements WPHookInterface, JobInterface, WPInstallableInterface
{
    use LoggerSafeTrait;

    public const LAST_FINISH_SUFFIX = '-last-run';
    public const SOURCE_USER = 'user';
    private const THROTTLED_MESSAGE = "Throttled";

    public function __construct(
        protected ApiWrapperInterface $api,
        private Cache $cache,
        protected SettingsManager $settingsManager,
        protected SubmissionManager $submissionManager,
        private int $throttleIntervalSeconds,
        private string $jobRunInterval,
        private int $workerTTL,
    ) {
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
        return 'wordpress_smartling_cron_flag_' . $this->getJobHookName();
    }

    /**
     * @throws EntityNotFoundException
     * @throws SmartlingApiException
     */
    public function placeLockFlag(bool $renew = false, string $source = ''): void
    {
        $profile = $this->getActiveProfile();
        $flagName = $this->getCronFlagName();
        $newFlagValue = time() + $this->workerTTL;

        if (true === $renew) {
            $msgTemplate = 'Renewing flag \'%s\' for cron job \'%s\' with value \'%s\' (TTL=%s)';
        } else {
            if ($source !== self::SOURCE_USER && $this->cache->get($flagName)) {
                throw new \RuntimeException(self::THROTTLED_MESSAGE);
            }
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

        if ($this->throttleIntervalSeconds > 0) {
            $this->cache->set($flagName, 1, $this->throttleIntervalSeconds);
        }
        if ($renew) {
            $this->api->renewLock($profile, $flagName, $this->workerTTL);
        } else {
            $this->api->acquireLock($profile, $flagName, $this->workerTTL);
        }
    }

    /**
     * @throws EntityNotFoundException
     * @throws SmartlingApiException
     */
    public function dropLockFlag(): void
    {
        $profile = $this->getActiveProfile();
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
        $this->api->releaseLock($profile, $flagName);
    }

    public function runCronJob(string $source = ''): void
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

        $message = null;
        try {
            $this->tryRunJob($source);
        } catch (EntityNotFoundException) {
            $message = "No active profiles, skipping {$this->getJobHookName()} run";
            $this->getLogger()->debug($message);
        } catch (SmartlingApiException $e) {
            $errorMessage = $e->getErrors()[0]['message'] ?? $e->getMessage();
            $message = "Failed to place lock flag for {$this->getJobHookName()}: $errorMessage";
            $this->getLogger()->debug($message);
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === self::THROTTLED_MESSAGE) {
                $message = self::THROTTLED_MESSAGE;
            } else {
                throw $e;
            }
        }
        catch (\Throwable $e) {
            $errorClass = get_class($e);
            $this->getLogger()->error("Caught class=\"$errorClass\", code=\"{$e->getCode()}\", message=\"{$e->getMessage()}\", trace: {$e->getTraceAsString()}");
            if ($source === self::SOURCE_USER) {
                throw new \RuntimeException($e->getMessage());
            }
        }
        if ($source === self::SOURCE_USER && $message !== null) {
            throw new \RuntimeException($message);
        }
    }

    /**
     * @throws EntityNotFoundException
     * @throws SmartlingApiException
     */
    private function tryRunJob(string $source = ''): void
    {
        $this->placeLockFlag(source: $source);
        try {
            $this->run($source);
        } finally {
            try {
                $this->dropLockFlag();
            } catch (EntityNotFoundException) {
                $this->getLogger()->debug('No active profiles when trying to drop lock flag for ' . $this->getJobHookName());
            } catch (SmartlingApiException $e) {
                $errorMessage = $e->getErrors()[0]['message'] ?? $e->getMessage();
                $this->getLogger()->debug("Failed to remove lock for {$this->getJobHookName()}: $errorMessage");
            }
            SimpleStorageHelper::set($this->getJobHookName() . self::LAST_FINISH_SUFFIX, time());
        }
    }

    private function getActiveProfile(): ConfigurationProfileEntity
    {
        $profile = ArrayHelper::first($this->settingsManager->getActiveProfiles());
        if ($profile === false) {
            throw new EntityNotFoundException('No active profiles');
        }
        return $profile;
    }

    public function register(): void
    {
        $this->install();
        if (!DiagnosticsHelper::isBlocked()) {
            add_action($this->getJobHookName(), [$this, 'runCronJob']);
        }
    }
}
