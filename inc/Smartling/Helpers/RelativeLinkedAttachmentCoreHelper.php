<?php

namespace Smartling\Helpers;

use DOMDocument;
use LibXMLError;
use Smartling\Base\ExportedAPI;
use Smartling\Base\SmartlingCore;
use Smartling\ContentTypes\ContentTypeHelper;
use Smartling\Extensions\Acf\AcfDynamicSupport;
use Smartling\Helpers\EventParameters\AfterDeserializeContentEventParameters;
use Smartling\Submissions\SubmissionEntity;
use Smartling\Submissions\SubmissionManager;
use Smartling\WP\WPHookInterface;

class RelativeLinkedAttachmentCoreHelper implements WPHookInterface
{
    use LoggerSafeTrait;

    private const string ACF_GUTENBERG_BLOCK = '<!-- wp:acf/.+ ({.+}) /-->';
    /**
     * RegEx to catch images from the string
     */
    protected const string PATTERN_IMAGE_GENERAL = '<img[^>]+>';

    private const PATTERN_LINK_GENERAL = '<a[^>]+>';

    protected const string PATTERN_THUMBNAIL_IDENTITY = '-\d+x\d+$';

    private array $acfDefinitions = [];
    private AfterDeserializeContentEventParameters $params;

    public function getParams(): AfterDeserializeContentEventParameters
    {
        return $this->params;
    }

    private function setParams(AfterDeserializeContentEventParameters $params): void
    {
        $this->params = $params;
    }

    public function __construct(
        protected SmartlingCore $core,
        private AcfDynamicSupport $acfDynamicSupport,
        protected SubmissionManager $submissionManager,
        protected WordpressFunctionProxyHelper $wordpressProxy,
        protected WordpressLinkHelper $wordpressLinkHelper,
    ) {
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

    protected function processString(string|array &$stringValue): void
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

        if (0 < preg_match_all(StringHelper::buildPattern(self::PATTERN_LINK_GENERAL), $stringValue, $matches)) {
            foreach ($matches[0] as $match) {
                $stringValue = $this->getReplacerForAnchorTag($match, $replacer)->processString($stringValue);
            }
        }
    }

    /**
     * Extracts src attribute from <img /> tag if possible, otherwise returns null.
     */

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

                    if (null !== $attachmentId) {
                        $attachmentSubmission = $this->submissionManager->findOne([
                            SubmissionEntity::FIELD_CONTENT_TYPE => ContentTypeHelper::POST_TYPE_ATTACHMENT,
                            SubmissionEntity::FIELD_SOURCE_BLOG_ID => $submission->getSourceBlogId(),
                            SubmissionEntity::FIELD_SOURCE_ID => $attachmentId,
                            SubmissionEntity::FIELD_TARGET_BLOG_ID => $submission->getTargetBlogId(),
                        ]);
                        if ($attachmentSubmission !== null) {
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

                        $this->getLogger()->debug("Skipping replacing id attachmentId=$attachmentId: no target submissions found");
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
        return (parse_url($url)['host'] ?? '') === '';
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

        try {
            $acfData = json_decode(stripslashes($block), true, 512, JSON_THROW_ON_ERROR);
            if (array_key_exists('data', $acfData)) {
                foreach ($acfData['data'] as $key => $value) {
                    if ((is_string($value) || is_int($value)) && array_key_exists($value, $this->acfDefinitions)
                        && array_key_exists('type', $this->acfDefinitions[$value])
                        && $this->acfDefinitions[$value]['type'] === 'image'
                        && str_starts_with($key, '_')
                        && array_key_exists(substr($key, 1), $acfData['data'])) {
                        $this->getLogger()->debug("Detected ACF image, key=$key");
                        $attachmentId = $acfData['data'][substr($key, 1)];

                        if (!empty($attachmentId) && is_numeric($attachmentId)) {
                            $attachment = $this->submissionManager->findOne([
                                SubmissionEntity::FIELD_CONTENT_TYPE => ContentTypeHelper::POST_TYPE_ATTACHMENT,
                                SubmissionEntity::FIELD_SOURCE_BLOG_ID => $sourceBlogId,
                                SubmissionEntity::FIELD_SOURCE_ID => (int)$attachmentId,
                                SubmissionEntity::FIELD_TARGET_BLOG_ID => $targetBlogId,
                            ]);
                            if ($attachment !== null) {
                                $result->addReplacementPair(new ReplacementPair((string)$attachmentId, (string)$attachment->getTargetId()));
                            } else {
                                $this->getLogger()->debug("Skipping replacing id attachmentId=$attachmentId, key=$key: no target submissions found");
                            }
                        } else {
                            $this->getLogger()->debug("Skipping replacing id, attachmentId=$attachmentId, key=$key: attachment id non numeric");
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            $this->getLogger()->debug("Failed to decode block $block, skipping id replacements: " . $e->getMessage());
        }
        return $result;
    }

    private function getReplacerForImgTag(string $tag, PairReplacerHelper $replacer): PairReplacerHelper
    {
        $result = $replacer;
        $path = $this->getAttributeFromTag($tag, 'img', 'src');

        if (null !== $path && $this->isRelativeUrl($path)) {
            $attachmentId = $this->getAttachmentId($path);
            if (null !== $attachmentId) {
                $submission = $this->getParams()->getSubmission();
                $sourceBlogId = $submission->getSourceBlogId();
                $targetBlogId = $submission->getTargetBlogId();
                $attachmentSubmission = $this->submissionManager->findOne([
                    SubmissionEntity::FIELD_CONTENT_TYPE => ContentTypeHelper::POST_TYPE_ATTACHMENT,
                    SubmissionEntity::FIELD_SOURCE_BLOG_ID => $sourceBlogId,
                    SubmissionEntity::FIELD_SOURCE_ID => $attachmentId,
                    SubmissionEntity::FIELD_TARGET_BLOG_ID => $targetBlogId,
                ]);
                if ($attachmentSubmission !== null) {
                    $result->addReplacementPair(new ReplacementPair($path, $this->core->getAttachmentRelativePathBySubmission($attachmentSubmission)));
                } else {
                    $this->getLogger()->debug("Skipping replacing id attachmentId=$attachmentId: no target submissions found");
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

    private function getReplacerForAnchorTag(string $tag, PairReplacerHelper $replacer): PairReplacerHelper
    {
        $result = $replacer;
        $href = $this->getAttributeFromTag($tag, 'a', 'href');

        if (null !== $href && $this->isRelativeUrl($href)) {
            $targetBlogId = $this->getParams()->getSubmission()->getTargetBlogId();
            $url = $this->wordpressLinkHelper->getTargetBlogLink($href, $targetBlogId);
            if ($url !== null) {
                $url = parse_url($url);
                if (is_array($url)) {
                    $result->addReplacementPair(new ReplacementPair($href, ($url['path'] ?? '') . ($url['query'] ?? '')));
                }
            }
        }

        return $result;
    }
}
