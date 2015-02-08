<?php
/**
 * Created by PhpStorm.
 * User: sergey@slepokurov.com
 * Date: 08.02.2015
 * Time: 16:26
 */

namespace Smartling\Helpers;


use Smartling\DbAl\LocalizationPluginProxyInterface;
use Smartling\Helpers\Options;
use Smartling\Helpers\PluginInfo;
use Smartling\Helpers\SiteHelper;

class EntityHelper {
	/**
	 * @var PluginInfo
	 */
	private $pluginInfo;
	/**
	 * @return LocalizationPluginProxyInterface
	 */
	private $connector;

	/**
	 * @var SiteHelper
	 */
	private $siteHelper;

	public function __construct ($pluginInfo, $connector, $siteHelper ) {
		$this->pluginInfo = $pluginInfo;
		$this->connector = $connector;
		$this->siteHelper = $siteHelper;
	}

	/**
	 * @return PluginInfo
	 */
	public function getPluginInfo () {
		return $this->pluginInfo;
	}

	/**
	 * @return Options
	 */
	public function getOptions () {
		return $this->getPluginInfo()->getOptions();
	}

	/**
	 * @return LocalizationPluginProxyInterface
	 */
	public function getConnector () {
		return $this->connector;
	}

	/**
	 * @return SiteHelper
	 */
	public function getSiteHelper () {
		return $this->siteHelper;
	}

	/**
	 * @param int $id
	 * @param string $type
	 * @return int
	 * @throws \Exception not found original
	 */
	public function getOriginal($id, $type = 'post') {
		if($this->getSiteHelper()->getCurrentBlogId() == $this->getOptions()->getLocales()->getDefaultBlog()) {
			return $id;
		}

		$linked = $this->getConnector()->getLinkedObjects($this->getSiteHelper()->getCurrentBlogId(), $id, $type);
		foreach($linked as $key => $item) {
			if($key == $this->getOptions()->getLocales()->getDefaultBlog()) {
				return $item;
			}
		}

		throw new \Exception("We can't find original item");
	}

	/**
	 * @param int $id
	 * @param string $type
	 * @return int
	 * @throws \Exception not found original
	 */
	public function getTarget($id, $targetBlog, $type = 'post') {
		if($this->getSiteHelper()->getCurrentBlogId() == $targetBlog) {
			return $id;
		}

		$linked = $this->getConnector()->getLinkedObjects($this->getSiteHelper()->getCurrentBlogId(), $id, $type);
		foreach($linked as $key => $item) {
			if($key == $targetBlog) {
				return $item;
			}
		}

		return null;
	}


}