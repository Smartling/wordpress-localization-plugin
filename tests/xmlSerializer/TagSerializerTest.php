<?php
use Faker\Factory;
use Smartling\Bootstrap;
use Smartling\Helpers\WordpressContentTypeHelper;
use Smartling\Helpers\XmlEncoder;
use Smartling\Processors\ContentTypeMapper\PostMapper;
use Smartling\Processors\PropertyDescriptor;
use Smartling\Processors\PropertyMapperFactory;
use Smartling\Processors\PropertyProcessors\PropertyProcessorFactory;

/**
 * Class TagSerializerTest
 */
class TagSerializerTest  extends PHPUnit_Framework_TestCase {
	/**
	 * @var PropertyMapperFactory
	 */
	private $mapperFactory;

	/**
	 * @var PropertyProcessorFactory
	 */
	private $processorFactory;

	/**
	 * @var Factory
	 */
	private $faker;

	public function __construct ( $name = null, array $data = array (), $dataName = '' ) {
		parent::__construct( $name, $data, $dataName );

		$this->init();
	}

	private function init () {
		$container = Bootstrap::getContainer();

		$this->mapperFactory    = $container->get( 'factory.propertyMapper' );
		$this->processorFactory = $container->get( 'factory.processor' );
		$this->faker            = Factory::create();
	}

	public function testPostEntityFieldsEncodingAndDecoding () {
		$type = WordpressContentTypeHelper::CONTENT_TYPE_POST_TAG;

		/**
		 * @var PostMapper $wrapper
		 */
		$wrapper = $this->mapperFactory->getMapper( $type );

		/**
		 * @var PropertyDescriptor[] $fields
		 */
		$fields = $wrapper->getFields();

		foreach ( $fields as $field ) {
			$field->setValue( $this->faker->realText( 180 ) );
		}

		$encodedXML = XmlEncoder::xmlEncode( $fields, $this->processorFactory );

		// cleaning content
		$fields_dec = $wrapper->getFields();

		$decodedXML = XmlEncoder::xmlDecode( $fields_dec, $encodedXML, $this->processorFactory );

		$sourceArray  = $this->simplifyDescriptors( $fields );
		$decodedArray = $this->simplifyDescriptors( $decodedXML );

		self::assertTrue( $sourceArray === $decodedArray, var_export( array ( $sourceArray, $decodedArray ), true ) );
	}

	/**
	 * @param PropertyDescriptor[] $descriptors
	 *
	 * @return array
	 */
	private function simplifyDescriptors ( array $descriptors ) {
		$result = array ();

		foreach ( $descriptors as $descriptor ) {
			$result[] = $this->simplifyDescriptor( $descriptor );
		}

		return $result;
	}

	/**
	 * @param PropertyDescriptor $descriptor
	 *
	 * @return array
	 */
	private function simplifyDescriptor ( PropertyDescriptor $descriptor ) {
		return $descriptor->toArray();
	}
}