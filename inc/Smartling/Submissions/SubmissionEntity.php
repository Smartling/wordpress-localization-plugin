<?php

namespace Smartling\Submissions;

use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Smartling\Base\SmartlingEntityAbstract;
use Smartling\Helpers\TextHelper;
use Smartling\Helpers\WordpressContentTypeHelper;

/**
 * Class SubmissionEntity
 *
 * @property int|null       $id
 * @property string         $source_title
 * @property int|null       $source_blog_id
 * @property string|null    $source_content_hash
 * @property string         $content_type
 * @property int            $source_id
 * @property string         $file_uri
 * @property string         $target_locale
 * @property int            $target_blog_id
 * @property int|null       $target_id
 * @property string         $submitter
 * @property string         $submission_date
 * @property string|null    $applied_date
 * @property int            $approved_string_count
 * @property int            $completed_string_count
 * @property string         $status
 *
 * @package Smartling\Submissions
 */
class SubmissionEntity extends SmartlingEntityAbstract {

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

	/**
	 * @var array Submission Statuses
	 */
	public static $submissionStatuses = array (
		self::SUBMISSION_STATUS_NOT_TRANSLATED,
		self::SUBMISSION_STATUS_NEW,
		self::SUBMISSION_STATUS_IN_PROGRESS,
		self::SUBMISSION_STATUS_COMPLETED,
		self::SUBMISSION_STATUS_FAILED,
	);

