<?php

namespace Smartling\Tests\Smartling\Settings;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Smartling\DbAl\SmartlingToCMSDatabaseAccessWrapperInterface;
use Smartling\Exception\SmartlingConfigException;
use Smartling\Exception\SmartlingDbException;
use Smartling\Exception\SmartlingHumanReadableException;
use Smartling\Settings\ConfigurationProfileEntity;
use Smartling\Settings\Locale;
use Smartling\Settings\SettingsManager;
use Smartling\Settings\TargetLocale;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Tests\Traits\SettingsManagerMock;

class SettingsManagerTest extends TestCase
{
    use SettingsManagerMock;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        defined('ARRAY_A') || define('ARRAY_A', 'ARRAY_A');
        defined('OBJECT') || define('OBJECT', 'OBJECT');
    }

    public function testGetProfileTargetBlogIdsByMainBlogIdWithDbException()
    {
        $this->expectException(SmartlingDbException::class);
        $mock = $this->getSettingsManagerMock();

        $mock
            ->expects(self::once())
            ->method('getSingleSettingsProfile')
            ->with(5)
            ->willReturnCallback(
                function () {
                    throw new SmartlingDbException();
                });

        $mock->getProfileTargetBlogIdsByMainBlogId(5);

    }

    public function testGetProfileTargetBlogIdsByMainBlogId()
    {

        $mock = $this->getSettingsManagerMock();

        $profile = new ConfigurationProfileEntity();

        $expectedLocales = [2,3,7];

        $profile->setId(5);
        $profile->setTargetLocales([
            TargetLocale::fromArray(['smartlingLocale' => 'en', 'enabled' => 1, 'blogId' => 2]),
            TargetLocale::fromArray(['smartlingLocale' => 'fr', 'enabled' => 1, 'blogId' => 3]),
            TargetLocale::fromArray(['smartlingLocale' => 'cn', 'enabled' => 0, 'blogId' => 4]),
            TargetLocale::fromArray(['smartlingLocale' => 'zh', 'enabled' => 0, 'blogId' => 6]),
            TargetLocale::fromArray(['smartlingLocale' => 'it', 'enabled' => 1, 'blogId' => 7]),
        ]);

        $mock
            ->expects(self::once())
            ->method('getSingleSettingsProfile')
            ->with(5)
            ->willReturn($profile);

        self::assertEquals($expectedLocales, $mock->getProfileTargetBlogIdsByMainBlogId(5));
    }

    public function testGetProfileTargetBlogIdsByMainBlogIdWithConfigException()
    {
        $this->expectException(SmartlingConfigException::class);
        $this->expectExceptionMessage('No active target locales found for profile id=5');
        $mock = $this->getSettingsManagerMock();
        $profile = new ConfigurationProfileEntity();

        $profile->setId(5);
        $profile->setTargetLocales([]);

        $mock
            ->expects(self::once())
            ->method('getSingleSettingsProfile')
            ->with(5)
            ->willReturn($profile);

        $mock->getProfileTargetBlogIdsByMainBlogId(5);
    }

    private function profileWithId(int $id): ConfigurationProfileEntity
    {
        $profile = new ConfigurationProfileEntity();
        $profile->setId($id);

        return $profile;
    }

    public function testGetProfileBySubmissionUsesStoredProfile()
    {
        $stored = $this->profileWithId(7);
        $sourceLocale = new Locale();
        $sourceLocale->setBlogId(1);
        $stored->setSourceLocale($sourceLocale);
        $mock = $this->createPartialMock(SettingsManager::class, ['getSingleSettingsProfile', 'getEntityById']);
        $mock->expects(self::once())->method('getEntityById')->with(7)->willReturn([$stored]);
        $mock->expects(self::never())->method('getSingleSettingsProfile');

        $submission = (new SubmissionEntity())->setSourceBlogId(1)->setConfigurationProfileId(7);

        self::assertSame($stored, $mock->getProfileBySubmission($submission));
    }

    public function testGetProfileBySubmissionLogsWarningWhenStoredProfileBlogMismatches()
    {
        $stored = $this->profileWithId(7);
        $sourceLocale = new Locale();
        $sourceLocale->setBlogId(2);
        $stored->setSourceLocale($sourceLocale);
        $mock = $this->createPartialMock(SettingsManager::class, ['getSingleSettingsProfile', 'getEntityById', 'getLogger']);
        $mock->method('getLogger')->willReturn(new NullLogger());
        $mock->expects(self::once())->method('getEntityById')->with(7)->willReturn([$stored]);
        $mock->expects(self::never())->method('getSingleSettingsProfile');

        $submission = (new SubmissionEntity())->setSourceBlogId(1)->setConfigurationProfileId(7);

        self::assertSame($stored, $mock->getProfileBySubmission($submission));
    }

    /**
     * Credentials/project now come from the stamped profile (getProfileBySubmission), so the
     * locale must too - otherwise a profile switch between request and delivery can send the
     * right project but the wrong (or a nonexistent) locale.
     */
    public function testGetSmartlingLocaleBySubmissionUsesStoredProfileNotActiveOne()
    {
        $stored = $this->profileWithId(9);
        $storedSourceLocale = new Locale();
        $storedSourceLocale->setBlogId(1);
        $stored->setSourceLocale($storedSourceLocale);
        $storedTargetLocale = new TargetLocale();
        $storedTargetLocale->setBlogId(2);
        $storedTargetLocale->setSmartlingLocale('de-DE');
        $stored->setTargetLocales([$storedTargetLocale]);

        $mock = $this->createPartialMock(SettingsManager::class, ['getSingleSettingsProfile', 'getEntityById']);
        $mock->expects(self::once())->method('getEntityById')->with(9)->willReturn([$stored]);
        $mock->expects(self::never())->method('getSingleSettingsProfile');

        $submission = (new SubmissionEntity())->setSourceBlogId(1)->setTargetBlogId(2)->setConfigurationProfileId(9);

        self::assertSame('de-DE', $mock->getSmartlingLocaleBySubmission($submission));
    }

    public function testGetProfileBySubmissionFallsBackWithoutStoredProfile()
    {
        $active = $this->profileWithId(3);
        $mock = $this->createPartialMock(SettingsManager::class, ['getSingleSettingsProfile', 'getEntityById']);
        $mock->expects(self::never())->method('getEntityById');
        $mock->expects(self::once())->method('getSingleSettingsProfile')->with(1)->willReturn($active);

        $submission = (new SubmissionEntity())->setSourceBlogId(1);

        self::assertSame($active, $mock->getProfileBySubmission($submission));
    }

    public function testGetProfileBySubmissionFallsBackWhenStoredProfileWasDeleted()
    {
        $active = $this->profileWithId(3);
        $mock = $this->createPartialMock(SettingsManager::class, ['getSingleSettingsProfile', 'getEntityById', 'getLogger']);
        $mock->method('getLogger')->willReturn(new NullLogger());
        $mock->expects(self::once())->method('getEntityById')->with(7)->willReturn([]);
        $mock->expects(self::once())->method('getSingleSettingsProfile')->with(1)->willReturn($active);

        $submission = (new SubmissionEntity())->setSourceBlogId(1)->setConfigurationProfileId(7);

        self::assertSame($active, $mock->getProfileBySubmission($submission));
    }

    private function resolverMock(): SettingsManager
    {
        $mock = $this->createPartialMock(SettingsManager::class, ['getEntityById', 'findEntityByMainLocale', 'getLogger']);
        $mock->method('getLogger')->willReturn(new NullLogger());

        return $mock;
    }

    private function profileForBlog(int $id, int $blogId, int $active): ConfigurationProfileEntity
    {
        $profile = $this->profileWithId($id);
        $locale = new Locale();
        $locale->setBlogId($blogId);
        $profile->setSourceLocale($locale);
        $profile->setIsActive($active);

        return $profile;
    }

    public function testResolveRequestedProfileUsesRequestedWhenValidAndActive()
    {
        $requested = $this->profileForBlog(5, 1, 1);
        $mock = $this->resolverMock();
        $mock->expects(self::once())->method('getEntityById')->with(5)->willReturn([$requested]);
        $mock->expects(self::never())->method('findEntityByMainLocale');

        self::assertSame($requested, $mock->resolveRequestedProfile(5, 1));
    }

    public function testResolveRequestedProfileRejectsUnknownProfile()
    {
        $this->expectException(SmartlingHumanReadableException::class);
        $mock = $this->resolverMock();
        $mock->method('getEntityById')->with(5)->willReturn([]);

        $mock->resolveRequestedProfile(5, 1);
    }

    public function testResolveRequestedProfileRejectsProfileOfDifferentBlog()
    {
        $this->expectException(SmartlingHumanReadableException::class);
        $mock = $this->resolverMock();
        $mock->method('getEntityById')->with(5)->willReturn([$this->profileForBlog(5, 99, 1)]);

        $mock->resolveRequestedProfile(5, 1);
    }

    public function testResolveRequestedProfileRejectsInactiveProfile()
    {
        $this->expectException(SmartlingHumanReadableException::class);
        $mock = $this->resolverMock();
        $mock->method('getEntityById')->with(5)->willReturn([$this->profileForBlog(5, 1, 0)]);

        $mock->resolveRequestedProfile(5, 1);
    }

    public function testResolveRequestedProfileFallsBackToActiveProfileWhenNoneRequested()
    {
        $only = $this->profileForBlog(3, 1, 1);
        $mock = $this->resolverMock();
        $mock->expects(self::never())->method('getEntityById');
        $mock->method('findEntityByMainLocale')->with(1)->willReturn([$only]);

        self::assertSame($only, $mock->resolveRequestedProfile(null, 1));
    }

    public function testResolveRequestedProfileThrowsWhenNoneRequestedAndNoneActive()
    {
        $this->expectException(SmartlingHumanReadableException::class);
        $mock = $this->resolverMock();
        $mock->method('findEntityByMainLocale')->with(1)->willReturn([]);

        $mock->resolveRequestedProfile(null, 1);
    }

    public function testAssertTargetBlogIdsBelongToProfileAllowsEnabledLocales()
    {
        $profile = $this->profileWithId(1);
        $profile->setTargetLocales([$this->enabledLocale(2), $this->enabledLocale(3)]);
        $mock = $this->resolverMock();

        $mock->assertTargetBlogIdsBelongToProfile($profile, [2, 3]);
        $this->addToAssertionCount(1);
    }

    public function testAssertTargetBlogIdsBelongToProfileRejectsBlogOutsideProfile()
    {
        $this->expectException(SmartlingHumanReadableException::class);
        $profile = $this->profileWithId(1);
        $profile->setTargetLocales([$this->enabledLocale(2)]);
        $mock = $this->resolverMock();

        $mock->assertTargetBlogIdsBelongToProfile($profile, [2, 99]);
    }

    public function testAssertTargetBlogIdsBelongToProfileRejectsDisabledLocale()
    {
        $this->expectException(SmartlingHumanReadableException::class);
        $profile = $this->profileWithId(1);
        $disabled = $this->enabledLocale(2);
        $disabled->setEnabled(false);
        $profile->setTargetLocales([$disabled]);
        $mock = $this->resolverMock();

        $mock->assertTargetBlogIdsBelongToProfile($profile, [2]);
    }

    private function enabledLocale(int $blogId): TargetLocale
    {
        $locale = new TargetLocale();
        $locale->setBlogId($blogId);
        $locale->setEnabled(true);

        return $locale;
    }

    public function testGetEntitiesQueries()
    {
        $db = $this->createMock(SmartlingToCMSDatabaseAccessWrapperInterface::class);
        $db->method('completeTableName')->willReturnArgument(0);
        $db->method('fetch')->willReturn([]);
        $x = $this->getMockBuilder(SettingsManager::class)->disableOriginalConstructor()
            ->onlyMethods(['getDbal', 'getLogger', 'fetchData', 'logQuery'])->getMock();
        $x->method('getDbal')->willReturn($db);
        $x->method('getLogger')->willReturn(new NullLogger());
        $selectQuery = "SELECT `id`, `profile_name`, `project_id`, `user_identifier`, `secret_key`, `is_active`, `original_blog_id`, `auto_authorize`, `retrieval_type`, `upload_on_update`, `publish_completed`, `download_on_change`, `clean_metadata_on_download`, `always_sync_images_on_upload`, `target_locales`, `filter_skip`, `filter_copy_by_field_name`, `filter_copy_by_field_value_regex`, `filter_flag_seo`, `clone_attachment`, `enable_notifications`, `filter_field_name_regexp` FROM `smartling_configuration_profiles`";
        $x->expects($this->once())->method('fetchData')->with($selectQuery);
        $x->expects($this->exactly(2))->method('logQuery')->withConsecutive([$selectQuery], ["SELECT COUNT(*) AS `cnt` FROM `smartling_configuration_profiles`"]);
        $x->getEntities();
    }

    public function testStoreEntityInsertQuery()
    {
        $db = $this->createMock(SmartlingToCMSDatabaseAccessWrapperInterface::class);
        $db->method('completeTableName')->willReturnArgument(0);
        $db->expects($this->once())->method('query')->with("INSERT  INTO `smartling_configuration_profiles` (`profile_name`, `project_id`, `user_identifier`, `secret_key`, `is_active`, `original_blog_id`, `auto_authorize`, `retrieval_type`, `upload_on_update`, `publish_completed`, `download_on_change`, `clean_metadata_on_download`, `always_sync_images_on_upload`, `target_locales`, `filter_skip`, `filter_copy_by_field_name`, `filter_copy_by_field_value_regex`, `filter_flag_seo`, `clone_attachment`, `enable_notifications`, `filter_field_name_regexp`) VALUES ('','','','','0','0','0','','','','','','','[]','','','','','','','')")->willReturn(true);
        $x = $this->getMockBuilder(SettingsManager::class)->disableOriginalConstructor()
            ->onlyMethods(['getDbal', 'getLogger', 'fetchData', 'logQuery'])->getMock();
        $x->method('getDbal')->willReturn($db);
        $x->method('getLogger')->willReturn(new NullLogger());
        $entity = new ConfigurationProfileEntity();
        $entity->setTargetLocales([]);
        $this->expectException(\TypeError::class); // storing fails, we only check query
        $x->storeEntity($entity);
    }

    public function testStoreEntityUpdateQuery()
    {
        $entityId = 17;
        $db = $this->createMock(SmartlingToCMSDatabaseAccessWrapperInterface::class);
        $db->method('completeTableName')->willReturnArgument(0);
        $db->expects($this->once())->method('query')->with("UPDATE `smartling_configuration_profiles` SET `profile_name` = '', `project_id` = '', `user_identifier` = '', `secret_key` = '', `is_active` = '0', `original_blog_id` = '0', `auto_authorize` = '0', `retrieval_type` = '', `upload_on_update` = '', `publish_completed` = '', `download_on_change` = '', `clean_metadata_on_download` = '', `always_sync_images_on_upload` = '', `target_locales` = '[]', `filter_skip` = '', `filter_copy_by_field_name` = '', `filter_copy_by_field_value_regex` = '', `filter_flag_seo` = '', `clone_attachment` = '', `enable_notifications` = '', `filter_field_name_regexp` = '' WHERE ( `id` = '$entityId' ) LIMIT 1")->willReturn(true);
        $x = $this->getMockBuilder(SettingsManager::class)->disableOriginalConstructor()
            ->onlyMethods(['getDbal', 'getLogger', 'fetchData', 'logQuery'])->getMock();
        $x->method('getDbal')->willReturn($db);
        $x->method('getLogger')->willReturn(new NullLogger());
        $entity = new ConfigurationProfileEntity();
        $entity->setId($entityId);
        $entity->setTargetLocales([]);
        $x->storeEntity($entity);
    }
}
