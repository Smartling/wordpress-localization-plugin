<?php

namespace Smartling\ContentTypes\Elementor;

use Smartling\ContentTypes\ContentTypeHelper;
use Smartling\Models\Content;
use Smartling\Models\RelatedContentInfo;

/**
 * Elementor widgets that run a WP_Query store the query in settings keyed by a widget specific prefix
 * (e.g. "posts_" for the Posts widget, "post_query_" for loop widgets).
 */
class ElementorQueryRelated
{
    private const TERM_ID_SUFFIXES = ['include_term_ids', 'exclude_term_ids'];
    private const POST_ID_SUFFIXES = ['posts_ids', 'exclude_ids'];

    public function addRelated(RelatedContentInfo $info, array $settings, string $containerId, string $prefix): RelatedContentInfo
    {
        foreach (self::TERM_ID_SUFFIXES as $suffix) {
            $this->addIds($info, $settings, $containerId, $prefix . $suffix, ContentTypeHelper::CONTENT_TYPE_TAXONOMY);
        }
        foreach (self::POST_ID_SUFFIXES as $suffix) {
            $this->addIds($info, $settings, $containerId, $prefix . $suffix, ContentTypeHelper::CONTENT_TYPE_POST);
        }

        return $info;
    }

    private function addIds(RelatedContentInfo $info, array $settings, string $containerId, string $key, string $contentType): void
    {
        $ids = $settings[$key] ?? [];
        if (!is_array($ids)) {
            return;
        }
        foreach ($ids as $index => $id) {
            if (is_numeric($id) && (int)$id > 0) {
                $info->addContent(new Content((int)$id, $contentType), $containerId, "settings/$key/$index");
            }
        }
    }
}
