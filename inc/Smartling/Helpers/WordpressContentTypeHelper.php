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
        foreach (static::getContentTypeManager()->getRegisteredContentTypes() as $type) {
            $map[$type] = $type;
        }

        return $map;
    }

    public static $internalTypes = ['post' => 'post'];

    /**
     * @return array
     * @throws SmartlingDirectRunRuntimeException
     */
    public static function getReverseMap()
    {
        static::checkRuntimeState();

        return array_merge(static::$internalTypes, static::getDynamicReverseMap());
    }

    private static function getDynamicLabelMap()
    {
        $contentTypeManager = static::getContentTypeManager();

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
        static::checkRuntimeState();

        return static::getDynamicLabelMap();
    }

    public static function getTypesRestrictedToBulkSubmit()
    {
        return [static::getContentTypeManager()->getRestrictedForBulkSubmit()];
    }

    /**
     * @return array
     */
    public static function getSupportedTaxonomyTypes()
    {
        $descriptors = static::getContentTypeManager()->getDescriptorsByBaseType('taxonomy');
        $dynamicallyRegisteredTaxonomies = [];
        foreach ($descriptors as $descriptor) {
            $dynamicallyRegisteredTaxonomies[] = $descriptor->getSystemName();
        }

        return $dynamicallyRegisteredTaxonomies;
    }

    /**
     * @return ContentTypeManager
     */
    private static function getContentTypeManager()
    {
        return Bootstrap::getContainer()->get('content-type-descriptor-manager');
    }

    /**
     * @param $contentType
     *
     * @return string
     */
    public static function getLocalizedContentType($contentType)
    {
        $map = static::getLabelMap();
        if (array_key_exists($contentType, $map)) {
            return $map[$contentType];
        } else {
            $mgr = static::getContentTypeManager();
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

    public static function getBaseTypeByContentType($contentType)
    {
        /**
         * @var ContentTypeAbstract $ctHandler
         */
        $ctHandler = static::getContentTypeManager()->getHandler($contentType);

        return $ctHandler->getBaseType();
    }

    public static function getEditUrl(SubmissionEntity $submission)
    {
        /**
         * @var ContentTypeAbstract $ctHandler
         */
        $ctHandler = static::getContentTypeManager()->getHandler($submission->getContentType());

        if ($ctHandler instanceof ContentTypeAbstract) {
            $tail = '';
            switch ($ctHandler->getBaseType()) {
                case 'post':
                    $tail = vsprintf('/post.php?post=%s&action=edit', [$submission->getTargetId()]);
                    break;
                case 'taxonomy':
                    $tail = '/term.php?taxonomy=category&tag_ID=2';
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
                        $submission->getContentType(),
                    ]
                )
            );

            return '';
        }

    }
}
