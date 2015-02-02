<?php

namespace Smartling\DbAl\WordpressContentEntities;

use Psr\Log\LoggerInterface;
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
	 * List of fields that affect the hash of the entity
	 * @var array
	 */
	protected $hashAffectingFields = array (
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
	);

	/**
	 * @inheritdoc
	 */
	public function calculateHash () {
		$sourceSting = '';

		foreach ( $this->hashAffectingFields as $fieldName ) {
			$sourceSting .= $this->$fieldName;
		}

		return md5( $sourceSting );
	}

	/**
	 * @inheritdoc
	 */
	public function __construct ( LoggerInterface $logger ) {
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
	public function get ( $guid ) {
		$post = get_post( $guid, ARRAY_A );

		if ( null !== $post ) {

			foreach ( $this->fields as $fieldName ) {
				$this->$fieldName = $post[ $fieldName ];
			}

			$this->hash = $this->calculateHash();

			return (object) $this;
		} else {
			$this->entityNotFound( WordpressContentTypeHelper::CONTENT_TYPE_POST, $guid );
		}
	}

	/**
	 * @inheritdoc
	 */
	public function set ( EntityAbstract $entity ) {
		// TODO: Implement set() method.
	}
}