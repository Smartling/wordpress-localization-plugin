<?php

namespace Smartling\Tests\Mocks;

use Smartling\Settings\ConfigurationProfileEntity;
use Smartling\Settings\SettingsManager;
use Smartling\Vendor\Smartling\Jobs\JobsApi;

class ApiWrapper extends \Smartling\ApiWrapper {
    private ?JobsApi $jobsApi;
    public function __construct(SettingsManager $manager, JobsApi $jobsApi = null, string $pluginName = 'Test', string $pluginVersion = 'Test')
    {
        parent::__construct($manager, $pluginName, $pluginVersion);
        $this->jobsApi = $jobsApi;
    }

    protected function getJobsApi(ConfigurationProfileEntity $profile): JobsApi
    {
        return $this->jobsApi;
    }
}
