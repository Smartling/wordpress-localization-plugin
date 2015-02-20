<?php

use Smartling\Bootstrap;
use Smartling\Helpers\WordpressContentTypeHelper;
use Smartling\Processors\CategoryMapper;
use Smartling\Processors\PropertyMapperFactory;

class CategoryMapperTest extends PHPUnit_Framework_TestCase {
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
		$type = WordpressContentTypeHelper::CONTENT_TYPE_CATEGORY;

		$wrapper = $this->mapperFactory->getMapper( $type );

		self::assertTrue( $wrapper instanceof CategoryMapper );
	}

	public function testCheckPostMapperFields () {
		$type = WordpressContentTypeHelper::CONTENT_TYPE_CATEGORY;

		/**
		 * @var CategoryMapper $wrapper
		 */
		$wrapper = $this->mapperFactory->getMapper( $type );

		$fields = $wrapper->getFields();

		self::assertTrue( $fields === array (
				'name',
				'description'
			) );
	}
}