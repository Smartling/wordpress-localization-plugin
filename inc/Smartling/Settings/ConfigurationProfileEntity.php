<?php

namespace Smartling\Settings;

use Psr\Log\LoggerInterface;
use Smartling\Base\SmartlingEntityAbstract;

/**
 * Class ConfigurationProfileEntity
 *
 * @package Smartling\Settings
 */
class ConfigurationProfileEntity extends SmartlingEntityAbstract {

	const REGEX_PROJECT_ID = '([0-9a-f]){9}';

	const REGEX_PROJECT_KEY = '[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}';

	protected static function getInstance ( LoggerInterface $logger ) {
		return new self( $logger );
	}

	static function getRetrievalTypes () {
		return array (
			'pseudo'    => __( 'Pseudo' ),
			'published' => __( 'Published' ),
			'pending'   => __( 'Pending' )
		);
	}

	/**
	 * @return array
	 */
	static function getFieldLabels () {
		return array (
			'id'             => __( 'ID' ),
			'profile_name'   => __( 'Profile Name' ),
			'api_url'        => __( 'API URL' ),
			'project_id'     => __( 'Project ID' ),
			'project_key'    => __( 'Project KEY' ),
			'is_active'      => __( 'Active' ),
			'main_locale'    => __( 'Main Locale' ),
			'auto_authorize' => __( 'Auto Authorize' ),
			'retrieval_type' => __( 'Retrieval Type' ),
		);
	}

	/**
	 * @return array
	 */
	static function getFieldDefinitions () {
		return array (
			'id'             => 'INT UNSIGNED NOT NULL AUTO_INCREMENT',
			'profile_name'   => 'VARCHAR(255) NOT NULL',
			'api_url'        => 'VARCHAR(255) NOT NULL',
			'project_id'     => 'VARCHAR(9) NOT NULL',
			'project_key'    => 'CHAR(36) NOT NULL',
			'is_active'      => 'INT(1) UNSIGNED NOT NULL',
			'main_locale'    => 'INT UNSIGNED NOT NULL',
			'auto_authorize' => 'INT(1) UNSIGNED NOT NULL',
			'retrieval_type' => vsprintf(
				'enum(\'%s\') NOT NULL',
				array ( implode( '\', \'', array_keys( self::getRetrievalTypes() ) ) ) ),
			'target_locales' => 'TEXT NULL',
		);
	}

	/**
	 * @return array
	 */
	static function getSortableFields () {
		return array (
			'profile_name'   => __( 'Profile Name' ),
			'project_id'     => __( 'Project ID' ),
			'is_active'      => __( 'Active' ),
			'main_locale'    => __( 'Main Locale' ),
			'auto_authorize' => __( 'Auto Authorize' ),
			'retrieval_type' => __( 'Retrieval Type' ),
		);
	}

	/**
	 * @return array
	 */
	static function getIndexes () {
		return array (
			array (
				'type'    => 'primary',
				'columns' => array ( 'id' )
			),
			array (
				'type'    => 'index',
				'columns' => array ( 'main_locale' )
			),
			array (
				'type'    => 'index',
				'columns' => array ( 'main_locale', 'is_active' )
			),
		);
	}

	/**
	 * @return string
	 */
	static function getTableName () {
		return 'smartling_configuration_profiles';
	}


	/**
	 * @return int
	 */
	public function getId () {
		return (int) $this->stateFields['id'];
	}

	/**
	 * @param $id
	 */
	public function setId ( $id ) {
		$this->stateFields['id'] = (int) $id;
	}

	/**
	 * @return mixed
	 */
	public function getProfileName () {
		return $this->stateFields['profile_name'];
	}

	/**
	 * @param $profileName
	 */
	public function setProfileName ( $profileName ) {
		$this->stateFields['profile_name'] = $profileName;
	}

	/**
	 * @return mixed
	 */
	public function getApiUrl () {
		return $this->stateFields['api_url'];
	}

	/**
	 * @param $apiUrl
	 */
	public function setApiUrl ( $apiUrl ) {
		$this->stateFields['api_url'] = $apiUrl;
	}

