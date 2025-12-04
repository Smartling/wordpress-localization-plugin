<?php

namespace Smartling\Tests\Smartling\Base;

use PHPUnit\Framework\TestCase;
use Smartling\ContentTypes\ExternalContentManager;
use Smartling\DbAl\UploadQueueManager;
use Smartling\Exception\SmartlingDirectRunRuntimeException;
use Smartling\Exception\SmartlingTargetPlaceholderCreationFailedException;
use Smartling\Extensions\Acf\AcfDynamicSupport;
use Smartling\Helpers\ArrayHelper;
use Smartling\Helpers\ContentSerializationHelper;
use Smartling\Helpers\FieldsFilterHelper;
use Smartling\Helpers\FileUriHelper;
use Smartling\Helpers\GutenbergBlockHelper;
use Smartling\Helpers\PostContentHelper;
use Smartling\Helpers\Serializers\SerializerJsonWithFallback;
use Smartling\Helpers\SiteHelper;
use Smartling\Helpers\TestRunHelper;
use Smartling\Helpers\XmlHelper;
use Smartling\Replacers\ReplacerFactory;
use Smartling\Settings\SettingsManager;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Tests\Mocks\WordpressFunctionsMockHelper;
use Smartling\Tests\Traits\DbAlMock;
use Smartling\Tests\Traits\DummyLoggerMock;
use Smartling\Tests\Traits\InvokeMethodTrait;
use Smartling\Tests\Traits\SettingsManagerMock;
use Smartling\Tests\Traits\SiteHelperMock;
use Smartling\Tests\Traits\SubmissionManagerMock;
use Smartling\Base\SmartlingCore;
use Smartling\Helpers\WordpressFunctionProxyHelper;
use Smartling\Helpers\ContentHelper;
use Smartling\DbAl\WordpressContentEntities\PostEntityStd;
use Smartling\Tuner\MediaAttachmentRulesManager;

class SmartlingCoreTest extends TestCase
{
    use InvokeMethodTrait;
    use DummyLoggerMock;
    use SettingsManagerMock;
    use SubmissionManagerMock;
    use SiteHelperMock;
    use DbAlMock;

    private SmartlingCore $core;
    protected function setUp(): void
    {
        WordpressFunctionsMockHelper::injectFunctionsMocks();
        $wpProxy = new WordpressFunctionProxyHelper();
        $acf = $this->createMock(AcfDynamicSupport::class);
        $gutenbergBlockHelper = new GutenbergBlockHelper(
            $acf,
            $this->createMock(ContentSerializationHelper::class),
            $this->createMock(MediaAttachmentRulesManager::class),
            $this->createMock(ReplacerFactory::class),
            new SerializerJsonWithFallback(),
            $this->createMock(SettingsManager::class),
            $wpProxy,
        );


        $this->core = new SmartlingCore(
            $acf,
            new ExternalContentManager(new FieldsFilterHelper(
                $acf,
                $this->createMock(ContentSerializationHelper::class),
                $this->createMock(SettingsManager::class),
                $wpProxy,
            ),
                $this->createMock(SiteHelper::class),
            ),
            $this->createMock(FileUriHelper::class),
            $gutenbergBlockHelper,
            new PostContentHelper(new ArrayHelper(), $gutenbergBlockHelper),
            $this->createMock(UploadQueueManager::class),
            new XmlHelper($this->createMock(ContentSerializationHelper::class), new SerializerJsonWithFallback(), $this->createMock(SettingsManager::class)),
            $this->createMock(TestRunHelper::class),
            $wpProxy,
        );
    }

    /**
     * @dataProvider readLockedTranslationFieldsBySubmissionDataProvider
     *
     * @param array            $expected
     * @param SubmissionEntity $submission
     * @param array            $entity
     * @param array            $meta
     */
    public function testReadLockedTranslationFieldsBySubmission(
        array $expected,
        SubmissionEntity $submission,
        array $entity = [],
        array $meta = []
    ) {
        $entityMock = $this->createPartialMock(PostEntityStd::class, ['toArray']);
        $entityMock
            ->method('toArray')
            ->willReturn($entity);

        $contentHelperMock = $this->createPartialMock(ContentHelper::class, ['readTargetContent', 'readTargetMetadata']);
        $contentHelperMock->method('readTargetMetadata')->willReturn($meta);
        $contentHelperMock->method('readTargetContent')->willReturn($entityMock);

        $obj = $this->core;
        $obj->setContentHelper($contentHelperMock);

        self::assertEquals(
            $expected,
            $this->invokeMethod(
                $obj,
                'readLockedTranslationFieldsBySubmission',
                [
                    $submission,
                ]
            )
        );
    }

