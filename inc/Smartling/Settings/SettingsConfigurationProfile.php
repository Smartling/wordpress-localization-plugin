<?php

namespace Smartling\Settings;

/**
 * Class SettingsConfigurationProfile
 *
 * @package inc\Smartling\Settings
 */
class SettingsConfigurationProfile {

	/**
	 * @var string
	 */
	private $apiUrl = 'https://capi.smartling.com/v1';

	/**
	 * @var string
	 */
	private $projectId;

	/**
	 * @var string
	 */
	private $userIdentifier;

	/**
	 * @var string
	 */
	private $secretKey;

	/**
	 * @var string
	 */
	private $retrievalType;

	/**
	 * @var bool false|string
	 */
	private $callBackUrl = false;

	/**
	 * @var bool
	 */
	private $autoAuthorize = false;

	/**
	 * @return string
	 */
	public function getApiUrl () {
		return $this->apiUrl;
	}

	/**
	 * @param string $apiUrl
	 */
	public function setApiUrl ( $apiUrl ) {
		$this->apiUrl = $apiUrl;
	}

	/**
	 * @return string
	 */
	public function getProjectId () {
		return $this->projectId;
	}

	/**
	 * @param string $projectId
	 */
	public function setProjectId ( $projectId ) {
		$this->projectId = $projectId;
	}

	/**
	 * @return string
	 */
	public function getSecretKey () {
		return $this->secretKey;
	}

	/**
	 * @param string $secretKey
	 */
	public function setSecretKey ( $secretKey ) {
		$this->secretKey = $secretKey;
	}

	/**
	 * @return string
	 */
	public function getUserIdentifier () {
		return $this->userIdentifier;
	}

	/**
	 * @param string $userIdentifier
	 */
	public function setUserIdentifier ( $userIdentifier ) {
		$this->userIdentifier = $userIdentifier;
	}

	/**
	 * @return string
	 */
	public function getRetrievalType () {
		return $this->retrievalType;
	}

	/**
	 * @param string $retrievalType
	 */
	public function setRetrievalType ( $retrievalType ) {
		$this->retrievalType = $retrievalType;
	}

	/**
	 * @return boolean
	 */
	public function getCallBackUrl () {
		return $this->callBackUrl;
	}

	/**
	 * @param boolean $callBackUrl
	 */
	public function setCallBackUrl ( $callBackUrl ) {
		$this->callBackUrl = $callBackUrl;
	}

	/**
	 * @return boolean
	 */
	public function getAutoAuthorize () {
		return $this->autoAuthorize;
	}

	/**
	 * @param boolean $autoAuthorize
	 */
	public function setAutoAuthorize ( $autoAuthorize ) {
		$this->autoAuthorize = $autoAuthorize;
	}

	/**
	 * @return array
	 */
	public function toArray () {
		return [
			'apiUrl'          => trim( $this->getApiUrl() ),
			'projectId'       => trim( $this->getProjectId() ),
			'user_identifier' => trim( $this->getUserIdentifier() ),
			'secret_key'      => trim( $this->getSecretKey() ),
			'retrievalType'   => trim( $this->getRetrievalType() ),
			'callBackUrl'     => trim( $this->getCallBackUrl() ),
			'autoAuthorize'   => trim( $this->getAutoAuthorize() ),
		];
	}
}