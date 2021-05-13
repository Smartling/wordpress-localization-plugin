<?php

namespace Smartling\Helpers;

use DOMDocument;
use LibXMLError;
use Psr\Log\LoggerInterface;
use Smartling\Base\ExportedAPI;
use Smartling\Base\SmartlingCore;
use Smartling\Extensions\Acf\AcfDynamicSupport;
use Smartling\Helpers\EventParameters\AfterDeserializeContentEventParameters;
use Smartling\MonologWrapper\MonologWrapper;
use Smartling\WP\WPHookInterface;

class RelativeLinkedAttachmentCoreHelper implements WPHookInterface
{
    private const ACF_GUTENBERG_BLOCK = '<!-- wp:acf/.+ ({.+}) /-->';
    /**
     * RegEx to catch images from the string
     */
    protected const PATTERN_IMAGE_GENERAL = '<img[^>]+>';

    protected const PATTERN_THUMBNAIL_IDENTITY = '-\d+x\d+$';

    private array $acfDefinitions = [];
    private AcfDynamicSupport $acfDynamicSupport;
    private LoggerInterface $logger;
    protected SmartlingCore $core;
    private AfterDeserializeContentEventParameters $params;

    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    public function getParams(): AfterDeserializeContentEventParameters
    {
        return $this->params;
    }

    private function setParams(AfterDeserializeContentEventParameters $params): void
    {
        $this->params = $params;
    }

    public function __construct(SmartlingCore $core, AcfDynamicSupport $acfDynamicSupport)
    {
        $this->acfDynamicSupport = $acfDynamicSupport;
        $this->core = $core;
        $this->logger = MonologWrapper::getLogger(static::class);
    }

    public function register(): void
    {
        add_action(ExportedAPI::EVENT_SMARTLING_AFTER_DESERIALIZE_CONTENT, [$this, 'processor']);
    }

    /**
     * Alters $params->translatedFields
     */
    public function processor(AfterDeserializeContentEventParameters $params): void
    {
        $this->setParams($params);
        if (count($this->acfDefinitions) === 0) {
            $this->acfDynamicSupport->run();
            $this->acfDefinitions = $this->acfDynamicSupport->getDefinitions();
        }

        $fields = &$params->getTranslatedFields();

        foreach ($fields as &$value) {
            $this->processString($value);
        }
    }

    /**
     * Recursively processes all found strings
     *
     * @param string|array $stringValue
     */
    protected function processString(&$stringValue): void
    {
        $replacer = new PairReplacerHelper();
        $matches = [];

        if (is_array($stringValue)) {
            foreach ($stringValue as &$value) {
                $this->processString($value);
            }
            return;
        }

        if (0 < preg_match_all(self::ACF_GUTENBERG_BLOCK, $stringValue, $matches)) {
            // ACF has it's own processing because we know how it works with attachments
            // TODO move to GutenbergBlockHelper
            foreach ($matches[1] as $match) {
                $stringValue = $this->getReplacerForAcfGutenbergBlock($match)->processString($stringValue);
            }
        }

        if (0 < preg_match_all(StringHelper::buildPattern(self::PATTERN_IMAGE_GENERAL), $stringValue, $matches)) {
            foreach ($matches[0] as $match) {
                $stringValue = $this->getReplacerForImgTag($match, $replacer)->processString($stringValue);
            }
        }
    }

    /**
     * Extracts src attribute from <img /> tag if possible, otherwise returns null.
     */
    private function getSourcePathFromImgTag(string $imgTagString): ?string
    {
        return $this->getAttributeFromTag($imgTagString, 'img', 'src');
    }

