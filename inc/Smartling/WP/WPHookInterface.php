<?php

namespace Smartling\WP;

/**
 * Interface WPHookInterface
 *
 * @package Smartling\WP
 */
interface WPHookInterface {

	/**
	 * Registers wp hook handlers. Invoked by wordpress.
	 *
	 * @param array $diagnosticData
	 */
	public function register (array $diagnosticData = array());
}