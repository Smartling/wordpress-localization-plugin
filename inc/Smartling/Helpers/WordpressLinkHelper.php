<?php

namespace Smartling\Helpers;

use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;

class WordpressLinkHelper {
    use LoggerSafeTrait;

    public function __construct(
        private SubmissionManager $submissionManager,
        private WordpressFunctionProxyHelper $wordpressProxy,
    ) {
    }

    public function getTargetBlogLink(string $sourceUrl, int $targetBlogId): ?string
    {
        $sourcePostId = $this->wordpressProxy->url_to_postid($sourceUrl);
        $this->getLogger()->debug("Looking for replacement of sourceUrl=$sourceUrl, targetBlogId=$targetBlogId, sourcePostId=$sourcePostId");
        if (0 !== $sourcePostId) {
            $submission = ArrayHelper::first($this->submissionManager->find([
                SubmissionEntity::FIELD_TARGET_BLOG_ID => $targetBlogId,
                SubmissionEntity::FIELD_SOURCE_ID => $sourcePostId,
            ]));
            if ($submission !== false) {
                $this->getLogger()->debug("Found submissionId={$submission->getId()}, sourceUrl=$sourceUrl, targetBlogId=$targetBlogId, sourcePostId=$sourcePostId");
                $result = $this->wordpressProxy->get_blog_permalink($targetBlogId, $submission->getTargetId()) ?: null;
                if ($result === null) {
                    $this->getLogger()->debug("Got no permalink for targetPostId={$submission->getTargetId()}, sourceUrl=$sourceUrl, targetBlogId=$targetBlogId, sourcePostId=$sourcePostId");
                } else {
                    $this->getLogger()->debug("Replacing sourceUrl=$sourceUrl with targetUrl=$result");
                }

                return $result;
            }
        }

        return null;
    }
}
