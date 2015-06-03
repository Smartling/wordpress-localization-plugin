<?php

namespace Smartling\Processors\ContentTypeMapper;

/**
 * Class PartnerMapper
 *
 * @package Smartling\Processors\ContentTypeMapper
 */
class PartnerMapper extends PostMapper {
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
					'name'      => 'subtitle',
					'value'     => '',
					'key'       => ''
				),
				array (
					'type'      => 'serialized-php-array',
					'meta'      => true,
					'mandatory' => false,
					'name'      => 'list_fields',
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
				),
				array (
					'type'      => 'serialized-php-array',
					'meta'      => true,
					'mandatory' => false,
					'name'      => 'policy_fields',
					'value'     => '',
					'key'       => '',
					'extra'     => array (
						'fields' => array (
							array (
								'name' => 'version_title',
								'type' => 'standard'
							),
							array (
								'name' => 'intro',
								'type' => 'standard'
							),
						)
					)
				),
				array (
					'type'      => 'serialized-php-array',
					'meta'      => true,
					'mandatory' => false,
					'name'      => 'policy_list_fields',
					'value'     => '',
					'key'       => '',
					'extra'     => array (
						'fields' => array (
							array (
								'name' => 'list_title',
								'type' => 'standard'
							),
						)
					)
				),
				array (
					'type'      => 'serialized-php-array',
					'meta'      => true,
					'mandatory' => false,
					'name'      => 'side_callout',
					'value'     => '',
					'key'       => '',
					'extra'     => array (
						'fields' => array (
							array (
								'name' => 'heading',
								'type' => 'standard'
							),
							array (
								'name' => 'cta_text',
								'type' => 'standard'
							),
							array (
								'name' => 'button_text',
								'type' => 'standard'
							),
						)
					)
				),
			)
		);
	}
}