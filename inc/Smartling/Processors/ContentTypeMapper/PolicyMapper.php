<?php

namespace Smartling\Processors\ContentTypeMapper;

/**
 * Class PolicyMapper
 *
 * Specially for SM
 *
 * @package Smartling\Processors\ContentTypeMapper
 */
class PolicyMapper extends PostMapper {

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
					'name'      => 'callout_area',
					'value'     => '',
					'key'       => ''
				),
				array (
					'type'      => 'standard',
					'meta'      => true,
					'mandatory' => false,
					'name'      => 'call_to_action',
					'value'     => '',
					'key'       => ''
				),
				array (
					'type'      => 'standard',
					'meta'      => true,
					'mandatory' => false,
					'name'      => 'content-panel',
					'value'     => '',
					'key'       => ''
				),
				array (
					'type'      => 'standard',
					'meta'      => true,
					'mandatory' => false,
					'name'      => 'custom_title',
					'value'     => '',
					'key'       => ''
				),
				array (
					'type'      => 'standard',
					'meta'      => true,
					'mandatory' => false,
					'name'      => 'dsq_thread_id',
					'value'     => '',
					'key'       => ''
				),
				array (
					'type'      => 'standard',
					'meta'      => true,
					'mandatory' => false,
					'name'      => 'footer_area',
					'value'     => '',
					'key'       => ''
				),
				array (
					'type'      => 'standard',
					'meta'      => true,
					'mandatory' => false,
					'name'      => 'footer_main_menu_visibility_meta_box',
					'value'     => '',
					'key'       => ''
				),
				array (
					'type'      => 'standard',
					'meta'      => true,
					'mandatory' => false,
					'name'      => 'footer_template_meta_box',
					'value'     => '',
					'key'       => ''
				),
				array (
					'type'      => 'standard',
					'meta'      => true,
					'mandatory' => false,
					'name'      => 'header_area',
					'value'     => '',
					'key'       => ''
				),
				array (
					'type'      => 'standard',
					'meta'      => true,
					'mandatory' => false,
					'name'      => 'header_green_area',
					'value'     => '',
					'key'       => ''
				),
				array (
					'type'      => 'standard',
					'meta'      => true,
					'mandatory' => false,
					'name'      => 'header_help_visibility_meta_box',
					'value'     => '',
					'key'       => ''
				),
				array (
					'type'      => 'standard',
					'meta'      => true,
					'mandatory' => false,
					'name'      => 'header_nav_visibility_meta_box',
					'value'     => '',
					'key'       => ''
				),
				array (
					'type'      => 'standard',
					'meta'      => true,
					'mandatory' => false,
					'name'      => 'header_sign_in_visibility_meta_box',
					'value'     => '',
					'key'       => ''
				),
				array (
					'type'      => 'standard',
					'meta'      => true,
					'mandatory' => false,
					'name'      => 'header_template_meta_box',
					'value'     => '',
					'key'       => ''
				),
				array (
					'type'      => 'standard',
					'meta'      => true,
					'mandatory' => false,
					'name'      => 'language_menu_visibility_meta_box',
					'value'     => '',
					'key'       => ''
				),
				array (
					'type'      => 'standard',
					'meta'      => true,
					'mandatory' => false,
					'name'      => 'left_sidebar_menu',
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
					'type'      => 'standard',
					'meta'      => true,
					'mandatory' => false,
					'name'      => 'page_side_cta',
					'value'     => '',
					'key'       => ''
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
					'type'      => 'standard',
					'meta'      => true,
					'mandatory' => false,
					'name'      => 'short_menu_visibility_meta_box',
					'value'     => '',
					'key'       => ''
				),
				array (
					'type'      => 'standard',
					'meta'      => true,
					'mandatory' => false,
					'name'      => 'sidebar_cta',
					'value'     => '',
					'key'       => ''
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