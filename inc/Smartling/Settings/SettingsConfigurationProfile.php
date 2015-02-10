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
	private $apiUrl = 'https://api.smartling.com/v1';

	/**
	 * @var string
	 */
	private $projectId;

	/**
	 * @var string
	 */
	private $key;

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
	public function getKey () {
		return $this->key;
	}

	/**
	 * @param string $key
	 */
	public function setKey ( $key ) {
		$this->key = $key;
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
	 * @param string $key
	 *
	 * @return array|false
	 */
	public function get ( $key ) {
		$values = get_site_option( $key );
		if ( $values ) {
			$this->setApiUrl( $values['apiUrl'] );
			$this->setProjectId( $values['projectId'] );
			$this->setKey( $values['key'] );
			$this->setRetrievalType( $values['retrievalType'] );
			$this->setCallBackUrl( $values['callBackUrl'] );
			$this->setAutoAuthorize( $values['autoAuthorize'] );
		}

		return $values;
	}

	/**
	 * @param string $key
	 */
	public function save ( $key ) {
		$option = get_site_option( $key );
		$values = $this->toArray();
		if ( ! $option ) {
			add_site_option( $key, $values );
		} else {
			update_site_option( $key, $values );
		}
	}

	/**
	 * @return array
	 */
	public function toArray () {
		return array (
			'apiUrl'        => trim( $this->getApiUrl() ),
			'projectId'     => trim( $this->getProjectId() ),
			'key'           => trim( $this->getKey() ),
			'retrievalType' => trim( $this->getRetrievalType() ),
			'callBackUrl'   => trim( $this->getCallBackUrl() ),
			'autoAuthorize' => trim( $this->getAutoAuthorize() )
		);
	}
}