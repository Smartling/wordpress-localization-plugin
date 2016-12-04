<?php

namespace Smartling\Helpers;

use Smartling\Bootstrap;
use Smartling\ContentTypes\ContentTypeInterface;
use Smartling\ContentTypes\ContentTypeManager;
use Smartling\Exception\SmartlingDirectRunRuntimeException;
use Smartling\Exception\SmartlingNotSupportedContentException;

/**
 * Class WordpressContentTypeHelper
 *
 * @package Smartling\Helpers
 */
class WordpressContentTypeHelper
{

    /**
     * 'term' based content type
     */
    const CONTENT_TYPE_TERM_POLICY_TYPE = 'policy_types';
    /**
     * 'post' based content type
     */
    const CONTENT_TYPE_POST_POLICY = 'policy';

    /**
     * 'post' based content type
     */
    const CONTENT_TYPE_POST_PARTNER = 'partner';

    /**
     * 'post' based content type
     */
    const CONTENT_TYPE_POST_TESTIMONIAL = 'testimonial';




    /**
     * Checks if Wordpress i10n function __ is registered
     * if not - throws an SmartlingDirectRunRuntimeException exception
     *
     * @throws SmartlingDirectRunRuntimeException
     */
    private static function checkRuntimeState()
    {
        if (!function_exists('__')) {
            $message = 'I10n Wordpress function not available on direct execution.';
            throw new SmartlingDirectRunRuntimeException($message);
        }
    }

    /**
     * Reverse map of Wordpress types to constants
     *
     * @var array
     */
    private static $_reverse_map = [


        'policy'        => self::CONTENT_TYPE_POST_POLICY,
        'partner'       => self::CONTENT_TYPE_POST_PARTNER,
        'testimonial'   => self::CONTENT_TYPE_POST_TESTIMONIAL,




    ];

    private static function getDynamicReverseMap()
    {
        /**
         * @var ContentTypeManager $contentTypeManager
         */
        $contentTypeManager = Bootstrap::getContainer()->get('content-type-descriptor-manager');

        $map=[];
        foreach ($contentTypeManager->getRegisteredContentTypes() as $type) {
            $map[$type]=$type;
        }

        return $map;
    }
    /**
     * @return array
     * @throws SmartlingDirectRunRuntimeException
     */
    public static function getReverseMap()
    {
        self::checkRuntimeState();

        return array_merge(self::$_reverse_map, self::getDynamicReverseMap());
    }

    private static function getDymamicLabelMap()
    {
        /**
         * @var ContentTypeManager $contentTypeManager
         */
        $contentTypeManager = Bootstrap::getContainer()->get('content-type-descriptor-manager');

        $map=[];
        foreach ($contentTypeManager->getRegisteredContentTypes() as $type) {
            $map[$type]=$contentTypeManager->getHandler($type)->getLabel();
        }

        return $map;
    }

    /**
     * @return array
     * @throws SmartlingDirectRunRuntimeException
     */
    public static function getLabelMap()
    {
        self::checkRuntimeState();



        // has to be hardcoded because i10n parser must see direct calls of __(CONSTANT STRING)
        return array_merge([
            self::CONTENT_TYPE_POST_POLICY      => __('Policy'),
            self::CONTENT_TYPE_POST_PARTNER     => __('Partner'),
            self::CONTENT_TYPE_POST_TESTIMONIAL => __('Testimonial'),





        ], self::getDymamicLabelMap());
    }

    public static function getTypesRestrictedToBulkSubmit()
    {
        $mgr = Bootstrap::getContainer()->get('content-type-descriptor-manager');
        /**
         * @var ContentTypeManager $mgr
         */
        $descriptors = $mgr->getRestrictedForBulkSubmit();

        return [$descriptors];
    }

    /**
     * @return array
     */
    public static function getSupportedTaxonomyTypes()
    {
        $mgr = Bootstrap::getContainer()->get('content-type-descriptor-manager');
        /**
         * @var ContentTypeManager $mgr
         */
        $descriptors = $mgr->getDescriptorsByBaseType('taxonomy');

        $dynamicallyRegisteredTaxonomies = [];

        foreach ($descriptors as $descriptor) {
            $dynamicallyRegisteredTaxonomies[]=$descriptor->getSystemName();
        }


        return array_merge([],$dynamicallyRegisteredTaxonomies);
    }

    /**
     * @param $contentType
     *
     * @return string
     * @throws SmartlingNotSupportedContentException
     */
    public static function getLocalizedContentType($contentType)
    {
        $map = self::getLabelMap();
        if (array_key_exists($contentType, $map)) {
            return $map[$contentType];
        } else {
            $mgr = Bootstrap::getContainer()->get('content-type-descriptor-manager');
            /**
             * @var ContentTypeManager $mgr
             */
            $descriptor = $mgr->getDescriptorByType($contentType);

            if ($descriptor instanceof ContentTypeInterface){
                return $descriptor->getLabel();
            } else {
                throw new SmartlingNotSupportedContentException(vsprintf('Content-type \'%s\' is not supported yet.',
                                                                         [$contentType]));
            }




        }
    }
}