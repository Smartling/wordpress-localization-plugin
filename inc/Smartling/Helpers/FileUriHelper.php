<?php
namespace Smartling\Helpers;

use InvalidArgumentException;
use Smartling\Bootstrap;

use Smartling\DbAl\WordpressContentEntities\PostEntityStd;
use Smartling\DbAl\WordpressContentEntities\TaxonomyEntityStd;

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

    private static function preparePermalink(mixed $string, SubmissionEntity $entity): string
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
     * @throws BlogNotFoundException
     * @throws SmartlingInvalidFactoryArgumentException
     * @throws SmartlingDirectRunRuntimeException
     * @throws InvalidArgumentException
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
        } else {
            $permalink = self::preparePermalink('', $submission);
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
