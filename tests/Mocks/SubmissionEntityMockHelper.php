<?php

namespace Smartling\Tests\Mocks;

/**
 * Class SubmissionEntityMockHelper
 * @package Smartling\Tests\Mocks
 */
class SubmissionEntityMockHelper
{
    const CLASS_NAME = 'Smartling\Submissions\SubmissionEntity';

    /**
     * @param \PHPUnit_Framework_MockObject_MockBuilder $mockBuilder
     *
     * @return mixed
     */
    public static function getRawMock(\PHPUnit_Framework_MockObject_MockBuilder $mockBuilder)
    {
        $submissionMock = $mockBuilder
            ->setMethods(self::$methods)
            ->disableOriginalConstructor()
            ->getMock();

        return $submissionMock;
    }

    protected static $methods = [
        'getVirtualFields',
        'getWordCount',
        'setWordCount',
        'getIsLocked',
        'setIsLocked',
        'getStatus',
        'setStatus',
        'getStatusColor',
        'getId',
        'setId',
        'getSourceTitle',
        'setSourceTitle',
        'getSourceBlogId',
        'setSourceBlogId',
        'getSourceContentHash',
        'setSourceContentHash',
        'getContentType',
        'setContentType',
        'getSourceId',
        'setSourceId',
        'getFileUri',
        'setFileUri',
        'getTargetLocale',
        'setTargetLocale',
        'getTargetBlogId',
        'setTargetBlogId',
        'getTargetId',
        'setTargetId',
        'getSubmitter',
        'setSubmitter',
        'getSubmissionDate',
        'setSubmissionDate',
        'getAppliedDate',
        'setAppliedDate',
        'getApprovedStringCount',
        'setApprovedStringCount',
        'getCompletedStringCount',
        'setCompletedStringCount',
        'getCompletionPercentage',
    ];
}