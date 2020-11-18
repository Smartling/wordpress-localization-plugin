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
    const ACF_GUTENBERG_BLOCK = '<!-- wp:acf/.+ ({.+}) /-->';
    /**
     * RegEx to catch images from the string
     */
    const PATTERN_IMAGE_GENERAL = '<img[^>]+>';

    const PATTERN_THUMBNAIL_IDENTITY = '-\d+x\d+$';

    private $acfDefinitions = [];
    private $acfDynamicSupport;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var SmartlingCore
     */
    private $core;

    /**
     * @var AfterDeserializeContentEventParameters
     */
    private $params;

    /**
     * @return LoggerInterface
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * @return SmartlingCore
     */
    public function getCore()
    {
        return $this->core;
    }

    /**
     * @return AfterDeserializeContentEventParameters
     */
    public function getParams()
    {
        return $this->params;
    }

    /**
     * @param AfterDeserializeContentEventParameters $params
     */
    private function setParams(AfterDeserializeContentEventParameters $params)
    {
        $this->params = $params;
    }

    public function __construct(SmartlingCore $core, AcfDynamicSupport $acfDynamicSupport)
    {
        $this->acfDynamicSupport = $acfDynamicSupport;
        $this->core = $core;
        $this->logger = MonologWrapper::getLogger(static::class);
    }

    public function register()
    {
        add_action(ExportedAPI::EVENT_SMARTLING_AFTER_DESERIALIZE_CONTENT, [$this, 'processor']);
    }

    /**
     * ExportedAPI::EVENT_SMARTLING_AFTER_DESERIALIZE_CONTENT event handler
     *
     * @param AfterDeserializeContentEventParameters $params
     */
    public function processor(AfterDeserializeContentEventParameters $params)
    {
        $this->setParams($params);
        if (count($this->acfDefinitions) === 0) {
            $this->acfDynamicSupport->run();
            $this->acfDefinitions = $this->acfDynamicSupport->getDefinitions();
        }

        $fields = &$params->getTranslatedFields();

        foreach ($fields as $name => &$value) {
            $this->processString($value);
        }
    }

    /**
     * Recursively processes all found strings
     *
     * @param string|array $stringValue
     */
    protected function processString(&$stringValue)
    {
        $replacer = new PairReplacerHelper();
        $matches = [];
        if (is_array($stringValue)) {
            foreach ($stringValue as $item => &$value) {
                $this->processString($value);
            }
            unset($value);
        } elseif (0 < preg_match_all(self::ACF_GUTENBERG_BLOCK, $stringValue, $matches)) {
            foreach ($matches[1] as $match) {
                $replacer = $this->processAcfGutenbergBlock($match, $replacer);
            }
        } elseif (0 < preg_match_all(StringHelper::buildPattern(self::PATTERN_IMAGE_GENERAL), $stringValue, $matches)) {
            foreach ($matches[0] as $match) {
                $replacer = $this->processImgTag($match, $replacer);
            }
        }
        $stringValue = $replacer->processString($stringValue);
    }

    /**
     * Extracts src attribute from <img /> tag if possible, otherwise returns false.
     *
     * @param string $imgTagString
     *
     * @return bool
     */
    private function getSourcePathFromImgTag($imgTagString)
    {
        return $this->getAttributeFromTag($imgTagString, 'img', 'src');
    }

    /**
     * @param string $path
     * @return array|null
     */
    private function tryProcessThumbnail($path)
    {
        $submission = $this->getParams()->getSubmission();
        $dir = $this->getCore()->getUploadFileInfo($submission->getSourceBlogId())['basedir'];

        $fullFileName = $dir . DIRECTORY_SEPARATOR . $this->getCore()->getFullyRelateAttachmentPath($submission, $path);

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
                        if ($this->getCore()->getTranslationHelper()->isRelatedSubmissionCreationNeeded(
                            'attachment',
                            $submission->getSourceBlogId(),
                            $attachmentId,
                            $submission->getTargetBlogId())
                        ) {
                            $attachmentSubmission = $this->getCore()->sendAttachmentForTranslation(
                                $submission->getSourceBlogId(),
                                $submission->getTargetBlogId(),
                                $attachmentId,
                                $submission->getBatchUid(),
                                $submission->getIsCloned()
                            );

                            $targetUploadInfo = $this->getCore()->getUploadFileInfo($submission->getTargetBlogId());

                            $fullTargetFileName = $targetUploadInfo['basedir'] . DIRECTORY_SEPARATOR .
                                $sourceFilePathInfo['filename'] . '.' . $sourceFilePathInfo['extension'];

                            if (copy($fullFileName, $fullTargetFileName) === false) {
                                $this->getLogger()->warning("Unknown error occurred while copying thumbnail from $fullFileName to $fullTargetFileName.");
                            }

                            $targetFileRelativePath = $this->getCore()
                                ->getAttachmentRelativePathBySubmission($attachmentSubmission);

                            $targetThumbnailPathInfo = pathinfo($targetFileRelativePath);

                            $targetThumbnailRelativePath = $targetThumbnailPathInfo['dirname'] . '/' .
                                $sourceFilePathInfo['basename'];

                            return ['from' => $path, 'to' => $targetThumbnailRelativePath];
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

    /**
     * @param string $path
     *
     * @return bool
     */
    protected function fileLooksLikeThumbnail($path)
    {
        $pattern = StringHelper::buildPattern('.+' . self::PATTERN_THUMBNAIL_IDENTITY);

        return preg_match($pattern, $path) > 0;
    }

    /**
     * Checks if given URL is relative
     *
     * @param string $url
     *
     * @return bool
     */
    private function isRelativeUrl($url)
    {
        $parts = parse_url($url);

        return $url === $parts['path'];
    }

    /**
     * @param $relativePath
     *
     * @return bool|int
     */
    private function getAttachmentId($relativePath)
    {
        return $this->returnId(vsprintf(
            "SELECT `post_id` as `id` FROM `%s` WHERE `meta_key` = '_wp_attached_file' AND `meta_value`='%s' LIMIT 1;",
            [
                RawDbQueryHelper::getTableName('postmeta'),
                $this->getCore()->getFullyRelateAttachmentPath($this->getParams()->getSubmission(), $relativePath),
            ]
        ));
    }

    /**
     * @param string $query
     * @return bool|int
     */
    protected function returnId($query)
    {
        $data = RawDbQueryHelper::query($query);

        $result = false;

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
     *
     * @param string $tagString
     * @param string $tagName
     * @param string $attribute
     *
     * @return false|string
     */
    protected function getAttributeFromTag($tagString, $tagName, $attribute)
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
        $value = false;
        if (1 === $images->length) {
            /** @var \DOMNode $node */
            $node = $images->item(0);
            if ($node->hasAttributes() && $value = $node->attributes->getNamedItem($attribute)) {
                if ($value instanceof \DOMAttr) {
                    $value = $value->nodeValue;
                }
            }
        }

        return $value;
    }

    /**
     * @param string $block
     * @param PairReplacerHelper $replacer
     * @return PairReplacerHelper
     */
    private function processAcfGutenbergBlock($block, PairReplacerHelper $replacer)
    {
        $result = $replacer;
        $submission = $this->getParams()->getSubmission();
        $sourceBlogId = $submission->getSourceBlogId();
        $targetBlogId = $submission->getTargetBlogId();

        $acfData = json_decode(stripslashes($block), true);
        if (array_key_exists('data', $acfData)) {
            foreach ($acfData['data'] as $key => $value) {
                if (array_key_exists($value, $this->acfDefinitions)
                    && array_key_exists('type', $this->acfDefinitions[$value])
                    && $this->acfDefinitions[$value]['type'] === 'image'
                    && strpos($key, '_') === 0
                    && array_key_exists(substr($key, 1), $acfData['data'])) {
                    $attachmentId = $acfData['data'][substr($key, 1)];

                    if (!empty($attachmentId) && is_numeric($attachmentId)) {
                        if ($this->getCore()->getTranslationHelper()->isRelatedSubmissionCreationNeeded('attachment', $sourceBlogId, (int)$attachmentId, $targetBlogId)) {
                            $attachment = $this->getCore()->sendAttachmentForTranslation($sourceBlogId, $targetBlogId, (int)$attachmentId, $submission->getBatchUid());
                            $result->addReplacementPair($attachmentId, $attachment->getTargetId());
                        } else {
                            $this->getLogger()->debug("Skipping attachment id $attachmentId due to manual relations handling");
                        }
                    } else {
                        $this->getLogger()->warning("Can not send attachment as it has empty id acfFieldId=${value} acfFieldValue=\"${attachmentId}\"");
                    }
                }
            }
        }
        return $result;
    }

    /**
     * @param string $tag
     * @param PairReplacerHelper $replacer
     * @return PairReplacerHelper
     */
    private function processImgTag($tag, PairReplacerHelper $replacer)
    {
        $result = $replacer;
        $path = $this->getSourcePathFromImgTag($tag);

        if (false !== $path && $this->isRelativeUrl($path)) {
            $attachmentId = $this->getAttachmentId($path);
            if (false !== $attachmentId) {
                $submission = $this->getParams()->getSubmission();
                $sourceBlogId = $submission->getSourceBlogId();
                $targetBlogId = $submission->getTargetBlogId();
                if ($this->getCore()->getTranslationHelper()->isRelatedSubmissionCreationNeeded('attachment', $sourceBlogId, $attachmentId, $targetBlogId)) {
                    $attachmentSubmission = $this->getCore()->sendAttachmentForTranslation($sourceBlogId, $targetBlogId, $attachmentId, $submission->getBatchUid(), $submission->getIsCloned());
                    $result->addReplacementPair($path, $this->getCore()->getAttachmentRelativePathBySubmission($attachmentSubmission));
                } else {
                    $this->getLogger()->debug("Skipping attachment id $attachmentId due to manual relations handling");
                }
            } else {
                $thumbnail = $this->tryProcessThumbnail($path);

                if ($thumbnail !== null) {
                    $result->addReplacementPair($thumbnail['from'], $thumbnail['to']);
                }
            }
        }
        return $result;
    }
}
