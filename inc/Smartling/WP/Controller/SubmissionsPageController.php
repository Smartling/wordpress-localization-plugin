<?php

namespace Smartling\WP\Controller;

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
	public function register () {
		add_action( 'admin_menu', [ $this, 'menu' ] );
		add_action( 'network_admin_menu', [ $this, 'menu' ] );
	}

	public function menu () {
		add_menu_page(
			'Submissions Board',
			'Smartling',
			'Administrator',
			'smartling-submissions-page',
			[ $this, 'renderPage' ] );
	}

	public function renderPage () {
		$table = new SubmissionTableWidget( $this->getManager(), $this->getEntityHelper() );
		$table->prepare_items();
		$this->view( $table );
	}
}