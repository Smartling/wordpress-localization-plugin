<?php

namespace Smartling\Processors\ContentTypeMapper;

/**
 * Class TestimonialMapper
 *
 * @package Smartling\Processors\ContentTypeMapper
 */
class TestimonialMapper extends PostMapper {
	/**
	 * @inheritdoc
	 */
	public function __construct () {
		parent::__construct();

		$this->addFields(
			array (
				array (
					'type'      => 'standard',
					'meta'      => true,
					'mandatory' => false,
					'name'      => 'testimonial_source',
					'value'     => '',
					'key'       => ''
				),
			)
		);
	}
}