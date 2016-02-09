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

		$this->ioFactory = Bootstrap::getContainer()->get( 'factory.contentIO' );
	}

	public function testGetTagWrapper () {
		$type = WordpressContentTypeHelper::CONTENT_TYPE_POST_TAG;

		$wrapper = $this->ioFactory->getMapper( $type );

		self::assertTrue( $wrapper instanceof TagEntity );
	}

	public function testGetTagWrapperException () {
		$type = WordpressContentTypeHelper::CONTENT_TYPE_POST_TAG;

		$type = strrev( $type );
		try {
			$wrapper = $this->ioFactory->getMapper( $type );
		} catch ( SmartlingInvalidFactoryArgumentException $e ) {
			self::assertTrue( $e instanceof SmartlingInvalidFactoryArgumentException );
		}
	}

	public function testReadTag () {
		$type = WordpressContentTypeHelper::CONTENT_TYPE_POST_TAG;

		$wrapper = $this->ioFactory->getMapper( $type );

		$result = $wrapper->get( 1 );


		self::assertTrue( $result instanceof TagEntity );

		self::assertTrue( $result->term_id === 1 );

		self::assertTrue( $result->name === 'Fake Name' );

		self::assertTrue( $result->slug === 'fake-name' );

		self::assertTrue( $result->taxonomy === $type );
	}

	public function testCloneTag () {
		$type = WordpressContentTypeHelper::CONTENT_TYPE_POST_TAG;

		$wrapper = $this->ioFactory->getMapper( $type );

		$result = $wrapper->get( 1 );

		$clone = clone $result;

		$originalClass = get_class( $result );

		self::assertTrue( $clone instanceof $originalClass );

		self::assertTrue( $clone !== $result );

	}

	public function testCleanTagFields () {
		$type = WordpressContentTypeHelper::CONTENT_TYPE_POST_TAG;

		$wrapper = $this->ioFactory->getMapper( $type );

		$result = $wrapper->get( 1 );

		$clone = clone $result;

		$clone->cleanFields();

		self::assertTrue( null === $clone->term_id );
	}


	public function testCreateTag () {
		$type = WordpressContentTypeHelper::CONTENT_TYPE_POST_TAG;

		$wrapper = $this->ioFactory->getMapper( $type );
		$result = $wrapper->get( 1 );
		$clone = clone $result;
		$clone->cleanFields();
		$clone->name = 'test';
		$clone->slug = 'test';
		$id = $wrapper->set( $clone );

		self::assertTrue( 2 === $id );
	}


	public function testUpdateTag () {
		$type = WordpressContentTypeHelper::CONTENT_TYPE_POST_TAG;

		$wrapper = $this->ioFactory->getMapper( $type );
		$result = $wrapper->get( 1 );
		$result->name .= 'new';
		$id = $wrapper->set( $result );

		self::assertTrue( 1 === $id );
	}
}