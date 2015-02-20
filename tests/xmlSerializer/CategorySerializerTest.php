<?php

use Smartling\Bootstrap;
use Smartling\Helpers\WordpressContentTypeHelper;
use Smartling\Helpers\XmlEncoder;

class CategorySerializerTest  extends PHPUnit_Framework_TestCase {
	/**
	 * @var PropertyMapperFactory
	 */
	private $mapperFactory;

	private $faker;

	public function __construct ( $name = null, array $data = array (), $dataName = '' ) {
		parent::__construct( $name, $data, $dataName );

		$this->init();
	}

	private function init () {
		$this->mapperFactory = Bootstrap::getContainer()->get( 'factory.propertyMapper' );

		$this->faker = Faker\Factory::create();
	}

	public function testPostEntityFieldsEncodingAndDecoding () {
		$type = WordpressContentTypeHelper::CONTENT_TYPE_CATEGORY;

		$wrapper = $this->mapperFactory->getMapper( $type );

		/**
		 * @var PostMapper $wrapper
		 */
		$fields = $wrapper->getFields();

		$data = array();

		foreach ( $fields as $field ) {
			$data[$field] = $this->faker->realText(180);
		}

		$encodedXML = XmlEncoder::xmlEncode($data);

		$decodedXML = XmlEncoder::xmlDecode($fields, $encodedXML);

		self::assertTrue($data === $decodedXML);
	}
}