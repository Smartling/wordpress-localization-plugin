<?php

namespace Smartling;

use Faker\Factory;
use Smartling\Helpers\WordpressContentTypeHelper;
use Smartling\Helpers\XmlEncoder;
use Smartling\Processors\ContentTypeMapper\PostMapper;
use Smartling\Processors\PropertyDescriptor;
use Smartling\Processors\PropertyMapperFactory;
use Smartling\Processors\PropertyProcessors\PropertyProcessorFactory;
use Smartling\SDK\SmartlingAPI;

/**
 * Class SmartlingApiMock
 *
 * @package Smartling
 */
class SmartlingApiMock extends SmartlingAPI {


	public function __construct () {
	}

	/**
	 * @inheritdoc
	 */
	public function getCodeStatus () {
		return 'SUCCESS';
	}

	public function getStatus ( $fileUri, $locale, $params = array () ) {
		return json_encode( array (
			'response' => array (
				'data' => array (
					'approvedStringCount'  => 100,
					'completedStringCount' => 500,
					'wordCount'            => 100500
				)
			)
		) );
	}

	/**
	 * @inheritdoc
	 */
	public function uploadContent ( $content, $params = array () ) {
		return $this->mockUpload();
	}

	/**
	 * @inheritdoc
	 */
	public function uploadFile ( $path, $params = array () ) {
		return $this->mockUpload();
	}

	private function mockUpload () {
		return null;
	}

	/**
	 * @inheritdoc
	 */
	public function downloadFile ( $fileUri, $locale, $params = array () ) {
		$type = WordpressContentTypeHelper::CONTENT_TYPE_POST;

		/**
		 * @var PropertyProcessorFactory $p_factory
		 */

		$p_factory = Bootstrap::getContainer()->get( 'factory.processor' );

		/**
		 * @var PropertyMapperFactory $factory
		 */

		$factory = Bootstrap::getContainer()->get( 'factory.propertyMapper' );
		$faker   = Factory::create();

		/**
		 * @var PostMapper $wrapper
		 */
		$wrapper = $factory->getMapper( $type );

		/**
		 * @var PropertyDescriptor[] $fields
		 */
		$fields = $wrapper->getFields();

		foreach ( $fields as $field ) {
			$field->setValue( $faker->realText( 180 ) );
		}

		$encodedXML = XmlEncoder::xmlEncode( $fields, $p_factory );

		return $encodedXML;
	}
}