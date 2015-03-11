<?php

namespace Smartling\WP;

use Smartling\Helpers\PluginInfo;

/**
 * Class i18n
 *
 * @package Smartling\WP
 */
class i18n implements WPHookInterface {
	/**
	 * @var PluginInfo
	 */
	private $pluginInfo;

	/**
	 * @param PluginInfo $pluginInfo
	 */
	public function __construct ( PluginInfo $pluginInfo ) {
		$this->pluginInfo = $pluginInfo;
	}

	/**
	 * @inheritdoc
	 */
	public function register () {
		load_plugin_textdomain(
			$this->pluginInfo->getDomain(),
			false,
			$this->pluginInfo->getDir() . DIRECTORY_SEPARATOR . 'languages' . DIRECTORY_SEPARATOR
		);
	}
}