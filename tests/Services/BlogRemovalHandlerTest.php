<?php

namespace Smartling\Tests\Services;

use PHPUnit\Framework\TestCase;
use Smartling\Services\BlogRemovalHandler;
use Smartling\Settings\ConfigurationProfileEntity;
use Smartling\Settings\TargetLocale;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Vendor\Smartling\AuditLog\Params\CreateRecordParameters;
use Smartling\ApiWrapperInterface;
use Smartling\Settings\SettingsManager;
use Smartling\Submissions\SubmissionManager;

class BlogRemovalHandlerTest extends TestCase
{
    public function testBlogRemovalHandler()
    {
        $locales = [];
        for ($blogId = 1; $blogId < 4; $blogId++) {
            $locale = $this->createMock(TargetLocale::class);
            $locale->method('getBlogId')->willReturn($blogId);
            $locales[] = $locale;
        }

        $profiles = [];
        foreach ([1, 3] as $blogId) {
            $profile = $this->createMock(ConfigurationProfileEntity::class);
            $profile->method('getSourceLocale')->willReturn(array_values(array_filter($locales, static function (TargetLocale $locale) use ($blogId) {
                return $locale->getBlogId() === $blogId;
            }))[0]);
            $profile->method('getTargetLocales')->willReturn(array_values(array_filter($locales, static function (TargetLocale $locale) use ($blogId) {
                return $locale->getBlogId() !== $blogId;
            })));
            $profiles[] = $profile;
        }

        $settingsManager = $this->createMock(SettingsManager::class);
        $settingsManager->method('getEntities')->willReturn($profiles);

        $submissions = [];
        foreach (['12', '13', '31', '32'] as $blogs) {
            $submission = $this->createMock(SubmissionEntity::class);
            $submission->method('getFileUri')->willReturn($blogs);
            $submission->method('getId')->willReturn((int)$blogs);
            $submission->method('getSourceBlogId')->willReturn((int)$blogs[0]);
            $submission->method('getTargetBlogId')->willReturn((int)$blogs[1]);
            $submissions[] = $submission;
        }

        $submissionManager = $this->createMock(SubmissionManager::class);
        $submissionManager->method('find')->willReturnCallback(function (array $arguments) use ($submissions) {
            if (array_key_exists(SubmissionEntity::FIELD_TARGET_BLOG_ID, $arguments)) {
                return array_filter($submissions, static function (SubmissionEntity $submission) use ($arguments) {
                    return $submission->getTargetBlogId() === $arguments[SubmissionEntity::FIELD_TARGET_BLOG_ID];
                });
            }

            return [];
        });
        $submissionManager->expects($this->exactly(2))->method('delete');

        $apiWrapper = $this->createMock(ApiWrapperInterface::class);
        $matcher = $this->exactly(2);
        $apiWrapper->expects($matcher)
            ->method('createAuditLogRecord')
            ->willReturnCallback(function (ConfigurationProfileEntity $profile, string $actionType, string $description) use ($matcher, $profiles) {
                $this->assertEquals($profiles[$matcher->getInvocationCount() - 1], $profile);
                $this->assertEquals(CreateRecordParameters::ACTION_TYPE_DELETE, $actionType);
                switch ($matcher->getInvocationCount()) {
                    case 1:
                        $this->assertEquals('Blog deletion handler, submissionId=12, fileUri=12', $description);
                        break;
                    case 2:
                        $this->assertEquals('Blog deletion handler, submissionId=32, fileUri=32', $description);
                        break;
                }
            });

        (new BlogRemovalHandler($apiWrapper, $settingsManager, $submissionManager))->blogRemovalHandler(2);
    }
}
