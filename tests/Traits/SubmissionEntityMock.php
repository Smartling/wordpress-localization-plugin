<?php

namespace Smartling\Tests\Traits;

use PHPUnit\Framework\MockObject\MockObject;
use Smartling\Helpers\WordpressContentTypeHelper;
use Smartling\Submissions\SubmissionEntity;

trait SubmissionEntityMock
{
    /**
     * @return MockObject|SubmissionEntity
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

        return $this->createPartialMock(SubmissionEntity::class, $methods);
    }

    private function getSerializedSubmission(string $fileUri, string $locale, \DateTime $lastModified = null, int $completion = 0, int $id = 1): array
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
            'submission_date'        => '0000-00-00 00:00:00',
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
