<?php

namespace Smartling\ContentTypes;

use Smartling\Helpers\WordpressFunctionProxyHelper;

/**
 * Class PostBasedContentTypeAbstract
 * @package Smartling\ContentTypes
 */
abstract class PostBasedContentTypeAbstract extends ContentTypeAbstract
{
    /**
     * The system name of Wordpress content type to make references safe.
     */
    const WP_CONTENT_TYPE = 'post';

    /**
     * Wordpress name of content-type, e.g.: post, page, post-tag
     * @return string
     */
    public function getSystemName(): string
    {
        return static::WP_CONTENT_TYPE;
    }

    /**
     * Base type can be 'post' or 'term' used for Multilingual Press plugin.
     * @return string
     */
    public function getBaseType(): string
    {
        return 'post';
    }

    /**
     * Display name of content type, e.g.: Post
     * @return string
     */
    public function getLabel(): string
    {
        $systemName = static::getSystemName();
        $result = WordpressFunctionProxyHelper::getPostTypes(['name' => $systemName], 'objects');

        if (0 < count($result) && array_key_exists($systemName, $result)) {
            return $result[$systemName]->label;
        } else {
            return 'unknown';
        }
    }

    /**
     * @return bool
     */
    public function isPost(): bool
    {
        return true;
    }

    protected function getRelatedTaxonomies()
    {
        $taxonomies = [];
        foreach (WordpressFunctionProxyHelper::getTaxonomies([], 'objects') as $taxonomy => $descriptor) {
            $relatedObjects = $descriptor->object_type;
            if (0 < count($relatedObjects)) {
                foreach ($relatedObjects as $relatedObjectSystemName) {
                    if (static::getSystemName() === $relatedObjectSystemName) {
                        $taxonomies[] = $taxonomy;
                    }
                }
            }
        }

        return $taxonomies;
    }
}
