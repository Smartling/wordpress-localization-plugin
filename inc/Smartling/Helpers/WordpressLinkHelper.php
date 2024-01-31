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
        return $this->replaceHost($sourceUrl, $targetBlogId);
    }

    public function replaceHost(string $sourceUrl, int $targetBlogId): ?string
    {
        $currentBlogHost = parse_url($this->wordpressProxy->get_home_url(), PHP_URL_HOST);
        if (!is_string($currentBlogHost)) {
            return null;
        }
        $parsed = parse_url($sourceUrl);
        if (!is_array($parsed) || !array_key_exists('host', $parsed)) {
            return null;
        }
        if ($parsed['host'] === $currentBlogHost) {
            return str_replace("://$currentBlogHost", "://" . parse_url($this->wordpressProxy->get_home_url($targetBlogId), PHP_URL_HOST), $sourceUrl);
        }

        return null;
    }
}
