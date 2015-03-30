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

		$this->ioFactory = Bootstrap::getContainer()->get( 'factory.contentIO' );
	}

	public function testGetPageWrapper () {
		$type = WordpressContentTypeHelper::CONTENT_TYPE_PAGE;

		$wrapper = $this->ioFactory->getMapper( $type );

		self::assertTrue( $wrapper instanceof PageEntity );
	}

	public function testGetPageWrapperException () {
		$type = WordpressContentTypeHelper::CONTENT_TYPE_PAGE;

		$type = strrev( $type );
		try {
			$wrapper = $this->ioFactory->getMapper( $type );
		} catch ( SmartlingInvalidFactoryArgumentException $e ) {
			self::assertTrue( $e instanceof SmartlingInvalidFactoryArgumentException );
		}
	}

	public function testReadPage () {
		$type = WordpressContentTypeHelper::CONTENT_TYPE_PAGE;

		$wrapper = $this->ioFactory->getMapper( $type );

		$result = $wrapper->get( 10 );


		self::assertTrue( $result instanceof PageEntity );

		self::assertTrue( $result->ID === 10 );

		self::assertTrue( $result->post_title === 'Here goes the title' );

		self::assertTrue( $result->guid === '/here-goes-the-title' );

		self::assertTrue( $result->post_type === $type );
	}

	public function testClonePage () {
		$type = WordpressContentTypeHelper::CONTENT_TYPE_PAGE;

		$wrapper = $this->ioFactory->getMapper( $type );

		$result = $wrapper->get( 10 );

		$clone = clone $result;

		$originalClass = get_class( $result );

		self::assertTrue( $clone instanceof $originalClass );

		self::assertTrue( $clone !== $result );

	}

	public function testCleanPageFields () {
		$type = WordpressContentTypeHelper::CONTENT_TYPE_PAGE;

		$wrapper = $this->ioFactory->getMapper( $type );

		$result = $wrapper->get( 10 );

		$clone = clone $result;

		$clone->cleanFields();

		self::assertTrue( null === $clone->ID );
	}

	public function testCreatePage () {
		$type = WordpressContentTypeHelper::CONTENT_TYPE_PAGE;

		$wrapper = $this->ioFactory->getMapper( $type );

		$result = $wrapper->get( 10 );

		$clone = clone $result;

		$clone->cleanFields();

		$clone->post_title   = 'test';
		$clone->post_content = 'test';

		$id = $wrapper->set( $clone );

		self::assertTrue( 2 === $id );
	}

	public function testUpdatePage () {
		$type = WordpressContentTypeHelper::CONTENT_TYPE_PAGE;

		$wrapper = $this->ioFactory->getMapper( $type );

		$result = $wrapper->get( 10 );

		$result->post_title .= 'new';

		$id = $wrapper->set( $result );

		self::assertTrue( 10 === $id );
	}
}