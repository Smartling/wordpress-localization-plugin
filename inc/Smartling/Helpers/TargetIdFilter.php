<?php

namespace Smartling\Helpers;

use Smartling\Base\ExportedAPI;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;
use Smartling\WP\WPHookInterface;

/**
 * Exposes the source id => target id mapping stored in submissions to custom code:
 * apply_filters('smartling_target_id', 13574, 'attachment') returns the id of the translated attachment in the current blog.
 *
 * The filter is called from front end code, so it never throws and tolerates input of any type.
 * Results are cached in the object cache (per request, unless a persistent object cache is used).
 */
class TargetIdFilter implements WPHookInterface
{
    use LoggerSafeTrait;

    private const CACHE_EXPIRATION = 60;
    private const CACHE_KEY_PREFIX = 'target_id_';
    private const NOT_FOUND = 0;

    public function __construct(
        private SubmissionManager $submissionManager,
        private WordpressFunctionProxyHelper $wpProxy,
        private Cache $cache,
    ) {
    }

    public function register(): void
    {
        $this->wpProxy->add_filter(ExportedAPI::FILTER_TARGET_ID, [$this, 'getTargetId'], 10, 5);
    }

    /**
     * Any submission that has a target id counts as a translation, regardless of its status:
     * the target id is set when the target content is created, which can happen before translations are
     * downloaded, and it stays set for failed or cancelled submissions of content that already exists in the target blog.
     *
     * @return mixed target id, or $sourceId unchanged if nothing is found and $fallbackToSource is true, null otherwise
     */
    public function getTargetId(
        mixed $sourceId,
        mixed $contentType = '',
        mixed $targetBlogId = null,
        mixed $fallbackToSource = true,
        mixed $sourceBlogId = null,
    ): mixed {
        $fallbackToSource = $fallbackToSource === null ? true : filter_var($fallbackToSource, FILTER_VALIDATE_BOOLEAN);
        $fallback = $fallbackToSource ? $sourceId : null;
        $id = filter_var($sourceId, FILTER_VALIDATE_INT);
        if (!is_int($id) || $id <= 0) {
            return $fallback;
        }

        try {
            $parameters = [
                SubmissionEntity::FIELD_SOURCE_ID => $id,
                SubmissionEntity::FIELD_TARGET_BLOG_ID => $this->toPositiveInt($targetBlogId) ?? $this->wpProxy->get_current_blog_id(),
            ];
            if (is_string($contentType) && $contentType !== '') {
                $parameters[SubmissionEntity::FIELD_CONTENT_TYPE] = $contentType;
            }
            $sourceBlogId = $this->toPositiveInt($sourceBlogId);
            if ($sourceBlogId !== null) {
                $parameters[SubmissionEntity::FIELD_SOURCE_BLOG_ID] = $sourceBlogId;
            }

            $targetId = $this->lookup($parameters);
        } catch (\Throwable $e) {
            $this->getLogger()->error("Unable to get target id for sourceId=$id: " . $e->getMessage());

            return $fallback;
        }

        return $targetId > 0 ? $targetId : $fallback;
    }

    private function lookup(array $parameters): int
    {
        $key = self::CACHE_KEY_PREFIX . md5(json_encode($parameters, JSON_THROW_ON_ERROR));
        $cached = $this->cache->get($key);
        if (is_int($cached)) {
            return $cached;
        }

        $submissions = $this->submissionManager->find($parameters, 2);
        $targetId = self::NOT_FOUND;
        $context = json_encode($parameters);
        if (count($submissions) === 1) {
            $targetId = max(self::NOT_FOUND, current($submissions)->getTargetId());
        }
        if ($targetId === self::NOT_FOUND) {
            // a missing translation is expected (e.g. called on the source blog) and this runs on front-end page views
            if (count($submissions) > 1) {
                $this->getLogger()->notice("Found more than one submission, target id is ambiguous, searchParams=$context");
            } else {
                $this->getLogger()->debug("No target id found, searchParams=$context");
            }
        }
        $this->cache->set($key, $targetId, self::CACHE_EXPIRATION);

        return $targetId;
    }

    private function toPositiveInt(mixed $value): ?int
    {
        $result = filter_var($value, FILTER_VALIDATE_INT);

        return is_int($result) && $result > 0 ? $result : null;
    }
}
