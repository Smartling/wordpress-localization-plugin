<?php

namespace Smartling\DbAl\WordpressContentEntities;

use Psr\Log\LoggerInterface;

/**
 * Class CategoryEntity
 *
 * @property int    $term_id
 * @property string $name
 * @property string slug
 * @property int    $term_group
 * @property int    $term_taxonomy_id
 * @property string $taxonomy
 * @property string $description
 * @property int    $parent
 * @property int    $count
 * @property int    $cat_ID
 * @property int    $category_count
 * @property string $category_description
 * @property string $cat_name
 * @property string $category_nicename
 * @property int    category_parent
 *
 * @package Smartling\DbAl\WordpressContentEntities
 */
class CategoryEntity extends EntityAbstract {

	/**
	 * Standard 'category' content-type fields
	 *
	 * @var array
	 */
	protected $fields = array (
		'term_id',
		'name',
		'slug',
		'term_group',
		'term_taxonomy_id',
		'taxonomy',
		'description',
		'parent',
		'count',
		'cat_ID',
		'category_count',
		'category_description',
		'cat_name',
		'category_nicename',
		'category_parent'
	);

	/**
	 * @inheritdoc
	 */
	public function __construct ( LoggerInterface $logger ) {
		$this->hashAffectingFields = array (
			'name',
			'slug',
			'taxonomy',
			'description',
			'category_description',
			'cat_name',
			'category_nicename',
		);

		parent::__construct( $logger );

		$this->setEntityFields( $this->fields );
	}

	/**
	 * Loads the entity from database
	 *
	 * @param $guid
	 *
	 * @return mixed
	 */
	public function get ( $guid ) {
		$category = get_category($guid, ARRAY_A);

		if ( null !== $category ) {
			return $this->resultToEntity( (object) $category, $this );
		} else {
			$this->entityNotFound( WordpressContentTypeHelper::CONTENT_TYPE_POST, $guid );
		}
	}

	/**
	 * Loads ALL entities from database
	 *
	 * @return mixed
	 */
	public function getAll () {

		$categories = get_categories();

		$result = array ();

		foreach ( $categories as $category ) {
			$result[] = $this->resultToEntity( $category );
		}

		return $result;
	}

	/**
	 * Stores entity to database
	 *
	 * @param EntityAbstract $entity
	 *
	 * @return mixed
	 */
	public function set ( EntityAbstract $entity = null ) {
		$instance = null === $entity ? $this : $entity;

		$res = wp_insert_category( $instance->toArray(), true );

		return $res;
	}


	public function getTitle()
	{
		return $this->name;
	}


}