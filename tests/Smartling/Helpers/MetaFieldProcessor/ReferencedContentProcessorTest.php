<?php

namespace Smartling\Tests\Smartling\Helpers\MetaFieldProcessor;

use PHPUnit\Framework\TestCase;
use Smartling\Helpers\ContentHelper;
use Smartling\Helpers\MetaFieldProcessor\ReferencedContentProcessor;
use Smartling\Helpers\TranslationHelper;
use Smartling\Jobs\JobEntity;
use Smartling\Jobs\JobEntityWithBatchUid;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;

class ReferencedContentProcessorTest extends TestCase
{
    private function getProcessor(TranslationHelper $translationHelper): ReferencedContentProcessor
    {
        return new ReferencedContentProcessor(
            $this->createMock(ContentHelper::class),
            $this->getMockBuilder(SubmissionManager::class)->disableOriginalConstructor()->getMock(),
            $translationHelper,
            '/.*/',
            'post',
        );
    }

    private function parentSubmission(?int $profileId): SubmissionEntity
    {
        $submission = (new SubmissionEntity())
            ->setSourceBlogId(1)
            ->setTargetBlogId(2)
            ->setConfigurationProfileId($profileId);
        $submission->setJobInfo(new JobEntity('Test job', 'jobUid', 'projectUid'));

        return $submission;
    }

    /**
     * Related content created while processing a submission must be stamped with the parent submission's own
     * profile, not whatever the source blog's default happens to be - otherwise related content silently ends up
     * under a different Smartling project than the one the user actually requested.
     */
    public function testProcessFieldPreTranslationPassesParentSubmissionProfileIdToRelatedContent(): void
    {
        $parent = $this->parentSubmission(7);

        $translationHelper = $this->createMock(TranslationHelper::class);
        $translationHelper->method('isRelatedSubmissionCreationNeeded')->willReturn(true);
        $translationHelper->expects($this->once())
            ->method('tryPrepareRelatedContent')
            ->with(
                'post',
                1,
                42,
                2,
                $this->isInstanceOf(JobEntityWithBatchUid::class),
                false,
                7,
            )
            ->willReturn($parent);

        $processor = $this->getProcessor($translationHelper);

        $processor->processFieldPreTranslation($parent, 'some_field', 42, []);
    }

    public function testProcessFieldPreTranslationPassesNullProfileIdWhenParentHasNone(): void
    {
        $parent = $this->parentSubmission(null);

        $translationHelper = $this->createMock(TranslationHelper::class);
        $translationHelper->method('isRelatedSubmissionCreationNeeded')->willReturn(true);
        $translationHelper->expects($this->once())
            ->method('tryPrepareRelatedContent')
            ->with(
                'post',
                1,
                42,
                2,
                $this->isInstanceOf(JobEntityWithBatchUid::class),
                false,
                null,
            )
            ->willReturn($parent);

        $processor = $this->getProcessor($translationHelper);

        $processor->processFieldPreTranslation($parent, 'some_field', 42, []);
    }
}
