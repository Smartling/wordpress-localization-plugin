<?php

namespace Smartling\Tests\Smartling\Base;

use PHPUnit\Framework\TestCase;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Tests\Mocks\WordpressFunctionsMockHelper;
use Smartling\Tests\Traits\DummyLoggerMock;
use Smartling\Tests\Traits\InvokeMethodTrait;

/**
 * Class SmartlingCoreTest
 * @package Smartling\Tests\Smartling\Base
 * @covers  \Smartling\Base\SmartlingCore
 */
class SmartlingCoreTest extends TestCase
{

    use InvokeMethodTrait;
    use DummyLoggerMock;


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
    public function testReadLockedTranslationFieldsBySubmission(array $expected, SubmissionEntity $submission, array $entity = [], array $meta = [])
    {
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
            [
                [
                    'entity' => [],
                    'meta'   => [],
                ],
                SubmissionEntity::fromArray(
                    [
                        'id'                     => 1,
                        'source_title'           => '',
                        'source_blog_id'         => 1,
                        'source_content_hash'    => 'abc',
                        'content_type'           => 'post',
                        'source_id'              => 1,
                        'file_uri'               => 'any',
                        'target_locale'          => 'any',
                        'target_blog_id'         => 0,
                        'target_id'              => 0,
                        'submitter'              => 'any',
                        'submission_date'        => 'any',
                        'applied_date'           => 'any',
                        'approved_string_count'  => 0,
                        'completed_string_count' => 0,
                        'status'                 => SubmissionEntity::SUBMISSION_STATUS_IN_PROGRESS,
                        'is_locked'              => 0,
                        'outdated'               => SubmissionEntity::FLAG_CONTENT_IS_UP_TO_DATE,
                    ], $this->getLogger()
                ),
                [],
                [],
            ],
            [
                [
                    'entity' => [
                        'a' => 'b',
                    ],
                    'meta'   => [],
                ],
                SubmissionEntity::fromArray(
                    [
                        'id'                     => 1,
                        'source_title'           => '',
                        'source_blog_id'         => 1,
                        'source_content_hash'    => 'abc',
                        'content_type'           => 'post',
                        'source_id'              => 1,
                        'file_uri'               => 'any',
                        'target_locale'          => 'any',
                        'target_blog_id'         => 0,
                        'target_id'              => 1,
                        'submitter'              => 'any',
                        'submission_date'        => 'any',
                        'applied_date'           => 'any',
                        'approved_string_count'  => 0,
                        'completed_string_count' => 0,
                        'status'                 => SubmissionEntity::SUBMISSION_STATUS_IN_PROGRESS,
                        'is_locked'              => 0,
                        'outdated'               => SubmissionEntity::FLAG_CONTENT_IS_UP_TO_DATE,
                        'locked_fields'          => serialize(['entity/a']),
                    ], $this->getLogger()
                ),
                ['a' => 'b'],
                [],
            ],
            [
                [
                    'entity' => [],
                    'meta'   => [
                        'c' => 'd',
                    ],
                ],
                SubmissionEntity::fromArray(
                    [
                        'id'                     => 1,
                        'source_title'           => '',
                        'source_blog_id'         => 1,
                        'source_content_hash'    => 'abc',
                        'content_type'           => 'post',
                        'source_id'              => 1,
                        'file_uri'               => 'any',
                        'target_locale'          => 'any',
                        'target_blog_id'         => 0,
                        'target_id'              => 1,
                        'submitter'              => 'any',
                        'submission_date'        => 'any',
                        'applied_date'           => 'any',
                        'approved_string_count'  => 0,
                        'completed_string_count' => 0,
                        'status'                 => SubmissionEntity::SUBMISSION_STATUS_IN_PROGRESS,
                        'is_locked'              => 0,
                        'outdated'               => SubmissionEntity::FLAG_CONTENT_IS_UP_TO_DATE,
                        'locked_fields'          => serialize(['meta/c']),
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
            [
                [
                    'entity' => [
                        'a' => 'b',
                    ],
                    'meta'   => [
                        'c' => 'd',
                    ],
                ],
                SubmissionEntity::fromArray(
                    [
                        'id'                     => 1,
                        'source_title'           => '',
                        'source_blog_id'         => 1,
                        'source_content_hash'    => 'abc',
                        'content_type'           => 'post',
                        'source_id'              => 1,
                        'file_uri'               => 'any',
                        'target_locale'          => 'any',
                        'target_blog_id'         => 0,
                        'target_id'              => 1,
                        'submitter'              => 'any',
                        'submission_date'        => 'any',
                        'applied_date'           => 'any',
                        'approved_string_count'  => 0,
                        'completed_string_count' => 0,
                        'status'                 => SubmissionEntity::SUBMISSION_STATUS_IN_PROGRESS,
                        'is_locked'              => 0,
                        'outdated'               => SubmissionEntity::FLAG_CONTENT_IS_UP_TO_DATE,
                        'locked_fields'          => serialize(['entity/a', 'meta/c']),
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
            [
                [
                    'entity' => [
                        'a' => 'b',
                    ],
                    'meta'   => [
                        'c' => 'd',
                    ],
                ],
                SubmissionEntity::fromArray(
                    [
                        'id'                     => 1,
                        'source_title'           => '',
                        'source_blog_id'         => 1,
                        'source_content_hash'    => 'abc',
                        'content_type'           => 'post',
                        'source_id'              => 1,
                        'file_uri'               => 'any',
                        'target_locale'          => 'any',
                        'target_blog_id'         => 0,
                        'target_id'              => 1,
                        'submitter'              => 'any',
                        'submission_date'        => 'any',
                        'applied_date'           => 'any',
                        'approved_string_count'  => 0,
                        'completed_string_count' => 0,
                        'status'                 => SubmissionEntity::SUBMISSION_STATUS_IN_PROGRESS,
                        'is_locked'              => 0,
                        'outdated'               => SubmissionEntity::FLAG_CONTENT_IS_UP_TO_DATE,
                        'locked_fields'          => serialize(['entity/a', 'meta/c', 'strange/?']),
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
}
