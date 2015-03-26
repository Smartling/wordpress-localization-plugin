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
					'type'      => 'standard',
					'meta'      => false,
					'mandatory' => true,
					'name'      => 'name',
					'value'     => '',
					'key'       => ''
				),
				array (
					'type'      => 'standard',
					'meta'      => false,
					'mandatory' => true,
					'name'      => 'description',
					'value'     => '',
					'key'       => ''
				),
				array (
					'type'      => 'wordpress-seo',
					'meta'      => true,
					'mandatory' => true,
					'name'      => 'wpseo_bctitle',
					'value'     => '',
					'key'       => 'seo'
				),
				array (
					'type'      => 'wordpress-seo',
					'meta'      => true,
					'mandatory' => true,
					'name'      => 'wpseo_metakey',
					'value'     => '',
					'key'       => 'seo'
				),
				array (
					'type'      => 'wordpress-seo',
					'meta'      => true,
					'mandatory' => true,
					'name'      => 'wpseo_desc',
					'value'     => '',
					'key'       => 'seo'
				),
				array (
					'type'      => 'wordpress-seo',
					'meta'      => true,
					'mandatory' => true,
					'name'      => 'wpseo_title',
					'value'     => '',
					'key'       => 'seo'
				),
			)
		);
	}
}