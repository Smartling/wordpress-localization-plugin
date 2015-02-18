<?php

use Smartling\Bootstrap;
use Smartling\DbAl\WordpressContentEntities\TagEntity;
use Smartling\Exception\SmartlingInvalidFactoryArgumentException;
use Smartling\Helpers\WordpressContentTypeHelper;
use Smartling\Processors\ContentEntitiesIOFactory;

/**
 * Class TagTest
 */
class TagTest extends PHPUnit_Framework_TestCase {

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
				$Tag = array (
					'term_id'          => 1,
					'name'             => 'Fake Tag',
					'slug'             => 'fake-tag',
					'term_group'       => 0,
					'term_taxonomy_id' => 0,
					'taxonomy'         => 'post_tag',
					'description'      => '',
					'parent'           => 0,
					'count'            => 0
				);

				return $Tag;
			}
		}

		$this->ioFactory = Bootstrap::getContainer()->get( 'factory.contentIO' );
	}

	public function testGetTagWrapper () {
		$type = WordpressContentTypeHelper::CONTENT_TYPE_POST_TAG;

		$wrapper = $this->ioFactory->getMapper( $type );

		$this->assertTrue( $wrapper instanceof TagEntity );
	}

	public function testGetTagWrapperException () {
		$type = WordpressContentTypeHelper::CONTENT_TYPE_POST_TAG;

		$type = strrev( $type );
		try {
			$wrapper = $this->ioFactory->getMapper( $type );
		} catch ( SmartlingInvalidFactoryArgumentException $e ) {
			$this->assertTrue( $e instanceof SmartlingInvalidFactoryArgumentException );
		}
	}

	public function testReadTag () {
		$type = WordpressContentTypeHelper::CONTENT_TYPE_POST_TAG;

		$wrapper = $this->ioFactory->getMapper( $type );

		$result = $wrapper->get( 1 );


		$this->assertTrue( $result instanceof TagEntity );

		$this->assertTrue( $result->term_id === 1 );

		$this->assertTrue( $result->name === 'Fake Tag' );

		$this->assertTrue( $result->slug === 'fake-tag' );

		$this->assertTrue( $result->taxonomy === $type );
	}

	public function testCloneTag () {
		$type = WordpressContentTypeHelper::CONTENT_TYPE_POST_TAG;

		$wrapper = $this->ioFactory->getMapper( $type );

		$result = $wrapper->get( 1 );

		$clone = clone $result;

		$originalClass = get_class( $result );

		$this->assertTrue( $clone instanceof $originalClass );

		$this->assertTrue( $clone !== $result );

	}

	public function testCleanTagFields () {
		$type = WordpressContentTypeHelper::CONTENT_TYPE_POST_TAG;

		$wrapper = $this->ioFactory->getMapper( $type );

		$result = $wrapper->get( 1 );

		$clone = clone $result;

		$clone->cleanFields();

		$this->assertTrue( null === $clone->term_id );
	}


	public function testCreateTag () {
		if ( ! function_exists( 'wp_insert_term' ) ) {
			function wp_insert_term ( $name, $type, $args ) {
				return array_merge( $args, array ( 'term_id' => 2 ) );
			}
		}

		$type = WordpressContentTypeHelper::CONTENT_TYPE_POST_TAG;

		$wrapper = $this->ioFactory->getMapper( $type );

		$result = $wrapper->get( 1 );

		$clone = clone $result;

		$clone->cleanFields();

		$clone->name = 'test';
		$clone->slug = 'test';

		$id = $wrapper->set( $clone );

		$this->assertTrue( 2 === $id );
	}


	public function testUpdateTag () {
		if ( ! function_exists( 'wp_update_term' ) ) {
			function wp_update_term ( $id, $type, $args ) {
				return array_merge( $args, array ( 'term_id' => $id ) );
			}
		}

		$type = WordpressContentTypeHelper::CONTENT_TYPE_POST_TAG;

		$wrapper = $this->ioFactory->getMapper( $type );

		$result = $wrapper->get( 1 );

		$result->name .= 'new';

		$id = $wrapper->set( $result );

		$this->assertTrue( 1 === $id );
	}
}