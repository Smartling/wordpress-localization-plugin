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
     * @var array Submission Statuses
     */
    public static $submissionStatuses = [
        self::SUBMISSION_STATUS_NEW,
        self::SUBMISSION_STATUS_IN_PROGRESS,
        self::SUBMISSION_STATUS_COMPLETED,
        self::SUBMISSION_STATUS_FAILED,
    ];

    public static function getFieldDefinitions()
    {
        return [
            'id'                     => static::DB_TYPE_U_BIGINT . ' ' . static::DB_TYPE_INT_MODIFIER_AUTOINCREMENT,
            'source_title'           => static::DB_TYPE_STRING_STANDARD,
            'source_blog_id'         => static::DB_TYPE_U_BIGINT,
            'source_content_hash'    => 'CHAR(32) NULL',
            'content_type'           => 'VARCHAR(32) NOT NULL',
            'source_id'              => static::DB_TYPE_U_BIGINT,
            'file_uri'               => 'VARCHAR(255) NULL',
            'target_locale'          => static::DB_TYPE_STRING_SMALL,
            'target_blog_id'         => static::DB_TYPE_U_BIGINT,
            'target_id'              => static::DB_TYPE_U_BIGINT . ' ' . static::DB_TYPE_DEFAULT_ZERO,
            'submitter'              => static::DB_TYPE_STRING_STANDARD,
            'submission_date'        => static::DB_TYPE_DATETIME,
            'applied_date'           => static::DB_TYPE_DATETIME,
            'approved_string_count'  => static::DB_TYPE_U_BIGINT . ' ' . static::DB_TYPE_DEFAULT_ZERO,
            'completed_string_count' => static::DB_TYPE_U_BIGINT . ' ' . static::DB_TYPE_DEFAULT_ZERO,
            'excluded_string_count'  => static::DB_TYPE_U_BIGINT . ' ' . static::DB_TYPE_DEFAULT_ZERO,
            'total_string_count'     => static::DB_TYPE_U_BIGINT . ' ' . static::DB_TYPE_DEFAULT_ZERO,
            'word_count'             => static::DB_TYPE_U_BIGINT . ' ' . static::DB_TYPE_DEFAULT_ZERO,
            'status'                 => static::DB_TYPE_STRING_SMALL,
            'is_locked'              => static::DB_TYPE_UINT_SWITCH . ' ' . static::DB_TYPE_DEFAULT_ZERO,
            'is_cloned'              => static::DB_TYPE_UINT_SWITCH . ' ' . static::DB_TYPE_DEFAULT_ZERO,
            'last_modified'          => static::DB_TYPE_DATETIME,
            'outdated'               => static::DB_TYPE_UINT_SWITCH,
            'last_error'             => static::DB_TYPE_STRING_TEXT,
            'batch_uid'              => static::DB_TYPE_STRING_64 . ' ' . static::DB_TYPE_DEFAULT_EMPTYSTRING,
            'locked_fields'          => 'TEXT NULL',
        ];
    }

    /**
     * @return array
     */
    public static function getSubmissionStatusLabels()
    {
        return [
            static::SUBMISSION_STATUS_NEW         => __(static::SUBMISSION_STATUS_NEW),
            static::SUBMISSION_STATUS_IN_PROGRESS => __(static::SUBMISSION_STATUS_IN_PROGRESS),
            static::SUBMISSION_STATUS_COMPLETED   => __(static::SUBMISSION_STATUS_COMPLETED),
            static::SUBMISSION_STATUS_FAILED      => __(static::SUBMISSION_STATUS_FAILED),
        ];
    }

    /**
     * @return array
     */
    public static function getFieldLabels()
    {
        return [
            'id'              => __('ID'),
            'source_title'    => __('Title'),
            'content_type'    => __('Type'),
            'file_uri'        => __('Smartling File URI'),
            'target_locale'   => __('Locale'),
            'submitter'       => __('Submitter'),
            'submission_date' => __('Time Submitted'),
            'applied_date'    => __('Time Applied'),
            'word_count'      => __('Words'),
            'progress'        => __('Progress'),
            'status'          => __('Status'),
            'outdated'        => __('Outdated'),
        ];
    }

    protected static function getInstance(LoggerInterface $logger)
    {
        return new static($logger);
    }

    public static function getSortableFields()
    {
        return [
            'id',
            'source_title',
            'content_type',
            'file_uri',
            'target_locale',
            'submitter',
            'submission_date',
            'word_count',
            'progress',
            'status',
        ];
    }

    public static function getIndexes()
    {
        return [
            [
                'type'    => 'primary',
                'columns' => ['id'],
            ],
            [
                'type'    => 'index',
                'columns' => ['content_type'],
            ],
            [
                'type'    => 'index',
                'columns' => [
                    'source_blog_id',
                    'source_id',
                    'content_type',
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
        $value = $this->stateFields['last_modified'];

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
            $this->stateFields['last_modified'] = $dateTime->format(static::DATETIME_FORMAT);
        } else {
            $dt = \DateTime::createFromFormat(static::DATETIME_FORMAT, $dateTime);
            if (false === $dt) {
                $dt = '1990-01-01 12:00:00';
            } else {
                $dt = $dt->format(static::DATETIME_FORMAT);
            }
            $this->stateFields['last_modified'] = $dt;
        }
    }

    public function getOutdated()
    {
        return (int)$this->stateFields['outdated'];
    }

    public function setOutdated($outdated)
    {
        $this->stateFields['outdated'] = (int)$outdated;
    }

    public function getIsCloned()
    {
        return (int)$this->stateFields['is_cloned'];
    }

    public function setIsCloned($isCloned)
    {
        $this->stateFields['is_cloned'] = (int)$isCloned;
    }

    /**
     * @return int
     */
    public function getWordCount()
    {
        return (int)$this->stateFields['word_count'];
    }

    /**
     * @param int $word_count
     */
    public function setWordCount($word_count)
    {
        $this->stateFields['word_count'] = (int)$word_count;
    }

    public function getIsLocked()
    {
        return (int)$this->stateFields['is_locked'];
    }

    public function setIsLocked($is_locked)
    {
        $this->stateFields['is_locked'] = (int)$is_locked;
    }

    /**
     * @return string
     */
    public function getStatus()
    {
        return $this->stateFields['status'];
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
            $this->stateFields['status'] = $status;
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
        if (1 === $this->getOutdated()) {
            $result['outdated'] = 'dashicons dashicons-warning';
        }
        if (1 === $this->getIsCloned()) {
            $result['cloned'] = 'dashicons dashicons-admin-page';
        }
        if ($this->hasLocks()) {
            $result['locked'] = 'dashicons dashicons-lock';
        }

        return $result;
    }

    /**
     * @return string
     */
    public function getStatusColor()
    {
        $statusColors = [
            static::SUBMISSION_STATUS_NEW         => 'yellow',
            static::SUBMISSION_STATUS_IN_PROGRESS => 'blue',
            static::SUBMISSION_STATUS_COMPLETED   => 'green',
            static::SUBMISSION_STATUS_FAILED      => 'red',
        ];

        return $statusColors[$this->getStatus()];
    }

    /**
     * @return int|null
     */
    public function getId()
    {
        return $this->stateFields['id'];
    }

    /**
     * @param int $id
     *
     * @return SubmissionEntity
     */
    public function setId($id)
    {
        $this->stateFields['id'] = null === $id ? $id : (int)$id;

        return $this;
    }

    /**
     * @return string
     */
    public function getSourceTitle($withReplacement = true)
    {
        $source_title = $this->stateFields['source_title'];

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
        $this->stateFields['source_title'] = $source_title;

        return $this;
    }

    /**
     * @return int
     */
    public function getSourceBlogId()
    {
        return (int)$this->stateFields['source_blog_id'];
    }

    /**
     * @param int $source_blog_id
     *
     * @return SubmissionEntity
     */
    public function setSourceBlogId($source_blog_id)
    {
        $this->stateFields['source_blog_id'] = (int)$source_blog_id;

        return $this;
    }

    /**
     * @return string
     */
    public function getSourceContentHash()
    {
        return $this->stateFields['source_content_hash'];
    }

    /**
     * @param string $source_content_hash
     *
     * @return SubmissionEntity
     */
    public function setSourceContentHash($source_content_hash)
    {
        $this->stateFields['source_content_hash'] = $source_content_hash;

        return $this;
    }

    /**
     * @return string
     */
    public function getContentType()
    {
        return $this->stateFields['content_type'];
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
            $this->stateFields['content_type'] = $reverseMap[$content_type];
        } else {

            $this->stateFields['content_type'] = $content_type;
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
        return (int)$this->stateFields['source_id'];
    }

    /**
     * @param string $source_id
     *
     * @return SubmissionEntity
     */
    public function setSourceId($source_id)
    {
        $this->stateFields['source_id'] = (int)$source_id;

        return $this;
    }

    /**
     * @return string
     */
    public function getFileUri()
    {
        if (empty($this->stateFields['file_uri'])) {

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

        return $this->stateFields['file_uri'];
    }

    /**
     * @param string $file_uri
     *
     * @return SubmissionEntity
     */
    protected function setFileUri($file_uri)
    {
        $this->stateFields['file_uri'] = $file_uri;

        return $this;
    }

    /**
     * @return string
     */
    public function getTargetLocale()
    {
        return $this->stateFields['target_locale'];
    }

    /**
     * @param string $target_locale
     *
     * @return SubmissionEntity
     */
    public function setTargetLocale($target_locale)
    {
        $this->stateFields['target_locale'] = $target_locale;

        return $this;
    }

    /**
     * @return int
     */
    public function getTargetBlogId()
    {
        return (int)$this->stateFields['target_blog_id'];
    }

    /**
     * @param int $target_blog_id
     *
     * @return SubmissionEntity
     */
    public function setTargetBlogId($target_blog_id)
    {
        $this->stateFields['target_blog_id'] = (int)$target_blog_id;

        return $this;
    }

    /**
     * @return string
     */
    public function getTargetId()
    {
        return (int)$this->stateFields['target_id'];
    }

    /**
     * @param string $target_id
     *
     * @return SubmissionEntity
     */
    public function setTargetId($target_id)
    {
        $this->stateFields['target_id'] = $target_id;

        return $this;
    }

    /**
     * @return string
     */
    public function getSubmitter()
    {
        return $this->stateFields['submitter'];
    }

    /**
     * @param string $submitter
     *
     * @return SubmissionEntity
     */
    public function setSubmitter($submitter)
    {
        $this->stateFields['submitter'] = $submitter;

        return $this;
    }

    /**
     * @return string
     */
    public function getSubmissionDate()
    {
        return $this->stateFields['submission_date'];
    }

    /**
     * @param string $submission_date
     *
     * @return SubmissionEntity
     */
    public function setSubmissionDate($submission_date)
    {
        $this->stateFields['submission_date'] = $submission_date;

        return $this;
    }

    /**
     * @return null|string
     */
    public function getAppliedDate()
    {
        return $this->stateFields['applied_date'];
    }

    /**
     * @param null|string $applied_date
     */
    public function setAppliedDate($applied_date)
    {
        $this->stateFields['applied_date'] = $applied_date;
    }

    /**
     * @return int
     */
    public function getApprovedStringCount()
    {
        return (int)$this->stateFields['approved_string_count'];
    }

    /**
     * @param int $approved_string_count
     *
     * @return SubmissionEntity
     */
    public function setApprovedStringCount($approved_string_count)
    {
        $this->stateFields['approved_string_count'] = (int)$approved_string_count;

        return $this;
    }

    /**
     * @return int
     */
    public function getCompletedStringCount()
    {
        return (int)$this->stateFields['completed_string_count'];
    }

    /**
     * @param int $completed_string_count
     *
     * @return SubmissionEntity
     */
    public function setCompletedStringCount($completed_string_count)
    {
        $this->stateFields['completed_string_count'] = (int)$completed_string_count;

        return $this;
    }

    /**
     * @return int
     */
    public function getExcludedStringCount()
    {
        return (int)$this->stateFields['excluded_string_count'];
    }

    /**
     * @param $excludedStringsCount
     *
     * @return $this
     */
    public function setExcludedStringCount($excludedStringsCount)
    {
        $this->stateFields['excluded_string_count'] = (int)$excludedStringsCount;

        return $this;
    }

    /**
     * @return int
     */
    public function getTotalStringCount()
    {
        return (int)$this->stateFields['total_string_count'];
    }

    /**
     * @param $totalStringsCount
     *
     * @return $this
     */
    public function setTotalStringCount($totalStringsCount)
    {
        $this->stateFields['total_string_count'] = (int)$totalStringsCount;

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

    public function getLastError()
    {
        return $this->stateFields['last_error'];
    }

    public function setLastError($message)
    {
        $this->stateFields['last_error'] = trim($message);
    }

    /**
     * @return string
     */
    public function getBatchUid()
    {
        return $this->stateFields['batch_uid'];
    }

    public function setBatchUid($batchUid)
    {
        $this->stateFields['batch_uid'] = trim($batchUid);
    }

    public function getLockedFields()
    {
        return $this->stateFields['locked_fields'];
    }

    public function setLockedFields($lockFields)
    {
        $this->stateFields['locked_fields'] = $lockFields;
    }

    /**
     * @return string
     */
    public static function getTableName()
    {
        return 'smartling_submissions';
    }
}