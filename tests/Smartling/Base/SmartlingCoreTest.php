<?php

namespace Smartling\Tests\Smartling\Base;

use PHPUnit\Framework\TestCase;
use Smartling\ApiWrapper;
use Smartling\Exception\SmartlingDbException;
use Smartling\Exception\SmartlingTargetPlaceholderCreationFailedException;
use Smartling\Settings\ConfigurationProfileEntity;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Tests\Mocks\WordpressFunctionsMockHelper;
use Smartling\Tests\Traits\DbAlMock;
use Smartling\Tests\Traits\DummyLoggerMock;
use Smartling\Tests\Traits\EntityHelperMock;
use Smartling\Tests\Traits\InvokeMethodTrait;
use Smartling\Tests\Traits\SettingsManagerMock;
use Smartling\Tests\Traits\SiteHelperMock;
use Smartling\Tests\Traits\SubmissionManagerMock;
use Smartling\Base\SmartlingCore;
use Smartling\Helpers\WordpressFunctionProxyHelper;

/**
 * Class SmartlingCoreTest
 *
 * @package Smartling\Tests\Smartling\Base
 * @covers  \Smartling\Base\SmartlingCore
 */
class SmartlingCoreTest extends TestCase
{

    use InvokeMethodTrait;
    use DummyLoggerMock;
    use SettingsManagerMock;
    use SubmissionManagerMock;
    use SiteHelperMock;
    use EntityHelperMock;
    use DbAlMock;

