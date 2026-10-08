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

    /**
     * Elementor keeps the values of hidden controls but ignores them unless the query mode matches, so the ids are
     * only collected when the mode setting (relative to the widget prefix) has the expected value.
     * suffix => [content type, remap only, mode setting suffix, expected mode value]
     */
    private const QUERY_SETTINGS = [
        'include_term_ids' => [ContentTypeHelper::CONTENT_TYPE_TAXONOMY, false, 'include', 'terms'],
        'exclude_term_ids' => [ContentTypeHelper::CONTENT_TYPE_TAXONOMY, true, 'exclude', 'terms'],
        'posts_ids' => [ContentTypeHelper::CONTENT_TYPE_POST, false, 'post_type', 'by_id'],
        'exclude_ids' => [ContentTypeHelper::CONTENT_TYPE_POST, true, 'exclude', 'manual_selection'],
    ];

    public function addRelated(RelatedContentInfo $info, array $settings, string $containerId, string $prefix): RelatedContentInfo
    {
        foreach (self::QUERY_SETTINGS as $suffix => [$contentType, $remapOnly, $modeSuffix, $mode]) {
            if ($this->isModeActive($settings[$prefix . $modeSuffix] ?? null, $mode)) {
                $this->addIds($info, $settings, $containerId, $prefix . $suffix, $contentType, $remapOnly);
            } else {
                $this->getLogger()->debug("Skipping {$prefix}{$suffix}, {$prefix}{$modeSuffix} does not select \"$mode\", containerId=$containerId");
            }
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
                $info->addContent(new Content($id, $contentType, $remapOnly), $containerId, "settings/$key/$index");
            }
        }
    }

    private function toId(mixed $value): ?int
    {
        $id = filter_var($value, FILTER_VALIDATE_INT);

        return is_int($id) && $id > 0 ? $id : null;
    }
}
