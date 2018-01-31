<?php

namespace Smartling\Helpers;

use Smartling\Bootstrap;
use Smartling\ContentTypes\ContentTypeAbstract;
use Smartling\ContentTypes\ContentTypeInterface;
use Smartling\ContentTypes\ContentTypeManager;
use Smartling\Exception\SmartlingDirectRunRuntimeException;
use Smartling\Submissions\SubmissionEntity;

/**
 * Class WordpressContentTypeHelper
 * @package Smartling\Helpers
 */
class WordpressContentTypeHelper
{
    /**
     * Checks if Wordpress i10n function __ is registered
     * if not - throws an SmartlingDirectRunRuntimeException exception
     * @throws SmartlingDirectRunRuntimeException
     */
    private static function checkRuntimeState()
    {
        if (!function_exists('__')) {
            $message = 'I10n Wordpress function not available on direct execution.';
            throw new SmartlingDirectRunRuntimeException($message);
        }
    }

    private static function getDynamicReverseMap()
    {
        $map = [];
        foreach (self::getContentTypeManager()->getRegisteredContentTypes() as $type) {
            $map[$type] = $type;
        }

        return $map;
    }

    public static $internalTypes = [];

    /**
     * @return array
     * @throws SmartlingDirectRunRuntimeException
     */
    public static function getReverseMap()
    {
        self::checkRuntimeState();

        return array_merge(self::$internalTypes, self::getDynamicReverseMap());
    }

    private static function getDynamicLabelMap()
    {
        $contentTypeManager = self::getContentTypeManager();

        $map = [];
        foreach ($contentTypeManager->getRegisteredContentTypes() as $type) {
            $map[$type] = $contentTypeManager->getHandler($type)->getLabel();
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

        return self::getDynamicLabelMap();
    }

    public static function getTypesRestrictedToBulkSubmit()
    {
        return [self::getContentTypeManager()->getRestrictedForBulkSubmit()];
    }

    /**
     * @return array
     */
    public static function getSupportedTaxonomyTypes()
    {
        $descriptors = self::getContentTypeManager()->getDescriptorsByBaseType('taxonomy');
        $dynamicallyRegisteredTaxonomies = [];
        foreach ($descriptors as $descriptor) {
            $dynamicallyRegisteredTaxonomies[] = $descriptor->getSystemName();
        }
        return $dynamicallyRegisteredTaxonomies;
    }

    /**
     * @return ContentTypeManager
     */
    private static function getContentTypeManager() {
        return Bootstrap::getContainer()->get('content-type-descriptor-manager');
    }

    /**
     * @param $contentType
     *
     * @return string
     */
    public static function getLocalizedContentType($contentType)
    {
        $map = self::getLabelMap();
        if (array_key_exists($contentType, $map)) {
            return $map[$contentType];
        } else {
            $mgr = self::getContentTypeManager();
            $message = vsprintf('Content-type \'%s\' is not supported.', [$contentType]);
            /**
             * @var ContentTypeManager $mgr
             */
            try {
                $descriptor = $mgr->getDescriptorByType($contentType);

                if ($descriptor instanceof ContentTypeInterface) {
                    return $descriptor->getLabel();
                } else {
                    Bootstrap::getLogger()->warning($message);
                }
            } catch (\Exception $e) {
                Bootstrap::getLogger()->warning($e->getMessage());
                Bootstrap::getLogger()->warning($message);
            }

            return $contentType;
        }
    }

    public static function getBaseTypeByContentType($contentType) {
        /**
         * @var ContentTypeAbstract $ctHandler
         */
        $ctHandler = self::getContentTypeManager()->getHandler($contentType);

        return $ctHandler->getBaseType();
    }

    public static function getEditUrl(SubmissionEntity $submission) {
        /**
         * @var ContentTypeAbstract $ctHandler
         */
        $ctHandler = self::getContentTypeManager()->getHandler($submission->getContentType());

        if ($ctHandler instanceof ContentTypeAbstract) {
            $tail = '';
            switch ($ctHandler->getBaseType()) {
                case 'post':
                    $tail = vsprintf('/post.php?post=%s&action=edit', [$submission->getTargetId()]);
                    break;
                case 'taxonomy':
                    $tail =  '/term.php?taxonomy=category&tag_ID=2';
                    break;
                default:
                    return '';
            }

            return get_admin_url($submission->getTargetBlogId(), $tail);
        } else {
            Bootstrap::getLogger()->warning(
                vsprintf(
                    'Requested edit URI for unknown content-type \'%s\'',
                    [
                        $submission->getContentType()
                    ]
                )
            );
            return '';
        }

    }
}