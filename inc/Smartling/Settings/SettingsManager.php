<?php

namespace Smartling\Settings;

use Psr\Log\LoggerInterface;
use Smartling\Processors\PropertyMapper;
use Smartling\Processors\PropertyMapperFactory;

/**
 * Class SettingsManager
 *
 * @package inc\Smartling\Settings
 */
class SettingsManager {

	const SMARTLING_ACCOUNT_INFO = 'smartling_options';

	const SMARTLING_LOCALES = 'smartling_locales';

	private $retrievalTypes = array (
		'pseudo',
		'published',
		'pending'
	);

	/**
	 * @var SettingsConfigurationProfile
	 */
	private $accountInfo;
	/**
	 * @var Locales
	 */
	private $locales;

	/**
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * @var PropertyMapper
	 */
	private $mapperWrapper;

	/**
	 * @return PropertyMapper
	 */
	public function getMapperWrapper () {
		return $this->mapperWrapper;
	}

	/**
	 * @return LoggerInterface
	 */
	public function getLogger () {
		return $this->logger;
	}


	/**
	 * Constructor
	 *
	 * @param LoggerInterface       $logger
	 * @param PropertyMapperFactory $mapperWrapper
	 */
	function __construct ( LoggerInterface $logger, PropertyMapperFactory $mapperWrapper ) {

		$this->logger         = $logger;
		$this->mapperWrapper = $mapperWrapper;

		$this->accountInfo = new SettingsConfigurationProfile();
		$this->locales     = new Locales();
		$this->get();
	}

	/**
	 * @param int $blogId
	 *
	 * @return SettingsConfigurationProfile
	 */
	public function getAccountInfo ($blogId = 1) {
		return $this->accountInfo;
	}

	/**
	 * @return Locales
	 */
	public function getLocales () {
		return $this->locales;
	}

	public function save () {
		$this->getAccountInfo()->save( self::SMARTLING_ACCOUNT_INFO );
		$this->getLocales()->save( self::SMARTLING_LOCALES );
	}

	public function get () {
		$this->getAccountInfo()->get( self::SMARTLING_ACCOUNT_INFO );
		$this->getLocales()->get( self::SMARTLING_LOCALES );
	}

	public function uninstall () {
		delete_site_option( self::SMARTLING_ACCOUNT_INFO );
		delete_site_option( self::SMARTLING_LOCALES );
	}

	/**
	 * @return array
	 */
	public function getRetrievalTypes () {
		return $this->retrievalTypes;
	}
}