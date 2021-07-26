<?php

namespace Smartling\Tests\Smartling\Settings;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Smartling\DbAl\SmartlingToCMSDatabaseAccessWrapperInterface;
use Smartling\Exception\SmartlingConfigException;
use Smartling\Exception\SmartlingDbException;
use Smartling\Settings\ConfigurationProfileEntity;
use Smartling\Settings\SettingsManager;
use Smartling\Settings\TargetLocale;
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
