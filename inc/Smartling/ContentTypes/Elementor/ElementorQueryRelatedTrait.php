<?php

namespace Smartling\ContentTypes\Elementor;

use Smartling\ContentTypes\ContentTypeHelper;
use Smartling\Models\Content;
use Smartling\Models\RelatedContentInfo;

/**
 * Elementor widgets that run a WP_Query store the query in settings keyed by a widget specific prefix
 * (e.g. "posts_" for the Posts widget, "post_query_" for loop widgets).
 */
trait ElementorQueryRelatedTrait
{
    private const QUERY_TERM_ID_SUFFIXES = ['include_term_ids', 'exclude_term_ids'];
    private const QUERY_POST_ID_SUFFIXES = ['posts_ids', 'exclude_ids'];

    protected function addQueryRelated(RelatedContentInfo $info, string $prefix): RelatedContentInfo
    {
        foreach (self::QUERY_TERM_ID_SUFFIXES as $suffix) {
            $this->addQueryIds($info, $prefix . $suffix, ContentTypeHelper::CONTENT_TYPE_TAXONOMY);
        }
        foreach (self::QUERY_POST_ID_SUFFIXES as $suffix) {
            $this->addQueryIds($info, $prefix . $suffix, ContentTypeHelper::CONTENT_TYPE_POST);
        }

        return $info;
    }

    private function addQueryIds(RelatedContentInfo $info, string $key, string $contentType): void
    {
        $ids = $this->settings[$key] ?? [];
        if (!is_array($ids)) {
            return;
        }
        foreach ($ids as $index => $id) {
            if (is_numeric($id) && (int)$id > 0) {
                $info->addContent(new Content((int)$id, $contentType), $this->id, "settings/$key/$index");
            }
        }
    }
}
