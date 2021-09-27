<?php

namespace Smartling\Tests\Jobs;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Smartling\ApiWrapperInterface;
use Smartling\Jobs\JobAbstract;
use Smartling\Settings\ConfigurationProfileEntity;
use Smartling\Settings\SettingsManager;
use Smartling\Submissions\SubmissionManager;
use Smartling\Tests\Mocks\WordpressFunctionsMockHelper;
use Smartling\Tests\Traits\DbAlMock;
use Smartling\Tests\Traits\EntityHelperMock;
use Smartling\Tests\Traits\SiteHelperMock;
use Smartling\Tests\Traits\SubmissionManagerMock;

class AbstractJobTest extends TestCase
{
    use DbAlMock;
    use EntityHelperMock;
    use SiteHelperMock;
    use SubmissionManagerMock;

    private ConfigurationProfileEntity $profile;
    private SubmissionManager $submissionManager;
    private string $projectId = 'testProjectId';
    private $wpdb;

    protected function setUp(): void
    {
        WordpressFunctionsMockHelper::injectFunctionsMocks();
        $this->submissionManager = $this->mockSubmissionManager(
            $this->mockDbAl(),
            $this->mockEntityHelper($this->mockSiteHelper())
        );
        $this->profile = $this->createMock(ConfigurationProfileEntity::class);
        $this->profile->method('getProjectId')->willReturn($this->projectId);
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    protected function tearDown(): void
    {
        global $wpdb;
        $wpdb = $this->wpdb;
    }

    public function testRunCronJobUnknownSource()
    {
        $exception = new \RuntimeException('test');
        $api = $this->createMock(ApiWrapperInterface::class);
        $api->method('acquireLock')->willThrowException($exception);
        $x = $this->getJobAbstractMock($api);

        try {
            $x->runCronJob();
            $x->runCronJob('test');
        } catch (\Exception $e) {
            $this->fail('Should not throw exceptions when source is not user');
        }
        $this->expectNotToPerformAssertions();
    }

    public function testRunCronJobUserSource()
    {
        $exception = new \RuntimeException('test');
        $api = $this->createMock(ApiWrapperInterface::class);
        $api->method('acquireLock')->willThrowException($exception);
        $this->expectExceptionObject($exception);

        $x = $this->getJobAbstractMock($api);

        $x->runCronJob(JobAbstract::SOURCE_USER);
        $this->fail('Should throw exception when source is user');
    }

    /**
     * @return MockObject|JobAbstract
     */
    private function getJobAbstractMock(ApiWrapperInterface $api)
    {
        $settingsManager = $this->createMock(SettingsManager::class);
        $settingsManager->method('getActiveProfiles')->willReturn([$this->profile]);

        return $this->getMockBuilder(JobAbstract::class)
            ->setConstructorArgs([
                $api,
                $settingsManager,
                $this->submissionManager,
                '5m',
                180,
            ])
            ->getMockForAbstractClass();
    }
}
