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

	public function getStatus ( $fileUri, $locale, $params = [ ] ) {
		return json_encode( [
			'response' => [
				'data' => [
					'approvedStringCount'  => 100,
					'completedStringCount' => 500,
					'wordCount'            => 100500,
				],
			],
		] );
	}

	/**
	 * @inheritdoc
	 */
	public function uploadContent ( $content, $params = [ ] ) {
		return $this->mockUpload();
	}

	/**
	 * @inheritdoc
	 */
	public function uploadFile ( $path, $params = [ ] ) {
		return $this->mockUpload();
	}

	private function mockUpload () {
		return null;
	}

	const FAKE_TEXT_LENGTH = 180;

	/**
	 * @inheritdoc
	 */
	public function downloadFile ( $fileUri, $locale, $params = [ ] ) {
		$faker = Factory::create();

		$array = [
			'entity' => [
				'post_title' => $faker->realText( 80 ),
				'post_body'  => $faker->realText( 4096 ),
			],
			'meta'   => [ ],
		];

		$encodedXML = XmlEncoder::xmlEncode( $array );

		return $encodedXML;
	}
}