<?php

namespace Smartling\WP\Controller;

use Smartling\Base\SmartlingCore;
use Smartling\Bootstrap;
use Smartling\Helpers\EntityHelper;
use Psr\Log\LoggerInterface;
use Smartling\DbAl\LocalizationPluginProxyInterface;
use Smartling\Helpers\WordpressContentTypeHelper;
use Smartling\Helpers\PluginInfo;
use Smartling\Submissions\SubmissionManager;
use Smartling\WP\View\SubmissionTableWidget;
use Smartling\WP\WPAbstract;
use Smartling\WP\WPHookInterface;

/**
 * Class SubmissionsPageController
 *
 * @package Smartling\WP\Controller
 */
class SubmissionsPageController
	extends WPAbstract
	implements WPHookInterface {

	/**
	 * @inheritdoc
	 */
	public function register ( array $diagnosticData = array () ) {
		//if ( false === $diagnosticData['selfBlock'] ) {
			add_action( 'admin_menu', array ( $this, 'menu' ) );
			add_action( 'network_admin_menu', array ( $this, 'menu' ) );
		//}
	}

	public function menu () {
		add_menu_page(
			'Submissions Board',
			'Smartling',
			'Administrator',
			'smartling-submissions-page',
			array ( $this, 'renderPage' ) );
	}

	public function renderPage () {
		$table = new SubmissionTableWidget( $this->getManager() );
		$this->view( $table );
	}
}