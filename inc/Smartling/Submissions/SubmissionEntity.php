<?php

namespace Smartling\Submissions;

use Psr\Log\LoggerInterface;
use Smartling\Bootstrap;
use Smartling\DbAl\LocalizationPluginProxyInterface;
use Smartling\Helpers\WordpressContentTypeHelper;

/**
 * Class SubmissionEntity
 *
 * @property integer|null $id
 * @property string       $sourceTitle
 * @property integer|null $sourceBlog
 * @property string|null  $sourceContentHash
 * @property string       $contentType
 * @property string       $sourceGUID
 * @property string       $fileUri
 * @property string       $targetLocale
 * @property integer      $targetBlog
 * @property string       $targetGUID
 * @property string       $submitter
 * @property integer      $submissionDate
 * @property integer      $approvedStringCount
 * @property integer      $completedStringCount
 * @property string       $status
 *
 * @package Smartling\Submissions
 */
class SubmissionEntity {

	/**
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * @var array
	 */
	private $initialFields = array ();

	/**
	 * @var bool
	 */
	private $initialValuesFixed = false;

	/**
	 * @var array
	 */
	public static $fieldsDefinition = array (
		'id'                   => 'INT UNSIGNED NOT NULL AUTO_INCREMENT',
		'sourceTitle'          => 'VARCHAR(255) NOT NULL',
		'sourceBlog'           => 'INT UNSIGNED NOT NULL',
		'sourceContentHash'    => 'CHAR(32) NULL',
		'contentType'          => 'VARCHAR(32) NOT NULL',
		'sourceGUID'           => 'VARCHAR(255) NOT NULL',
		'fileUri'              => 'VARCHAR(255) NOT NULL',
		'targetLocale'         => 'VARCHAR(16) NOT NULL',
		'targetBlog'           => 'INT UNSIGNED NOT NULL',
		'targetGUID'           => 'VARCHAR(255) NOT NULL',
		'submitter'            => 'VARCHAR(255) NOT NULL',
		'submissionDate'       => 'INT UNSIGNED NOT NULL',
		'approvedStringCount'  => 'INT UNSIGNED NOT NULL',
		'completedStringCount' => 'INT UNSIGNED NOT NULL',
		'status'               => 'VARCHAR(16) NOT NULL',
	);

	/**
	 * Submission Status  'Not Translated'
	 */
	const SUBMISSION_STATUS_NOT_TRANSLATED = 'Not Translated';

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

	/**+
	 * @var array Submission Statuses
	 */
	public static $submissionStatuses = array (
		self::SUBMISSION_STATUS_NOT_TRANSLATED,
		self::SUBMISSION_STATUS_NEW,
		self::SUBMISSION_STATUS_IN_PROGRESS,
		self::SUBMISSION_STATUS_COMPLETED,
		self::SUBMISSION_STATUS_FAILED,
	);

	/**
	 * @return array
	 */
	public static function getSubmissionStatusLabels () {
		return array (
			self::SUBMISSION_STATUS_NOT_TRANSLATED => __( self::SUBMISSION_STATUS_NOT_TRANSLATED ),
			self::SUBMISSION_STATUS_NEW            => __( self::SUBMISSION_STATUS_NEW ),
			self::SUBMISSION_STATUS_IN_PROGRESS    => __( self::SUBMISSION_STATUS_IN_PROGRESS ),
			self::SUBMISSION_STATUS_COMPLETED      => __( self::SUBMISSION_STATUS_COMPLETED ),
			self::SUBMISSION_STATUS_FAILED         => __( self::SUBMISSION_STATUS_FAILED ),
		);
	}


	/**
	 * @return array
	 */
	public static function getFieldLabels () {
		return array (
			'id'                  => __( 'ID' ),
			'sourceTitle'         => __( 'Title' ),
			'sourceBlog'          => __( 'Source Blog ID' ),
			//'sourceContentHash'     => 'Content hash',
			'contentType'         => __( 'Type' ),
			'sourceGUID'          => __( 'Source URI' ),
			'fileUri'             => __( 'Smartling File URI' ),
			'targetLocale'        => __( 'Locale' ),
			'targetBlog'          => __( 'Target Blog ID' ),
			//'targetGUID'            => 'Target URI',
			'submitter'           => __( 'Submitter' ),
			'submissionDate'      => __( 'Submitted' ),
			'approvedStringCount' => __( 'Approved Words' ),
			'progress'            => __( 'Progress' ),
			'status'              => __( 'Status' ),
		);
	}

	/**
	 * @var array
	 */
	public static $fieldsSortable = array (
		'id',
		'sourceTitle',
		//'sourceBlog',
		'contentType',
		//'sourceGUID',
		'fileUri',
		'targetLocale',
		//'targetBlog',
		//'targetGUID',
		'submitter',
		'submissionDate',
		'approvedStringCount',
		//'completedStringCount',
		'progress',
		'status',
	);

