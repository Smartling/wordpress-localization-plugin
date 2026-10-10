<?php

namespace Smartling\ContentTypes\Elementor;

use Smartling\ContentTypes\ContentTypeHelper;
use Smartling\Helpers\LoggerSafeTrait;
use Smartling\Models\Content;
use Smartling\Models\RelatedContentInfo;

/**
 * Elementor widgets that run a WP_Query store the query in settings keyed by a widget specific prefix
 * (e.g. "posts_" for the Posts widget, "post_query_" for loop widgets).
 *
 * Included terms and posts are sent for translation as related content. Excluded ones are remap-only:
 * their ids are replaced with the translated ones if they are already translated, but excluding something
 * does not cause it to be submitted.
 */
class ElementorQueryRelated
{
    use LoggerSafeTrait;

    private const POST_TYPE_MANUAL_SELECTION = 'by_id';
    private const POST_TYPE_CURRENT_QUERY = 'current_query';
    private const POST_TYPE_RELATED = 'related';

    /**
     * Elementor Pro (Elementor_Post_Query, checked against 3.33.2) keeps the values of hidden controls but ignores them
     * unless the query mode matches:
     * - {prefix}post_type = by_id (manual selection): only posts_ids is used, everything else is skipped
     * - {prefix}post_type = current_query: the main query is used as is, none of the settings is used
     * - {prefix}post_type = related: include_term_ids is not used, the terms of the current post are
     * - otherwise posts_ids is ignored, and the other lists need their own mode ({prefix}include / {prefix}exclude)
     *   to contain the expected value
     * Term lists hold term_taxonomy_ids (get_term_by('term_taxonomy_id') and 'field' => 'term_taxonomy_id' in the query).
     *
     * suffix => [content type, remap only, only used for this post type, ignored for these post types,
     *            mode setting suffix, expected mode value]
     */
    private const QUERY_SETTINGS = [
        'include_term_ids' => [
            ContentTypeHelper::CONTENT_TYPE_TAXONOMY, false, null,
            [self::POST_TYPE_MANUAL_SELECTION, self::POST_TYPE_CURRENT_QUERY, self::POST_TYPE_RELATED], 'include', 'terms',
        ],
        'exclude_term_ids' => [
            ContentTypeHelper::CONTENT_TYPE_TAXONOMY, true, null,
            [self::POST_TYPE_MANUAL_SELECTION, self::POST_TYPE_CURRENT_QUERY], 'exclude', 'terms',
        ],
        'posts_ids' => [ContentTypeHelper::CONTENT_TYPE_POST, false, self::POST_TYPE_MANUAL_SELECTION, [], null, null],
        'exclude_ids' => [
            ContentTypeHelper::CONTENT_TYPE_POST, true, null,
            [self::POST_TYPE_MANUAL_SELECTION, self::POST_TYPE_CURRENT_QUERY], 'exclude', 'manual_selection',
        ],
    ];

    public function addRelated(RelatedContentInfo $info, array $settings, string $containerId, string $prefix): RelatedContentInfo
    {
        $postType = $settings[$prefix . 'post_type'] ?? null;
        foreach (self::QUERY_SETTINGS as $suffix => [$contentType, $remapOnly, $onlyPostType, $ignoredPostTypes, $modeSuffix, $mode]) {
            if (($onlyPostType !== null && $postType !== $onlyPostType)
                || in_array($postType, $ignoredPostTypes, true)
                || ($mode !== null && !$this->isModeActive($settings[$prefix . $modeSuffix] ?? null, $mode))
            ) {
                $this->getLogger()->debug("Skipping {$prefix}{$suffix}, it is not used by the query mode of the widget, containerId=$containerId");
                continue;
            }
            $this->addIds($info, $settings, $containerId, $prefix . $suffix, $contentType, $remapOnly);
        }

        return $info;
    }

    private function isModeActive(mixed $modeSetting, string $mode): bool
    {
        return is_array($modeSetting) ? in_array($mode, $modeSetting, true) : $modeSetting === $mode;
    }

    /**
     * Related content of loop widgets (loop-grid, loop-carousel): the item template and the query
     */
    public function addLoopRelated(RelatedContentInfo $info, array $settings, string $containerId): RelatedContentInfo
    {
        $key = 'template_id';
        $id = $this->toId($settings[$key] ?? null);
        if ($id !== null) {
            $info->addContent(new Content($id, ContentTypeHelper::CONTENT_TYPE_POST), $containerId, "settings/$key");
        }

        return $this->addRelated($info, $settings, $containerId, 'post_query_');
    }

    private function addIds(RelatedContentInfo $info, array $settings, string $containerId, string $key, string $contentType, bool $remapOnly): void
    {
        $ids = $settings[$key] ?? [];
        if (!is_array($ids)) {
            $this->getLogger()->debug("Ignoring non-array value of setting $key, containerId=$containerId");
            return;
        }
        foreach ($ids as $index => $id) {
            $id = $this->toId($id);
            if ($id !== null) {
                $info->addContent(new Content($id, $contentType, $remapOnly, $contentType === ContentTypeHelper::CONTENT_TYPE_TAXONOMY), $containerId, "settings/$key/$index");
            }
        }
    }

    private function toId(mixed $value): ?int
    {
        $id = filter_var($value, FILTER_VALIDATE_INT);

        return is_int($id) && $id > 0 ? $id : null;
    }
}
