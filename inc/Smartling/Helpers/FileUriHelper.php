<?php
namespace Smartling\Helpers;

use InvalidArgumentException;
use LogicException;
use Smartling\Bootstrap;

use Smartling\DbAl\WordpressContentEntities\EntityHandler;
use Smartling\DbAl\WordpressContentEntities\PostEntityStd;
use Smartling\DbAl\WordpressContentEntities\TaxonomyEntityStd;
use Smartling\DbAl\WordpressContentEntities\VirtualEntityAbstract;

use Smartling\Exception\BlogNotFoundException;
use Smartling\Exception\SmartlingConfigException;
use Smartling\Exception\SmartlingDirectRunRuntimeException;
use Smartling\Exception\SmartlingInvalidFactoryArgumentException;
use Smartling\Processors\ContentEntitiesIOFactory;
use Smartling\Submissions\SubmissionEntity;

class FileUriHelper
{
    private static function checkSubmission(SubmissionEntity $submission): void
    {
        if (StringHelper::isNullOrEmpty($submission->getSourceTitle(false))) {
            $submission->setSourceTitle('UNTITLED');
        }
    }

    /**
     * @param mixed $string
     * @param SubmissionEntity $entity
     * @return string
     */
    private static function preparePermalink($string, SubmissionEntity $entity): string
    {
        self::checkSubmission($entity);
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
        }

        return $fallBack;
    }

    private static function getSiteHelper(): SiteHelper
    {
        $id = 'site.helper';
        $result = Bootstrap::getContainer()->get($id);
        if (!$result instanceof SiteHelper) {
            throw new SmartlingConfigException("$id is expected to be " . SiteHelper::class);
        }
        return $result;
    }

    private static function getIoFactory(): ContentEntitiesIOFactory
    {
        $id = 'factory.contentIO';
        $result = Bootstrap::getContainer()->get($id);
        if (!$result instanceof ContentEntitiesIOFactory) {
            throw new SmartlingConfigException("$id is expected to be " . ContentEntitiesIOFactory::class);
        }
        return $result;
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
        } elseif ($ioWrapper instanceof EntityHandler) {
            $permalink = self::preparePermalink($ioWrapper->getTitle($submission->getSourceId()), $submission);
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
