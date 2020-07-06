<?php

namespace Smartling\Submissions;

use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Smartling\Base\ExportedAPI;
use Smartling\Base\SmartlingEntityAbstract;
use Smartling\Helpers\EventParameters\SmartlingFileUriFilterParamater;
use Smartling\Helpers\FileUriHelper;
use Smartling\Helpers\StringHelper;
use Smartling\Helpers\TextHelper;
use Smartling\Helpers\WordpressContentTypeHelper;
use Smartling\Helpers\WordpressUserHelper;

/**
 * Class SubmissionEntity
 * @property int         $id
 * @property string      $source_title
 * @property int         $source_blog_id
 * @property string|null $source_content_hash
 * @property string      $content_type
 * @property int         $source_id
 * @property string      $file_uri
 * @property string      $target_locale
 * @property int         $target_blog_id
 * @property int         $target_id
 * @property string      $submitter
 * @property string      $submission_date
 * @property string      $applied_date
 * @property int         $approved_string_count
 * @property int         $completed_string_count
 * @property int         $excluded_string_count
 * @property int         $total_string_count
 * @property string      $status
 * @property int         $is_locked
 * @property \DateTime   $last_modified
 * @property int         $outdated
 * @package Smartling\Submissions
 */
class SubmissionEntity extends SmartlingEntityAbstract
{

    const FLAG_CONTENT_IS_OUT_OF_DATE = 1;

    const FLAG_CONTENT_IS_UP_TO_DATE = 0;

    const DATETIME_FORMAT = 'Y-m-d H:i:s';

    /**
     * Submission Status  'New'
     */
    const SUBMISSION_STATUS_NEW = 'New';

    /**
     * Submission Status  'In Progress'
     */
    const SUBMISSION_STATUS_IN_PROGRESS = 'In Progress';

    /**
     * Submission Status  'Completed'
     */
    const SUBMISSION_STATUS_COMPLETED = 'Completed';

    /**
     * Submission Status  'Failed'
     */
    const SUBMISSION_STATUS_FAILED = 'Failed';

    /**
     * Submission Status 'Cancelled'
     */
    const SUBMISSION_STATUS_CANCELLED = 'Cancelled';

    /**
     * @var array Submission Statuses
     */
    public static $submissionStatuses = [
        self::SUBMISSION_STATUS_NEW,
        self::SUBMISSION_STATUS_IN_PROGRESS,
        self::SUBMISSION_STATUS_COMPLETED,
        self::SUBMISSION_STATUS_FAILED,
        self::SUBMISSION_STATUS_CANCELLED,
    ];

    const FIELD_ID = 'id';
    const FIELD_SOURCE_TITLE = 'source_title';
    const FIELD_SOURCE_BLOG_ID = 'source_blog_id';
    const FIELD_SOURCE_CONTENT_HASH = 'source_content_hash';
    const FIELD_CONTENT_TYPE = 'content_type';
    const FIELD_SOURCE_ID = 'source_id';
    const FIELD_FILE_URI = 'file_uri';
    const FIELD_TARGET_LOCALE = 'target_locale';
    const FIELD_TARGET_BLOG_ID = 'target_blog_id';
    const FIELD_TARGET_ID = 'target_id';
    const FIELD_SUBMITTER = 'submitter';
    const FIELD_SUBMISSION_DATE = 'submission_date';
    const FIELD_APPLIED_DATE = 'applied_date';
    const FIELD_APPROVED_STRING_COUNT = 'approved_string_count';
    const FIELD_COMPLETED_STRING_COUNT = 'completed_string_count';
    const FIELD_EXCLUDED_STRING_COUNT = 'excluded_string_count';
    const FIELD_TOTAL_STRING_COUNT = 'total_string_count';
    const FIELD_WORD_COUNT = 'word_count';
    const FIELD_STATUS = 'status';
    const FIELD_IS_LOCKED = 'is_locked';
    const FIELD_IS_CLONED = 'is_cloned';
    const FIELD_LAST_MODIFIED = 'last_modified';
    const FIELD_OUTDATED = 'outdated';
    const FIELD_LAST_ERROR = 'last_error';
    const FIELD_BATCH_UID = 'batch_uid';
    const FIELD_LOCKED_FIELDS = 'locked_fields';

