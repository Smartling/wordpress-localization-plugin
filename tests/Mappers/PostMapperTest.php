<?php

use Smartling\Bootstrap;
use Smartling\Helpers\WordpressContentTypeHelper;
use Smartling\Processors\PostMapper;
use Smartling\Processors\PropertyMapperFactory;

class PostMapperTest extends PHPUnit_Framework_TestCase {
	/**
	 * @var PropertyMapperFactory
	 */
	private $mapperFactory;

	public function __construct ( $name = null, array $data = array (), $dataName = '' ) {
		parent::__construct( $name, $data, $dataName );

		$this->init();
	}

	private function init () {
		$this->mapperFactory = Bootstrap::getContainer()->get( 'factory.propertyMapper' );
	}

	public function testGetPostMapper () {
		$type = WordpressContentTypeHelper::CONTENT_TYPE_POST;

		$wrapper = $this->mapperFactory->getHandler( $type );

		self::assertTrue( $wrapper instanceof PostMapper );
	}

	public function testCheckPostMapperFields () {
		$type = WordpressContentTypeHelper::CONTENT_TYPE_POST;

		/**
		 * @var PostMapper $wrapper
		 */
		$wrapper = $this->mapperFactory->getHandler( $type );

		$fields = $wrapper->getFields();

		self::assertTrue( $fields === array (
				'post_title',
				'post_content'
			) );
	}
}