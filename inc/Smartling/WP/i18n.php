<?php
/**
 * Created by PhpStorm.
 * User: sergey@slepokurov.com
 * Date: 20.01.2015
 * Time: 14:28
 */

namespace Smartling\WP;

use Smartling\WP\WPHookInterface;
use Smartling\Helpers\PluginInfo;


class i18n implements WPHookInterface {
	/**
	 * @var PluginInfo
	 */
	private $pluginInfo;

	public function __construct ( PluginInfo $pluginInfo ) {
		$this->pluginInfo = $pluginInfo;
	}

	public function register () {
		load_plugin_textdomain(
			$this->pluginInfo->getDomain(),
			false,
			$this->pluginInfo->getDir() . DIRECTORY_SEPARATOR . 'languages' . DIRECTORY_SEPARATOR
		);
	}
}