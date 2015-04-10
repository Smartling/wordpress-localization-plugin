<?php

namespace Smartling\Processors;

/**
 * Class PropertyDescriptor
 *
 * @package Smartling\Processors
 */
class PropertyDescriptor {

	/**
	 * @var string
	 */
	private $type = '';

	/**
	 * @var bool
	 */
	private $meta = false;

	/**
	 * @var bool
	 */
	private $mandatory = true;

	/**
	 * @var string
	 */
	private $name = '';

	/**
	 * @var string
	 */
	private $value = '';

	/**
	 * @var string
	 */
	private $key = '';

	/**
	 * @param $type
	 * @param $name
	 */
	public function __construct ( $type = '', $name = '' ) {
		$this->setType( $type );
		$this->setName( $name );
	}

	/**
	 * @return array
	 */
	public function toArray () {
		return array (
			'type'      => $this->getType(),
			'meta'      => $this->isMeta(),
			'mandatory' => $this->isMandatory(),
			'name'      => $this->getName(),
			'value'     => $this->getValue(),
			'key'       => $this->getKey()
		);
	}

	/**
	 * @param array $state
	 *
	 * @return PropertyDescriptor
	 */
	public static function fromArray ( array $state ) {
		$fields = array (
			'type',
			'meta',
			'mandatory',
			'name',
			'value',
			'key'
		);

		$obj = new self();

		foreach ( $fields as $field ) {
			if ( array_key_exists( $field, $state ) ) {
				$setter = vsprintf( 'set%s', array ( ucfirst( $field ) ) );

				$obj->$setter( $state[ $field ] );
			}
		}

		return $obj;
	}

	/**
	 * @return string
	 */
	public function getType () {
		return $this->type;
	}

	/**
	 * @param string $type
	 */
	public function setType ( $type ) {
		$this->type = $type;
	}

	/**
	 * @return boolean
	 */
	public function isMeta () {
		return $this->meta;
	}

	/**
	 * @param boolean $meta
	 */
	public function setMeta ( $meta ) {
		$this->meta = $meta;
	}

	/**
	 * @return boolean
	 */
	public function isMandatory () {
		return $this->mandatory;
	}

	/**
	 * @param boolean $mandatory
	 */
	public function setMandatory ( $mandatory ) {
		$this->mandatory = $mandatory;
	}

	/**
	 * @return string
	 */
	public function getName () {
		return $this->name;
	}

	/**
	 * @param string $name
	 */
	public function setName ( $name ) {
		$this->name = $name;
	}

	/**
	 * @return string
	 */
	public function getValue () {
		return $this->value;
	}

	/**
	 * @param string $value
	 */
	public function setValue ( $value ) {
		$this->value = $value;
	}

	/**
	 * @return string
	 */
	public function getKey () {
		return $this->key;
	}

	/**
	 * @param string $key
	 */
	public function setKey ( $key ) {
		$this->key = $key;
	}
}