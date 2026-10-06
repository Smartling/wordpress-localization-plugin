<?php

namespace Smartling\Helpers;

use Smartling\Base\ExportedAPI;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;
use Smartling\WP\WPHookInterface;

/**
 * Exposes the source id => target id mapping stored in submissions to custom code:
 * apply_filters('smartling_target_id', 13574, 'attachment') returns the id of the translated attachment in the current blog.
 */
class TargetIdFilter implements WPHookInterface
{
    use LoggerSafeTrait;

    public function __construct(
        private SubmissionManager $submissionManager,
        private WordpressFunctionProxyHelper $wpProxy,
    ) {
    }

    public function register(): void
    {
        $this->wpProxy->add_filter(ExportedAPI::FILTER_TARGET_ID, [$this, 'getTargetId'], 10, 5);
    }

    public function getTargetId(
        mixed $sourceId,
        string $contentType = '',
        ?int $targetBlogId = null,
        bool $fallbackToSource = true,
        ?int $sourceBlogId = null,
    ): ?int {
        if (!is_numeric($sourceId) || (int)$sourceId <= 0) {
            return null;
        }
        $sourceId = (int)$sourceId;
        $parameters = [
            SubmissionEntity::FIELD_SOURCE_ID => $sourceId,
            SubmissionEntity::FIELD_TARGET_BLOG_ID => $targetBlogId ?? $this->wpProxy->get_current_blog_id(),
        ];
        if ($contentType !== '') {
            $parameters[SubmissionEntity::FIELD_CONTENT_TYPE] = $contentType;
        }
        if ($sourceBlogId !== null) {
            $parameters[SubmissionEntity::FIELD_SOURCE_BLOG_ID] = $sourceBlogId;
        }

        $submission = $this->submissionManager->findOne($parameters);
        if ($submission !== null && $submission->getTargetId() > 0) {
            return $submission->getTargetId();
        }

        $this->getLogger()->debug("No target id found for sourceId=$sourceId, contentType=\"$contentType\"" . ($fallbackToSource ? ', falling back to source id' : ''));

        return $fallbackToSource ? $sourceId : null;
    }
}