	public static function getFieldDefinitions () {
		return array (
			'id'                     => 'INT UNSIGNED NOT NULL AUTO_INCREMENT',
			'source_title'           => 'VARCHAR(255) NOT NULL',
			'source_blog_id'         => 'INT UNSIGNED NOT NULL',
			'source_content_hash'    => 'CHAR(32) NULL',
			'content_type'           => 'VARCHAR(32) NOT NULL',
			'source_id'              => 'INT UNSIGNED NOT NULL',
			'file_uri'               => 'VARCHAR(255) NULL',
			'target_locale'          => 'VARCHAR(16) NOT NULL',
			'target_blog_id'         => 'INT UNSIGNED NOT NULL',
			'target_id'              => 'INT UNSIGNED NULL',
			'submitter'              => 'VARCHAR(255) NOT NULL',
			'submission_date'        => 'DATETIME NOT NULL',
			'applied_date'           => 'DATETIME NULL',
			'approved_string_count'  => 'INT UNSIGNED NULL',
			'completed_string_count' => 'INT UNSIGNED NULL',
			'word_count'             => 'INT UNSIGNED NULL',
			'status'                 => 'VARCHAR(16) NOT NULL',
		);
	}

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
			'id'              => __( 'ID' ),
			'source_title'    => __( 'Title' ),
			'content_type'    => __( 'Type' ),
			'file_uri'        => __( 'Smartling File URI' ),
			'target_locale'   => __( 'Locale' ),
			'submitter'       => __( 'Submitter' ),
			'submission_date' => __( 'Time Submitted' ),
			'applied_date'    => __( 'Time Applied' ),
			'word_count'      => __( 'Words' ),
			'progress'        => __( 'Progress' ),
			'status'          => __( 'Status' ),
		);
	}

	protected static function getInstance ( LoggerInterface $logger ) {
		return new self( $logger );
	}

	public static function getSortableFields () {
		return array (
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
		);
	}

	public static function getIndexes () {
		return array (
			array (
				'type'    => 'primary',
				'columns' => array ( 'id' )
			),
			array (
				'type'    => 'index',
				'columns' => array ( 'content_type' )
			),
			array (
				'type'    => 'index',
				'columns' => array ( 'source_blog_id', 'source_id', 'content_type' )
			),
		);
	}

	/**
	 * @return array
	 */
	protected function getVirtualFields () {
		return array (
			'progress' => $this->getCompletionPercentage() . '%'
		);
	}

	/**
	 * @return int
	 */
	public function getWordCount () {
		return (int) $this->stateFields['word_count'];
	}

	/**
	 * @param int $word_count
	 */
	public function setWordCount ( $word_count ) {
		$this->stateFields['word_count'] = (int) $word_count;
	}

	/**
	 * @return string
	 */
	public function getStatus () {
		return $this->stateFields['status'];
	}

	/**
	 * @param string $status
	 *
	 * @return SubmissionEntity
	 */
	public function setStatus ( $status ) {
		if ( in_array( $status, self::$submissionStatuses ) ) {
			$this->stateFields['status'] = $status;
		} else {
			$message = vsprintf(
				'Invalid status value. Got \'%s\', expected one of: %s',
				array (
					$status,
					implode(
						',',
						self::$submissionStatuses
					)
				)
			);

			$this->logger->error( $message );

			throw new InvalidArgumentException( $message );
		}

		return $this;
	}

	/**
	 * @return string
	 */
	public function getStatusColor () {
		$statusColors = array (
			self::SUBMISSION_STATUS_NOT_TRANSLATED => 'yellow',
			self::SUBMISSION_STATUS_NEW            => 'yellow',
			self::SUBMISSION_STATUS_IN_PROGRESS    => 'blue',
			self::SUBMISSION_STATUS_COMPLETED      => 'green',
			self::SUBMISSION_STATUS_FAILED         => 'red',
		);

		return $statusColors[ $this->getStatus() ] ? : '';
	}

	/**
	 * @return int|null
	 */
	public function getId () {
		return $this->stateFields['id'];
	}

	/**
	 * @param int $id
	 *
	 * @return SubmissionEntity
	 */
	public function setId ( $id ) {
		$this->stateFields['id'] = null === $id ? $id : (int) $id;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getSourceTitle ( $withReplacement = true ) {
		$source_title = $this->stateFields['source_title'];

		if ( $withReplacement ) {
			$source_title = mb_strlen( $source_title, 'utf8' ) > 255
				? TextHelper::mb_wordwrap( $source_title, 252 ) . '...'
				: $source_title;
		}

		return $source_title;
	}

	/**
	 * @param string $source_title
	 *
	 * @return SubmissionEntity
	 */
	public function setSourceTitle ( $source_title ) {
		$this->stateFields['source_title'] = $source_title;

		return $this;
	}

	/**
	 * @return int
	 */
	public function getSourceBlogId () {
		return (int) $this->stateFields['source_blog_id'];
	}

	/**
	 * @param int $source_blog_id
	 *
	 * @return SubmissionEntity
	 */
	public function setSourceBlogId ( $source_blog_id ) {
		$this->stateFields['source_blog_id'] = (int) $source_blog_id;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getSourceContentHash () {
		return $this->stateFields['source_content_hash'];
	}

	/**
	 * @param string $source_content_hash
	 *
	 * @return SubmissionEntity
	 */
	public function setSourceContentHash ( $source_content_hash ) {
		$this->stateFields['source_content_hash'] = $source_content_hash;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getContentType () {
		return $this->stateFields['content_type'];
	}

	/**
	 * @param string $content_type
	 *
	 * @return SubmissionEntity
	 */
	public function setContentType ( $content_type ) {
		$reverseMap = WordpressContentTypeHelper::getReverseMap();

		if ( array_key_exists( $content_type, $reverseMap ) ) {
			$this->stateFields['content_type'] = $reverseMap[ $content_type ];
		} else {
			$message = vsprintf(
				'Invalid content type. Got \'%s\', expected one of: %s',
				array (
					$content_type,
					implode(
						',',
						$reverseMap
					)
				)
			);
			$this->logger->error( $message );
			throw new \InvalidArgumentException( $message );
		}

		return $this;
	}

	/**
	 * @return string
	 */
	public function getSourceId () {
		return (int) $this->stateFields['source_id'];
	}

	/**
	 * @param string $source_id
	 *
	 * @return SubmissionEntity
	 */
	public function setSourceId ( $source_id ) {
		$this->stateFields['source_id'] = (int) $source_id;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getFileUri () {
		if ( empty( $this->stateFields['file_uri'] ) ) {

			$fileUri = vsprintf( '%s_%s_%s_%s.xml', array (
				trim( TextHelper::mb_wordwrap( $this->getSourceTitle( false ), 210 ), "\n\r\t,. -_\0\x0B" ),
				$this->getContentType(),
				$this->getSourceBlogId(),
				$this->getSourceId()
			) );

			$this->setFileUri( $fileUri );
		}

		return $this->stateFields['file_uri'];
	}

	/**
	 * @param string $file_uri
	 *
	 * @return SubmissionEntity
	 */
	protected function setFileUri ( $file_uri ) {
		$this->stateFields['file_uri'] = $file_uri;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getTargetLocale () {
		return $this->stateFields['target_locale'];
	}

	/**
	 * @param string $target_locale
	 *
	 * @return SubmissionEntity
	 */
	public function setTargetLocale ( $target_locale ) {
		$this->stateFields['target_locale'] = $target_locale;

		return $this;
	}

	/**
	 * @return int
	 */
	public function getTargetBlogId () {
		return (int) $this->stateFields['target_blog_id'];
	}

	/**
	 * @param int $target_blog_id
	 *
	 * @return SubmissionEntity
	 */
	public function setTargetBlogId ( $target_blog_id ) {
		$this->stateFields['target_blog_id'] = (int) $target_blog_id;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getTargetId () {
		return $this->stateFields['target_id'];
	}

	/**
	 * @param string $target_id
	 *
	 * @return SubmissionEntity
	 */
	public function setTargetId ( $target_id ) {
		$this->stateFields['target_id'] = $target_id;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getSubmitter () {
		return $this->stateFields['submitter'];
	}

	/**
	 * @param string $submitter
	 *
	 * @return SubmissionEntity
	 */
	public function setSubmitter ( $submitter ) {
		$this->stateFields['submitter'] = $submitter;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getSubmissionDate () {
		return $this->stateFields['submission_date'];
	}

	/**
	 * @param string $submission_date
	 *
	 * @return SubmissionEntity
	 */
	public function setSubmissionDate ( $submission_date ) {
		$this->stateFields['submission_date'] = $submission_date;

		return $this;
	}

	/**
	 * @return null|string
	 */
	public function getAppliedDate () {
		return $this->stateFields['applied_date'];
	}

	/**
	 * @param null|string $applied_date
	 */
	public function setAppliedDate ( $applied_date ) {
		$this->stateFields['applied_date'] = $applied_date;
	}

	/**
	 * @return int
	 */
	public function getApprovedStringCount () {
		return (int) $this->stateFields['approved_string_count'];
	}

	/**
	 * @param int $approved_string_count
	 *
	 * @return SubmissionEntity
	 */
	public function setApprovedStringCount ( $approved_string_count ) {
		$this->stateFields['approved_string_count'] = (int) $approved_string_count;

		return $this;
	}

	/**
	 * @return int
	 */
	public function getCompletedStringCount () {
		return (int) $this->stateFields['completed_string_count'];
	}

	/**
	 * @param int $completed_string_count
	 *
	 * @return SubmissionEntity
	 */
	public function setCompletedStringCount ( $completed_string_count ) {
		$this->stateFields['completed_string_count'] = (int) $completed_string_count;

		return $this;
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

	/**
	 * @return string
	 */
	public static function getTableName () {
		return 'smartling_submissions';
	}
}