    public static function getFieldDefinitions()
    {
        return [
            static::FIELD_ID => static::DB_TYPE_U_BIGINT . ' ' . static::DB_TYPE_INT_MODIFIER_AUTOINCREMENT,
            static::FIELD_SOURCE_TITLE => static::DB_TYPE_STRING_STANDARD,
            static::FIELD_SOURCE_BLOG_ID => static::DB_TYPE_U_BIGINT,
            static::FIELD_SOURCE_CONTENT_HASH => 'CHAR(32) NULL',
            static::FIELD_CONTENT_TYPE => 'VARCHAR(32) NOT NULL',
            static::FIELD_SOURCE_ID => static::DB_TYPE_U_BIGINT,
            static::FIELD_FILE_URI => 'VARCHAR(255) NULL',
            static::FIELD_TARGET_LOCALE => static::DB_TYPE_STRING_SMALL,
            static::FIELD_TARGET_BLOG_ID => static::DB_TYPE_U_BIGINT,
            static::FIELD_TARGET_ID => static::DB_TYPE_U_BIGINT . ' ' . static::DB_TYPE_DEFAULT_ZERO,
            static::FIELD_SUBMITTER => static::DB_TYPE_STRING_STANDARD,
            static::FIELD_SUBMISSION_DATE => static::DB_TYPE_DATETIME,
            static::FIELD_APPLIED_DATE => static::DB_TYPE_DATETIME,
            static::FIELD_APPROVED_STRING_COUNT => static::DB_TYPE_U_BIGINT . ' ' . static::DB_TYPE_DEFAULT_ZERO,
            static::FIELD_COMPLETED_STRING_COUNT => static::DB_TYPE_U_BIGINT . ' ' . static::DB_TYPE_DEFAULT_ZERO,
            static::FIELD_EXCLUDED_STRING_COUNT => static::DB_TYPE_U_BIGINT . ' ' . static::DB_TYPE_DEFAULT_ZERO,
            static::FIELD_TOTAL_STRING_COUNT => static::DB_TYPE_U_BIGINT . ' ' . static::DB_TYPE_DEFAULT_ZERO,
            static::FIELD_WORD_COUNT => static::DB_TYPE_U_BIGINT . ' ' . static::DB_TYPE_DEFAULT_ZERO,
            static::FIELD_STATUS => static::DB_TYPE_STRING_SMALL,
            static::FIELD_IS_LOCKED => static::DB_TYPE_UINT_SWITCH . ' ' . static::DB_TYPE_DEFAULT_ZERO,
            static::FIELD_IS_CLONED => static::DB_TYPE_UINT_SWITCH . ' ' . static::DB_TYPE_DEFAULT_ZERO,
            static::FIELD_LAST_MODIFIED => static::DB_TYPE_DATETIME,
            static::FIELD_OUTDATED => static::DB_TYPE_UINT_SWITCH,
            static::FIELD_LAST_ERROR => static::DB_TYPE_STRING_TEXT,
            static::FIELD_BATCH_UID => static::DB_TYPE_STRING_64 . ' ' . static::DB_TYPE_DEFAULT_EMPTYSTRING,
            static::FIELD_LOCKED_FIELDS => 'TEXT NULL',
        ];
    }

    /**
     * @return array
     */
    public static function getSubmissionStatusLabels()
    {
        return [
            static::SUBMISSION_STATUS_NEW => __(static::SUBMISSION_STATUS_NEW),
            static::SUBMISSION_STATUS_IN_PROGRESS => __(static::SUBMISSION_STATUS_IN_PROGRESS),
            static::SUBMISSION_STATUS_COMPLETED => __(static::SUBMISSION_STATUS_COMPLETED),
            static::SUBMISSION_STATUS_FAILED => __(static::SUBMISSION_STATUS_FAILED),
        ];
    }

