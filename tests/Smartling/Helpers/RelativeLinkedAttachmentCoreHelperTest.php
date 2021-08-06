<?php

namespace Smartling\Tests\Smartling\Helpers;

use PHPUnit\Framework\TestCase;
use Smartling\Base\SmartlingCore;
use Smartling\DbAl\WordpressContentEntities\PostEntityStd;
use Smartling\Extensions\Acf\AcfDynamicSupport;
use Smartling\Helpers\EntityHelper;
use Smartling\Helpers\EventParameters\AfterDeserializeContentEventParameters;
use Smartling\Helpers\RelativeLinkedAttachmentCoreHelper;
use Smartling\Helpers\TranslationHelper;
use Smartling\Helpers\WordpressFunctionProxyHelper;
use Smartling\Jobs\JobEntity;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;
use Smartling\Tests\Mocks\WordpressFunctionsMockHelper;
use Smartling\Tuner\MediaAttachmentRulesManager;

class RelativeLinkedAttachmentCoreHelperTest extends TestCase
{
    private $mediaAttachmentRulesManager;
    private $sourceBlogId = 3;
    private $targetBlogId = 5;

    protected function setUp(): void
    {
        WordpressFunctionsMockHelper::injectFunctionsMocks();
        $this->mediaAttachmentRulesManager = $this->createMock(MediaAttachmentRulesManager::class);
    }

    /**
     * @dataProvider processGutenbergBlockAcfProvider
     */
    public function testProcessGutenbergBlockAcf(string $string, array $definitions, int $sourceId, int $targetId)
    {
        $submission = $this->getSubmission();
        $submission->setSourceId($sourceId);
        $submission->setTargetId($targetId);
        $jobInfo = new JobEntity('', '', '', 1, new \DateTime('2000-12-24 01:23:46'), new \DateTime('2001-12-24 02:34:45'));
        $submission->setBatchUid('');
        $submission->setJobInfo($jobInfo);
        $acf = $this->getMockBuilder(AcfDynamicSupport::class)
            ->setConstructorArgs([$this->createMock(EntityHelper::class)])
            ->getMock();
        $acf->method('getDefinitions')->willReturn($definitions);

        $translationHelper = $this->createMock(TranslationHelper::class);
        $translationHelper->method('isRelatedSubmissionCreationNeeded')->willReturn(true);

        $core = $this->createMock(SmartlingCore::class);
        $core->method('getTranslationHelper')->willReturn($translationHelper);
        $core->expects(self::once())->method('sendAttachmentForTranslation')
            ->with($this->sourceBlogId, $this->targetBlogId, $sourceId)
            ->willReturn($submission);
        $x = $this->getMockBuilder(RelativeLinkedAttachmentCoreHelper::class)->setConstructorArgs([
            $core,
            $acf,
            $this->mediaAttachmentRulesManager,
            $this->createMock(SubmissionManager::class),
            $this->createMock(WordpressFunctionProxyHelper::class),
        ])->onlyMethods([])->getMock();
        $source = [$string];
        $meta = [];

        $content = $this->createMock(PostEntityStd::class);

        $x->processor(new AfterDeserializeContentEventParameters($source, $submission, $content, $meta));

        self::assertEquals(
            str_replace($sourceId, $targetId, $string),
            $source[0]
        );
    }

    /**
     * Attachment id can be an empty string.
     *
     * Connector must not throw `Source id can not be 0` exception in this case.
     */
    public function testProcessGutenbergBlockAcfWithAttachmentWithEmptyId()
    {
        $string = '<!-- wp:acf/testimonial {\"id\":\"block_5f1eb3f391cda\",\"name\":\"acf/testimonial\",\"data\":{\"media\":\"\",\"_media\":\"field_5eb1344b55a84\",\"description\":\"text\",\"_description\":\"field_5ef64590591dc\"},\"align\":\"\",\"mode\":\"edit\"} /-->';
        $sourceId = 0;
        $targetId = 262;

        $submission = $this->getSubmission();
        $submission->setSourceId($sourceId);
        $submission->setTargetId($targetId);
        $submission->setBatchUid('');

        $acf = $this->getMockBuilder(AcfDynamicSupport::class)
            ->setConstructorArgs([$this->createMock(EntityHelper::class)])
            ->getMock();
        $acf->method('getDefinitions')->willReturn(
            [
                'field_5eb1344b55a84' => ['type' => 'image'],
                'field_5ef64590591dc' => ['type' => 'text']
            ]
        );

        $translationHelper = $this->createMock(TranslationHelper::class);
        $translationHelper->method('isRelatedSubmissionCreationNeeded')->willReturn(true);

        $core = $this->createMock(SmartlingCore::class);
        $core->method('getTranslationHelper')->willReturn($translationHelper);
        $core->expects(self::never())->method('sendAttachmentForTranslation');

        $x = $this->getMockBuilder(RelativeLinkedAttachmentCoreHelper::class)->setConstructorArgs([
            $core,
            $acf,
            $this->mediaAttachmentRulesManager,
            $this->createMock(SubmissionManager::class),
            $this->createMock(WordpressFunctionProxyHelper::class),
        ])->onlyMethods([])->getMock();

        $source = [$string];
        $meta = [];
        $content = $this->createMock(PostEntityStd::class);

        $x->processor(new AfterDeserializeContentEventParameters($source, $submission, $content, $meta));
    }

