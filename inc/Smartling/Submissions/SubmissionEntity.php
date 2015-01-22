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

    public static $fieldsDefinition = array(
        'id'                    => 'INT UNSIGNED NOT NULL AUTO_INCREMENT',
        'sourceTitle'           => 'VARCHAR(255) NOT NULL',
        'sourceBlog'            => 'INT UNSIGNED NOT NULL',
        'sourceContentHash'     => 'CHAR(32) NULL',
        'contentType'           => 'VARCHAR(32) NOT NULL',
        'sourceGUID'            => 'VARCHAR(255) NOT NULL',
        'fileUri'               => 'VARCHAR(255) NOT NULL',
        'targetLocale'          => 'VARCHAR(16) NOT NULL',
        'targetBlog'            => 'INT UNSIGNED NOT NULL',
        'targetGUID'            => 'VARCHAR(255) NOT NULL',
        'submitter'             => 'VARCHAR(255) NOT NULL',
        'submissionDate'        => 'INT UNSIGNED NOT NULL',
        'sourceWordsCount'      => 'INT UNSIGNED NOT NULL',
        'sourceWordsTranslated' => 'INT UNSIGNED NOT NULL',
        'status'                => 'VARCHAR(16) NOT NULL',
    );

    public static $fieldsLabels = array(
        'id'                    => 'ID',
        'sourceTitle'           => 'Title',
        'sourceBlog'            => 'Source Blog ID',
        'sourceContentHash'     => 'Content hash',
        'contentType'           => 'Type',
        'sourceGUID'            => 'Source URI',
        'fileUri'               => 'Smartling File URI',
        'targetLocale'          => 'Locale',
        'targetBlog'            => 'Target Blog ID',
        'targetGUID'            => 'Target URI',
        'submitter'             => 'Submitter',
        'submissionDate'        => 'Submitted',
        'sourceWordsCount'      => 'Words',
        'sourceWordsTranslated' => 'Words translated',
        'status'                => 'Status',
    );

    public static $fieldsSortable = array(
        'id',
        'sourceTitle',
        'sourceBlog',
        'contentType',
        'sourceGUID',
        'fileUri',
        'targetLocale',
        'targetBlog',
        'targetGUID',
        'submitter',
        'submissionDate',
        'sourceWordsCount',
        'sourceWordsTranslated',
        'status',
    );

    public static $indexes = array(
        array(
            'type'      =>  'primary',
            'columns'   =>  array('id')
        ),
        array(
            'type'      =>  'index',
            'columns'   =>  array('contentType')
        ),
    );

    /**
     * Magic wrapper for fields
     * may be used as virtual setter, e.g.:
     *      $object->contentType = $value
     * instead of
     *      $object->setContentType($value)
     *
     * @param string $key
     * @param mixed $value
     */
    public function __set($key, $value)
    {
        if (in_array($key, array_keys(self::$fieldsDefinition))) {

            $setter = 'set' . ucfirst($key);

            $this->$setter($value);
        }
    }

    /**
     * Magic wrapper for fields
     * may be used as virtual setter, e.g.:
     *      $value = $object->contentType
     * instead of
     *      $value = $object->getContentType()
     *
     * @param string $key
     */
    public function __get($key)
    {
        if (in_array($key, array_keys(self::$fieldsDefinition))) {

            $getter = 'get' . ucfirst($key);

            return $this->$getter();
        }
    }

    /**
     * Converts associative array to SubmissionEntity
     * array keys must match field names;
     * @param array $array
     * @param LoggerInterface $logger
     * @param ContentTypeHelper $ct_helper
     * @return SubmissionEntity
     */
    public static function fromArray(array $array, LoggerInterface $logger, ContentTypeHelper $ct_helper)
    {
        $obj = new self($logger, $ct_helper);

        foreach($array as $field => $value){
            $obj->$field = $value;
        }

        return $obj;
    }

    public function toArray()
    {
        $arr = array();


        foreach(array_keys(self::$fieldsDefinition) as $field) {
            $arr[$field] = $this->$field;
        }

        return $arr;
    }

    /**
     * Constructor
     * @param LoggerInterface $logger
     * @param ContentTypeHelper $ct_helper
     */
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
     * @var string
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
     * @var string
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
     * @var string
     */
    private $status = null;

    /**
     * @return string
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @param string $status
     */
    public function setStatus($status)
    {
        $this->status = $status;
    }


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
     * @return string
     */
    public function getSourceGUID()
    {
        return $this->sourceGUID;
    }

    /**
     * @param string $sourceGUID
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
     * @return string
     */
    public function getTargetGUID()
    {
        return $this->targetGUID;
    }

    /**
     * @param string $targetGUID
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