    /**
     * @return array
     */
    public static function getFieldLabels()
    {
        return [
            static::FIELD_ID => __('ID'),
            static::FIELD_SOURCE_TITLE => __('Title'),
            static::FIELD_CONTENT_TYPE => __('Type'),
            static::FIELD_FILE_URI => __('Smartling File URI'),
            static::FIELD_TARGET_LOCALE => __('Locale'),
            static::FIELD_SUBMITTER => __('Submitter'),
            static::FIELD_SUBMISSION_DATE => __('Time Submitted'),
            static::FIELD_APPLIED_DATE => __('Time Applied'),
            static::FIELD_WORD_COUNT => __('Words'),
            'progress' => __('Progress'),
            static::FIELD_STATUS => __('Status'),
            static::FIELD_OUTDATED => __('Outdated'),
        ];
    }

    protected static function getInstance(LoggerInterface $logger)
    {
        return new static($logger);
    }

    public static function getSortableFields()
    {
        return [
            static::FIELD_ID,
            static::FIELD_SOURCE_TITLE,
            static::FIELD_CONTENT_TYPE,
            static::FIELD_FILE_URI,
            static::FIELD_TARGET_LOCALE,
            static::FIELD_SUBMITTER,
            static::FIELD_SUBMISSION_DATE,
            static::FIELD_WORD_COUNT,
            'progress',
            static::FIELD_STATUS,
        ];
    }

    public static function getIndexes()
    {
        return [
            [
                'type' => 'primary',
                'columns' => [static::FIELD_ID],
            ],
            [
                'type' => 'index',
                'columns' => [static::FIELD_CONTENT_TYPE],
            ],
            [
                'type' => 'index',
                'columns' => [
                    static::FIELD_SOURCE_BLOG_ID,
                    static::FIELD_SOURCE_ID,
                    static::FIELD_CONTENT_TYPE,
                ],
            ],
        ];
    }

    /**
     * @return array
     */
    protected function getVirtualFields()
    {
        return [
            'progress' => $this->getCompletionPercentage() . '%',
        ];
    }

    /**
     * @return \DateTime
     */
    public function getLastModified()
    {
        $value = $this->stateFields[static::FIELD_LAST_MODIFIED];

        $dt = \DateTime::createFromFormat(static::DATETIME_FORMAT, $value);

        if (false === $dt) {
            $dt = \DateTime::createFromFormat('U', 0);
        }

        return $dt;
    }

    /**
     * @param \DateTime $dateTime
     */
    public function setLastModified($dateTime)
    {
        if ($dateTime instanceof \DateTime) {
            $this->stateFields[static::FIELD_LAST_MODIFIED] = $dateTime->format(static::DATETIME_FORMAT);
        } else {
            $dt = \DateTime::createFromFormat(static::DATETIME_FORMAT, $dateTime);
            if (false === $dt) {
                $dt = '1990-01-01 12:00:00';
            } else {
                $dt = $dt->format(static::DATETIME_FORMAT);
            }
            $this->stateFields[static::FIELD_LAST_MODIFIED] = $dt;
        }
    }

    public function getOutdated()
    {
        return (int)$this->stateFields[static::FIELD_OUTDATED];
    }

    public function setOutdated($outdated)
    {
        $this->stateFields[static::FIELD_OUTDATED] = (int)$outdated;
    }

    public function getIsCloned()
    {
        return (int)$this->stateFields[static::FIELD_IS_CLONED];
    }

    public function setIsCloned($isCloned)
    {
        $this->stateFields[static::FIELD_IS_CLONED] = (int)$isCloned;
    }

    /**
     * @return int
     */
    public function getWordCount()
    {
        return (int)$this->stateFields[static::FIELD_WORD_COUNT];
    }

    /**
     * @param int $word_count
     */
    public function setWordCount($word_count)
    {
        $this->stateFields[static::FIELD_WORD_COUNT] = (int)$word_count;
    }

    public function getIsLocked()
    {
        return (int)$this->stateFields[static::FIELD_IS_LOCKED];
    }

    public function setIsLocked($is_locked)
    {
        $this->stateFields[static::FIELD_IS_LOCKED] = (int)$is_locked;
    }

    /**
     * @return string
     */
    public function getStatus()
    {
        return $this->stateFields[static::FIELD_STATUS];
    }

