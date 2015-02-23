<?php

namespace Smartling;

use Faker\Factory;
use Smartling\Helpers\WordpressContentTypeHelper;
use Smartling\Helpers\XmlEncoder;
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

	private function mockUpload() {
		return null;
	}

	/**
	 * @inheritdoc
	 */
	public function downloadFile ( $fileUri, $locale, $params = array () ) {
		$type = WordpressContentTypeHelper::CONTENT_TYPE_POST;

		$factory = Bootstrap::getContainer()->get( 'factory.propertyMapper' );
		$faker = Factory::create();


		$wrapper = $factory->getMapper( $type );

		/**
		 * @var PostMapper $wrapper
		 */
		$fields = $wrapper->getFields();

		$data = array();

		foreach ( $fields as $field ) {
			$data[$field] = $faker->realText(180);
		}

		$encodedXML = XmlEncoder::xmlEncode($data);

		return $encodedXML;
	}
}