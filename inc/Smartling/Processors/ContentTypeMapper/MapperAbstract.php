<?php

namespace Smartling\Processors\ContentTypeMapper;

use Smartling\Processors\PropertyDescriptor;

/**
 * Class MapperAbstract
 *
 * @package Smartling\Processors\ContentTypeMapper
 */
abstract class MapperAbstract {

	/**
	 * @var array
	 */
	private $fields;

	/**
	 * @return PropertyDescriptor[]
	 */
	public function getFields () {
		return $this->buildDescriptorSet( $this->fields );
	}

	/**
	 * @param array
	 */
	protected function setFields ( $fields ) {
		$this->fields = $fields;
	}

	/**
	 * @param array $field
	 *
	 * @return PropertyDescriptor
	 */
	protected function buildDescriptor (array $field) {
		return PropertyDescriptor::fromArray($field);
	}

	/**
	 * @param array $fields
	 *
	 * @return PropertyDescriptor[]
	 */
	protected function buildDescriptorSet ( array $fields ) {
		$set = array ();
		foreach ( $fields as $field ) {
			$set[] = $this->buildDescriptor( $field );
		}

		return $set;
	}
}