    /**
     * @param string $status
     *
     * @return SubmissionEntity
     * @throws \InvalidArgumentException
     */
    public function setStatus($status)
    {
        if (in_array($status, static::$submissionStatuses, true)) {
            $this->stateFields[static::FIELD_STATUS] = $status;
        } else {
            $message = vsprintf('Invalid status value. Got \'%s\', expected one of: %s', [
                $status,
                implode(',', static::$submissionStatuses),
            ]);

            $this->logger->error($message);

            throw new InvalidArgumentException($message);
        }

        switch ($this->getStatus()) {
            case static::SUBMISSION_STATUS_NEW:
                $this->setLastError('');
                $this->setSubmitter(WordpressUserHelper::getUserLogin());
                $this->setApprovedStringCount(0);
                $this->setCompletedStringCount(0);
                break;
            case static::SUBMISSION_STATUS_IN_PROGRESS:
                $this->setOutdated(0);
                break;
            default:
        }

        return $this;
    }

    public function hasLocks()
    {
        $fields = maybe_unserialize($this->getLockedFields());

        return 1 === $this->getIsLocked() || (is_array($fields) && 0 < count($fields));
    }

    public function getStatusFlags()
    {
        $result = [];
        $template = 'dashicons dashicons-%s';
        if (1 === $this->getOutdated()) {
            $result['outdated'] = vsprintf($template, ['warning']);
        }
        if (1 === $this->getIsCloned()) {
            $result['cloned'] = vsprintf($template, ['admin-page']);
        }
        if ($this->hasLocks()) {
            $result['locked'] = vsprintf($template, ['lock']);
        }

        return $result;
    }

    /**
     * @return string
     */
    public function getStatusColor()
    {
        $statusColors = [
            static::SUBMISSION_STATUS_NEW => 'yellow',
            static::SUBMISSION_STATUS_IN_PROGRESS => 'blue',
            static::SUBMISSION_STATUS_COMPLETED => 'green',
            static::SUBMISSION_STATUS_FAILED => 'red',
        ];

        return $statusColors[$this->getStatus()];
    }

    /**
     * @return int|null
     */
    public function getId()
    {
        return $this->stateFields[static::FIELD_ID];
    }

    /**
     * @param int $id
     *
     * @return SubmissionEntity
     */
    public function setId($id)
    {
        $this->stateFields[static::FIELD_ID] = null === $id ? $id : (int)$id;

        return $this;
    }

    /**
     * @return string
     */
    public function getSourceTitle($withReplacement = true)
    {
        $source_title = $this->stateFields[static::FIELD_SOURCE_TITLE];

        if ($withReplacement) {
            $source_title = mb_strlen($source_title, 'utf8') > 255 ? TextHelper::mb_wordwrap($source_title, 252) . '...'
                : $source_title;
        }

        return $source_title;
    }

    /**
     * @param string $source_title
     *
     * @return SubmissionEntity
     */
    public function setSourceTitle($source_title)
    {
        $this->stateFields[static::FIELD_SOURCE_TITLE] = $source_title;

        return $this;
    }

    /**
     * @return int
     */
    public function getSourceBlogId()
    {
        return (int)$this->stateFields[static::FIELD_SOURCE_BLOG_ID];
    }

    /**
     * @param int $source_blog_id
     *
     * @return SubmissionEntity
     */
    public function setSourceBlogId($source_blog_id)
    {
        $this->stateFields[static::FIELD_SOURCE_BLOG_ID] = (int)$source_blog_id;

        return $this;
    }

    /**
     * @return string
     */
    public function getSourceContentHash()
    {
        return $this->stateFields[static::FIELD_SOURCE_CONTENT_HASH];
    }

    /**
     * @param string $source_content_hash
     *
     * @return SubmissionEntity
     */
    public function setSourceContentHash($source_content_hash)
    {
        $this->stateFields[static::FIELD_SOURCE_CONTENT_HASH] = $source_content_hash;

        return $this;
    }

    /**
     * @return string
     */
    public function getContentType()
    {
        return $this->stateFields[static::FIELD_CONTENT_TYPE];
    }

