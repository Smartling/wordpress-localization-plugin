<?php
/**
 * Created by PhpStorm.
 * User: sergey@slepokurov.com
 * Date: 08.02.2015
 * Time: 16:26
 */

namespace Smartling\Helpers;


use Psr\Log\LoggerInterface;
use Smartling\DbAl\LocalizationPluginProxyInterface;
use Smartling\DbAl\WordpressContentEntities\PostEntity;
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

	/**
	 * @var LoggerInterface
	 */
	private $logger;

	public function __construct ($pluginInfo, $connector, $siteHelper, $logger ) {
		$this->pluginInfo = $pluginInfo;
		$this->connector = $connector;
		$this->siteHelper = $siteHelper;
		$this->logger = $logger;
	}

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
			//TODO mb some collision
			return $id;
		}

		$linked = $this->getConnector()->getLinkedObjects($this->getSiteHelper()->getCurrentBlogId(), $id, $type);
		foreach($linked as $key => $item) {
			if($key == $this->getOptions()->getLocales()->getDefaultBlog()) {
				return $item;
			}
		}

	//	throw new \Exception("We can't find original item");
		return null;
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

	/**
	 * @param int $id
	 * @param string $type
	 * @return int
	 * @throws \Exception not found original
	 */
	public function createTarget($sourceId, $targetBlog, $type = 'post') {
		$targetId = $this->getTarget($sourceId, $targetBlog, $type);
		if($targetId == null) {
			$postEntity = new PostEntity($this->getLogger());
			$post = $postEntity->get($sourceId);

			$args = array(
				'comment_status' => $post["comment_status"],
				'ping_status'    => $post["ping_status"],
				'post_author'    => $post["post_author"],
				'post_content'   => $post["post_content"],
				'post_excerpt'   => $post["post_excerpt"],
				'post_name'      => $post["post_name"],
				'post_parent'    => $post["post_parent"],
				'post_password'  => $post["post_password"],
				'post_status'    => 'draft',
				'post_title'     => $post["post_title"],
				'post_type'      => $post["post_type"],
				'to_ping'        => $post["to_ping"],
				'menu_order'     => $post["menu_order"]
			);

			$this->getSiteHelper()->switchBlogId($targetBlog);
			$targetId = $postEntity->insert($args);
			$this->getSiteHelper()->restoreBlogId();
			//TODO Duplicate taxonomies, duplicate metainfo
		}

		return $targetId;
	}

	/*
	 * $taxonomies = get_object_taxonomies($post->post_type); // returns array of taxonomy names for post type, ex array("category", "post_tag");
		foreach ($taxonomies as $taxonomy) {
			$post_terms = wp_get_object_terms($post_id, $taxonomy, array('fields' => 'slugs'));
			wp_set_object_terms($new_post_id, $post_terms, $taxonomy, false);
		}


		$post_meta_infos = $wpdb->get_results("SELECT meta_key, meta_value FROM $wpdb->postmeta WHERE post_id=$post_id");
		if (count($post_meta_infos)!=0) {
		$sql_query = "INSERT INTO $wpdb->postmeta (post_id, meta_key, meta_value) ";
		foreach ($post_meta_infos as $meta_info) {
		$meta_key = $meta_info->meta_key;
		$meta_value = addslashes($meta_info->meta_value);
		$sql_query_sel[]= "SELECT $new_post_id, '$meta_key', '$meta_value'";
		}
		$sql_query.= implode(" UNION ALL ", $sql_query_sel);
		$wpdb->query($sql_query);
		}
	 */
}