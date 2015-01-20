<?php

namespace Smartling\Submissions;
use Psr\Log\LoggerInterface;
use Smartling\Helpers\ContentTypeHelper;

/**
 * Class SubmissionEntity
 * @package Smartling\Submissions
 */
class SubmissionEntity {

    /**
     * @var LoggerInterface
     */
    private $logger = null;

    /**
     * @var ContentTypeHelper
     */
    private $helper = null;

    public function __construct(LoggerInterface $logger, ContentTypeHelper $ct_helper)
    {
        $this->logger = $logger;
        $this->helper = $ct_helper;
    }

    /**
     * Submission unique id
     * @var null|integer
     */
    private $id                     =   null;

    /**
     * Submission Entity title
     * @var string
     */
    private $sourceTitle            =   null;

    /**
     * Source content blog id
     * @var integer
     */
    private $sourceBlog             =   null;

    /**
     * Hash of source content to find out if it is changed
     * @var string
     */
    private $sourceContentHash      =   null;

    /**
     * ContentType as a constant from Smartling\Helpers\ContentTypeHelper
     * @var string
     */
    private $contentType            =   null;

    /**
     * unique identifier of source content
     * @var mixed
     */
    private $sourceGUID             =   null;

    /**
     * Smartling API content package unique identifier
     * @var string
     */
    private $fileUri                =   null;

    /**
     * Target locale
     * @var string
     */
    private $targetLocale           =   null;

    /**
     * Id of linked blog to place the translation on 'download'
     * @var integer
     */
    private $targetBlog             =   null;

    /**
     * unique identifier of target content
     * @var mixed
     */
    private $targetGUID             =   null;

    /**
     * Submitter identity
     * @var string
     */
    private $submitter              =   null;

    /**
     * Date and Time of submission
     * @var string
     */
    private $submissionDate         =   null;

    /**
     * Count of words in source content
     * @var integer
     */
    private $sourceWordsCount       =   null;

    /**
     * Count of translated words
     * @var integer
     */
    private $sourceWordsTranslated  =   null;

    /**
     * @return int|null
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     */
    public function setId($id)
    {
        $this->id = (int) $id;
    }

    /**
     * @return string
     */
    public function getSourceTitle()
    {
        return $this->sourceTitle;
    }

    /**
     * @param string $sourceTitle
     */
    public function setSourceTitle($sourceTitle)
    {
        $this->sourceTitle = $sourceTitle;
    }

    /**
     * @return int
     */
    public function getSourceBlog()
    {
        return (int) $this->sourceBlog;
    }

    /**
     * @param int $sourceBlog
     */
    public function setSourceBlog($sourceBlog)
    {
        $this->sourceBlog = (int) $sourceBlog;
    }

    /**
     * @return string
     */
    public function getSourceContentHash()
    {
        return $this->sourceContentHash;
    }

    /**
     * @param string $sourceContentHash
     */
    public function setSourceContentHash($sourceContentHash)
    {
        $this->sourceContentHash = $sourceContentHash;
    }

    /**
     * @return string
     */
    public function getContentType()
    {
        return $this->contentType;
    }

    /**
     * @param string $contentType
     */
    public function setContentType($contentType)
    {
        $reverseMap = $this->helper->getReverseMap();

        if (in_array($contentType, array_keys($reverseMap))) {
            $this->contentType = $reverseMap[$contentType];
        } else {
            $message = vsprintf("Invalid content type. Got '%s', expected one of: %s", array($contentType, implode(',', $reverseMap)));

            $this->logger->error($message);

            throw new \InvalidArgumentException($message);
        }

    }

    /**
     * @return mixed
     */
    public function getSourceGUID()
    {
        return $this->sourceGUID;
    }

    /**
     * @param mixed $sourceGUID
     */
    public function setSourceGUID($sourceGUID)
    {
        $this->sourceGUID = $sourceGUID;
    }

    /**
     * @return string
     */
    public function getFileUri()
    {
        return $this->fileUri;
    }

    /**
     * @param string $fileUri
     */
    public function setFileUri($fileUri)
    {
        $this->fileUri = $fileUri;
    }

    /**
     * @return string
     */
    public function getTargetLocale()
    {
        return $this->targetLocale;
    }

    /**
     * @param string $targetLocale
     */
    public function setTargetLocale($targetLocale)
    {
        $this->targetLocale = $targetLocale;
    }

    /**
     * @return int
     */
    public function getTargetBlog()
    {
        return (int) $this->targetBlog;
    }

    /**
     * @param int $targetBlog
     */
    public function setTargetBlog($targetBlog)
    {
        $this->targetBlog = (int) $targetBlog;
    }

    /**
     * @return mixed
     */
    public function getTargetGUID()
    {
        return $this->targetGUID;
    }

    /**
     * @param mixed $targetGUID
     */
    public function setTargetGUID($targetGUID)
    {
        $this->targetGUID = $targetGUID;
    }

    /**
     * @return string
     */
    public function getSubmitter()
    {
        return $this->submitter;
    }

    /**
     * @param string $submitter
     */
    public function setSubmitter($submitter)
    {
        $this->submitter = $submitter;
    }

    /**
     * @return string
     */
    public function getSubmissionDate()
    {
        return $this->submissionDate;
    }

    /**
     * @param string $submissionDate
     */
    public function setSubmissionDate($submissionDate)
    {
        $this->submissionDate = $submissionDate;
    }

    /**
     * @return int
     */
    public function getSourceWordsCount()
    {
        return (int) $this->sourceWordsCount;
    }

    /**
     * @param int $sourceWordsCount
     */
    public function setSourceWordsCount($sourceWordsCount)
    {
        $this->sourceWordsCount = (int) $sourceWordsCount;
    }

    /**
     * @return int
     */
    public function getSourceWordsTranslated()
    {
        return (int) $this->sourceWordsTranslated;
    }

    /**
     * @param int $sourceWordsTranslated
     */
    public function setSourceWordsTranslated($sourceWordsTranslated)
    {
        $this->sourceWordsTranslated = (int) $sourceWordsTranslated;
    }

    public function getCompletionPercentage()
    {
        $percentage = 0;

        if (0 != $this->getSourceWordsCount()) {
            $percentage = $this->getSourceWordsTranslated() / $this->getSourceWordsCount();
        }

        if ($percentage > 1) {
            $percentage = 1;
        }

        return (int) $percentage * 100;
    }





}