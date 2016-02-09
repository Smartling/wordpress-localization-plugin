<?php
namespace Smartling\Helpers;

use InvalidArgumentException;
use Smartling\Bootstrap;
use Smartling\DbAl\WordpressContentEntities\PostEntity;
use Smartling\DbAl\WordpressContentEntities\TaxonomyEntityAbstract;
use Smartling\Exception\SmartlingDirectRunRuntimeException;
use Smartling\Exception\SmartlingInvalidFactoryArgumentException;
use Smartling\Processors\ContentEntitiesIOFactory;
use Smartling\Submissions\SubmissionEntity;
use Symfony\Component\DependencyInjection\Exception\LogicException;

/**
 * Class FileUriHelper
 * @package Smartling\Helpers
 */
class FileUriHelper
{

    /**
     * @param $submission
     *
     * @throws InvalidArgumentException
     */
    private static function checkSubmission($submission)
    {
        if (!($submission instanceof SubmissionEntity)) {
            throw new InvalidArgumentException('Expected SubmissionEntity');
        }

        if (StringHelper::isNullOrEmpty($submission->getSourceTitle(false))) {
            throw new InvalidArgumentException('sourceTitle cannot be empty.');
        }
    }

    /**
     * @param string           $string
     *
     * @param SubmissionEntity $entity
     *
     * @return string
     *
     * @throws InvalidArgumentException
     */
    private static function preparePermalink($string, $entity)
    {
        self::checkSubmission($entity);
        $fallBack = rtrim($entity->getSourceTitle(false), '/');
        if (is_string($string)) {
            $pathinfo = parse_url($string);
            if (false !== $pathinfo) {
                $path = rtrim($pathinfo['path'], '/');
                if (StringHelper::isNullOrEmpty($path)) {
                    return $fallBack;
                } else {
                    return $path;
                }
            } else {
                return $fallBack;
            }
        } else {
            return $fallBack;
        }
    }


    /**
     * @return SiteHelper
     */
    private static function getSiteHelper()
    {
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        return Bootstrap::getContainer()
                        ->get('site.helper');
    }

    /**
     * @return ContentEntitiesIOFactory
     */
    private static function getIoFactory()
    {
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        return Bootstrap::getContainer()
                        ->get('factory.contentIO');
    }

    /**
     * @param SubmissionEntity $submission
     *
     * @return string
     * @throws \Exception
     * @throws BlogNotFoundException
     * @throws SmartlingInvalidFactoryArgumentException
     * @throws SmartlingDirectRunRuntimeException
     * @throws InvalidArgumentException
     * @throws LogicException
     */
    public static function generateFileUri(SubmissionEntity $submission)
    {
        $ioFactory = self::getIoFactory();
        $siteHelper = self::getSiteHelper();

        $ioWrapper = $ioFactory->getMapper($submission->getContentType());

        $needBlogSwitch = $siteHelper->getCurrentBlogId() !== $submission->getSourceBlogId();

        if ($needBlogSwitch) {
            $siteHelper->switchBlogId($submission->getSourceBlogId());
        }

        if ($ioWrapper instanceof TaxonomyEntityAbstract) {
            /* term-based content */
            $permalink = self::preparePermalink(get_term_link($submission->getSourceId()), $submission);
        } elseif ($ioWrapper instanceof PostEntity) {
            /* post-based content */
            $permalink = self::preparePermalink(get_permalink($submission->getSourceId()), $submission);
        } else {
            $message = vsprintf(
                'Original entity should be post-based or taxonomy and should be an appropriate ancestor of Smartling DBAL classes. Got:%s',
                [
                    get_class($ioWrapper),
                ]
            );
            throw new LogicException($message);
        }

        $fileUri = vsprintf(
            '%s_%s_%s_%s.xml',
            [
                trim(TextHelper::mb_wordwrap($permalink, 210), "\n\r\t,. -_\0\x0B"),
                $submission->getContentType(),
                $submission->getSourceBlogId(),
                $submission->getSourceId(),
            ]
        );

        if ($needBlogSwitch) {
            $siteHelper->restoreBlogId();
        }

        return $fileUri;
    }
}