<?php

namespace sm\post_meta;

function register_meta() {

}
/*
sm_body_class text notranslate
policy_list_fields array psrtial
policy_fields array partial
*/
add_action( 'wp_loaded', 'sm\post_meta\register_meta' );

/**
 * Register the Policy Fields meta box.
 *
 * @param  $post WP_Post object.
 */
function add_policy_meta( $post ) {
	add_meta_box(
		'policy_list_fields',
		esc_html__( 'Policy Center List Fields' ),
		'sm\post_meta\display_policy_list_fields_meta',
		'policy',
		'normal',
		'high'
	);

	add_meta_box(
		'policy_fields',
		esc_html__( 'Policy Fields' ),
		'sm\post_meta\display_policy_meta',
		'policy',
		'normal',
		'high'
	);
}



/**
 * Displays the "Policy Fields" meta box.
 *
 * @param  object $post WP_Post.
 * @return string HTML markup for the meta box.
 */
function display_policy_meta( $post ) {
	$policy_fields = array();
	$policy_fields = get_post_meta( $post->ID, 'policy_fields', false );
	$effective = '';
	if ( isset( $policy_fields[0]['effective_date'] ) && is_int( $policy_fields[0]['effective_date'] ) ) {
		$effective = date( 'm/d/Y', $policy_fields[0]['effective_date'] );
	}
	$version_title = isset( $policy_fields[0]['version_title'] ) ? $policy_fields[0]['version_title'] : '';
	$intro = isset( $policy_fields[0]['intro'] ) ? $policy_fields[0]['intro'] : '';

	wp_nonce_field( 'save_policy_fields', 'policy_fields_nonce' );

	printf( '<label for="policy_fields[effective]">%s </label>', esc_html__( 'Policy Effective Date', 'sm' ) );
	printf( '<input name="policy_fields[effective_date]" id="policy_fields[effective_date]" class="policy-meta datepicker" value="%s"><br>', esc_attr( $effective ) );

	printf( '<label for="policy_fields[version_title]">%s </label>', esc_html__( 'Policy Version Title', 'sm' ) );
	printf( '<input name="policy_fields[version_title]" id="policy_fields[version_title]" class="policy-meta" value="%s"><br>', esc_attr( $version_title ) );

	printf( '<label for="policy_fields[intro]">%s</label><br>', esc_html__( 'Policy Introduction', 'sm' ) );

	wp_editor( $intro, 'policy_fields_intro', array(
		'textarea_rows' => 10,
		'textarea_name' => 'policy_fields[intro]',
		'media_buttons' => false,
		'teeny'         => true,
	) );

	printf( '<br><p><strong>%s:</strong><br><img width="300" src="%s"></p>', esc_attr__( 'Example', 'sm' ), esc_url( IMG_ROOT . '/admin/effective_date_intro.png' ) );

}

/**
 * Displays the "Policy Center List Fields" meta box.
 *
 * @param  object $post WP_Post.
 * @return string HTML markup for the meta box.
 */
function display_policy_list_fields_meta( $post ) {
	$policy_list_fields = array();
	$policy_list_fields = get_post_meta( $post->ID, 'policy_list_fields', false );
	$icon = isset( $policy_list_fields[0]['icon'] ) ? $policy_list_fields[0]['icon'] : 'page';

	$list_title = isset( $policy_list_fields[0]['list_title'] ) ? $policy_list_fields[0]['list_title'] : '';

	$output = '<p class="howto">These fields are used to display this Policy in the "Policies" list on the <a href="https://www.surveymonkey.com/mp/policy/" target="_blank">Policy Center Page</a>.</p>';

	wp_nonce_field( 'save_policy_list_fields', 'policy_list_fields_nonce' );

	$output .= sprintf( '<label for="policy_list_fields[list_title]">%s </label>', esc_html__( 'Policy Center List Title', 'sm' ) );
	$output .= sprintf( '<input type="text" name="policy_list_fields[list_title]" id="policy_list_fields[list_title]" class="large-text" value="%s"><br><br>', esc_textarea( $list_title ) );

	$output .= sprintf( '<label for="policy_list_fields[effective]">%s</label><br><br>', esc_html__( 'Policy Center List Icon', 'sm' ) );
	$output .= sprintf( '<input type="radio" name="policy_list_fields[icon]" id="policy_list_fields[icon]" value="page" %s> <img src="%s" alt="" class="spacer-mrs valign-txt-top"><br>', ( 'page' === $icon ) ? 'checked' : '', esc_url( IMG_ROOT . '/list_icon_page.png' ) );
	$output .= sprintf( '<input type="radio" name="policy_list_fields[icon]" id="policy_list_fields[icon]" value="pdf" %s> <img src="%s" alt="" class="spacer-mrs valign-txt-top"><br>', ( 'pdf' === $icon ) ? 'checked' : '', esc_url( IMG_ROOT . '/list_icon_pdf.png' ) );


	$output .= sprintf( '<p><br><strong>%s</strong><br><img width="242" src="%s"></p>', esc_html__( 'Example:', 'sm' ), esc_url( IMG_ROOT . '/admin/policy_line_item.png' ) );

	echo $output;
}

/**
 * @param $post_id
 * @return bool|int|void
 */
function save_policy_meta( $post_id ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( empty( $_POST['post_type'] ) || 'policy' !== $_POST['post_type']
	     || empty( $_POST['policy_fields'] )
	     || ! wp_verify_nonce( $_POST['policy_fields_nonce'], 'save_policy_fields' )
	     || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$policy_fields = array();
	$policy_fields['effective_date'] = isset( $_POST['policy_fields']['effective_date'] ) ? strtotime( $_POST['policy_fields']['effective_date'] ) : '';
	$policy_fields['version_title'] = isset( $_POST['policy_fields']['version_title'] ) ? esc_attr( $_POST['policy_fields']['version_title'] ) : '';
	$policy_fields['intro'] = isset( $_POST['policy_fields']['intro'] ) ?
		wp_kses_post( $_POST['policy_fields']['intro'] ) : '';

	return update_post_meta( $post_id, 'policy_fields', $policy_fields );

}
add_action( 'save_post', 'sm\post_meta\save_policy_meta', 10, 1 );

/**
 * @param $post_id
 * @return bool|int|void
 */
function save_policy_list_fields_meta( $post_id ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( empty( $_POST['post_type'] )
	     || 'policy' !== $_POST['post_type']
	     || empty( $_POST['policy_list_fields'] )
	     || ! wp_verify_nonce( $_POST['policy_list_fields_nonce'], 'save_policy_list_fields' )
	     || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$policy_list_fields = array();
	$policy_list_fields['list_title'] = isset( $_POST['policy_list_fields']['list_title'] ) ?
		sanitize_text_field(
			$_POST['policy_list_fields']['list_title'] ) : '';
	$policy_list_fields['icon'] = isset( $_POST['policy_list_fields']['icon'] ) ?
		sanitize_key( $_POST['policy_list_fields']['icon'] ) : 'page';

	return update_post_meta( $post_id, 'policy_list_fields', $policy_list_fields );

}
add_action( 'save_post', 'sm\post_meta\save_policy_list_fields_meta', 10, 1 );

