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

		self::assertTrue( $result->name === 'Fake Name' );

		self::assertTrue( $result->slug === 'fake-name' );

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
		$type = WordpressContentTypeHelper::CONTENT_TYPE_CATEGORY;

		$wrapper = $this->ioFactory->getMapper( $type );
		$result = $wrapper->get( 1 );
		$result->name .= 'new';
		$id = $wrapper->set( $result );

		self::assertTrue( 1 === $id );
	}
}