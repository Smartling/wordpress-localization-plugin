<?php

namespace Smartling\Submissions;

use InvalidArgumentException;
use Smartling\Base\ExportedAPI;
use Smartling\Base\SmartlingEntityAbstract;
use Smartling\Exception\SmartlingDirectRunRuntimeException;
use Smartling\Helpers\EventParameters\SmartlingFileUriFilterParamater;
use Smartling\Helpers\FileUriHelper;
use Smartling\Helpers\StringHelper;
use Smartling\Helpers\TextHelper;
use Smartling\Helpers\WordpressContentTypeHelper;
use Smartling\Helpers\WordpressUserHelper;
use Smartling\Jobs\JobEntity;
use Smartling\Jobs\JobEntityWithBatchUid;
use Smartling\Vendor\Psr\Log\LoggerInterface;

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
    public const FLAG_CONTENT_IS_OUT_OF_DATE = 1;

    public const FLAG_CONTENT_IS_UP_TO_DATE = 0;

    public const DATETIME_FORMAT = 'Y-m-d H:i:s';

    public const SUBMISSION_STATUS_NEW = 'New';
    public const SUBMISSION_STATUS_IN_PROGRESS = 'In Progress';
    public const SUBMISSION_STATUS_COMPLETED = 'Completed';
    public const SUBMISSION_STATUS_FAILED = 'Failed';
    public const SUBMISSION_STATUS_CANCELLED = 'Cancelled';

    public static array $submissionStatuses = [
        self::SUBMISSION_STATUS_NEW,
        self::SUBMISSION_STATUS_IN_PROGRESS,
        self::SUBMISSION_STATUS_COMPLETED,
        self::SUBMISSION_STATUS_FAILED,
        self::SUBMISSION_STATUS_CANCELLED,
    ];

    public const FIELD_ID = 'id';
    public const FIELD_SOURCE_TITLE = 'source_title';
    public const FIELD_SOURCE_BLOG_ID = 'source_blog_id';
    public const FIELD_SOURCE_CONTENT_HASH = 'source_content_hash';
    public const FIELD_CONTENT_TYPE = 'content_type';
    public const FIELD_SOURCE_ID = 'source_id';
    public const FIELD_FILE_URI = 'file_uri';
    public const FIELD_TARGET_LOCALE = 'target_locale';
    public const FIELD_TARGET_BLOG_ID = 'target_blog_id';
    public const FIELD_TARGET_ID = 'target_id';
    public const FIELD_SUBMITTER = 'submitter';
    public const FIELD_SUBMISSION_DATE = 'submission_date';
    public const FIELD_APPLIED_DATE = 'applied_date';
    public const FIELD_APPROVED_STRING_COUNT = 'approved_string_count';
    public const FIELD_COMPLETED_STRING_COUNT = 'completed_string_count';
    public const FIELD_EXCLUDED_STRING_COUNT = 'excluded_string_count';
    public const FIELD_TOTAL_STRING_COUNT = 'total_string_count';
    public const FIELD_WORD_COUNT = 'word_count';
    public const FIELD_STATUS = 'status';
    public const FIELD_IS_LOCKED = 'is_locked';
    public const FIELD_IS_CLONED = 'is_cloned';
    public const FIELD_LAST_MODIFIED = 'last_modified';
    public const FIELD_OUTDATED = 'outdated';
    public const FIELD_LAST_ERROR = 'last_error';
    public const FIELD_BATCH_UID = 'batch_uid';
    public const FIELD_LOCKED_FIELDS = 'locked_fields';

    public const VIRTUAL_FIELD_JOB_LINK = 'job_link';

    private ?JobEntity $jobInformation = null;

    public static function getFieldDefinitions(): array
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

    public static function getSubmissionStatusLabels(): array
    {
        return [
            static::SUBMISSION_STATUS_NEW => __(static::SUBMISSION_STATUS_NEW),
            static::SUBMISSION_STATUS_IN_PROGRESS => __(static::SUBMISSION_STATUS_IN_PROGRESS),
            static::SUBMISSION_STATUS_COMPLETED => __(static::SUBMISSION_STATUS_COMPLETED),
            static::SUBMISSION_STATUS_FAILED => __(static::SUBMISSION_STATUS_FAILED),
        ];
    }

    public static function getFieldLabels(): array
    {
        return [
            static::FIELD_ID => __('ID'),
            static::FIELD_SOURCE_TITLE => __('Title'),
            static::FIELD_CONTENT_TYPE => __('Type'),
            static::FIELD_FILE_URI => __('Smartling File URI'),
            static::VIRTUAL_FIELD_JOB_LINK => __('Smartling Job'),
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

    protected static function getInstance(): SubmissionEntity
    {
        return new static();
    }

    public static function getSortableFields(): array
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

    public static function getIndexes(): array
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

    protected function getVirtualFields(): array
    {
        return [
            'progress' => $this->getCompletionPercentage() . '%',
        ];
    }

    public function getLastModified(): \DateTime
    {
        $value = $this->stateFields[static::FIELD_LAST_MODIFIED];

        $dt = \DateTime::createFromFormat(static::DATETIME_FORMAT, $value);

        if (false === $dt) {
            $dt = \DateTime::createFromFormat('U', 0);
        }

        return $dt;
    }

    /**
     * @param string|\DateTime|null $dateTime
     */
    public function setLastModified($dateTime): void
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

    public function getOutdated(): int
    {
        return (int)$this->stateFields[static::FIELD_OUTDATED];
    }

    public function setOutdated(?int $outdated): void
    {
        $this->stateFields[static::FIELD_OUTDATED] = $outdated;
    }

    public function getIsCloned(): int
    {
        return (int)$this->stateFields[static::FIELD_IS_CLONED];
    }

    public function setIsCloned(?int $isCloned): void
    {
        $this->stateFields[static::FIELD_IS_CLONED] = $isCloned;
    }

    public function getWordCount(): int
    {
        return (int)$this->stateFields[static::FIELD_WORD_COUNT];
    }

    public function setWordCount(?int $word_count): void
    {
        $this->stateFields[static::FIELD_WORD_COUNT] = $word_count;
    }

    public function getIsLocked(): int
    {
        return (int)$this->stateFields[static::FIELD_IS_LOCKED];
    }

    public function setIsLocked(?int $is_locked): void
    {
        $this->stateFields[static::FIELD_IS_LOCKED] = $is_locked;
    }

    public function getStatus(): string
    {
        return (string)$this->stateFields[static::FIELD_STATUS];
    }

    public function setStatus(string $status): SubmissionEntity
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

    public function hasLocks(): bool
    {
        return 1 === $this->getIsLocked() || 0 < count($this->getLockedFields());
    }

    public function getStatusFlags(): array
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

    public function getStatusColor(): string
    {
        $statusColors = [
            static::SUBMISSION_STATUS_NEW => 'yellow',
            static::SUBMISSION_STATUS_IN_PROGRESS => 'blue',
            static::SUBMISSION_STATUS_COMPLETED => 'green',
            static::SUBMISSION_STATUS_FAILED => 'red',
        ];

        return $statusColors[$this->getStatus()];
    }

    public function getId(): ?int
    {
        return $this->stateFields[static::FIELD_ID];
    }

    public function setId(?int $id): SubmissionEntity
    {
        $this->stateFields[static::FIELD_ID] = $id;

        return $this;
    }

    public function getSourceTitle(bool $withReplacement = true): string
    {
        $source_title = $this->stateFields[static::FIELD_SOURCE_TITLE];

        if ($withReplacement) {
            $source_title = mb_strlen($source_title, 'utf8') > 255 ? TextHelper::mb_wordwrap($source_title, 252) . '...'
                : $source_title;
        }

        return (string)$source_title;
    }

    public function setSourceTitle(string $source_title): SubmissionEntity
    {
        $this->stateFields[static::FIELD_SOURCE_TITLE] = $source_title;

        return $this;
    }

    public function getSourceBlogId(): int
    {
        return (int)$this->stateFields[static::FIELD_SOURCE_BLOG_ID];
    }

    public function setSourceBlogId(int $source_blog_id): SubmissionEntity
    {
        $this->stateFields[static::FIELD_SOURCE_BLOG_ID] = $source_blog_id;

        return $this;
    }

    public function getSourceContentHash(): string
    {
        return (string)$this->stateFields[static::FIELD_SOURCE_CONTENT_HASH];
    }

    public function setSourceContentHash(?string $source_content_hash): SubmissionEntity
    {
        $this->stateFields[static::FIELD_SOURCE_CONTENT_HASH] = $source_content_hash;

        return $this;
    }

    public function getContentType(): string
    {
        return (string)$this->stateFields[static::FIELD_CONTENT_TYPE];
    }

    /**
     * @param string $content_type
     * @return SubmissionEntity
     * @throws SmartlingDirectRunRuntimeException
     */
    public function setContentType(string $content_type): SubmissionEntity
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
     * @throws SmartlingDirectRunRuntimeException
     */
    public static function fromArray(array $array, LoggerInterface $logger): SubmissionEntity
    {
        $obj = parent::fromArray($array, $logger);
        if (!$obj instanceof self) {
            throw new \RuntimeException(__CLASS__ . ' expected');
        }

        $obj->setContentType($obj->getContentType());

        return $obj;
    }

    public function getSourceId(): int
    {
        return (int)$this->stateFields[static::FIELD_SOURCE_ID];
    }

    public function setSourceId(int $source_id): SubmissionEntity
    {
        $this->stateFields[static::FIELD_SOURCE_ID] = $source_id;

        return $this;
    }

    /**
     * Will try to set file uri if it is currently empty
     */
    public function getFileUri(): string
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

    public function getStateFieldFileUri(): string
    {
        return (string)$this->stateFields[static::FIELD_FILE_URI];
    }

    protected function setFileUri(?string $file_uri): SubmissionEntity
    {
        $this->stateFields[static::FIELD_FILE_URI] = $file_uri;

        return $this;
    }

    public function getTargetLocale(): string
    {
        return (string)$this->stateFields[static::FIELD_TARGET_LOCALE];
    }

    public function setTargetLocale(?string $target_locale): SubmissionEntity
    {
        $this->stateFields[static::FIELD_TARGET_LOCALE] = $target_locale;

        return $this;
    }

    public function getTargetBlogId(): int
    {
        return (int)$this->stateFields[static::FIELD_TARGET_BLOG_ID];
    }

    public function setTargetBlogId(int $target_blog_id): SubmissionEntity
    {
        $this->stateFields[static::FIELD_TARGET_BLOG_ID] = $target_blog_id;

        return $this;
    }

    public function getTargetId(): int
    {
        return (int)$this->stateFields[static::FIELD_TARGET_ID];
    }

    public function setTargetId(?int $target_id): SubmissionEntity
    {
        $this->stateFields[static::FIELD_TARGET_ID] = $target_id;

        return $this;
    }

    public function getSubmitter(): string
    {
        return (string)$this->stateFields[static::FIELD_SUBMITTER];
    }

    public function setSubmitter(string $submitter): SubmissionEntity
    {
        $this->stateFields[static::FIELD_SUBMITTER] = $submitter;

        return $this;
    }

    public function getSubmissionDate(): string
    {
        return (string)$this->stateFields[static::FIELD_SUBMISSION_DATE];
    }

    public function setSubmissionDate(string $submission_date): SubmissionEntity
    {
        $this->stateFields[static::FIELD_SUBMISSION_DATE] = $submission_date;

        return $this;
    }

    public function getAppliedDate(): ?string
    {
        return $this->stateFields[static::FIELD_APPLIED_DATE];
    }

    public function setAppliedDate(?string $applied_date): void
    {
        $this->stateFields[static::FIELD_APPLIED_DATE] = $applied_date;
    }

    public function getApprovedStringCount(): int
    {
        return (int)$this->stateFields[static::FIELD_APPROVED_STRING_COUNT];
    }

    public function setApprovedStringCount(int $approved_string_count): SubmissionEntity
    {
        $this->stateFields[static::FIELD_APPROVED_STRING_COUNT] = $approved_string_count;

        return $this;
    }

    public function getCompletedStringCount(): int
    {
        return (int)$this->stateFields[static::FIELD_COMPLETED_STRING_COUNT];
    }

    public function setCompletedStringCount(int $completed_string_count): SubmissionEntity
    {
        $this->stateFields[static::FIELD_COMPLETED_STRING_COUNT] = $completed_string_count;

        return $this;
    }

    public function getExcludedStringCount(): int
    {
        return (int)$this->stateFields[static::FIELD_EXCLUDED_STRING_COUNT];
    }

    public function setExcludedStringCount(?int $excludedStringsCount): SubmissionEntity
    {
        $this->stateFields[static::FIELD_EXCLUDED_STRING_COUNT] = $excludedStringsCount;

        return $this;
    }

    public function getTotalStringCount(): int
    {
        return (int)$this->stateFields[static::FIELD_TOTAL_STRING_COUNT];
    }

    public function setTotalStringCount(?int $totalStringsCount): SubmissionEntity
    {
        $this->stateFields[static::FIELD_TOTAL_STRING_COUNT] = $totalStringsCount;

        return $this;
    }

    public function getCompletionPercentage(): int
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

    public function getLastError(): string
    {
        return (string)$this->stateFields[static::FIELD_LAST_ERROR];
    }

    public function setLastError(string $message): void
    {
        $this->stateFields[static::FIELD_LAST_ERROR] = trim($message);
    }

    public function getBatchUid(): string
    {
        return (string)$this->stateFields[static::FIELD_BATCH_UID];
    }

    public function setBatchUid($batchUid): void
    {
        $this->stateFields[static::FIELD_BATCH_UID] = trim($batchUid);
    }

    public function getJobInfo(): JobEntity
    {
        return $this->jobInformation ?? JobEntity::EMPTY();
    }

    public function getJobInfoWithBatchUid(): JobEntityWithBatchUid
    {
        $jobInfo = $this->getJobInfo();
        return new JobEntityWithBatchUid($this->getBatchUid(), $jobInfo->getJobName(), $jobInfo->getJobUid(), $jobInfo->getProjectUid());
    }

    public function setJobInfo(JobEntity $jobInfo): void
    {
        $this->jobInformation = $jobInfo;
    }

    /**
     * @return string[]
     */
    public function getLockedFields(): array
    {
        $unserialized = maybe_unserialize($this->stateFields[static::FIELD_LOCKED_FIELDS]);
        if (!is_array($unserialized)) {
            $unserialized = [];
        }
        return $unserialized;
    }

    /**
     * @param string|array $lockFields
     */
    public function setLockedFields($lockFields): void
    {
        $this->stateFields[static::FIELD_LOCKED_FIELDS] = $lockFields;
    }

    public static function getTableName(): string
    {
        return 'smartling_submissions';
    }
}
