<?php
namespace Smartling\Helpers;

use InvalidArgumentException;
use Smartling\Bootstrap;

use Smartling\DbAl\WordpressContentEntities\PostEntityStd;
use Smartling\DbAl\WordpressContentEntities\TaxonomyEntityStd;
use Smartling\DbAl\WordpressContentEntities\VirtualEntityAbstract;
use Smartling\Exception\BlogNotFoundException;
use Smartling\Exception\SmartlingDirectRunRuntimeException;
use Smartling\Exception\SmartlingInvalidFactoryArgumentException;
use Smartling\Processors\ContentEntitiesIOFactory;
use Smartling\Submissions\SubmissionEntity;
use Symfony\Component\DependencyInjection\Exception\LogicException;

class FileUriHelper
{

    /**
     * @param mixed $string
     * @param SubmissionEntity $entity
     * @return string
     * @throws InvalidArgumentException
     */
    private static function preparePermalink($string, SubmissionEntity $entity): string
    {
        if (StringHelper::isNullOrEmpty($entity->getSourceTitle(false))) {
            $entity->setSourceTitle('UNTITLED');
        }
        $fallBack = rtrim($entity->getSourceTitle(false), '/');
        if (is_string($string)) {
            $pathinfo = parse_url($string);
            if (false !== $pathinfo) {
                $path = rtrim($pathinfo['path'], '/');
                if (StringHelper::isNullOrEmpty($path)) {
                    return $fallBack;
                }

                return $path;
            }

            return $fallBack;
        }

        return $fallBack;
    }

    private static function getSiteHelper(): SiteHelper
    {
        return Bootstrap::getContainer()
                        ->get('site.helper');
    }

    private static function getIoFactory(): ContentEntitiesIOFactory
    {
        return Bootstrap::getContainer()
                        ->get('factory.contentIO');
    }

    /**
     * @param SubmissionEntity $submission
     *
     * @return string
     * @throws BlogNotFoundException
     * @throws SmartlingInvalidFactoryArgumentException
     * @throws SmartlingDirectRunRuntimeException
     * @throws InvalidArgumentException
     * @throws LogicException
     */
    public static function generateFileUri(SubmissionEntity $submission): string
    {
        $ioFactory = self::getIoFactory();
        $siteHelper = self::getSiteHelper();

        $ioWrapper = $ioFactory->getMapper($submission->getContentType());

        $needBlogSwitch = $siteHelper->getCurrentBlogId() !== $submission->getSourceBlogId();

        if ($needBlogSwitch) {
            $siteHelper->switchBlogId($submission->getSourceBlogId());
        }

        if ($ioWrapper instanceof TaxonomyEntityStd) {
            /* term-based content */
            $permalink = self::preparePermalink(get_term_link($submission->getSourceId()), $submission);
        } elseif ($ioWrapper instanceof PostEntityStd) {
            /* post-based content */
            $permalink = self::preparePermalink(get_permalink($submission->getSourceId()), $submission);
        } elseif ($ioWrapper instanceof VirtualEntityAbstract) {
            /* widget content */
            $permalink = self::preparePermalink('', $submission);
        } else {
            $message = vsprintf(
                'Original entity should be based on PostEntity or TaxonomyEntityAbstract or VirtualEntityAbstract and should be an appropriate ancestor of Smartling DBAL classes. Got:%s',
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
