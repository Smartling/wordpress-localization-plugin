<?php

namespace Smartling\Base;

use PHPUnit\Framework\TestCase;
use Smartling\ContentTypes\ExternalContentManager;
use Smartling\DbAl\UploadQueueManager;
use Smartling\DbAl\WordpressContentEntities\Entity;
use Smartling\DbAl\WordpressContentEntities\PostEntityStd;
use Smartling\Helpers\ContentHelper;
use Smartling\Helpers\ContentSerializationHelper;
use Smartling\Helpers\FieldsFilterHelper;
use Smartling\Helpers\FileUriHelper;
use Smartling\Helpers\GutenbergBlockHelper;
use Smartling\Helpers\PostContentHelper;
use Smartling\Helpers\TestRunHelper;
use Smartling\Helpers\TranslationHelper;
use Smartling\Helpers\WordpressFunctionProxyHelper;
use Smartling\Helpers\XmlHelper;
use Smartling\Settings\SettingsManager;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;

class SmartlingCoreTraitTest extends TestCase
{
    public function testPrepareTargetContentClonedGuid()
    {
        $wpProxy = $this->createMock(WordpressFunctionProxyHelper::class);
        $wpProxy->method('apply_filters')->willReturnArgument(2);

        $x = new SmartlingCore(
            $this->createMock(ExternalContentManager::class),
            $this->createMock(FileUriHelper::class),
            $this->createMock(GutenbergBlockHelper::class),
            $this->createMock(PostContentHelper::class),
            $this->createMock(UploadQueueManager::class),
            $this->createMock(XmlHelper::class),
            $this->createMock(TestRunHelper::class),
            $wpProxy,
        );

        $entity = new PostEntityStd();
        $entity->guid = 'test';

        $contentHelper = $this->createMock(ContentHelper::class);
        $contentHelper->method('readSourceContent')->willReturn($entity);
        $contentHelper->expects($this->once())->method('writeTargetContent')
            ->willReturnCallback(function (SubmissionEntity $_, Entity $entity) {
                $this->assertInstanceOf(PostEntityStd::class, $entity);
                $this->assertEquals(null, $entity->guid);

                return $entity;
            });

        $x->setContentHelper($contentHelper);
        $x->setContentSerializationHelper($this->createMock(ContentSerializationHelper::class));
        $x->setFieldsFilter($this->createMock(FieldsFilterHelper::class));
        $x->setSettingsManager($this->createMock(SettingsManager::class));

        $submissionManager = $this->createMock(SubmissionManager::class);
        $submissionManager->method('storeEntity')->willReturnArgument(0);

        $x->setSubmissionManager($submissionManager);
        $x->setTranslationHelper($this->createMock(TranslationHelper::class));

        $submission = $this->createMock(SubmissionEntity::class);
        $submission->method('getTargetId')->willReturn(0);
        $submission->method('isCloned')->willReturn(true);
        $submission->expects($this->never())->method('setLastError');

        $x->prepareTargetContent($submission);
    }
}
