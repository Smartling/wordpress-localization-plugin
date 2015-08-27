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
		return [
			'pseudo'    => __( 'Pseudo' ),
			'published' => __( 'Published' ),
			'pending'   => __( 'Pending' ),
		];
	}

	/**
	 * @return array
	 */
	static function getFieldLabels () {
		return [
			'id'               => __( 'ID' ),
			'profile_name'     => __( 'Profile Name' ),
			//'api_url'        => __( 'API URL' ),
			'project_id'       => __( 'Project ID' ),
			'api_key'          => __( 'Api Key' ),
			'is_active'        => __( 'Active' ),
			'original_blog_id' => __( 'Main Locale' ),
			'auto_authorize'   => __( 'Auto Authorize' ),
			'retrieval_type'   => __( 'Retrieval Type' ),
		];
	}

	/**
	 * @return array
	 */
	static function getFieldDefinitions () {
		return [
			'id'               => self::DB_TYPE_U_BIGINT . ' ' . self::DB_TYPE_INT_MODIFIER_AUTOINCREMENT,
			'profile_name'     => self::DB_TYPE_STRING_STANDARD,
			'api_url'          => self::DB_TYPE_STRING_STANDARD,
			'project_id'       => 'CHAR(9) NOT NULL',
			'api_key'          => 'CHAR(36) NOT NULL',
			'is_active'        => self::DB_TYPE_UINT_SWITCH,
			'original_blog_id' => self::DB_TYPE_U_BIGINT,
			'auto_authorize'   => self::DB_TYPE_UINT_SWITCH,
			'retrieval_type'   => self::DB_TYPE_STRING_SMALL,
			'target_locales'   => 'TEXT NULL',
		];
	}

	/**
	 * @return array
	 */
	static function getSortableFields () {
		return [
			'profile_name',
			'project_id',
			'is_active',
			'original_blog_id',
			'auto_authorize',
			'retrieval_type',
		];
	}

	/**
	 * @return array
	 */
	static function getIndexes () {
		return [
			[
				'type'    => 'primary',
				'columns' => [ 'id' ],
			],
			[
				'type'    => 'index',
				'columns' => [ 'original_blog_id', 'is_active' ],
			],
		];
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
		return $this->stateFields['api_url'] ? : 'https://capi.smartling.com/v1';
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
		$this->stateFields['project_id'] = $projectId;

		if ( ! preg_match( vsprintf( '/%s/ius', [ self::REGEX_PROJECT_ID ] ), trim( $projectId, '/' ) ) ) {
			$this->logger->warning( vsprintf( 'Got invalid project ID: %s', [ $projectId ] ) );
		}
	}

	/**
	 * @return mixed
	 */
	public function getApiKey () {
		return $this->stateFields['api_key'];
	}

	/**
	 * @param $projectKey
	 */
	public function setApiKey ( $projectKey ) {
		if ( ! preg_match( vsprintf( '/%s/ius', [ self::REGEX_PROJECT_KEY ] ), trim( $projectKey, '/' ) ) ) {
			$this->logger->warning( vsprintf( 'Got invalid project KEY: %s', [ $projectKey ] ) );
		} else {
			$this->stateFields['api_key'] = $projectKey;
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
		$this->stateFields['is_active'] = (int) $isActive;
	}

	/**
	 * @return Locale
	 */
	public function getOriginalBlogId () {
		return $this->stateFields['original_blog_id'];
	}

	public function setOriginalBlogId ( $mainLocale ) {
		$this->stateFields['original_blog_id'] = $mainLocale;
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
				[ $retrievalType, implode( ', ', array_keys( self::getRetrievalTypes() ) ) ] ) );
		}
	}

	/**
	 * @return TargetLocale[]
	 */
	public function getTargetLocales () {
		if ( ! array_key_exists( 'target_locales', $this->stateFields ) ) {
			$this->setTargetLocales( [ ] );
		}

		return $this->stateFields['target_locales'];
	}

	public function setTargetLocales ( $targetLocales ) {
		$this->stateFields['target_locales'] = $targetLocales;
	}

	public function toArray ( $addVirtualColumns = true ) {
		$state = parent::toArray( false );

		$state['original_blog_id'] = $this->getOriginalBlogId()->getBlogId();

		$state['auto_authorize'] = ! $state['auto_authorize'] ? 0 : 1;
		$state['is_active']      = ! $state['is_active'] ? 0 : 1;

		$serializedTargetLocales = [ ];
		if ( 0 < count( $this->getTargetLocales() ) ) {
			foreach ( $this->getTargetLocales() as $targetLocale ) {
				$serializedTargetLocales[] = $targetLocale->toArray();
			}
		}
		$state['target_locales'] = json_encode( $serializedTargetLocales );

		return $state;
	}

	public static function fromArray ( array $array, LoggerInterface $logger ) {

		if ( ! array_key_exists( 'target_locales', $array ) ) {
			$array['target_locales'] = '';
		}

		/**
		 * @var ConfigurationProfileEntity $obj
		 */
		$obj = parent::fromArray( $array, $logger );

		$locale = new Locale();
		$locale->setBlogId( $obj->getOriginalBlogId() );

		$obj->setOriginalBlogId( $locale );

		$unserializedTargetLocales = [ ];

		$curLocales = $obj->getTargetLocales();

		if ( is_string( $curLocales ) ) {
			$decoded = json_decode( $curLocales, true );

			if ( is_array( $decoded ) ) {
				foreach ( $decoded as $targetLocaleArr ) {
					$unserializedTargetLocales[] = TargetLocale::fromArray( $targetLocaleArr );
				}
				$obj->setTargetLocales( $unserializedTargetLocales );

			} else {
				$obj->setTargetLocales( [ ] );
			}
		}

		return $obj;
	}
}