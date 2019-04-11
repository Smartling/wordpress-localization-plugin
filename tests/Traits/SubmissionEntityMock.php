<?php

namespace Smartling\Tests\Traits;

use Smartling\Helpers\WordpressContentTypeHelper;
use Smartling\Submissions\SubmissionEntity;

/**
 * Class SubmissionEntityMock
 * @package Smartling\Tests\Traits
 */
trait SubmissionEntityMock
{
    /**
     * @return \PHPUnit_Framework_MockObject_MockObject|SubmissionEntity
     */
    private function getSubmissionEntityMock()
    {
        $methods = ['getVirtualFields', 'getWordCount', 'setWordCount', 'getIsLocked', 'setIsLocked',
                    'getStatus', 'setStatus', 'getStatusColor', 'getId', 'setId', 'getSourceTitle',
                    'setSourceTitle', 'getSourceBlogId', 'setSourceBlogId', 'getSourceContentHash',
                    'setSourceContentHash', 'getContentType', 'setContentType', 'getSourceId',
                    'setSourceId', 'getFileUri', 'setFileUri', 'getTargetLocale', 'setTargetLocale',
                    'getTargetBlogId', 'setTargetBlogId', 'getTargetId', 'setTargetId', 'getSubmitter',
                    'setSubmitter', 'getSubmissionDate', 'setSubmissionDate', 'getAppliedDate',
                    'setAppliedDate', 'getApprovedStringCount', 'setApprovedStringCount',
                    'getCompletedStringCount', 'setCompletedStringCount', 'getCompletionPercentage',];

        return $this->getMockBuilder('Smartling\Submissions\SubmissionEntity')
            ->setMethods($methods)
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * @param string         $fileUri
     * @param string         $locale
     * @param null|\DateTime $lastModified
     * @param int            $completion
     * @param int            $id
     *
     * @return array
     */
    private function getSerializedSubmission($fileUri, $locale, $lastModified = null, $completion = 0, $id = 1)
    {
        WordpressContentTypeHelper::$internalTypes = ['Post' => 'post'];

        return [
            'id'                     => $id,
            'source_title'           => 'A',
            'source_blog_id'         => 1,
            'source_content_hash'    => '',
            'content_type'           => 'post',
            'source_id'              => 7,
            'file_uri'               => $fileUri,
            'target_locale'          => $locale,
            'target_blog_id'         => 2,
            'target_id'              => null,
            'submitter'              => '',
            'submission_date'        => null,
            'applied_date'           => null,
            'approved_string_count'  => $completion,
            'completed_string_count' => $completion,
            'excluded_string_count'  => 0,
            'total_string_count'     => $completion,
            'status'                 => SubmissionEntity::SUBMISSION_STATUS_IN_PROGRESS,
            'is_locked'              => 0,
            'is_cloned'              => 0,
            'last_modified'          => $lastModified,
        ];
    }
}