<?php

namespace Smartling\Tests\IntegrationTests\tests;

use Smartling\Tests\IntegrationTests\SmartlingUnitTestCaseAbstract;

class ProfileCreationTest extends SmartlingUnitTestCaseAbstract
{
    public function setUp(): void
    {
        $this->cleanUpTables();
        $this->registerPostTypes();
    }

    public function testCreateProfile()
    {
        $profile = $this->createProfile();
        $profile = $this->getSettingsManager()->storeEntity($profile);
        self::assertEquals(1, $profile->getId());
    }

    public function testUpdateProfile()
    {
        $this->ensureProfileExists();
        $profile = $this->getProfileById(1);
        self::assertEquals(1, (int)$profile->getAutoAuthorize());
        $profile->setAutoAuthorize(0);
        $this->getSettingsManager()->storeEntity($profile);
        $profile = $this->getProfileById(1);
        self::assertEquals(0, (int)$profile->getAutoAuthorize());
    }
}
