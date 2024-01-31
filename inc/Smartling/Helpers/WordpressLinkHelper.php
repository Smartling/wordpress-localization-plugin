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

    public function getRedirected(string $url): string
    {
        $currentBlogHost = parse_url($this->wordpressProxy->get_home_url(), PHP_URL_HOST);
        if (!is_string($currentBlogHost)) {
            return $url;
        }
        $parsed = parse_url($url);

        if (!is_array($parsed) || ($currentBlogHost && $currentBlogHost !== $parsed['host'])) {
            return $url;
        }

        if (class_exists('\Red_Item') && array_key_exists('path', $parsed)) {
            try {
                $redirects = \Red_Item::get_for_url($parsed['path']);
                foreach ((array)$redirects as $item) {
                    $action = $item->get_match($parsed['path']);

                    if ($action) {
                        return $action->get_target();
                    }
                }
            } catch (\Throwable $e) {
                $this->getLogger()->notice("Caught exception while getting canonical for url=$url: {$e->getMessage()}");
            }
        }

        return $url;
    }

    public function getTargetBlogLink(string $sourceUrl, int $targetBlogId): ?string
    {
        $sourceUrl = $this->getRedirected($sourceUrl);
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