    private function tryProcessThumbnail(string $path): ?ReplacementPair
    {
        $submission = $this->getParams()->getSubmission();
        $dir = $this->core->getUploadFileInfo($submission->getSourceBlogId())['basedir'];

        $fullFileName = $dir . DIRECTORY_SEPARATOR . $this->core->getFullyRelateAttachmentPath($submission, $path);

        if (FileHelper::testFile($fullFileName)) {
            $sourceFilePathInfo = pathinfo($fullFileName);

            if ($this->fileLooksLikeThumbnail($sourceFilePathInfo['filename'])) {
                $originalFilename =
                    preg_replace(
                        StringHelper::buildPattern(self::PATTERN_THUMBNAIL_IDENTITY),
                        '',
                        $sourceFilePathInfo['filename']) . '.' . $sourceFilePathInfo['extension'];

                $possibleOriginalFilePath = $sourceFilePathInfo['dirname'] . DIRECTORY_SEPARATOR . $originalFilename;

                if (FileHelper::testFile($possibleOriginalFilePath)) {
                    $relativePathOfOriginalFile = str_replace(
                        $dir . DIRECTORY_SEPARATOR,
                        '',
                        $possibleOriginalFilePath
                    );

                    $attachmentId = $this->getAttachmentId($relativePathOfOriginalFile);

                    if (false !== $attachmentId) {
                        if ($this->core->getTranslationHelper()->isRelatedSubmissionCreationNeeded(
                            'attachment',
                            $submission->getSourceBlogId(),
                            $attachmentId,
                            $submission->getTargetBlogId())
                        ) {
                            $attachmentSubmission = $this->core->sendAttachmentForTranslation(
                                $submission->getSourceBlogId(),
                                $submission->getTargetBlogId(),
                                $attachmentId,
                                $submission->getJobInfoWithBatchUid(),
                                $submission->getIsCloned()
                            );

                            $targetUploadInfo = $this->core->getUploadFileInfo($submission->getTargetBlogId());

                            $fullTargetFileName = $targetUploadInfo['basedir'] . DIRECTORY_SEPARATOR .
                                $sourceFilePathInfo['filename'] . '.' . $sourceFilePathInfo['extension'];

                            if (copy($fullFileName, $fullTargetFileName) === false) {
                                $this->getLogger()->warning("Unknown error occurred while copying thumbnail from $fullFileName to $fullTargetFileName.");
                            }

                            $targetFileRelativePath = $this->core
                                ->getAttachmentRelativePathBySubmission($attachmentSubmission);

                            $targetThumbnailPathInfo = pathinfo($targetFileRelativePath);

                            $targetThumbnailRelativePath = $targetThumbnailPathInfo['dirname'] . '/' .
                                $sourceFilePathInfo['basename'];

                            return new ReplacementPair($path, $targetThumbnailRelativePath);
                        }

                        $this->getLogger()->debug("Skipping attachment id $attachmentId due to manual relations handling");
                        return null;
                    }

                    $this->getLogger()->warning("Referenced original file (absolute path): $possibleOriginalFilePath found by thumbnail (absolute path) : $fullFileName is not found in the media library. Skipping.");
                } else {
                    $this->getLogger()->warning("Original file: $possibleOriginalFilePath for the referenced thumbnail: $fullFileName not found. Skipping.");
                }
            } else {
                $this->getLogger()->warning("Referenced file: $fullFileName does not seems to be a thumbnail. Skipping.");
            }
        } else {
            $this->getLogger()->warning('Referenced file (absolute path) not found. Skipping.');
        }

        return null;
    }

    protected function fileLooksLikeThumbnail(string $path): bool
    {
        $pattern = StringHelper::buildPattern('.+' . self::PATTERN_THUMBNAIL_IDENTITY);

        return preg_match($pattern, $path) > 0;
    }

    private function isRelativeUrl(string $url): bool
    {
        $parts = parse_url($url);

        return $url === $parts['path'];
    }

    private function getAttachmentId(string $relativePath): ?int
    {
        return $this->returnId(vsprintf(
            "SELECT `post_id` as `id` FROM `%s` WHERE `meta_key` = '_wp_attached_file' AND `meta_value`='%s' LIMIT 1;",
            [
                RawDbQueryHelper::getTableName('postmeta'),
                $this->core->getFullyRelateAttachmentPath($this->getParams()->getSubmission(), $relativePath),
            ]
        ));
    }

    protected function returnId(string $query): ?int
    {
        $data = RawDbQueryHelper::query($query);

        $result = null;

        if (is_array($data) && 1 === count($data)) {
            $resultRow = ArrayHelper::first($data);

            if (is_array($resultRow) && array_key_exists('id', $resultRow)) {
                $result = (int)$resultRow['id'];
            }
        }

        return $result;
    }

