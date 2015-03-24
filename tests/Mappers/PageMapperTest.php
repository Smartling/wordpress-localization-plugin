<?php

use Smartling\Bootstrap;
use Smartling\Helpers\WordpressContentTypeHelper;
use Smartling\Processors\PageMapper;
use Smartling\Processors\PropertyMapperFactory;

class PageMapperTest extends PHPUnit_Framework_TestCase {
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
		$type = WordpressContentTypeHelper::CONTENT_TYPE_PAGE;

		$wrapper = $this->mapperFactory->getHandler( $type );

		self::assertTrue( $wrapper instanceof PageMapper );
	}

	public function testCheckPostMapperFields () {
		$type = WordpressContentTypeHelper::CONTENT_TYPE_PAGE;

		/**
		 * @var PageMapper $wrapper
		 */
		$wrapper = $this->mapperFactory->getHandler( $type );

		$fields = $wrapper->getFields();

		self::assertTrue( $fields === array (
				'post_title',
				'post_content'
			) );
	}
}