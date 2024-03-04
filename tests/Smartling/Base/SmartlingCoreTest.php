<?php

namespace Smartling\Tests\Smartling\Base;

use PHPUnit\Framework\TestCase;
use Smartling\ApiWrapper;
use Smartling\ContentTypes\ExternalContentManager;
use Smartling\Exception\SmartlingDbException;
use Smartling\Exception\SmartlingDirectRunRuntimeException;
use Smartling\Exception\SmartlingTargetPlaceholderCreationFailedException;
use Smartling\Extensions\Acf\AcfDynamicSupport;
use Smartling\Helpers\ContentSerializationHelper;
use Smartling\Helpers\FieldsFilterHelper;
use Smartling\Helpers\FileUriHelper;
use Smartling\Helpers\GutenbergBlockHelper;
use Smartling\Helpers\PostContentHelper;
use Smartling\Helpers\Serializers\SerializerJsonWithFallback;
use Smartling\Helpers\SiteHelper;
use Smartling\Helpers\TestRunHelper;
use Smartling\Helpers\XmlHelper;
use Smartling\Jobs\JobEntityWithBatchUid;
use Smartling\Replacers\ReplacerFactory;
use Smartling\Settings\ConfigurationProfileEntity;
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
use Smartling\Vendor\Smartling\Exceptions\SmartlingApiException;

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
        $gutenbergBlockHelper = new GutenbergBlockHelper(
            $this->createMock(AcfDynamicSupport::class),
            $this->createMock(ContentSerializationHelper::class),
            $this->createMock(MediaAttachmentRulesManager::class),
            $this->createMock(ReplacerFactory::class),
            new SerializerJsonWithFallback(),
            $this->createMock(SettingsManager::class),
            $wpProxy,
        );


        $this->core = new SmartlingCore(
            new ExternalContentManager(new FieldsFilterHelper(
                $this->createMock(AcfDynamicSupport::class),
                $this->createMock(ContentSerializationHelper::class),
                $this->createMock(SettingsManager::class),
                $wpProxy,
            ),
                $this->createMock(SiteHelper::class),
            ),
            $this->createMock(FileUriHelper::class),
            $gutenbergBlockHelper,
            new PostContentHelper($gutenbergBlockHelper),
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

    public function testFixSubmissionBatchUid()
    {
        $this->expectException(SmartlingDbException::class);
        $obj = $this->core;
        $submission = new SubmissionEntity();
        $submission
            ->setStatus(SubmissionEntity::SUBMISSION_STATUS_NEW)
            ->setContentType('post')
            ->setSourceBlogId(1)
            ->setSourceId(1)
            ->setTargetBlogId(1);


        $settingsManager = $this->getSettingsManagerMock();

        $submissionManager = $this->mockSubmissionManager(
            $this->mockDbAl()
        );

        $submissionManager->expects(self::once())->method('storeEntity')->with($submission)->willReturn($submission);

        $obj->setSubmissionManager($submissionManager);

        $settingsManager
            ->method('findEntityByMainLocale')
            ->with($submission->getSourceBlogId())
            ->willReturn([]);

        $settingsManager
            ->method('getSingleSettingsProfile')
            ->with($submission->getSourceBlogId())
            ->willThrowException(new SmartlingDbException(''));

        $obj->setSettingsManager($settingsManager);
        $this->invokeMethod($obj, 'fixSubmissionBatchUid', [$submission]);
    }

    public function testFixSubmissionBatchUidWithApiWrapper()
    {
        $this->expectException(SmartlingApiException::class);
        $obj = $this->core;
        $submission = new SubmissionEntity();
        $submission
            ->setStatus(SubmissionEntity::SUBMISSION_STATUS_NEW)
            ->setContentType('post')
            ->setSourceBlogId(1)
            ->setSourceId(1)
            ->setTargetBlogId(1);


        $profile = $this->createMock(ConfigurationProfileEntity::class);

        $settingsManager = $this->getSettingsManagerMock();

        $submissionManager = $this->mockSubmissionManager(
            $this->mockDbAl()
        );

        $submissionManager->expects(self::once())->method('storeEntity')->with($submission)->willReturn($submission);

        $obj->setSubmissionManager($submissionManager);

        $settingsManager
            ->method('findEntityByMainLocale')
            ->with($submission->getSourceBlogId())
            ->willReturn([$profile]);

        $settingsManager
            ->method('getSingleSettingsProfile')
            ->with($submission->getSourceBlogId())
            ->willReturn($profile);

        $obj->setSettingsManager($settingsManager);

        $apiWrapper = new ApiWrapper($settingsManager,'a','b');
        $obj->setApiWrapper($apiWrapper);

        $this->invokeMethod($obj, 'fixSubmissionBatchUid', [$submission]);
    }

    public function testFixSubmissionBatchUidWithApiWrapperAndBatchUid()
    {
        $batchUid = 'testtest';
        $obj = $this->core;
        $submission = new SubmissionEntity();
        $submission
            ->setStatus(SubmissionEntity::SUBMISSION_STATUS_NEW)
            ->setContentType('post')
            ->setSourceBlogId(1)
            ->setSourceId(1)
            ->setTargetBlogId(1);

        $profile = $this->createMock(ConfigurationProfileEntity::class);

        $settingsManager = $this->getSettingsManagerMock();

        $submissionManager = $this->mockSubmissionManager(
            $this->mockDbAl()
        );

        $submissionManager->expects(self::once())->method('storeEntity')->with($submission)->willReturn($submission);

        $obj->setSubmissionManager($submissionManager);

        $settingsManager
            ->method('findEntityByMainLocale')
            ->with($submission->getSourceBlogId())
            ->willReturn([$profile]);

        $settingsManager
            ->method('getSingleSettingsProfile')
            ->with($submission->getSourceBlogId())
            ->willReturn($profile);

        $obj->setSettingsManager($settingsManager);

        $apiWrapperMock = $this->createPartialMock(ApiWrapper::class, ['retrieveJobInfoForDailyBucketJob']);
        $apiWrapperMock
            ->expects(self::once())
            ->method('retrieveJobInfoForDailyBucketJob')
            ->with($profile, false)
            ->willReturn(new JobEntityWithBatchUid($batchUid,'jobName', '', ''));

        $obj->setApiWrapper($apiWrapperMock);

        $result = $this->invokeMethod($obj, 'fixSubmissionBatchUid', [$submission]);

        self::assertEquals($batchUid, $result->getBatchUid());
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
