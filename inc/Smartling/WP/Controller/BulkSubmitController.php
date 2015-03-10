<?php
/**
 * Created by PhpStorm.
 * User: sergey@slepokurov.com
 * Date: 02.03.2015
 * Time: 11:44
 */

namespace Smartling\WP\Controller;


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
	public function register ( array $diagnosticData = array () ) {
		if ( false === $diagnosticData['selfBlock'] ) {
			add_action( 'admin_menu', array ( $this, 'menu' ) );
			add_action( 'network_admin_menu', array ( $this, 'menu' ) );
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
		$this->getEntityHelper()->getSiteHelper()->switchBlogId( $this->getEntityHelper()->getSettingsManager()->getLocales()->getDefaultBlog() );
		$table = new BulkSubmitTableWidget(
			$this->getManager(),
			$this->getPluginInfo(),
			$this->getEntityHelper()
		);
		$this->view( $table );
		$this->getEntityHelper()->getSiteHelper()->restoreBlogId();
	}
}