    /**
     * Extracts attribute from html tag string
     */
    protected function getAttributeFromTag(string $tagString, string $tagName, string $attribute): ?string
    {
        $dom = new DOMDocument();
        $state = libxml_use_internal_errors(true);
        $dom->loadHTML($tagString);
        $errors = libxml_get_errors();
        libxml_use_internal_errors($state);
        if (0 < count($errors)) {
            foreach ($errors as $error) {
                if ($error instanceof libXMLError) {
                    /**
                     * @var  libXMLError $error
                     */
                    $level = '';
                    switch ($error->level) {
                        case LIBXML_ERR_NONE:
                            break;
                        case LIBXML_ERR_WARNING:
                            $level = 'WARNING';
                            break;
                        case LIBXML_ERR_ERROR:
                            $level = 'ERROR';
                            break;
                        case LIBXML_ERR_FATAL:
                            $level = 'FATAL ERROR';
                            break;
                        default:
                            $level = 'UNKNOWN:' . $error->level;
                    }
                    if ('' !== $level) {
                        $template = 'An \'%s\' raised with message: \'%s\' by XML (libxml) parser while parsing string \'%s\' line %s.';
                        $message = vsprintf($template, [
                            $level,
                            $error->message,
                            base64_encode($tagString),
                            $error->line,
                        ]);
                        $this->getLogger()->debug($message);
                    }
                }
            }
        }
        $images = $dom->getElementsByTagName($tagName);
        $value = null;
        if (1 === $images->length) {
            /** @var \DOMNode $node */
            $node = $images->item(0);
            if ($node->hasAttributes()) {
                $value = $node->attributes->getNamedItem($attribute);
                if ($value instanceof \DOMAttr) {
                    $value = $value->nodeValue;
                }
            }
        }

        return $value;
    }

    private function getReplacerForAcfGutenbergBlock(string $block): PairReplacerHelper
    {
        $result = new PairReplacerHelper();
        $submission = $this->getParams()->getSubmission();
        $sourceBlogId = $submission->getSourceBlogId();
        $targetBlogId = $submission->getTargetBlogId();

        $acfData = json_decode(stripslashes($block), true, 512, JSON_THROW_ON_ERROR);
        if (array_key_exists('data', $acfData)) {
            foreach ($acfData['data'] as $key => $value) {
                if (array_key_exists($value, $this->acfDefinitions)
                    && array_key_exists('type', $this->acfDefinitions[$value])
                    && $this->acfDefinitions[$value]['type'] === 'image'
                    && strpos($key, '_') === 0
                    && array_key_exists(substr($key, 1), $acfData['data'])) {
                    $attachmentId = $acfData['data'][substr($key, 1)];

                    if (!empty($attachmentId) && is_numeric($attachmentId)) {
                        if ($this->core->getTranslationHelper()->isRelatedSubmissionCreationNeeded('attachment', $sourceBlogId, (int)$attachmentId, $targetBlogId)) {
                            $attachment = $this->core->sendAttachmentForTranslation($sourceBlogId, $targetBlogId, (int)$attachmentId, $submission->getJobInfoWithBatchUid());
                            $result->addReplacementPair(new ReplacementPair((string)$attachmentId, (string)$attachment->getTargetId()));
                        } else {
                            $this->getLogger()->debug("Skipping attachment id $attachmentId due to manual relations handling");
                        }
                    } else {
                        $this->getLogger()->warning("Can not send attachment as it has empty id acfFieldId=$value acfFieldValue=\"$attachmentId\"");
                    }
                }
            }
        }
        return $result;
    }

    private function getReplacerForImgTag(string $tag, PairReplacerHelper $replacer): PairReplacerHelper
    {
        $result = $replacer;
        $path = $this->getSourcePathFromImgTag($tag);

        if (false !== $path && $this->isRelativeUrl($path)) {
            $attachmentId = $this->getAttachmentId($path);
            if (false !== $attachmentId) {
                $submission = $this->getParams()->getSubmission();
                $sourceBlogId = $submission->getSourceBlogId();
                $targetBlogId = $submission->getTargetBlogId();
                if ($this->core->getTranslationHelper()->isRelatedSubmissionCreationNeeded('attachment', $sourceBlogId, $attachmentId, $targetBlogId)) {
                    $attachmentSubmission = $this->core->sendAttachmentForTranslation($sourceBlogId, $targetBlogId, $attachmentId, $submission->getJobInfoWithBatchUid(), $submission->getIsCloned());
                    $result->addReplacementPair(new ReplacementPair($path, $this->core->getAttachmentRelativePathBySubmission($attachmentSubmission)));
                } else {
                    $this->getLogger()->debug("Skipping attachment id $attachmentId due to manual relations handling");
                }
            } else {
                $thumbnail = $this->tryProcessThumbnail($path);
                if ($thumbnail !== null) {
                    $result->addReplacementPair($thumbnail);
                }
            }
        }
        return $result;
    }
}