    public function readLockedTranslationFieldsBySubmissionDataProvider(): array
    {
        try {
            SubmissionEntity::fromArray([], $this->getLogger());
        } catch (SmartlingDirectRunRuntimeException) {
            $this->markTestSkipped('Requires active wordpress');
        }
        return [
            'test with new content' => [
                [
                    'entity' => [],
                    'meta' => [],
                ],
                SubmissionEntity::fromArray(
                    [
                        'id' => 1,
                        'source_title' => '',
                        'source_blog_id' => 1,
                        'source_content_hash' => 'abc',
                        'content_type' => 'post',
                        'source_id' => 1,
                        'file_uri' => 'any',
                        'target_locale' => 'any',
                        'target_blog_id' => 0,
                        'target_id' => 0,
                        'submitter' => 'any',
                        'submission_date' => 'any',
                        'applied_date' => 'any',
                        'approved_string_count' => 0,
                        'completed_string_count' => 0,
                        'status' => SubmissionEntity::SUBMISSION_STATUS_IN_PROGRESS,
                        'is_locked' => 0,
                        'outdated' => SubmissionEntity::FLAG_CONTENT_IS_UP_TO_DATE,
                    ], $this->getLogger()
                ),
                [],
                [],
            ],
            'test only entity' => [
                [
                    'entity' => [
                        'a' => 'b',
                    ],
                    'meta' => [],
                ],
                SubmissionEntity::fromArray(
                    [
                        'id' => 1,
                        'source_title' => '',
                        'source_blog_id' => 1,
                        'source_content_hash' => 'abc',
                        'content_type' => 'post',
                        'source_id' => 1,
                        'file_uri' => 'any',
                        'target_locale' => 'any',
                        'target_blog_id' => 0,
                        'target_id' => 1,
                        'submitter' => 'any',
                        'submission_date' => 'any',
                        'applied_date' => 'any',
                        'approved_string_count' => 0,
                        'completed_string_count' => 0,
                        'status' => SubmissionEntity::SUBMISSION_STATUS_IN_PROGRESS,
                        'is_locked' => 0,
                        'outdated' => SubmissionEntity::FLAG_CONTENT_IS_UP_TO_DATE,
                        'locked_fields' => serialize(['entity/a']),
                    ], $this->getLogger()
                ),
                ['a' => 'b'],
                [],
            ],
            'test only meta' => [
                [
                    'entity' => [],
                    'meta' => [
                        'c' => 'd',
                    ],
                ],
                SubmissionEntity::fromArray(
                    [
                        'id' => 1,
                        'source_title' => '',
                        'source_blog_id' => 1,
                        'source_content_hash' => 'abc',
                        'content_type' => 'post',
                        'source_id' => 1,
                        'file_uri' => 'any',
                        'target_locale' => 'any',
                        'target_blog_id' => 0,
                        'target_id' => 1,
                        'submitter' => 'any',
                        'submission_date' => 'any',
                        'applied_date' => 'any',
                        'approved_string_count' => 0,
                        'completed_string_count' => 0,
                        'status' => SubmissionEntity::SUBMISSION_STATUS_IN_PROGRESS,
                        'is_locked' => 0,
                        'outdated' => SubmissionEntity::FLAG_CONTENT_IS_UP_TO_DATE,
                        'locked_fields' => serialize(['meta/c']),
                    ], $this->getLogger()
                ),
                [
                    'a' => 'b',
                ],
                [
                    'c' => 'd',
                    'e' => 'f',
                ],
            ],
            'test entity and meta' => [
                [
                    'entity' => [
                        'a' => 'b',
                    ],
                    'meta' => [
                        'c' => 'd',
                    ],
                ],
                SubmissionEntity::fromArray(
                    [
                        'id' => 1,
                        'source_title' => '',
                        'source_blog_id' => 1,
                        'source_content_hash' => 'abc',
                        'content_type' => 'post',
                        'source_id' => 1,
                        'file_uri' => 'any',
                        'target_locale' => 'any',
                        'target_blog_id' => 0,
                        'target_id' => 1,
                        'submitter' => 'any',
                        'submission_date' => 'any',
                        'applied_date' => 'any',
                        'approved_string_count' => 0,
                        'completed_string_count' => 0,
                        'status' => SubmissionEntity::SUBMISSION_STATUS_IN_PROGRESS,
                        'is_locked' => 0,
                        'outdated' => SubmissionEntity::FLAG_CONTENT_IS_UP_TO_DATE,
                        'locked_fields' => serialize(['entity/a', 'meta/c']),
                    ], $this->getLogger()
                ),
                [
                    'a' => 'b',
                    'q' => 'p',
                ],
                [
                    'c' => 'd',
                    'e' => 'f',
                ],
            ],
            'test with bad fields' => [
                [
                    'entity' => [
                        'a' => 'b',
                    ],
                    'meta' => [
                        'c' => 'd',
                    ],
                ],
                SubmissionEntity::fromArray(
                    [
                        'id' => 1,
                        'source_title' => '',
                        'source_blog_id' => 1,
                        'source_content_hash' => 'abc',
                        'content_type' => 'post',
                        'source_id' => 1,
                        'file_uri' => 'any',
                        'target_locale' => 'any',
                        'target_blog_id' => 0,
                        'target_id' => 1,
                        'submitter' => 'any',
                        'submission_date' => 'any',
                        'applied_date' => 'any',
                        'approved_string_count' => 0,
                        'completed_string_count' => 0,
                        'status' => SubmissionEntity::SUBMISSION_STATUS_IN_PROGRESS,
                        'is_locked' => 0,
                        'outdated' => SubmissionEntity::FLAG_CONTENT_IS_UP_TO_DATE,
                        'locked_fields' => serialize(['entity/a', 'meta/c', 'strange/?']),
                    ], $this->getLogger()
                ),
                [
                    'a' => 'b',
                    'q' => 'p',
                ],
                [
                    'c' => 'd',
                    'e' => 'f',
                ],
            ],
            'test with broken data' => [
                [
                    'entity' => [],
                    'meta' => [],
                ],
                SubmissionEntity::fromArray(
                    [
                        'id' => 1,
                        'source_title' => '',
                        'source_blog_id' => 1,
                        'source_content_hash' => 'abc',
                        'content_type' => 'post',
                        'source_id' => 1,
                        'file_uri' => 'any',
                        'target_locale' => 'any',
                        'target_blog_id' => 0,
                        'target_id' => 1,
                        'submitter' => 'any',
                        'submission_date' => 'any',
                        'applied_date' => 'any',
                        'approved_string_count' => 0,
                        'completed_string_count' => 0,
                        'status' => SubmissionEntity::SUBMISSION_STATUS_IN_PROGRESS,
                        'is_locked' => 0,
                        'outdated' => SubmissionEntity::FLAG_CONTENT_IS_UP_TO_DATE,
                        'locked_fields' => '!' . serialize(['entity/a', 'meta/c', 'strange/?']),
                    ], $this->getLogger()
                ),
                [
                    'a' => 'b',
                    'q' => 'p',
                ],
                [
                    'c' => 'd',
                    'e' => 'f',
                ],
            ],
        ];
    }

