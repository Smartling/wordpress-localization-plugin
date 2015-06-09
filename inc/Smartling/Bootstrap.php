<?php

namespace Smartling;

use Exception;
use Psr\Log\LoggerInterface;
use Smartling\Exception\MultilingualPluginNotFoundException;
use Smartling\Exception\SmartlingConfigException;
use Smartling\Helpers\DiagnosticsHelper;
use Smartling\Settings\ConfigurationProfileEntity;
use Smartling\Settings\SettingsManager;
use Smartling\WP\WPHookInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * Class Bootstrap
 *
 * @package Smartling
 */
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
	 * @throws Exception
	 */
	public static function getLogger () {
		$object = self::getContainer()->get( 'logger' );

		if ( $object instanceof LoggerInterface ) {
			return $object;
		} else {
			$message = 'Something went wrong with initialization of DI Container and logger cannot be retrieved.';
			throw new Exception( $message );
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
		$container->setParameter( 'plugin.upload', SMARTLING_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'upload' );

		$pluginUrl = '';
		if ( defined( 'SMARTLING_CLI_EXECUTION' ) && false === SMARTLING_CLI_EXECUTION ) {
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
		register_shutdown_function( array ( $this, 'shutdownHandler' ) );

		$this->detectMultilangPlugins();

		try {
			if ( defined( 'SMARTLING_CLI_EXECUTION' ) && SMARTLING_CLI_EXECUTION === false ) {
				$this->test();
				$this->registerHooks();
				$this->run();
			}
		} catch ( Exception $e ) {
			$message = "Unhandled exception caught. Disabling plugin.\n";
			$message .= "Message: '" . $e->getMessage() . "'\n";
			$message .= "Location: '" . $e->getFile() . ':' . $e->getLine() . "'\n";
			$message .= "Trace: " . $e->getTraceAsString() . "\n";
			self::$_logger->emergency( $message );
			DiagnosticsHelper::addDiagnosticsMessage( $message, true );
		}
	}

	/**
	 * Last chance to know what had happened if Wordpress is down.
	 */
	public function shutdownHandler () {
		$logger = Bootstrap::getLogger();

		$loggingPattern = E_ALL
		                  ^ E_NOTICE
		                  ^ E_WARNING
		                  ^ E_USER_NOTICE
		                  ^ E_USER_WARNING
		                  ^ E_STRICT
		                  ^ E_DEPRECATED;

		$data = error_get_last();

		/**
		 * @var int $errorType
		 */
		$errorType = &$data['type'];

		if ( $errorType && $loggingPattern ) {
			$message = "An Error occurred and Wordpress is down.\n";
			$message .= "Message: '" . $data['message'] . "'\n";
			$message .= "Location: '" . $data['file'] . ':' . $data['line'] . "'\n";
			$logger->emergency( $message );
		}
	}

	public function activate () {
		self::getContainer()->set( 'multilang_plugins', array () );
		$this->fromContainer( 'site.db' )->install();
		$this->fromContainer( 'wp.cron' )->install();
	}

	public function deactivate () {
		$this->fromContainer( 'wp.cron' )->uninstall();
	}

	public function uninstall () {
		if ( defined( 'SMARTLING_COMPLETE_REMOVE' ) ) {
			$this->fromContainer( 'site.db' )->uninstall();
		}
	}

	/**
	 * @throws MultilingualPluginNotFoundException
	 */
	public function detectMultilangPlugins ( $scielent = true ) {
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

		$logger->debug( 'Searching for Wordpress multilingual plugins' );

		$_found = false;

		if ( class_exists( 'Mlp_Load_Controller', false ) ) {
			$mlPluginsStatuses['multilingual-press-pro'] = true;
			$logger->debug( 'found "multilingual-press" plugin' );

			$_found = true;
		}

		if ( false === $_found ) {
			$message = 'No active multilingual plugins found.';

			$logger->warning( $message );

			if ( ! $scielent ) {
				throw new MultilingualPluginNotFoundException( $message );
			}
		}

		self::getContainer()->setParameter( 'multilang_plugins', $mlPluginsStatuses );
	}

	public function checkUploadFolder () {
		$path = $this->getContainer()->getParameter( 'plugin.upload' );
		if ( ! file_exists( $path ) ) {
			mkdir( $path, 0777 );
		}
	}

	/**
	 * Tests if current Wordpress Configuration can work with Smartling Plugin
	 *
	 * @return mixed
	 */
	protected function test () {
		$this->testThirdPartyPluginsRequirements();

		$php_extensions = array (
			'curl',
			'mbstring'
		);

		foreach ( $php_extensions as $ext ) {
			$this->testPhpExtension( $ext );
		}

		$this->testPluginSetup();

		// display adminpanel-wide diagnostic error messgaes.
		add_action( 'admin_notices', array ( $this, 'displayMessages' ) );
	}

	public function displayMessages () {
		$type = 'error';
		$messages=DiagnosticsHelper::getMessages();
		if (0 < count($messages))
		{
			$msg = '';
			foreach($messages as $message)
			{
				$msg .= vsprintf( '<div class="%s"><p>%s</p></div>', array ( $type, $message ) );
			}


			echo $msg;
		}

	}

	protected function testThirdPartyPluginsRequirements () {
		/**
		 * @var array $data
		 */
		$data = self::getContainer()->getParameter( 'multilang_plugins' );

		$blockWork = true;

		foreach ( $data as $value ) {
			// there is at least one plugin that can be used
			if ( true === $value ) {
				$blockWork = false;
				break;
			}
		}

		if ( true === $blockWork ) {
			$mainMessage = 'No active suitable localization plugin found. Please install and activate one, e.g.: <a href="/wp-admin/network/plugin-install.php?tab=search&s=multilingual+press">Multilingual Press.</a>';

			self::$_logger->critical( 'Boot :: ' . $mainMessage );

			DiagnosticsHelper::addDiagnosticsMessage( $mainMessage, true );
		}
	}

	protected function testPhpExtension ( $extension ) {
		if ( ! extension_loaded( $extension ) ) {
			$mainMessage = $extension . ' php extension is required to run the plugin is not installed or enabled.';

			self::$_logger->critical( 'Boot :: ' . $mainMessage );

			DiagnosticsHelper::addDiagnosticsMessage( $mainMessage, true );
		}
	}

	protected function testPluginSetup () {
		/**
		 * @var SettingsManager $sm
		 */
		$sm = self::getContainer()->get( 'manager.settings' );

		$total    = 0;
		$profiles = $sm->getEntities( array (), null, $total, true );

		if ( 0 === count( $profiles ) ) {
			$mainMessage = 'No active smartling configuration profiles found. Please create at least one on <a href="/wp-admin/admin.php?page=smartling_configuration_profile_list">settings page</a>';

			self::$_logger->critical( 'Boot :: ' . $mainMessage );

			DiagnosticsHelper::addDiagnosticsMessage( $mainMessage, true );
		}
	}

	public function run () {
		$this->checkUploadFolder();
	}
}
