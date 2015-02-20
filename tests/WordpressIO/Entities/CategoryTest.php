<?php

use Smartling\Bootstrap;
use Smartling\DbAl\WordpressContentEntities\CategoryEntity;
use Smartling\Exception\SmartlingInvalidFactoryArgumentException;
use Smartling\Helpers\WordpressContentTypeHelper;
use Smartling\Processors\ContentEntitiesIOFactory;

/**
 * Class CategoryTest
 */
class CategoryTest extends PHPUnit_Framework_TestCase {

	/**
	 * @var ContentEntitiesIOFactory
	 */
	private $ioFactory;

	public function __construct ( $name = null, array $data = array (), $dataName = '' ) {
		parent::__construct( $name, $data, $dataName );

		$this->init();
	}

	private function init () {


		defined( 'ARRAY_A' ) || define( 'ARRAY_A', 'ARRAY_A' );

		if ( ! function_exists( 'get_term' ) ) {
			function get_term ( $id, $taxonomy, $outputFormat ) {
				$category = array (
					'term_id'          => 1,
					'name'             => 'Fake Category',
					'slug'             => 'fake-category',
					'term_group'       => 0,
					'term_taxonomy_id' => 0,
					'taxonomy'         => 'category',
					'description'      => '',
					'parent'           => 0,
					'count'            => 0
				);

				return $category;
			}
		}


		$this->ioFactory = Bootstrap::getContainer()->get( 'factory.contentIO' );
	}

	public function testGetCategoryWrapper () {
		$type = WordpressContentTypeHelper::CONTENT_TYPE_CATEGORY;

		$wrapper = $this->ioFactory->getMapper( $type );

		self::assertTrue( $wrapper instanceof CategoryEntity );
	}


	public function testGetCategoryWrapperException () {
		$type = WordpressContentTypeHelper::CONTENT_TYPE_CATEGORY;

		$type = strrev( $type );
		try {
			$wrapper = $this->ioFactory->getMapper( $type );
		} catch ( SmartlingInvalidFactoryArgumentException $e ) {
			self::assertTrue( $e instanceof SmartlingInvalidFactoryArgumentException );
		}
	}

	public function testReadCategory () {
		$type = WordpressContentTypeHelper::CONTENT_TYPE_CATEGORY;

		$wrapper = $this->ioFactory->getMapper( $type );

		$result = $wrapper->get( 1 );


		self::assertTrue( $result instanceof CategoryEntity );

		self::assertTrue( $result->term_id === 1 );

		self::assertTrue( $result->name === 'Fake Category' );

		self::assertTrue( $result->slug === 'fake-category' );

		self::assertTrue( $result->taxonomy === $type );
	}

	public function testCloneCategory () {
		$type = WordpressContentTypeHelper::CONTENT_TYPE_CATEGORY;

		$wrapper = $this->ioFactory->getMapper( $type );

		$result = $wrapper->get( 1 );

		$clone = clone $result;

		$originalClass = get_class( $result );

		self::assertTrue( $clone instanceof $originalClass );

		self::assertTrue( $clone !== $result );

	}

	public function testCleanCategoryFields () {
		$type = WordpressContentTypeHelper::CONTENT_TYPE_CATEGORY;

		$wrapper = $this->ioFactory->getMapper( $type );

		$result = $wrapper->get( 1 );

		$clone = clone $result;

		$clone->cleanFields();

		self::assertTrue( null === $clone->term_id );
	}


	public function testCreateCategory () {
		if ( ! function_exists( 'wp_insert_term' ) ) {
			function wp_insert_term ( $name, $type, $args ) {
				return array_merge( $args, array ( 'term_id' => 2 ) );
			}
		}

		$type = WordpressContentTypeHelper::CONTENT_TYPE_CATEGORY;

		$wrapper = $this->ioFactory->getMapper( $type );

		$result = $wrapper->get( 1 );

		$clone = clone $result;

		$clone->cleanFields();

		$clone->name = 'test';
		$clone->slug = 'test';

		$id = $wrapper->set( $clone );

		self::assertTrue( 2 === $id );
	}


	public function testUpdateCategory () {
		if ( ! function_exists( 'wp_update_term' ) ) {
			function wp_update_term ( $id, $type, $args ) {
				return array_merge( $args, array ( 'term_id' => $id ) );
			}
		}

		$type = WordpressContentTypeHelper::CONTENT_TYPE_CATEGORY;

		$wrapper = $this->ioFactory->getMapper( $type );

		$result = $wrapper->get( 1 );

		$result->name .= 'new';

		$id = $wrapper->set( $result );

		self::assertTrue( 1 === $id );
	}
}