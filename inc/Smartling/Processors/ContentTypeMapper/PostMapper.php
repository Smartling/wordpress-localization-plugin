<?php

namespace Smartling\Processors\ContentTypeMapper;

/**
 * Class PostMapper
 *
 * @package Smartling\Processors
 */
class PostMapper extends MapperAbstract {

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
					'name'      => 'post_title',
					'value'     => '',
					'key'       => ''
				),
				array (
					'type'      => 'standard',
					'meta'      => false,
					'mandatory' => true,
					'name'      => 'post_content',
					'value'     => '',
					'key'       => ''
				),
				array (
					'type'      => 'wordpress-seo',
					'meta'      => true,
					'mandatory' => false,
					'name'      => '_yoast_wpseo_opengraph-title',
					'value'     => '',
					'key'       => 'seo'
				),
				array (
					'type'      => 'wordpress-seo',
					'meta'      => true,
					'mandatory' => false,
					'name'      => '_yoast_wpseo_opengraph-description',
					'value'     => '',
					'key'       => 'seo'
				),
				array (
					'type'      => 'wordpress-seo',
					'meta'      => true,
					'mandatory' => false,
					'name'      => '_yoast_wpseo_focuskw',
					'value'     => '',
					'key'       => 'seo'
				),
				array (
					'type'      => 'wordpress-seo',
					'meta'      => true,
					'mandatory' => false,
					'name'      => '_yoast_wpseo_title',
					'value'     => '',
					'key'       => 'seo'
				),
				array (
					'type'      => 'wordpress-seo',
					'meta'      => true,
					'mandatory' => false,
					'name'      => '_yoast_wpseo_metadesc',
					'value'     => '',
					'key'       => 'seo'
				),
			)
		);
	}
}