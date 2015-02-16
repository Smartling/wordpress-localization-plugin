<?php

namespace Smartling\DbAl\WordpressContentEntities;

use Psr\Log\LoggerInterface;
use Smartling\Exception\SmartlingDataUpdateException;
use Smartling\Helpers\WordpressContentTypeHelper;

/**
 * Class PostEntity
 *
 * @property null|integer $ID
 * @property integer      $post_author
 * @property string       $post_date
 * @property string       $post_date_gmt
 * @property string       $post_content
 * @property string       $post_title
 * @property null|integer $post_excerpt
 * @property string       $post_status
 * @property null|integer $comment_status
 * @property string       $ping_status
 * @property string       $post_password
 * @property string       $post_name
 * @property string       $to_ping
 * @property string       $pinged
 * @property string       $post_modified
 * @property string       $post_modified_gmt
 * @property string       $post_content_filtered
 * @property integer      $post_parent
 * @property string       $guid
 * @property integer      $menu_order
 * @property string       $post_type
 * @property string       $post_mime_type
 * @property integer      $comment_count
 * @property string       $hash
 *
 * @package Smartling\DbAl\WordpressContentEntities
 */
class PostEntity extends EntityAbstract {

	/**
	 * Standard 'post' content-type fields
	 *
	 * @var array
	 */
	protected $fields = array (
		'ID',
		'post_author',
		'post_date',
		'post_date_gmt',
		'post_content',
		'post_title',
		'post_excerpt',
		'post_status',
		'comment_status',
		'ping_status',
		'post_password',
		'post_name',
		'to_ping',
		'pinged',
		'post_modified',
		'post_modified_gmt',
		'post_content_filtered',
		'post_parent',
		'guid',
		'menu_order',
		'post_type',
		'post_mime_type',
		'comment_count',
	);

	/**
	 * @inheritdoc
	 */
	public function __construct ( LoggerInterface $logger ) {
		$this->hashAffectingFields = array_merge( $this->hashAffectingFields, array (
			'ID',
			'post_author',
			'post_content',
			'post_title',
			'post_status',
			'comment_status',
			'post_name',
			'post_parent',
			'guid',
			'post_type',
		) );

		parent::__construct( $logger );

		$this->setEntityFields( $this->fields );
	}

	/**
	 * @inheritdoc
	 */
	protected function getFieldNameByMethodName ( $method ) {
		$fieldName = parent::getFieldNameByMethodName( $method );

		// wordpress uses ID instead of id
		if ( 'iD' === $fieldName ) {
			$fieldName = 'ID';
		}

		return $fieldName;
	}

	/**
	 * @inheritdoc
	 */
	protected function getNonClonableFields () {
		return array (
			'comment_count',
			'guid',
			'ID'
		);
	}

	/**
	 * @inheritdoc
	 */
	public function get ( $guid ) {
		$post = get_post( $guid, ARRAY_A );

		if ( null !== $post ) {
			return $this->resultToEntity( $post, $this );
		} else {
			$this->entityNotFound( WordpressContentTypeHelper::CONTENT_TYPE_POST, $guid );
		}

		return $post === null ? array () : $post;
	}

	/**
	 * @inheritdoc
	 */
	public function set ( EntityAbstract $entity = null ) {
		$instance = null === $entity ? $this : $entity;

		$res = wp_insert_post( $instance->toArray(), true );

		if ( is_wp_error( $res ) ) {

			$msgFields = array ();

			$curFields = $entity->toArray();

			foreach ( $curFields as $field => $value ) {
				$msgFields[] = vsprintf( "%s = %s", array ( $field, $value ) );
			}

			$message = vsprintf( 'An error had happened while saving post to database: %s. Params: %s',
				array ( implode( ' | ', $res->get_error_messages() ), implode( ' || ', $msgFields ) ) );

			$this->getLogger()->error( $message );

			throw new SmartlingDataUpdateException( $message );

		}

		return (int) $res;
	}

	/**
	 * Loads ALL entities from database
	 *
	 * @return array
	 */
	public function getAll () {

		$arguments = array (
			'numberposts'      => '',
			'offset'           => 0,
			'category'         => 0,
			'orderby'          => 'date',
			'order'            => 'DESC',
			'include'          => array (),
			'exclude'          => array (),
			'meta_key'         => '',
			'meta_value'       => '',
			'post_type'        => 'post',
			'suppress_filters' => true
		);

		$posts = get_posts( $arguments );

		$result = array ();

		foreach ( $posts as $post ) {
			$result[] = $this->resultToEntity( $post );
		}

		return $result;

	}

	public function getTitle () {
		return $this->post_title;
	}

	/**
	 * @inheritdoc
	 */
	public function getPrimaryFieldName () {
		return 'ID';
	}
}