    /**
     * @return array
     */
    public function processGutenbergBlockAcfProvider()
    {
        return [
            [
                '<!-- wp:acf/testimonial {\"id\":\"block_5f1eb3f391cda\",\"name\":\"acf/testimonial\",\"data\":' .
                '{\"media\":\"297\",\"_media\":\"field_5eb1344b55a84\",\"description\":\"text\",\"_description\":' .
                '\"field_5ef64590591dc\"},\"align\":\"\",\"mode\":\"edit\"} /-->',
                ['field_5eb1344b55a84' => ['type' => 'image'], 'field_5ef64590591dc' => ['type' => 'text']],
                297,
                262,
            ],
        ];
    }

    public function testReplaceRelativeUrl() {
        $sourcePostRelations = [
            "/some/permalink" => 7,
            "/other/untranslated" => 11,
            "/other/translated" => 13,
        ];
        $targetPostRelations = [
            "/some/translated/permalink" => 17,
            "/other/translated/permalink" => 19,
        ];
        $submissionManager = $this->createMock(SubmissionManager::class);
        $submissionManager->method('find')->willReturnCallback(function ($parameters) use ($sourcePostRelations, $targetPostRelations) {
            $relations = [
                $sourcePostRelations["/some/permalink"] => $targetPostRelations["/some/translated/permalink"],
                $sourcePostRelations["/other/translated"] => $targetPostRelations["/other/translated/permalink"],
            ];
            if (array_key_exists($parameters[SubmissionEntity::FIELD_SOURCE_ID], $relations)) {
                $submission = $this->getSubmission();
                $submission->setTargetId($relations[$parameters[SubmissionEntity::FIELD_SOURCE_ID]]);
                return [$submission];
            }
            return [];
        });
        $wordpressProxy = $this->createMock(WordpressFunctionProxyHelper::class);
        $wordpressProxy->method('get_blog_permalink')->willReturnCallback(function ($blogId, $postId) use ($targetPostRelations) {
            return 'http://te.example.com' . array_flip($targetPostRelations)[$postId] ?? '/not/translated';
        });
        $wordpressProxy->method('url_to_postid')->willReturnCallback(function ($url) use ($sourcePostRelations) {
            return $sourcePostRelations[$url] ?? 0;
        });
        $x = new RelativeLinkedAttachmentCoreHelper(
            $this->createMock(SmartlingCore::class),
            $this->createMock(AcfDynamicSupport::class),
            $submissionManager,
            $wordpressProxy
        );

        $submission = $this->getSubmission();

        $source = [<<<HTML
<!-- wp:paragraph -->
<p>I'm a paragraph with a relative <a href="/some/permalink" data-type="post" data-id="473">link</a>,
<a href="/other/untranslated">another untranslated post</a> post, and a <a href="/other/translated">translated</a> one.
There is also an <a href="https://absolute.com/post/content">absolute</a> link.</p>
<!-- /wp:paragraph -->
HTML
        ];
        $parameters = new AfterDeserializeContentEventParameters($source, $submission, $this->createMock(PostEntityStd::class), []);
        $x->processor($parameters);
        $this->assertEquals(<<<HTML
<!-- wp:paragraph -->
<p>I'm a paragraph with a relative <a href="/some/translated/permalink" data-type="post" data-id="473">link</a>,
<a href="/other/untranslated">another untranslated post</a> post, and a <a href="/other/translated/permalink">translated</a> one.
There is also an <a href="https://absolute.com/post/content">absolute</a> link.</p>
<!-- /wp:paragraph -->
HTML
            , $parameters->getTranslatedFields()[0]);
    }

    private function getSubmission(): SubmissionEntity
    {
        $submission = new SubmissionEntity();
        $submission->setSourceBlogId($this->sourceBlogId);
        $submission->setTargetBlogId($this->targetBlogId);
        $submission->setContentType('content_type');
        return $submission;
    }
}
