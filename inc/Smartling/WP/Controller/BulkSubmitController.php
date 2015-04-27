<?php
namespace Smartling\WP\Controller;

use Smartling\Helpers\DiagnosticsHelper;
use Smartling\Helpers\HtmlTagGeneratorHelper;
use Smartling\WP\View\BulkSubmitTableWidget;
use Smartling\WP\WPAbstract;
use Smartling\WP\WPHookInterface;

/**
 * Class BulkSubmitController
 *
 * @package Smartling\WP\Controller
 */
class BulkSubmitController
	extends WPAbstract
	implements WPHookInterface {

	/**
	 * @inheritdoc
	 */
	public function register () {
		if ( ! DiagnosticsHelper::isBlocked() ) {
			add_action( 'admin_menu', array ( $this, 'menu' ) );
		}
	}

	public function menu () {
		add_submenu_page(
			'smartling-submissions-page',
			'Bulk Submit',
			'Bulk Submit',
			'Administrator',
			'smartling-bulk-submit',
			array (
				$this,
				'renderPage'
			)
		);
	}

	public function renderPage () {
		$currentBlogId = $this->getEntityHelper()->getSiteHelper()->getCurrentBlogId();

		$applicableProfiles = $this->getEntityHelper()->getSettingsManager()->findEntityByMainLocale( $currentBlogId );

		if ( 0 === count( $applicableProfiles ) ) {
			echo HtmlTagGeneratorHelper::tag( 'p', __( 'No suitable profile found for this site.' ) );
		} else {
			$profile = reset( $applicableProfiles );
			$table   = new BulkSubmitTableWidget(
				$this->getManager(),
				$this->getPluginInfo(),
				$this->getEntityHelper(),
				$profile
			);
			$this->view( $table );
		}
	}
}