    public function __construct($name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);
        $this->setUp();
    }

    protected function setUp()
    {
        WordpressFunctionsMockHelper::injectFunctionsMocks();
    }

    /**
     * @covers       \Smartling\Base\SmartlingCore::readLockedTranslationFieldsBySubmission
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
        $entityMock = $this
            ->getMockBuilder('Smartling\DbAl\WordpressContentEntities\PostEntityStd')
            ->setMethods(['toArray'])
            ->getMock();

        $entityMock
            ->expects(self::any())
            ->method('toArray')
            ->willReturn($entity);

        $contentHelperMock = $this
            ->getMockBuilder('Smartling\Helpers\ContentHelper')
            ->setMethods(['readTargetContent', 'readTargetMetadata'])
            ->getMock();

        $contentHelperMock->expects(self::any())->method('readTargetMetadata')->willReturn($meta);
        $contentHelperMock->expects(self::any())->method('readTargetContent')->willReturn($entityMock);

        $obj = new \Smartling\Base\SmartlingCore();
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


    public function readLockedTranslationFieldsBySubmissionDataProvider()
    {

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

    /**
     * @covers \Smartling\Base\SmartlingCore::fixSubmissionBatchUid
     * @expectedException Smartling\Exception\SmartlingDbException
     */
    public function testFixSubmissionBatchUid()
    {
        $obj = new \Smartling\Base\SmartlingCore();
        $submission = new SubmissionEntity();
        $submission
            ->setStatus(SubmissionEntity::SUBMISSION_STATUS_NEW)
            ->setContentType('post')
            ->setSourceBlogId(1)
            ->setSourceId(1)
            ->setTargetBlogId(1);


        $settingsManager = $this->getSettingsManagerMock();

        $submissionManager = $this->mockSubmissionManager(
            $this->mockDbAl(),
            $this->mockEntityHelper($this->mockSiteHelper())
        );

        $submissionManager->expects(self::once())->method('storeEntity')->with($submission)->willReturn($submission);

        $obj->setSubmissionManager($submissionManager);

        $settingsManager
            ->expects(self::any())
            ->method('findEntityByMainLocale')
            ->with($submission->getSourceBlogId())
            ->willReturn([]);

        $settingsManager
            ->expects(self::any())
            ->method('getSingleSettingsProfile')
            ->with($submission->getSourceBlogId())
            ->willReturnCallback(function () {
                throw new SmartlingDbException('');
            });

        $obj->setSettingsManager($settingsManager);
        $this->invokeMethod($obj, 'fixSubmissionBatchUid', [$submission]);
    }

    /**
     * @covers \Smartling\Base\SmartlingCore::fixSubmissionBatchUid
     * @expectedException Smartling\Exceptions\SmartlingApiException
     */
    public function testFixSubmissionBatchUidWithApiWrapper()
    {
        $obj = new \Smartling\Base\SmartlingCore();
        $submission = new SubmissionEntity();
        $submission
            ->setStatus(SubmissionEntity::SUBMISSION_STATUS_NEW)
            ->setContentType('post')
            ->setSourceBlogId(1)
            ->setSourceId(1)
            ->setTargetBlogId(1);


        $profile = new ConfigurationProfileEntity();
        $profile->setIsActive(true);
        $profile->setProjectId('a');
        $profile->setUserIdentifier('b');
        $profile->setSecretKey('c');
        $profile->setOriginalBlogId($submission->getSourceBlogId());

        $settingsManager = $this->getSettingsManagerMock();

        $submissionManager = $this->mockSubmissionManager(
            $this->mockDbAl(),
            $this->mockEntityHelper($this->mockSiteHelper())
        );

        $submissionManager->expects(self::once())->method('storeEntity')->with($submission)->willReturn($submission);

        $obj->setSubmissionManager($submissionManager);

        $settingsManager
            ->expects(self::any())
            ->method('findEntityByMainLocale')
            ->with($submission->getSourceBlogId())
            ->willReturn([$profile]);

        $settingsManager
            ->expects(self::any())
            ->method('getSingleSettingsProfile')
            ->with($submission->getSourceBlogId())
            ->willReturn($profile);

        $obj->setSettingsManager($settingsManager);

        $apiWrapper = new ApiWrapper($settingsManager,'a','b');
        $obj->setApiWrapper($apiWrapper);

        $this->invokeMethod($obj, 'fixSubmissionBatchUid', [$submission]);
    }


    /**
     * @covers \Smartling\Base\SmartlingCore::fixSubmissionBatchUid
     */
    public function testFixSubmissionBatchUidWithApiWrapperAndBatchUid()
    {
        $obj = new \Smartling\Base\SmartlingCore();
        $submission = new SubmissionEntity();
        $submission
            ->setStatus(SubmissionEntity::SUBMISSION_STATUS_NEW)
            ->setContentType('post')
            ->setSourceBlogId(1)
            ->setSourceId(1)
            ->setTargetBlogId(1);


        $profile = new ConfigurationProfileEntity();
        $profile->setIsActive(true);
        $profile->setProjectId('a');
        $profile->setUserIdentifier('b');
        $profile->setSecretKey('c');
        $profile->setOriginalBlogId($submission->getSourceBlogId());

        $settingsManager = $this->getSettingsManagerMock();

        $submissionManager = $this->mockSubmissionManager(
            $this->mockDbAl(),
            $this->mockEntityHelper($this->mockSiteHelper())
        );

        $submissionManager->expects(self::once())->method('storeEntity')->with($submission)->willReturn($submission);

        $obj->setSubmissionManager($submissionManager);

        $settingsManager
            ->expects(self::any())
            ->method('findEntityByMainLocale')
            ->with($submission->getSourceBlogId())
            ->willReturn([$profile]);

        $settingsManager
            ->expects(self::any())
            ->method('getSingleSettingsProfile')
            ->with($submission->getSourceBlogId())
            ->willReturn($profile);

        $obj->setSettingsManager($settingsManager);

        $apiWrapperMock = $this->getMockBuilder('Smartling\ApiWrapper')
             ->setMethods(['retrieveBatchForBucketJob'])
             ->disableOriginalConstructor()
             ->getMock();

        $apiWrapperMock
            ->expects(self::once())
            ->method('retrieveBatchForBucketJob')
            ->with($profile, false)
            ->willReturn('testtest');


        $obj->setApiWrapper($apiWrapperMock);

        $result = $this->invokeMethod($obj, 'fixSubmissionBatchUid', [$submission]);

        self::assertEquals('testtest', $result->getBatchUid());
    }

    /**
     * @covers \Smartling\Base\SmartlingCore::getXMLFiltered
     * @expectedException \Smartling\Exception\SmartlingTargetPlaceholderCreationFailedException
     * @expectedExceptionMessage Failed creating target placeholder for submission id='5', source_blog_id='1', source_id='1', target_blog_id='0' with message: ''
     */
    public function testExceptionOnTargetPlaceholderCreationFail()
    {
        $obj = $this->getMockBuilder(SmartlingCore::class)
                   ->setMethods(
                       [
                           'getFunctionProxyHelper',
                       ]
                   )
                   ->getMock();

        $submissionManager = $this->mockSubmissionManager(
            $this->mockDbAl(),
            $this->mockEntityHelper($this->mockSiteHelper())
        );

        $submissionManager
            ->expects(self::once())
            ->method('setErrorMessage')
            ->willReturnCallback(function(SubmissionEntity $s, $msg) {
                $s->setLastError($msg);
                return $s;
            });
        
        $obj->setSubmissionManager ($submissionManager);

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

        $proxyMock = $this->getMockBuilder(WordpressFunctionProxyHelper::class)
             ->setMethods(
                 [
                     'apply_filters',
                 ]
             )
             ->disableOriginalConstructor()
             ->getMock();


        $proxyMock->expects(self::once())->method('apply_filters')->willReturn($returnedSubmission);
        $obj->expects(self::once())->method('getFunctionProxyHelper')->willReturn($proxyMock);

        $this->invokeMethod($obj, 'getXMLFiltered', [$submission]);
    }
}
