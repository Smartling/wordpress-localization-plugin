<?php
namespace Smartling\Helpers;

use Smartling\Base\ExportedAPI;
use Smartling\DbAl\WordpressContentEntities\PostEntityStd;
use Smartling\DbAl\WordpressContentEntities\TaxonomyEntityStd;
use Smartling\Exception\BlogNotFoundException;
use Smartling\Exception\SmartlingConfigException;
use Smartling\Exception\SmartlingDirectRunRuntimeException;
use Smartling\Exception\SmartlingInvalidFactoryArgumentException;
use Smartling\Helpers\EventParameters\SmartlingFileUriFilterParamater;
use Smartling\Processors\ContentEntitiesIOFactory;
use Smartling\Submissions\Submission;

class FileUriHelper
{
    use LoggerSafeTrait;

    private const FILE_URI_FORMAT = '%s_%s_%s_%s.xml';
    private const MAX_PERMALINK_LENGTH = 210;
    private const UNTITLED = 'UNTITLED';

    public function __construct(
        private ContentEntitiesIOFactory $ioFactory,
        private SiteHelper $siteHelper,
    ) {
    }

    /**
     * @throws SmartlingInvalidFactoryArgumentException
     * @throws SmartlingConfigException
     */
    private function buildFileUri(Submission $submission): string {
        try {
            $wrapper = $this->ioFactory->getMapper($submission->getContentType());
        } catch (SmartlingInvalidFactoryArgumentException $e) {
            $this->getLogger()->notice(sprintf(
                'ContentType=%s is not registered, expected one of %s',
                $submission->getContentType(),
                implode(',' , array_keys($this->ioFactory->getCollection()))
            ));
            throw $e;
        }
        if ($wrapper instanceof TaxonomyEntityStd) {
            $permalink = $this->preparePermalink(get_term_link($submission->getSourceId()), $submission->getSourceTitle());
        } elseif ($wrapper instanceof PostEntityStd) {
            $permalink = $this->preparePermalink(get_permalink($submission->getSourceId()), $submission->getSourceTitle());
        } else {
            $permalink = $this->preparePermalink('', $submission->getSourceTitle());
        }

        return sprintf(
            self::FILE_URI_FORMAT,
            trim(TextHelper::mb_wordwrap($permalink, self::MAX_PERMALINK_LENGTH), "\n\r\t,. -_\0\x0B"),
            $submission->getContentType(),
            $submission->getSourceBlogId(),
            $submission->getSourceId(),
        );
    }

    /**
     * @throws SmartlingInvalidFactoryArgumentException
     * @throws BlogNotFoundException
     * @throws SmartlingConfigException
     * @throws SmartlingDirectRunRuntimeException
     */
    public function generateFileUri(Submission $submission): string {
        if ($this->siteHelper->getCurrentBlogId() !== $submission->getSourceBlogId()) {
            $fileUri = $this->siteHelper->withBlog($submission->getSourceBlogId(), function () use ($submission) {
                return $this->buildFileUri($submission);
            });
        } else {
            $fileUri = $this->buildFileUri($submission);
        }

        $filterParams = (new SmartlingFileUriFilterParamater())
            ->setContentType($submission->getContentType())
            ->setFileUri($fileUri)
            ->setSourceBlogId($submission->getSourceBlogId())
            ->setSourceContentId($submission->getSourceId());

        $filterParams = apply_filters(ExportedAPI::FILTER_SMARTLING_FILE_URI, $filterParams);

        if (($filterParams instanceof SmartlingFileUriFilterParamater)
            && !StringHelper::isNullOrEmpty($filterParams->getFileUri())
        ) {
            $fileUri = $filterParams->getFileUri();
        }

        return $fileUri;
    }

    public function preparePermalink(mixed $string, string $title): string
    {
        if ($title === '') {
            $title = self::UNTITLED;
        }
        $fallBack = rtrim($title , '/');
        if (is_string($string)) {
            $pathInfo = parse_url($string);
            if (is_array($pathInfo) && array_key_exists('path', $pathInfo)) {
                $path = rtrim($pathInfo['path'], '/');
                return $path === '' ? $fallBack : $path;
            }
        }

        return $fallBack;
    }
}
