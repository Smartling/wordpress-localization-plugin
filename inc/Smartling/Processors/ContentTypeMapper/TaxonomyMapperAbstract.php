<?php

namespace Smartling\Processors\ContentTypeMapper;

/**
 * Class TaxonomyMapperAbstract
 *
 * @package Smartling\Processors\ContentTypeMapper
 */
abstract class TaxonomyMapperAbstract extends MapperAbstract {

	/**
	 * Constructor
	 */
	public function __construct () {
		$this->setFields(
			array (
				array (
					'type'      => 'standard.taxonomy.name',
					'meta'      => false,
					'mandatory' => true,
					'name'      => 'name',
					'value'     => '',
					'key'       => ''
				),
				array (
					'type'      => 'standard.taxonomy.description',
					'meta'      => false,
					'mandatory' => true,
					'name'      => 'description',
					'value'     => '',
					'key'       => ''
				)
			)
		);
	}
}