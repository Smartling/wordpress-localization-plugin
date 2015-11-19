<?php

namespace Smartling\Helpers\EventParameters;

use Smartling\DbAl\WordpressContentEntities\EntityAbstract;
use Smartling\Submissions\SubmissionEntity;

/**
 * Class BeforeSerializeContentEventParameters
 *
 * @package Smartling\Helpers\EventParameters
 */
class BeforeSerializeContentEventParameters {

	/**
	 * @var array
	 */
	private $preparedFields;

	/**
	 * @var SubmissionEntity
	 */
	private $submission;

	/**
	 * @var EntityAbstract
	 */
	private $originalContent;

	/**
	 * @var array
	 */
	private $originalMetadata;


	public function __construct (
		array & $source,
		SubmissionEntity $submission,
		EntityAbstract $contentEntity,
		array $meta
	) {
		$this->setPreparedFields( $source );
		$this->setSubmission( $submission );
		$this->setOriginalContent( $contentEntity );
		$this->setOriginalMetadata( $meta );
	}

	/**
	 * @return array by reference for update
	 */
	public function &getPreparedFields () {
		return $this->preparedFields;
	}

	/**
	 * @param array $preparedFields
	 */
	private function setPreparedFields ( & $preparedFields ) {
		$this->preparedFields = &$preparedFields;
	}

	/**
	 * @return SubmissionEntity
	 */
	public function getSubmission () {
		return $this->submission;
	}

	/**
	 * @param SubmissionEntity $submission
	 */
	private function setSubmission ( $submission ) {
		$this->submission = $submission;
	}

	/**
	 * @return EntityAbstract
	 */
	public function getOriginalContent () {
		return $this->originalContent;
	}

	/**
	 * @param EntityAbstract $originalContent
	 */
	private function setOriginalContent ( $originalContent ) {
		$this->originalContent = $originalContent;
	}

	/**
	 * @return array
	 */
	public function getOriginalMetadata () {
		return $this->originalMetadata;
	}

	/**
	 * @param array $originalMetadata
	 */
	private function setOriginalMetadata ( $originalMetadata ) {
		$this->originalMetadata = $originalMetadata;
	}


}