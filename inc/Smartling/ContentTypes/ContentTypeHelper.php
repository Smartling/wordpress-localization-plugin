<?php

namespace Smartling\ContentTypes;

use Smartling\Helpers\WordpressFunctionProxyHelper;

class ContentTypeHelper {

    public const CONTENT_TYPE_POST = 'post';
    public const CONTENT_TYPE_TAXONOMY = 'taxonomy';
    public const CONTENT_TYPE_UNKNOWN = 'unknown';

    private WordpressFunctionProxyHelper $wpProxy;

    public function __construct(WordpressFunctionProxyHelper $wpProxy)
    {
        $this->wpProxy = $wpProxy;
    }

    public function getContentType(string $contentType): string
    {
        if (array_key_exists($contentType, $this->wpProxy->get_post_types())) {
            return self::CONTENT_TYPE_POST;
        }
        if (array_key_exists($contentType, $this->wpProxy->get_taxonomies())) {
            return self::CONTENT_TYPE_TAXONOMY;
        }

        return self::CONTENT_TYPE_UNKNOWN;
    }

    public function isPost(string $contentType): bool
    {
        return $this->getContentType($contentType) === self::CONTENT_TYPE_POST;
    }

    public function isTaxonomy(string $contentType): bool
    {
        return $this->getContentType($contentType) === self::CONTENT_TYPE_TAXONOMY;
    }
}