    public function testExceptionOnTargetPlaceholderCreationFail()
    {
        $obj = $this->createPartialMock(SmartlingCore::class, ['getFunctionProxyHelper', 'getLogger']);

        $submissionManager = $this->mockSubmissionManager(
            $this->mockDbAl()
        );

        $submissionManager
            ->expects(self::once())
            ->method('setErrorMessage')
            ->willReturnCallback(function(SubmissionEntity $s, $msg) {
                $s->setLastError($msg);
                return $s;
            });

        $obj->setSubmissionManager($submissionManager);

        $submission = new SubmissionEntity();
        $submission
            ->setId(5)
            ->setStatus(SubmissionEntity::SUBMISSION_STATUS_NEW)
            ->setContentType('post')
            ->setSourceBlogId(1)
            ->setSourceId(1)
            ->setTargetBlogId(1);


        $returnedSubmission = clone $submission;
        $returnedSubmission->setStatus(SubmissionEntity::SUBMISSION_STATUS_FAILED);

        $proxyMock = $this->createPartialMock(WordpressFunctionProxyHelper::class, ['apply_filters']);
        $proxyMock->expects(self::once())->method('apply_filters')->willReturn($returnedSubmission);

        $obj->expects(self::once())->method('getFunctionProxyHelper')->willReturn($proxyMock);
        $this->expectException(SmartlingTargetPlaceholderCreationFailedException::class);
        $this->expectExceptionMessage("Failed creating target placeholder for submission id='5', source_blog_id='1', source_id='1', target_blog_id='1', target_id='0' with message:");

        $obj->getXMLFiltered($submission);
    }
}
