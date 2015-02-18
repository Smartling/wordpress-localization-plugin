<?php

use Smartling\Bootstrap;
use Smartling\DbAl\WordpressContentEntities\PageEntity;
use Smartling\Exception\SmartlingInvalidFactoryArgumentException;
use Smartling\Helpers\DateTimeHelper;
use Smartling\Helpers\WordpressContentTypeHelper;
use Smartling\Processors\ContentEntitiesIOFactory;

/**
 * Class PageTest
 */
class PageTest extends PHPUnit_Framework_TestCase {

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

		if ( ! function_exists( 'is_wp_error' ) ) {
			function is_wp_error ( $something ) {
				return false;
			}
		}

		if ( ! function_exists( 'get_post' ) ) {
			function get_post ( $id, $returnError ) {

				$date = DateTimeHelper::dateTimeToString( new DateTime() );

				$post = array (
					'ID'                    => 1,
					'post_author'           => 1,
					'post_date'             => $date,
					'post_date_gmt'         => $date,
					'post_content'          => 'Test content',
					'post_title'            => 'Here goes the title',
					'post_excerpt'          => '',
					'post_status'           => 'published',
					'comment_status'        => 'open',
					'ping_status'           => '',
					'post_password'         => '',
					'post_name'             => 'Here goes the title',
					'to_ping'               => '',
					'pinged'                => '',
					'post_modified'         => $date,
					'post_modified_gmt'     => $date,
					'post_content_filtered' => '',
					'post_parent'           => 0,
					'guid'                  => '/here-goes-the-title',
					'menu_order'            => 0,
					'post_type'             => 'page',
					'post_mime_type'        => 'post',
					'comment_count'         => 0,
				);

				return $post;
			}
		}


		$this->ioFactory = Bootstrap::getContainer()->get( 'factory.contentIO' );
	}

	public function testGetPageWrapper () {
		$type = WordpressContentTypeHelper::CONTENT_TYPE_PAGE;

		$wrapper = $this->ioFactory->getMapper( $type );

		$this->assertTrue( $wrapper instanceof PageEntity );
	}

	public function testGetPageWrapperException () {
		$type = WordpressContentTypeHelper::CONTENT_TYPE_PAGE;

		$type = strrev( $type );
		try {
			$wrapper = $this->ioFactory->getMapper( $type );
		} catch ( SmartlingInvalidFactoryArgumentException $e ) {
			$this->assertTrue( $e instanceof SmartlingInvalidFactoryArgumentException );
		}
	}

	public function testReadPage () {
		$type = WordpressContentTypeHelper::CONTENT_TYPE_PAGE;

		$wrapper = $this->ioFactory->getMapper( $type );

		$result = $wrapper->get( 1 );


		$this->assertTrue( $result instanceof PageEntity );

		$this->assertTrue( $result->ID === 1 );

		$this->assertTrue( $result->post_title === 'Here goes the title' );

		$this->assertTrue( $result->guid === '/here-goes-the-title' );

		$this->assertTrue( $result->post_type === $type );
	}

	public function testClonePage () {
		$type = WordpressContentTypeHelper::CONTENT_TYPE_PAGE;

		$wrapper = $this->ioFactory->getMapper( $type );

		$result = $wrapper->get( 1 );

		$clone = clone $result;

		$originalClass = get_class( $result );

		$this->assertTrue( $clone instanceof $originalClass );

		$this->assertTrue( $clone !== $result );

	}

	public function testCleanPageFields () {
		$type = WordpressContentTypeHelper::CONTENT_TYPE_PAGE;

		$wrapper = $this->ioFactory->getMapper( $type );

		$result = $wrapper->get( 1 );

		$clone = clone $result;

		$clone->cleanFields();

		$this->assertTrue( null === $clone->ID );
	}

	public function testCreatePage () {
		if ( ! function_exists( 'wp_insert_post' ) ) {
			function wp_insert_post ( array $fields, $returnError ) {
				return 2;
			}
		}

		$type = WordpressContentTypeHelper::CONTENT_TYPE_PAGE;

		$wrapper = $this->ioFactory->getMapper( $type );

		$result = $wrapper->get( 1 );

		$clone = clone $result;

		$clone->cleanFields();

		$clone->post_title   = 'test';
		$clone->post_content = 'test';

		$id = $wrapper->set( $clone );

		$this->assertTrue( 2 === $id );
	}

	public function testUpdatePage () {
		if ( ! function_exists( 'wp_insert_post' ) ) {
			function wp_insert_post ( array $fields, $returnError ) {
				return $fields['ID'];
			}
		}

		$type = WordpressContentTypeHelper::CONTENT_TYPE_PAGE;

		$wrapper = $this->ioFactory->getMapper( $type );

		$result = $wrapper->get( 1 );

		$result->post_title .= 'new';

		$id = $wrapper->set( $result );

		$this->assertTrue( 1 === $id );
	}
}