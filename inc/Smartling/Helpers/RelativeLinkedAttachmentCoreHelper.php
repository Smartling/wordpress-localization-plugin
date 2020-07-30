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

/**
 * Class RelativeLinkedAttachmentCoreHelper
 * @package inc\Smartling\Helpers
 */
class RelativeLinkedAttachmentCoreHelper implements WPHookInterface
{
    const ACF_GUTENBERG_BLOCK = '<!-- wp:acf/.+ ({.+}) /-->';
    /**
     * RegEx to catch images from the string
     */
    const PATTERN_IMAGE_GENERAL = '<img[^>]+>';

    const PATTERN_THUMBNAIL_IDENTITY = '-\d+x\d+$';

    private $acfDefinitions;
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
    private $replacer;

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

    public function __construct(SmartlingCore $core, EntityHelper $entityHelper)
    {
        $this->acfDefinitions = (new AcfDynamicSupport($entityHelper))->collectDefinitions();
        $this->core = $core;
        $this->logger = MonologWrapper::getLogger(static::class);
        $this->replacer = new PairReplacerHelper();
    }

    public function register()
    {
        add_action(ExportedAPI::EVENT_SMARTLING_AFTER_DESERIALIZE_CONTENT, [$this, 'processor']);
    }

    /**
     * A XmlEncoder::EVENT_SMARTLING_AFTER_DESERIALIZE_CONTENT event handler
     *
     * @param AfterDeserializeContentEventParameters $params
     */
    public function processor(AfterDeserializeContentEventParameters $params)
    {
        $this->setParams($params);

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
        $matches = [];
        if (is_array($stringValue)) {
            foreach ($stringValue as $item => &$value) {
                $this->processString($value);
            }
            unset($value);
        } elseif (0 < preg_match_all(self::ACF_GUTENBERG_BLOCK, $stringValue, $matches)) {
            $acfData = json_decode(stripslashes($matches[1][0]), true);
            if (array_key_exists('data', $acfData)) {
                foreach ($acfData['data'] as $key => $value) {
                    if (array_key_exists($value, $this->acfDefinitions)
                        && $this->acfDefinitions[$value]['type'] === 'image') {
                        $attachmentId = $acfData['data'][substr($key, 1)];
                        $attachment = $this->getCore()->sendAttachmentForTranslation(
                            $this->getParams()->getSubmission()->getSourceBlogId(),
                            $this->getParams()->getSubmission()->getTargetBlogId(),
	                        (int)$attachmentId,
                            $this->getParams()->getSubmission()->getBatchUid()
                        );
                        $this->replacer->addReplacementPair($attachmentId, $attachment->getTargetId());
                    }
                }
            }
        } elseif (0 < preg_match_all(StringHelper::buildPattern(self::PATTERN_IMAGE_GENERAL), $stringValue, $matches)) {
            foreach ($matches[0] as $match) {
                $path = $this->getSourcePathFromImgTag($match);

                if ((false !== $path) && ($this->testIfUrlIsRelative($path))) {
                    $attachmentId = $this->getAttachmentId($path);
                    if (false !== $attachmentId) {
                        $attachmentSubmission = $this->getCore()->sendAttachmentForTranslation(
                            $this->getParams()->getSubmission()->getSourceBlogId(),
                            $this->getParams()->getSubmission()->getTargetBlogId(),
                            $attachmentId,
                            $this->getParams()->getSubmission()->getBatchUid(),
                            $this->getParams()->getSubmission()->getIsCloned()
                        );
                        $this->replacer->addReplacementPair(
                            $path,
                            $this->getCore()->getAttachmentRelativePathBySubmission($attachmentSubmission)
                        );
                    } else {
                        $result = $this->tryProcessThumbnail($path);

                        if (false !== $result) {
                            $this->replacer->addReplacementPair($result['from'], $result['to']);
                        }
                    }
                }
            }
        }
        $stringValue = $this->replacer->processString($stringValue);
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
     * @param $path
     *
     * @return false|array
     */
    private function tryProcessThumbnail($path)
    {
        $dir = $this->getCore()->getUploadFileInfo($this->getParams()->getSubmission()->getSourceBlogId())['basedir'];

        $fullFileName = $dir . DIRECTORY_SEPARATOR .
            $this->getCore()->getFullyRelateAttachmentPath($this->getParams()->getSubmission(), $path);

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
                        $attachmentSubmission = $this->getCore()->sendAttachmentForTranslation(
                                $this->getParams()->getSubmission()->getSourceBlogId(),
                                $this->getParams()->getSubmission()->getTargetBlogId(),
                                $attachmentId,
                                $this->getParams()->getSubmission()->getBatchUid(),
                                $this->getParams()->getSubmission()->getIsCloned()
                            );

                        $targetUploadInfo = $this->getCore()
                            ->getUploadFileInfo($this->getParams()
                                ->getSubmission()
                                ->getTargetBlogId());

                        $fullTargetFileName = $targetUploadInfo['basedir'] . DIRECTORY_SEPARATOR .
                            $sourceFilePathInfo['filename'] . '.' . $sourceFilePathInfo['extension'];

                        $copyResult = copy($fullFileName, $fullTargetFileName);

                        if (false === $copyResult) {
                            $this->getLogger()
                                ->warning(
                                    vsprintf(
                                        'Unknown error occurred while copying thumbnail from %s to %s.',
                                        [
                                            $fullFileName,
                                            $fullTargetFileName,
                                        ]
                                    )
                                );
                        }

                        $targetFileRelativePath = $this->getCore()
                            ->getAttachmentRelativePathBySubmission($attachmentSubmission);

                        $targetThumbnailPathInfo = pathinfo($targetFileRelativePath);

                        $targetThumbnailRelativePath = $targetThumbnailPathInfo['dirname'] . '/' .
                            $sourceFilePathInfo['basename'];

                        return ['from' => $path, 'to' => $targetThumbnailRelativePath];
                    }

                    $this->getLogger()
                        ->warning(
                            vsprintf(
                                'Referenced original file (absolute path): %s found by thumbnail (absolute path) : %s is not found in the media library. Skipping.',
                                [
                                    $possibleOriginalFilePath,
                                    $fullFileName,
                                ]
                            )
                        );
                } else {
                    $this->getLogger()
                        ->warning(
                            vsprintf(
                                'Original file: %s for the referenced thumbnail: %s not found. Skipping.',
                                [
                                    $possibleOriginalFilePath,
                                    $fullFileName,
                                ]
                            )
                        );
                }
            } else {
                $this->getLogger()
                    ->warning(
                        vsprintf(
                            'Referenced file: %s  does not seems to be a thumbnail. Skipping.',
                            [
                                $fullFileName,
                            ]
                        )
                    );
            }


        } else {
            $this->getLogger()
                ->warning(
                    vsprintf(
                        'Referenced file (absolute path) not found. Skipping.',
                        [
                            $fullFileName,
                        ]
                    )
                );
        }

        return false;
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
    private function testIfUrlIsRelative($url)
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
    protected function returnId($query) {
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
                if (($value instanceof \DOMAttr)) {
                    $value = $value->nodeValue;
                }
            }
        }

        return $value;
    }

}