	/**
	 * @var array
	 */
	public static $indexes = array (
		array (
			'type'    => 'primary',
			'columns' => array ( 'id' )
		),
		array (
			'type'    => 'index',
			'columns' => array ( 'contentType' )
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
	 * @param mixed  $value
	 */
	public function __set ( $key, $value ) {
		if ( in_array( $key, array_keys( self::$fieldsDefinition ) ) ) {

			$setter = 'set' . ucfirst( $key );

			if ( ! $this->initialValuesFixed && ! array_key_exists( $key, $this->initialFields ) ) {
				$this->initialFields[ $key ] = $value;
			}

			$this->$setter( $value );
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
	public function __get ( $key ) {
		if ( in_array( $key, array_keys( self::$fieldsDefinition ) ) ) {

			$getter = 'get' . ucfirst( $key );

			return $this->$getter();
		}
	}

	public function fixInitialValues () {
		$this->initialValuesFixed = true;
	}

	public function getChangedFields () {
		$fieldList = array_keys( self::$fieldsDefinition );

		$changedFields = array ();

		foreach ( $fieldList as $field ) {
			$initiallValue = array_key_exists( $field, $this->initialFields ) ?
				$this->initialFields[ $field ] : null;
			$currentValue  = $this->$field;

			if ( $initiallValue !== $currentValue ) {
				$changedFields[ $field ] = $currentValue;
			}
		}

		return $changedFields;
	}

	/**
	 * Converts associative array to SubmissionEntity
	 * array keys must match field names;
	 *
	 * @param array           $array
	 * @param LoggerInterface $logger
	 *
	 * @return SubmissionEntity
	 */
	public static function fromArray ( array $array, LoggerInterface $logger ) {
		$obj = new self( $logger );

		foreach ( $array as $field => $value ) {
			$obj->$field = $value;
		}

		// Fix initial values to detect what fields were changed.
		$obj->fixInitialValues();

		return $obj;
	}

	/**
	 * @param bool $addVirtualColumns
	 *
	 * @return array
	 */
	public function toArray ( $addVirtualColumns = true ) {
		$arr = array ();

		foreach ( array_keys( self::$fieldsDefinition ) as $field ) {
			$arr[ $field ] = $this->$field;
		}

		if ( true === $addVirtualColumns ) {
			$arr['progress'] = $this->getCompletionPercentage() . '%';
		}


		return $arr;
	}

	/**
	 * Constructor
	 *
	 * @param LoggerInterface $logger
	 */
	public function __construct ( LoggerInterface $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Submission unique id
	 *
	 * @var null|integer
	 */
	private $id;

	/**
	 * Submission Entity title
	 *
	 * @var string
	 */
	private $sourceTitle;

	/**
	 * Source content blog id
	 *
	 * @var integer
	 */
	private $sourceBlog;

	/**
	 * Hash of source content to find out if it is changed
	 *
	 * @var string
	 */
	private $sourceContentHash;

	/**
	 * ContentType as a constant from WordpressContentTypeHelper
	 *
	 * @var string
	 */
	private $contentType;

	/**
	 * unique identifier of source content
	 *
	 * @var string
	 */
	private $sourceGUID;

	/**
	 * Smartling API content package unique identifier
	 *
	 * @var string
	 */
	private $fileUri;

	/**
	 * Target locale
	 *
	 * @var string
	 */
	private $targetLocale;

	/**
	 * Id of linked blog to place the translation on 'download'
	 *
	 * @var integer
	 */
	private $targetBlog;

	/**
	 * unique identifier of target content
	 *
	 * @var string
	 */
	private $targetGUID;

	/**
	 * Submitter identity
	 *
	 * @var string
	 */
	private $submitter;

	/**
	 * Date and Time of submission
	 *
	 * @var string
	 */
	private $submissionDate;

	/**
	 * Count of words in source content
	 *
	 * @var integer
	 */
	private $approvedStringCount;

	/**
	 * Count of translated words
	 *
	 * @var integer
	 */
	private $completedStringCount;

	/**
	 * @var string
	 */
	private $status;

	/**
	 * @return string
	 */
	public function getStatus () {
		return $this->status;
	}

	/**
	 * @param string $status
	 */
	public function setStatus ( $status ) {
		if ( in_array( $status, self::$submissionStatuses ) ) {
			$this->status = $status;
		} else {
			$message = vsprintf( 'Invalid content type. Got \'%s\', expected one of: %s',
				array ( $status, implode( ',', self::$submissionStatuses ) ) );

			$this->logger->error( $message );

			throw new \InvalidArgumentException( $message );
		}
	}


	/**
	 * @return int|null
	 */
	public function getId () {
		return $this->id;
	}

	/**
	 * @param int $id
	 */
	public function setId ( $id ) {
		$this->id = (int) $id;
	}

	/**
	 * @return string
	 */
	public function getSourceTitle () {
		return $this->sourceTitle;
	}

	/**
	 * @param string $sourceTitle
	 */
	public function setSourceTitle ( $sourceTitle ) {
		$this->sourceTitle = $sourceTitle;
	}

	/**
	 * @return int
	 */
	public function getSourceBlog () {
		return (int) $this->sourceBlog;
	}

	/**
	 * @param int $sourceBlog
	 */
	public function setSourceBlog ( $sourceBlog ) {
		$this->sourceBlog = (int) $sourceBlog;
	}

	/**
	 * @return string
	 */
	public function getSourceContentHash () {
		return $this->sourceContentHash;
	}

	/**
	 * @param string $sourceContentHash
	 */
	public function setSourceContentHash ( $sourceContentHash ) {
		$this->sourceContentHash = $sourceContentHash;
	}

	/**
	 * @return string
	 */
	public function getContentType () {
		return $this->contentType;
	}

	/**
	 * @param string $contentType
	 */
	public function setContentType ( $contentType ) {
		$reverseMap = WordpressContentTypeHelper::getReverseMap();

		if ( in_array( $contentType, array_keys( $reverseMap ) ) ) {
			$this->contentType = $reverseMap[ $contentType ];
		} else {
			$message = vsprintf( 'Invalid content type. Got \'%s\', expected one of: %s',
				array ( $contentType, implode( ',', $reverseMap ) ) );

			$this->logger->error( $message );

			throw new \InvalidArgumentException( $message );
		}
	}

	/**
	 * @return string
	 */
	public function getSourceGUID () {
		return $this->sourceGUID;
	}

	/**
	 * @param string $sourceGUID
	 */
	public function setSourceGUID ( $sourceGUID ) {
		$this->sourceGUID = $sourceGUID;
	}

	/**
	 * @return string
	 */
	public function getFileUri () {
		return $this->fileUri;
	}

	/**
	 * @param string $fileUri
	 */
	public function setFileUri ( $fileUri ) {
		$this->fileUri = $fileUri;
	}

	/**
	 * @return string
	 */
	public function getTargetLocale () {
		return $this->targetLocale;
	}

	/**
	 * @param string $targetLocale
	 */
	public function setTargetLocale ( $targetLocale ) {
		$this->targetLocale = $targetLocale;
	}

	/**
	 * @return int
	 */
	public function getTargetBlog () {
		return (int) $this->targetBlog;
	}

	/**
	 * @param int $targetBlog
	 */
	public function setTargetBlog ( $targetBlog ) {
		$this->targetBlog = (int) $targetBlog;
	}

	/**
	 * @return string
	 */
	public function getTargetGUID () {
		return $this->targetGUID;
	}

	/**
	 * @param string $targetGUID
	 */
	public function setTargetGUID ( $targetGUID ) {
		$this->targetGUID = $targetGUID;
	}

	/**
	 * @return string
	 */
	public function getSubmitter () {
		return $this->submitter;
	}

	/**
	 * @param string $submitter
	 */
	public function setSubmitter ( $submitter ) {
		$this->submitter = $submitter;
	}

	/**
	 * @return string
	 */
	public function getSubmissionDate () {
		return $this->submissionDate;
	}

	/**
	 * @param string $submissionDate
	 */
	public function setSubmissionDate ( $submissionDate ) {
		$this->submissionDate = $submissionDate;
	}

	/**
	 * @return int
	 */
	public function getApprovedStringCount () {
		return (int) $this->approvedStringCount;
	}

	/**
	 * @param int $approvedStringCount
	 */
	public function setApprovedStringCount ( $approvedStringCount ) {
		$this->approvedStringCount = (int) $approvedStringCount;
	}

	/**
	 * @return int
	 */
	public function getCompletedStringCount () {
		return (int) $this->completedStringCount;
	}

	/**
	 * @param int $completedStringCount
	 */
	public function setCompletedStringCount ( $completedStringCount ) {
		$this->completedStringCount = (int) $completedStringCount;
	}

	/**
	 * @return int
	 */
	public function getCompletionPercentage () {
		$percentage = 0;

		if ( 0 !== $this->getApprovedStringCount() ) {
			$percentage = $this->getCompletedStringCount() / $this->getApprovedStringCount();
		}

		if ( $percentage > 1 ) {
			$percentage = 1;
		}

		return (int) ( $percentage * 100 );
	}
}