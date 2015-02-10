<?php

namespace Smartling\Helpers;

use Psr\Log\LoggerInterface;
use Smartling\DbAl\LocalizationPluginProxyInterface;
use Smartling\Exception\SmartlingDbException;
use Smartling\Processors\ContentEntitiesIOFactory;
use Smartling\Settings\SettingsManager;

/**
 * Class EntityHelper
 *
 * @package Smartling\Helpers
 */
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

	/**
	 * @var ContentEntitiesIOFactory
	 */
	private $contentIoFactory;

	/**
	 * @return PluginInfo
	 */
	public function getPluginInfo () {
		return $this->pluginInfo;
	}

	/**
	 * @param PluginInfo $pluginInfo
	 */
	public function setPluginInfo ( $pluginInfo ) {
		$this->pluginInfo = $pluginInfo;
	}

	/**
	 * @return LocalizationPluginProxyInterface
	 */
	public function getConnector () {
		return $this->connector;
	}

	/**
	 * @param LocalizationPluginProxyInterface $connector
	 */
	public function setConnector ( $connector ) {
		$this->connector = $connector;
	}

	/**
	 * @return SiteHelper
	 */
	public function getSiteHelper () {
		return $this->siteHelper;
	}

	/**
	 * @param SiteHelper $siteHelper
	 */
	public function setSiteHelper ( $siteHelper ) {
		$this->siteHelper = $siteHelper;
	}

	/**
	 * @return LoggerInterface
	 */
	public function getLogger () {
		return $this->logger;
	}

	/**
	 * @param LoggerInterface $logger
	 */
	public function setLogger ( $logger ) {
		$this->logger = $logger;
	}

	/**
	 * @return SettingsManager
	 */
	public function getSettingsManager () {
		return $this->getPluginInfo()->getSettingsManager();
	}

	/**
	 * @return ContentEntitiesIOFactory
	 */
	//public function getContentIoFactory () {
	//	return $this->contentIoFactory;
	//}

	/**
	 * @param ContentEntitiesIOFactory $contentIoFactory
	 */
	public function setContentIoFactory ( $contentIoFactory ) {
		$this->contentIoFactory = $contentIoFactory;
	}

	/**
	 * Returns id of original content linked to given or throws the exception
	 *
	 * @param int    $id
	 * @param string $type
	 *
	 * @return int
	 * @throws SmartlingDbException
	 */
	public function getOriginalContentId ( $id, $type = WordpressContentTypeHelper::CONTENT_TYPE_POST ) {

		$curBlog = $this->getSiteHelper()->getCurrentBlogId();
		$defBlog = $this->getSettingsManager()->getLocales()->getDefaultBlog();

		var_dump($curBlog,$defBlog);

		if ( $curBlog === $defBlog ) {
			//TODO mb some collision
			return $id;
		}

		$linkedObjects = $this->getConnector()->getLinkedObjects( $curBlog, $id, $type );

		foreach ( $linkedObjects as $blogId => $contentId ) {
			if ( $blogId === $defBlog ) {
				return $contentId;
			}
		}

		$message = vsprintf( 'For given content-type: \'%s\' id:%s in blog %s link to original content id not found',
			array (
				$type,
				$id,
				$curBlog
			) );

		$this->getLogger()->error( $message );

		throw new SmartlingDbException ( $message );
	}

	/**
	 * @param int    $id
	 * @param string $type
	 *
	 * @return int
	 * @throws \Exception not found original
	 */
	public function getTarget ( $id, $targetBlog, $type = 'post' ) {
		if ( $this->getSiteHelper()->getCurrentBlogId() == $targetBlog ) {
			return $id;
		}

		$linked = $this->getConnector()->getLinkedObjects( $this->getSiteHelper()->getCurrentBlogId(), $id, $type );
		foreach ( $linked as $key => $item ) {
			if ( $key == $targetBlog ) {
				return $item;
			}
		}

		return null;
	}

	/**
	 * @param int    $id
	 * @param string $type
	 *
	 * @return int
	 * @throws \Exception not found original
	 */
	public function createTarget ( $sourceId, $targetBlog, $type = 'post' ) {
		$targetId = $this->getTarget( $sourceId, $targetBlog, $type );
		if ( $targetId == null ) {
			$postEntity = new PostEntity( $this->getLogger() );
			$post       = $postEntity->get( $sourceId );

			$args = array (
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

			$this->getSiteHelper()->switchBlogId( $targetBlog );
			$targetId = $postEntity->insert( $args );
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