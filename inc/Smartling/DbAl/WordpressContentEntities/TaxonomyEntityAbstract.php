<?php

namespace Smartling\DbAl\WordpressContentEntities;

use Psr\Log\LoggerInterface;
use Smartling\Exception\EntityNotFoundException;
use Smartling\Exception\SmartlingDbException;
use Smartling\Helpers\WordpressContentTypeHelper;

/**
 * Class TaxonomyEntityAbstract
 *
 * @package Smartling\DbAl\WordpressContentEntities
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
 */
abstract class TaxonomyEntityAbstract extends EntityAbstract {

	/**
	 * Type of taxonomy ('category','tag', etc.)
	 *
	 * @var string
	 */
	private $taxonomyType = '';

	/**
	 * Standard taxonomy fields
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
	);

	/**
	 * @return string
	 */
	public function getTaxonomyType () {
		return $this->taxonomyType;
	}

	/**
	 * @param string $taxonomyType
	 */
	public function setTaxonomyType ( $taxonomyType ) {
		$this->taxonomyType = $taxonomyType;
	}

	/**
	 * @inheritdoc
	 */
	public function __construct ( LoggerInterface $logger ) {
		$this->hashAffectingFields = array (
			'name',
			'description',
		);

		parent::__construct( $logger );

		$this->setEntityFields( $this->fields );
	}

	/**
	 * @inheritdoc
	 */
	public function getTitle () {
		return $this->name;
	}

	/**
	 * @param $guid
	 *
	 * @return array
	 *
	 * @throws SmartlingDbException
	 * @throws EntityNotFoundException
	 */
	public function get ( $guid ) {
		$term = get_term( $guid, $this->getTaxonomyType(), ARRAY_A );

		if ( $term instanceof \WP_Error ) {
			$message = vsprintf( 'An error occurred while reading taxonomy id=%s, type=%s: %s',
				array ( $guid, $this->getTaxonomyType(), $term->get_error_message() ) );

			$this->getLogger()->error( $message );

			throw new SmartlingDbException( $message );
		}

		if ( null === $term ) {
			$this->entityNotFound( $this->getTaxonomyType(), $guid );
		}

		return $this->resultToEntity( $term, $this );
	}

	/**
	 * Loads ALL entities from database
	 *
	 * @return TaxonomyEntityAbstract[]
	 * @throws SmartlingDbException
	 */
	public function getAll () {

		$taxonomies = array (
			$this->getTaxonomyType(),
		);

		$args = array (
			'orderby'           => 'term_id',
			'order'             => 'ASC',
			'hide_empty'        => false,
			'exclude'           => array (),
			'exclude_tree'      => array (),
			'include'           => array (),
			'number'            => '',
			'fields'            => 'all',
			'slug'              => '',
			'parent'            => '',
			'hierarchical'      => true,
			'child_of'          => 0,
			'get'               => '',
			'name__like'        => '',
			'description__like' => '',
			'pad_counts'        => false,
			'offset'            => '',
			'search'            => '',
			'cache_domain'      => 'core'
		);

		$terms = get_terms( $taxonomies, $args );

		if ( $terms instanceof \WP_Error ) {
			$message = vsprintf( 'An error occurred while reading all taxonomies of type %s: %s',
				array ( $this->getTaxonomyType(), $terms->get_error_message() ) );

			$this->getLogger()->error( $message );

			throw new SmartlingDbException( $message );
		}

		$result = array ();

		foreach ( $terms as $term ) {
			$result[] = $this->resultToEntity( (array) $term );
		}

		return $result;
	}


	/**
	 * @param EntityAbstract $entity
	 *
	 * @return array
	 * @throws SmartlingDbException
	 */
	public function set ( EntityAbstract $entity = null ) {

		$me = get_class( $this );

		if ( ! ( $entity instanceof $me ) ) {
			$entity = $this;
		}

		$update = ! ( null === $entity->term_id );

		$data = $entity->toArray();

		$argFields = array (
			'name',
			'slug',
			'parent',
			'description'
		);

		$args = array ();

		foreach ( $argFields as $field ) {
			$argFields[ $field ] = $data[ $field ];
		}

		$result = $update
			? wp_update_term( $entity->term_id, $entity->taxonomy, $args )
			: wp_insert_term( $entity->name, $entity->taxonomy, $args );

		if ( $result instanceof \WP_Error ) {
			$message = vsprintf(
				'An error occurred while saving taxonomy id=%s, type=%s: %s',
				array (
					( $entity->term_id ? $entity->term_id : '<none>' ),
					$this->getTaxonomyType(),
					$result->get_error_message()
				)
			);

			$this->getLogger()->error( $message );

			throw new SmartlingDbException( $message );
		}

		foreach ( $result as $field => $value ) {
			$entity->$field = $value;
		}

		return $entity->{$this->getPrimaryFieldName()};

	}

	/**
	 * @inheritdoc
	 */
	protected function getNonClonableFields () {
		return array (
			'term_id',
			'parent',
			'count',
		);
	}

	public static function detectTermTaxonomyByTermId ( $termId ) {
		$taxonomies = WordpressContentTypeHelper::getSupportedTaxonomyTypes();

		$args = array (
			'orderby'           => 'term_id',
			'order'             => 'ASC',
			'hide_empty'        => false,
			'exclude'           => array (),
			'exclude_tree'      => array (),
			'include'           => array (),
			'number'            => '',
			'fields'            => 'all',
			'slug'              => '',
			'parent'            => '',
			'hierarchical'      => true,
			'child_of'          => 0,
			'get'               => '',
			'name__like'        => '',
			'description__like' => '',
			'pad_counts'        => false,
			'offset'            => '',
			'search'            => '',
			'cache_domain'      => 'core'
		);

		$terms = get_terms( $taxonomies, $args );


		$result = array ();

		if ( $terms instanceof \WP_Error ) {
			$message = vsprintf( 'An error occurred while readin all taxonomies of type: %s',
				array ( $terms->get_error_message() ) );

			throw new SmartlingDbException( $message );
		} else {
			foreach ( $terms as $term ) {
				if ( (int) $term->term_id === (int) $termId ) {
					$result[] = $term->taxonomy;
					break;
				}
			}
		}

		return $result;
	}

	public function cleanFields ( $value = null ) {
		parent::cleanFields( $value );
		$this->name = '';
	}

	/**
	 * @inheritdoc
	 */
	public function getPrimaryFieldName () {
		return 'term_id';
	}

}