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

    private const TERM_ID_SUFFIXES = ['include_term_ids' => false, 'exclude_term_ids' => true];
    private const POST_ID_SUFFIXES = ['posts_ids' => false, 'exclude_ids' => true];

    public function addRelated(RelatedContentInfo $info, array $settings, string $containerId, string $prefix): RelatedContentInfo
    {
        foreach (self::TERM_ID_SUFFIXES as $suffix => $remapOnly) {
            $this->addIds($info, $settings, $containerId, $prefix . $suffix, ContentTypeHelper::CONTENT_TYPE_TAXONOMY, $remapOnly);
        }
        foreach (self::POST_ID_SUFFIXES as $suffix => $remapOnly) {
            $this->addIds($info, $settings, $containerId, $prefix . $suffix, ContentTypeHelper::CONTENT_TYPE_POST, $remapOnly);
        }

        return $info;
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