	/**
	 * @return mixed
	 */
	public function getProjectId () {
		return $this->stateFields['project_id'];
	}

	/**
	 * @param $projectId
	 */
	public function setProjectId ( $projectId ) {
		if ( preg_match( vsprintf( '/%s/ius', array ( self::REGEX_PROJECT_ID ) ), trim( $projectId, '/' ) ) ) {
			$this->stateFields['project_id'] = $projectId;
		} else {
			$this->logger->warning( vsprintf( 'Got invalid project ID: %s', array ( $projectId ) ) );
		}
	}

	/**
	 * @return mixed
	 */
	public function getProjectKey () {
		return $this->stateFields['project_key'];
	}

	/**
	 * @param $projectKey
	 */
	public function setProjectKey ( $projectKey ) {
		if ( preg_match( vsprintf( '/%s/ius', array ( self::REGEX_PROJECT_KEY ) ), trim( $projectKey, '/' ) ) ) {
			$this->stateFields['project_key'] = $projectKey;
		} else {
			$this->logger->warning( vsprintf( 'Got invalid project KEY: %s', array ( $projectKey ) ) );
		}
	}

	/**
	 * @return mixed
	 */
	public function getIsActive () {
		return $this->stateFields['is_active'];
	}

	/**
	 * @param $isActive
	 */
	public function setIsActive ( $isActive ) {
		$this->stateFields['is_active'] = (bool) $isActive;
	}

	/**
	 * @return Locale
	 */
	public function getMainLocale () {
		return $this->stateFields['main_locale'];
	}

	public function setMainLocale ( $mainLocale ) {
		$this->stateFields['main_locale'] = $mainLocale;
	}

	public function getAutoAuthorize () {
		return $this->stateFields['auto_authorize'];
	}

	public function setAutoAuthorize ( $autoAuthorize ) {
		$this->stateFields['auto_authorize'] = (bool) $autoAuthorize;
	}

	public function getRetrievalType () {
		return $this->stateFields['retrieval_type'];
	}

	public function setRetrievalType ( $retrievalType ) {
		if ( array_key_exists( $retrievalType, self::getRetrievalTypes() ) ) {
			$this->stateFields['retrieval_type'] = $retrievalType;
		} else {
			$this->logger->warning( vsprintf( 'Got invalid retrievalType: %s, expected one of: %s',
				array ( $retrievalType, implode( ', ', array_keys( self::getRetrievalTypes() ) ) ) ) );
		}
	}

	/**
	 * @return TargetLocale[]
	 */
	public function getTargetLocales () {
		return $this->stateFields['target_locales'];
	}

	public function setTargetLocales ( $targetLocales ) {
		$this->stateFields['target_locales'] = $targetLocales;
	}

	public function toArray($addVirtualColumns = true)
	{
		$state = parent::toArray(false);

		$state['main_locale'] = $this->getMainLocale()->getBlogId();

		$serializedTargetLocales = array();
		foreach($this->getTargetLocales() as $targetLocale)
		{
			$serializedTargetLocales[] = $targetLocale->toArray();
		}
		$state['target_locales'] = json_encode($serializedTargetLocales);

		return $state;
	}

	public static function fromArray(array $array, LoggerInterface $logger)
	{
		/**
		 * @var ConfigurationProfileEntity $obj
		 */
		$obj = parent::fromArray($array, $logger);

		$locale=new Locale();
		$locale->setBlogId($obj->getMainLocale());

		$obj->setMainLocale($locale);

		$unserializedTargetLocales = array();

		$decoded = json_decode($obj->getTargetLocales(), true);

		if (is_array($decoded))
		{
			foreach($decoded as $targetLocaleArr)
			{
				$unserializedTargetLocales[] = TargetLocale::fromArray($targetLocaleArr);
			}
			$obj->setTargetLocales($unserializedTargetLocales);

		} else {
			$obj->setTargetLocales(array());
		}


		return $obj;
	}
}