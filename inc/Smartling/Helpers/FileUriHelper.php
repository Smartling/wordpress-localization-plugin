<?php
namespace Smartling\Helpers;

use Smartling\Bootstrap;
use Smartling\DbAl\WordpressContentEntities\PostEntity;
use Smartling\DbAl\WordpressContentEntities\TaxonomyEntityAbstract;
use Smartling\Processors\ContentEntitiesIOFactory;
use Smartling\Submissions\SubmissionEntity;

/**
 * Class FileUriHelper
 * @package Smartling\Helpers
 */
class FileUriHelper
{

    /**
     * @param $string
     *
     * @return string
     */
    private static function preparePermalink($string)
    {
        $pathinfo = parse_url($string);

        return rtrim($pathinfo['path'], '/');;
    }


    /**
     * @return SiteHelper
     */
    private static function getSiteHelper()
    {
        return Bootstrap::getContainer()
                        ->get('site.helper');
    }

    /**
     * @return ContentEntitiesIOFactory
     */
    private static function getIoFactory()
    {
        return Bootstrap::getContainer()
                        ->get('factory.contentIO');
    }

    /**
     * @param SubmissionEntity $submission
     *
     * @return string
     * @throws \Exception
     * @throws \Smartling\Exception\BlogNotFoundException
     */
    public static function generateFileUri(SubmissionEntity $submission)
    {
        $ioFactory = self::getIoFactory();
        $siteHelper = self::getSiteHelper();

        $ioWrapper = $ioFactory->getMapper($submission->getContentType());

        $fileUri = '';

        $needBlogSwitch = $siteHelper->getCurrentBlogId() != $submission->getSourceBlogId();

        if ($needBlogSwitch) {
            $siteHelper->switchBlogId($submission->getSourceBlogId());
        }

        if ($ioWrapper instanceof TaxonomyEntityAbstract) {
            /* term-based content */

            $permalink = self::preparePermalink(get_term_link($submission->getSourceId()));

            $fileUri = vsprintf(
                '%s_%s_%s_%s.xml',
                [
                    trim(TextHelper::mb_wordwrap($permalink, 210), "\n\r\t,. -_\0\x0B"),
                    $submission->getContentType(),
                    $submission->getSourceBlogId(),
                    $submission->getSourceId(),
                ]
            );
        } elseif ($ioWrapper instanceof PostEntity) {
            /* post-based content */

            $permalink = self::preparePermalink(get_permalink($submission->getSourceId()));

            $fileUri = vsprintf(
                '%s_%s_%s_%s.xml',
                [
                    trim(TextHelper::mb_wordwrap($permalink, 210), "\n\r\t,. -_\0\x0B"),
                    $submission->getContentType(),
                    $submission->getSourceBlogId(),
                    $submission->getSourceId(),
                ]
            );
        } else {
            $message = vsprintf(
                'Original entity should be post-based or taxonomy and should be an appropriate ancestor of Smartling DBAL classes. Got:%s',
                [
                    get_class($ioWrapper),
                ]
            );
            throw new \Exception($message);
        }

        if ($needBlogSwitch) {
            $siteHelper->restoreBlogId();
        }

        return $fileUri;
    }
}