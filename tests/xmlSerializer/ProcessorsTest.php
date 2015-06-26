<?php
use Faker\Factory;
use Smartling\Bootstrap;
use Smartling\Helpers\XmlEncoder;
use Smartling\Processors\ContentTypeMapper\NoTypeMapper;
use Smartling\Processors\PropertyDescriptor;
use Smartling\Processors\PropertyMapperFactory;
use Smartling\Processors\PropertyProcessors\PropertyProcessorFactory;

/**
 * Class ProcessorsTest
 *
 * @package xmlSerializer
 */
class ProcessorsTest extends PHPUnit_Framework_TestCase {

	/**
	 * @var PropertyProcessorFactory
	 */
	private $processorFactory;

	/**
	 * @var PropertyMapperFactory
	 */
	private $mapperFactory;

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

		$mapper = new NoTypeMapper();
		$mapper->addFields(
			array (
				'type'      => 'serialized-php-array',
				'meta'      => true,
				'mandatory' => false,
				'name'      => 'php_array',
				'value'     => '',
				'key'       => '',
				'extra'     => array (
					'fields' => array (
						array (
							'name' => 'name',
							'type' => 'standard'
						),
						array (
							'name' => 'intro',
							'type' => 'standard'
						),
						array (
							'name' => 'how',
							'type' => 'standard'
						),
					)
				)
			)
		);

		$this->mapperFactory->registerMapper( 'test', $mapper );

	}

	public function testSerialiedArrayProcessor () {
		$type = 'test';

		/**
		 * @var NoTypeMapper $wrapper
		 */
		$wrapper = $this->mapperFactory->getMapper( $type );

		/**
		 * @var PropertyDescriptor[] $fields
		 */
		$fields = $wrapper->getFields();

		$originalValue = array (
			'name'  => $this->faker->realText( 180 ),
			'intro' => $this->faker->realText( 180 ),
			'how'   => $this->faker->realText( 180 )
		);

		foreach ( $fields as $field ) {
			$field->setValue( serialize( $originalValue ) );

			$subFields = $field->getSubFields();
				foreach ( $subFields as $subField ) {
					$subFieldName = $subField->getName();
					if ( array_key_exists( $subFieldName, $originalValue ) ) {
						$subField->setValue( $originalValue[ $subFieldName ] );
					}
				}
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