    /**
     * @param string $content_type
     *
     * @return SubmissionEntity
     */
    public function setContentType($content_type)
    {
        $reverseMap = WordpressContentTypeHelper::getReverseMap();

        if (array_key_exists($content_type, $reverseMap)) {
            $this->stateFields[static::FIELD_CONTENT_TYPE] = $reverseMap[$content_type];
        } else {

            $this->stateFields[static::FIELD_CONTENT_TYPE] = $content_type;
            $this->setLastError('Invalid Content Type');
            $this->setStatus(static::SUBMISSION_STATUS_FAILED);
            $message = vsprintf('Invalid content type. Got \'%s\', expected one of: %s', [
                $content_type,
                implode(',', $reverseMap),
            ]);
            $this->logger->error($message);
        }

        return $this;
    }

    /**
     * Converts associative array to entity
     * array keys must match field names;
     *
     * @param array           $array
     * @param LoggerInterface $logger
     *
     * @return SubmissionEntity
     */
    public static function fromArray(array $array, LoggerInterface $logger)
    {
        $obj = parent::fromArray($array, $logger);

        $obj->setContentType($obj->getContentType());

        return $obj;
    }

    /**
     * @return string
     */
    public function getSourceId()
    {
        return (int)$this->stateFields[static::FIELD_SOURCE_ID];
    }

    /**
     * @param string $source_id
     *
     * @return SubmissionEntity
     */
    public function setSourceId($source_id)
    {
        $this->stateFields[static::FIELD_SOURCE_ID] = (int)$source_id;

        return $this;
    }

    /**
     * @return string
     */
    public function getFileUri()
    {
        if (empty($this->stateFields[static::FIELD_FILE_URI])) {

            $fileUri = FileUriHelper::generateFileUri($this);

            $filterParams = (new SmartlingFileUriFilterParamater())
                ->setContentType($this->getContentType())
                ->setFileUri($fileUri)
                ->setSourceBlogId($this->getSourceBlogId())
                ->setSourceContentId($this->getSourceId());

            $filterParams = apply_filters(ExportedAPI::FILTER_SMARTLING_FILE_URI, $filterParams);

            if (($filterParams instanceof SmartlingFileUriFilterParamater)
                && !StringHelper::isNullOrEmpty($filterParams->getFileUri())
            ) {
                $fileUri = $filterParams->getFileUri();
            }

            $this->setFileUri($fileUri);
        }

        return $this->stateFields[static::FIELD_FILE_URI];
    }

    /**
     * @param string $file_uri
     *
     * @return SubmissionEntity
     */
    protected function setFileUri($file_uri)
    {
        $this->stateFields[static::FIELD_FILE_URI] = $file_uri;

        return $this;
    }

    /**
     * @return string
     */
    public function getTargetLocale()
    {
        return $this->stateFields[static::FIELD_TARGET_LOCALE];
    }

    /**
     * @param string $target_locale
     *
     * @return SubmissionEntity
     */
    public function setTargetLocale($target_locale)
    {
        $this->stateFields[static::FIELD_TARGET_LOCALE] = $target_locale;

        return $this;
    }

    /**
     * @return int
     */
    public function getTargetBlogId()
    {
        return (int)$this->stateFields[static::FIELD_TARGET_BLOG_ID];
    }

    /**
     * @param int $target_blog_id
     *
     * @return SubmissionEntity
     */
    public function setTargetBlogId($target_blog_id)
    {
        $this->stateFields[static::FIELD_TARGET_BLOG_ID] = (int)$target_blog_id;

        return $this;
    }

    /**
     * @return string
     */
    public function getTargetId()
    {
        return (int)$this->stateFields[static::FIELD_TARGET_ID];
    }

    /**
     * @param string $target_id
     *
     * @return SubmissionEntity
     */
    public function setTargetId($target_id)
    {
        $this->stateFields[static::FIELD_TARGET_ID] = $target_id;

        return $this;
    }

    /**
     * @return string
     */
    public function getSubmitter()
    {
        return $this->stateFields[static::FIELD_SUBMITTER];
    }

    /**
     * @param string $submitter
     *
     * @return SubmissionEntity
     */
    public function setSubmitter($submitter)
    {
        $this->stateFields[static::FIELD_SUBMITTER] = $submitter;

        return $this;
    }

