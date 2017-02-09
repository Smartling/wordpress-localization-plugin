<?php

namespace Smartling\ContentTypes;
use Smartling\Helpers\WordpressFunctionProxyHelper;

/**
 * Class TermBasedContentTypeAbstract
 * @package Smartling\ContentTypes
 */
abstract class TermBasedContentTypeAbstract extends ContentTypeAbstract
{
    /**
     * The system name of Wordpress content type to make references safe.
     */
    const WP_CONTENT_TYPE = 'term';

    /**
     * Wordpress name of content-type, e.g.: post, page, post-tag
     * @return string
     */
    public function getSystemName()
    {
        return static::WP_CONTENT_TYPE;
    }

    /**
     * Base type can be 'post' or 'term' used for Multilingual Press plugin.
     * @return string
     */
    public function getBaseType()
    {
        return 'taxonomy';
    }

    /**
     * Display name of content type, e.g.: Post
     * @return string
     */
    public function getLabel()
    {
        $systemName = static::getSystemName();
        $result = WordpressFunctionProxyHelper::getTaxonomies(['name' => $systemName], 'objects');

        if (0 < count($result) && array_key_exists($systemName, $result)) {
            return $result[$systemName]->label;
        } else {
            return 'unknown';
        }
    }

    /**
     * @return array [
     *  'submissionBoard'   => true|false,
     *  'bulkSubmit'        => true|false
     * ]
     */
    public function getVisibility()
    {
        return [
            'submissionBoard' => true,
            'bulkSubmit'      => true,
        ];
    }

    /**
     * @return bool
     */
    public function isTaxonomy()
    {
        return true;
    }
}
