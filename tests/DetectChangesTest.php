<?php

namespace Smartling\Tests;

use Smartling\Helpers\DetectChangesHelper;
use Smartling\Helpers\WordpressContentTypeHelper;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Tests\Mocks\WordpressFunctionsMockHelper;
use Smartling\Tests\Traits\DummyLoggerMock;
use Smartling\Tests\Traits\InvokeMethodTrait;
use Smartling\Tests\Traits\SubmissionEntityMock;

/**
 * Class DetectChangesTest
 * @package Smartling\Tests
 * @covers  Smartling\Helpers\DetectChangesHelper
 */
class DetectChangesTest extends \PHPUnit_Framework_TestCase
{
    use DummyLoggerMock;
    use SubmissionEntityMock;
    use InvokeMethodTrait;

    /**
     * @var DetectChangesHelper|\PHPUnit_Framework_MockObject_MockObject
     */
    private $detectChangesHelperMock;

    /**
     * @return DetectChangesHelper|\PHPUnit_Framework_MockObject_MockObject
     */
    public function getDetectChangesHelperMock()
    {
        return $this->detectChangesHelperMock;
    }

    /**
     * @param DetectChangesHelper|\PHPUnit_Framework_MockObject_MockObject $detectChangesHelperMock
     */
    public function setDetectChangesHelperMock($detectChangesHelperMock)
    {
        $this->detectChangesHelperMock = $detectChangesHelperMock;
    }


    /**
     * {@inheritDoc}
     */
    protected function setUp()
    {
        $mock = $this->getMockBuilder('Smartling\Helpers\DetectChangesHelper')
            ->setMethods(null)
            ->disableOriginalConstructor()
            ->getMock();

        $mock->setLogger($this->getLogger());

        $this->setDetectChangesHelperMock($mock);
    }

    /**
     * @covers       Smartling\Helpers\DetectChangesHelper::checkSubmissionHash()
     * @dataProvider checkSubmissionHashDataProvider
     *
     * @param array  $submissionFields
     * @param bool   $needStatusChange
     * @param string $newHash
     */
    public function testCheckSubmissionHash(array $submissionFields, $needStatusChange, $newHash)
    {
        WordpressContentTypeHelper::$internalTypes = ['post' => 'Post'];
        WordpressFunctionsMockHelper::injectFunctionsMocks();
        $initialSubmission = SubmissionEntity::fromArray($submissionFields, $this->getLogger());
        $submission = SubmissionEntity::fromArray($submissionFields, $this->getLogger());

        $processedSubmission = $this->invokeMethod(
            $this->getDetectChangesHelperMock(),
            'checkSubmissionHash',
            [
                $submission,
                $needStatusChange,
                $newHash,
            ]
        );
        /**
         * @var SubmissionEntity $processedSubmission
         */

        if ($initialSubmission->getSourceContentHash() === $newHash) {
            self::assertEquals(SubmissionEntity::FLAG_CONTENT_IS_UP_TO_DATE, $processedSubmission->getOutdated());
        } else {
            self::assertEquals(SubmissionEntity::FLAG_CONTENT_IS_OUT_OF_DATE, $processedSubmission->getOutdated());
        }

        if (true === $needStatusChange) {
            self::assertEquals(SubmissionEntity::SUBMISSION_STATUS_NEW, $processedSubmission->getStatus());
        } else {
            self::assertEquals($initialSubmission->getStatus(), $processedSubmission->getStatus());
        }
    }

    public function checkSubmissionHashDataProvider()
    {
        return [
            [

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
                ],
                false,
                'abc',
            ],
            [

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
                    'outdated'               => SubmissionEntity::FLAG_CONTENT_IS_OUT_OF_DATE,
                ],
                false,
                'abc',
            ],
            [

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
                    'outdated'               => SubmissionEntity::FLAG_CONTENT_IS_OUT_OF_DATE,
                ],
                false,
                'def',
            ],
            [

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
                    'status'                 => SubmissionEntity::SUBMISSION_STATUS_NEW,
                    'is_locked'              => 0,
                    'outdated'               => SubmissionEntity::FLAG_CONTENT_IS_OUT_OF_DATE,
                ],
                true,
                'def',
            ],
        ];
    }
}