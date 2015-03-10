<?php

namespace Smartling\WP;

use Psr\Log\LoggerInterface;
use Smartling\Base\SmartlingCore;
use Smartling\Bootstrap;
use Smartling\Helpers\PluginInfo;

/**
 * Class JobEngine
 *
 * @package Smartling\WP
 */
class JobEngine implements WPHookInterface {

	const CRON_HOOK = 'smartling_cron_hourly_work';
	const LOCK_NAME = 'smartling-cron.pid';

	public function __construct ( $logger, $pluginInfo ) {
		$this->logger     = $logger;
		$this->pluginInfo = $pluginInfo;
	}

	public function install () {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time(), 'hourly', self::CRON_HOOK );
		}
	}

	public function uninstall () {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	public function register ( array $diagnosticData = array () ) {
		if ( false === $diagnosticData['selfBlock'] ) {
			add_action( self::CRON_HOOK, array ( $this, 'doWork' ) );
		}
	}

	/**
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * @var PluginInfo
	 */
	private $pluginInfo;

	/**
	 * @return LoggerInterface
	 */
	public function getLogger () {
		return $this->logger;
	}

	/**
	 * @return PluginInfo
	 */
	public function getPluginInfo () {
		return $this->pluginInfo;
	}

	/**
	 * @return string
	 */
	public function getLockFileName () {
		return $this->getPluginInfo()->getDir() . DIRECTORY_SEPARATOR . self::LOCK_NAME;
	}

	/**
	 * Cron job trigger handler
	 */
	public function doWork () {
		$lockFile = $this->getLockFileName();
		$fp       = fopen( $lockFile, 'w+' );
		$this->getLogger()->info( 'Cron init' );
		if ( flock( $fp, LOCK_EX ) ) {
			ftruncate( $fp, 0 );
			fwrite( $fp, sprintf( "Started: %s\nPID: %s", date( 'Y-m-d H:i:s' ), getmypid() ) );

			// run check & download
			$this->job();

			fflush( $fp );
			flock( $fp, LOCK_UN );
		} else {
			$this->getLogger()->info( "Couldn't get the lock!\nCheck {$lockFile} for more info." );
		}

		fclose( $fp );
		$this->getLogger()->info( 'Cron stop' );
	}


	/**
	 * Checks & Downloads completed translations
	 */
	public function job () {
		$this->getLogger()->info( 'Cron start' );

		/**
		 * @var SmartlingCore $ep
		 */
		$ep = Bootstrap::getContainer()->get( 'entrypoint' );
		$ep->bulkCheckNewAndInProgress();
	}
}