    /**
     * @return string
     */
    public function getSubmissionDate()
    {
        return $this->stateFields[static::FIELD_SUBMISSION_DATE];
    }

    /**
     * @param string $submission_date
     *
     * @return SubmissionEntity
     */
    public function setSubmissionDate($submission_date)
    {
        $this->stateFields[static::FIELD_SUBMISSION_DATE] = $submission_date;

        return $this;
    }

    /**
     * @return null|string
     */
    public function getAppliedDate()
    {
        return $this->stateFields[static::FIELD_APPLIED_DATE];
    }

    /**
     * @param null|string $applied_date
     */
    public function setAppliedDate($applied_date)
    {
        $this->stateFields[static::FIELD_APPLIED_DATE] = $applied_date;
    }

    /**
     * @return int
     */
    public function getApprovedStringCount()
    {
        return (int)$this->stateFields[static::FIELD_APPROVED_STRING_COUNT];
    }

    /**
     * @param int $approved_string_count
     *
     * @return SubmissionEntity
     */
    public function setApprovedStringCount($approved_string_count)
    {
        $this->stateFields[static::FIELD_APPROVED_STRING_COUNT] = (int)$approved_string_count;

        return $this;
    }

    /**
     * @return int
     */
    public function getCompletedStringCount()
    {
        return (int)$this->stateFields[static::FIELD_COMPLETED_STRING_COUNT];
    }

    /**
     * @param int $completed_string_count
     *
     * @return SubmissionEntity
     */
    public function setCompletedStringCount($completed_string_count)
    {
        $this->stateFields[static::FIELD_COMPLETED_STRING_COUNT] = (int)$completed_string_count;

        return $this;
    }

    /**
     * @return int
     */
    public function getExcludedStringCount()
    {
        return (int)$this->stateFields[static::FIELD_EXCLUDED_STRING_COUNT];
    }

    /**
     * @param $excludedStringsCount
     *
     * @return $this
     */
    public function setExcludedStringCount($excludedStringsCount)
    {
        $this->stateFields[static::FIELD_EXCLUDED_STRING_COUNT] = (int)$excludedStringsCount;

        return $this;
    }

    /**
     * @return int
     */
    public function getTotalStringCount()
    {
        return (int)$this->stateFields[static::FIELD_TOTAL_STRING_COUNT];
    }

    /**
     * @param $totalStringsCount
     *
     * @return $this
     */
    public function setTotalStringCount($totalStringsCount)
    {
        $this->stateFields[static::FIELD_TOTAL_STRING_COUNT] = (int)$totalStringsCount;

        return $this;
    }

    /**
     * @return int
     */
    public function getCompletionPercentage()
    {
        $percentage = 0;

        if (($this->getApprovedStringCount() + $this->getCompletedStringCount()) > 0) {
            if (0 !== $this->getApprovedStringCount()) {
                $percentage = $this->getCompletedStringCount() / $this->getApprovedStringCount();
            }
            if ($percentage > 1) {
                $percentage = 1;
            }

            if (1 === $this->getIsCloned()) {
                $percentage = 1;
            }
        } else {
            // if nothing is authorized then check special case when everything was excluded
            $percentage = 0 === ($this->getTotalStringCount() - $this->getExcludedStringCount()) ? 1 : 0;
        }

        return (int)($percentage * 100);
    }

    /**
     * @return string
     */
    public function getLastError()
    {
        return $this->stateFields[static::FIELD_LAST_ERROR];
    }

    public function setLastError($message)
    {
        $this->stateFields[static::FIELD_LAST_ERROR] = trim($message);
    }

    /**
     * @return string
     */
    public function getBatchUid()
    {
        return $this->stateFields[static::FIELD_BATCH_UID];
    }

    public function setBatchUid($batchUid)
    {
        $this->stateFields[static::FIELD_BATCH_UID] = trim($batchUid);
    }

    public function getLockedFields()
    {
        return $this->stateFields[static::FIELD_LOCKED_FIELDS];
    }

    public function setLockedFields($lockFields)
    {
        $this->stateFields[static::FIELD_LOCKED_FIELDS] = $lockFields;
    }

    /**
     * @return string
     */
    public static function getTableName()
    {
        return 'smartling_submissions';
    }
}