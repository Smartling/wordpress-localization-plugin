<?php

namespace Smartling;

use Psr\Log\LoggerInterface;
use Smartling\Helpers\XmlEncoder;
use Smartling\WP\WPHookInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Smartling\Exception\MultilingualPluginNotFoundException;
use Smartling\Exception\SmartlingConfigException;

class Bootstrap {

	/**
	 * @var ContainerBuilder $container
	 */
	private static $_container = null;

	/**
	 * @var LoggerInterface
	 */
	private static $_logger = null;

	/**
	 * @return LoggerInterface
	 * @throws \Exception
	 */
	public static function getLogger () {
		$object = self::getContainer()->get( 'logger' );

		if ( $object instanceof LoggerInterface ) {
			return $object;
		} else {
			$message = 'Something went wrong with initialization of DI Container and logger cannot be retrieved.';
			throw new \Exception( $message );
		}
	}


	/**
	 * Initializes DI Container from YAML config file
	 *
	 * @throws SmartlingConfigException
	 */
	protected static function _initContainer () {
		$container = new ContainerBuilder();

		self::setCoreParameters( $container );

		$configDir = SMARTLING_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'inc';

		$fileLocator = new FileLocator( $configDir );

		$loader = new YamlFileLoader( $container, $fileLocator );

		try {
			$loader->load( 'services.yml' );
		} catch ( \Exception $e ) {
			throw new SmartlingConfigException( 'Error in YAML configuration file', 0, $e );
		}

		self::$_container = $container;
		self::$_logger    = $container->get( 'logger' );
	}

	/**
	 * Extracts mixed from container
	 *
	 * @param string $id
	 * @param bool   $is_param
	 *
	 * @return mixed
	 */
	protected function fromContainer ( $id, $is_param = false ) {
		$container = self::getContainer();
		$content   = null;

		if ( $is_param ) {
			$content = $container->getParameter( $id );
		} else {
			$content = $container->get( $id );
		}

		return $content;
	}

	/**
	 * @return ContainerBuilder
	 * @throws SmartlingConfigException
	 */
	public static function getContainer () {
		if ( is_null( self::$_container ) ) {
			self::_initContainer();
		}

		return self::$_container;
	}

	private static function setCoreParameters ( ContainerBuilder $container ) {
		// plugin dir (to use in config file)
		$container->setParameter( 'plugin.dir', SMARTLING_PLUGIN_DIR );
		$container->setParameter( 'plugin.upload', SMARTLING_PLUGIN_DIR . DIRECTORY_SEPARATOR . "upload" );

		$pluginUrl = '';
		if (  defined( 'SMARTLING_CLI_EXECUTION' ) && false === SMARTLING_CLI_EXECUTION ) {
			$pluginUrl = plugin_dir_url( SMARTLING_PLUGIN_DIR . DIRECTORY_SEPARATOR . '..' );
		}

		$container->setParameter( 'plugin.url', $pluginUrl );
	}

	public function registerHooks () {
		$hooks = $this->fromContainer( 'wp.hooks', true );
		foreach ( $hooks as $hook ) {
			$object = $this->fromContainer( $hook );
			if ( $object instanceof WPHookInterface ) {
				$object->register();
			}
		}
	}

	public function load () {
		$this->detectMultilangPlugins();

		if (  defined( 'SMARTLING_CLI_EXECUTION' ) && SMARTLING_CLI_EXECUTION === false ) {
			$this->registerHooks();
			$this->run();
		}
	}

	public function activate () {
		$this->fromContainer( 'site.db' )->install();
	}

	public function deactivate () {

	}

	public function uninstall () {
		$this->fromContainer( 'manager.settings' )->uninstall();
		$this->fromContainer( 'site.db' )->uninstall();
	}

	/**
	 * @throws MultilingualPluginNotFoundException
	 */
	public function detectMultilangPlugins () {
		/**
		 * @var LoggerInterface $logger
		 */
		$logger = self::getContainer()->get( 'logger' );

		$mlPluginsStatuses =
			array (
				'multilingual-press-pro' => false,
				'polylang'               => false,
				'wpml'                   => false,
			);

		$logger->info( 'Searching for Wordpress multilingual plugins' );

		$_found = false;

		if ( class_exists( 'Mlp_Load_Controller', false ) ) {
			$mlPluginsStatuses['multilingual-press-pro'] = true;
			$logger->info( 'found "multilingual-press-pro" plugin' );

			$_found = true;
		}

		if ( false === $_found ) {
			throw new MultilingualPluginNotFoundException( 'No active multilingual plugins found.' );
		}

		self::getContainer()->set( 'multilang_plugins', $mlPluginsStatuses );
	}

	public function checkUploadFolder() {
		$path = $this->getContainer()->getParameter( 'plugin.upload' );
		if(!file_exists($path)) {
			mkdir($path, 0777);
		}
	}

	public function run () {
		$this->checkUploadFolder();
	}
}
