<?php

namespace Smartling\Helpers;

use DOMDocument;
use Psr\Log\LoggerInterface;
use Smartling\Base\ExportedAPI;
use Smartling\Base\SmartlingCore;
use Smartling\Helpers\EventParameters\AfterDeserializeContentEventParameters;
use Smartling\MonologWrapper\MonologWrapper;
use Smartling\WP\WPHookInterface;

/**
 * Class RelativeLinkedAttachmentCoreHelper
 * @package inc\Smartling\Helpers
 */
class RelativeLinkedAttachmentCoreHelper implements WPHookInterface
{

    /**
     * RegEx to catch images from the string
     */
    const PATTERN_IMAGE_GENERAL = '<img[^>]+>';


    const PATTERN_THUMBNAIL_IDENTITY = '-\d+x\d+$';

    /**
     * @var LoggerInterface
     */
    private $logger = null;

    /**
     * @var SmartlingCore
     */
    private $ep = null;

    /**
     * @var AfterDeserializeContentEventParameters
     */
    private $params = null;

    /**
     * @return LoggerInterface
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * @param LoggerInterface $logger
     */
    private function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @return SmartlingCore
     */
    public function getCore()
    {
        return $this->ep;
    }

    /**
     * @param SmartlingCore $ep
     */
    private function setCore(SmartlingCore $ep)
    {
        $this->ep = $ep;
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
    private function setParams($params)
    {
        $this->params = $params;
    }


    public function __construct(SmartlingCore $ep)
    {
        $logger = MonologWrapper::getLogger(get_called_class());
        $this->setLogger($logger);
        $this->setCore($ep);
    }

    /**
     * @inheritdoc
     */
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

        foreach ($fields as $name => & $value) {
            $this->processString($value);
        }
    }

    /**
     * Recursively processes all found strings
     *
     * @param $stringValue
     */
    protected function processString(& $stringValue)
    {
        $replacer = new PairReplacerHelper();

        if (is_array($stringValue)) {
            foreach ($stringValue as $item => & $value) {
                $this->processString($value);
            }
        } else {
            $matches = [];
            if (0 < preg_match_all(StringHelper::buildPattern(self::PATTERN_IMAGE_GENERAL), $stringValue, $matches)) {
                foreach ($matches[0] as $match) {
                    $path = $this->getSourcePathFromImgTag($match);

                    if ((false !== $path) && ($this->testIfUrlIsRelative($path))) {
                        $attachmentId = $this->getAttachmentId($path);
                        if (false !== $attachmentId) {
                            $attachmentSubmission = $this->getCore()
                                ->sendAttachmentForTranslation(
                                    $this->getParams()
                                        ->getSubmission()
                                        ->getSourceBlogId(),
                                    $this->getParams()
                                        ->getSubmission()
                                        ->getTargetBlogId(),
                                    $attachmentId
                                );
                            $replacer->addReplacementPair(
                                $path,
                                $this->getCore()
                                    ->getAttachmentRelativePathBySubmission($attachmentSubmission)
                            );
                        } else {
                            $result = $this->tryProcessThumbnail($path);

                            if (false !== $result) {
                                $replacer->addReplacementPair(
                                    $result['from'],
                                    $result['to']
                                );
                            }
                        }
                    }
                }
            }
        }
        $stringValue = $replacer->processString($stringValue);
    }

    /**
     * Extracts src attribute from <img /> tag if possible, otherwise returns false.
     *
     * @param $imgTagString
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
        $sourceUploadInfo = $this->getCore()
            ->getUploadFileInfo($this->getParams()
                                    ->getSubmission()
                                    ->getSourceBlogId());

        $a = $this->getCore()
            ->getFullyRelateAttachmentPath($this->getParams()
                                               ->getSubmission(), $path);

        $fullFileName = $sourceUploadInfo['basedir'] . DIRECTORY_SEPARATOR . $a;

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
                        $sourceUploadInfo['basedir'] . DIRECTORY_SEPARATOR,
                        '',
                        $possibleOriginalFilePath
                    );

                    $attachmentId = $this->getAttachmentId($relativePathOfOriginalFile);

                    if (false !== $attachmentId) {
                        $attachmentSubmission = $this->getCore()
                            ->sendAttachmentForTranslation(
                                $this->getParams()
                                    ->getSubmission()
                                    ->getSourceBlogId(),
                                $this->getParams()
                                    ->getSubmission()
                                    ->getTargetBlogId(),
                                $attachmentId
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

                        $result = ['from' => $path, 'to' => $targetThumbnailRelativePath];

                        return $result;
                    } else {
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
                    }
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

        return preg_match($pattern, $path) > 0 ? true : false;
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
     * @return false|int
     */
    private function getAttachmentId($relativePath)
    {

        $a = $this->getCore()->getFullyRelateAttachmentPath($this->getParams()->getSubmission(), $relativePath);

        $query = vsprintf(
            'SELECT `post_id` as `id` FROM `%s` WHERE `meta_key` = \'_wp_attached_file\' AND `meta_value`=\'%s\' LIMIT 1;',
            [
                RawDbQueryHelper::getTableName('postmeta'),
                $a,
            ]
        );

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
        if (0 < count($errors))
        {
            foreach ($errors as $error) {
                if ($error instanceof libXMLError){
                    /**
                     * @var  libXMLError $error
                     */
                    $level = '';
                    switch ($error->level) {
                        case LIBXML_ERR_NONE:
                            continue;
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
                            $level = 'UNKNOWN:'.$error->level;
                    }

                    $template = 'An \'%s\' raised with message: \'%s\' by XML (libxml) parser while parsing string \'%s\' line %s.';
                    $message = vsprintf($template, [
                        $level,
                        $error->message,
                        base64_encode($tagString),
                        $error->line
                    ]);
                    $this->getLogger()->debug($message);
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