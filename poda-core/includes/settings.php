<?php
/**
 * Settings > Studio Poda: contact details and social links.
 */

defined( 'ABSPATH' ) || exit;

function poda_settings_fields() {
	return array(
		'email'     => array( __( 'Enquiries go to', 'poda-core' ), 'email', 'sheku@sevynlabs.io' ),
		'address'   => array( __( 'Studio address', 'poda-core' ), 'text', '14 Bus Stop Lane, Freetown' ),
		'hours'     => array( __( 'Opening hours', 'poda-core' ), 'text', 'Mon–Fri, 9–6 GMT' ),
		'instagram' => array( __( 'Instagram URL', 'poda-core' ), 'url', 'https://instagram.com/studiopoda' ),
		'tiktok'    => array( __( 'TikTok URL', 'poda-core' ), 'url', 'https://tiktok.com/@studiopoda' ),
		'linkedin'  => array( __( 'LinkedIn URL', 'poda-core' ), 'url', 'https://linkedin.com/company/studiopoda' ),
		'behance'   => array( __( 'Behance URL', 'poda-core' ), 'url', 'https://behance.net/studiopoda' ),
	);
}

add_action(
	'admin_init',
	static function () {
		register_setting(
			'poda_settings',
			'poda_settings',
			array(
				'type'              => 'array',
				'sanitize_callback' => static function ( $input ) {
					$out = array();
					foreach ( poda_settings_fields() as $key => $f ) {
						$v = isset( $input[ $key ] ) ? $input[ $key ] : '';
						if ( 'email' === $f[1] ) {
							$out[ $key ] = sanitize_email( $v );
						} elseif ( 'url' === $f[1] ) {
							$out[ $key ] = esc_url_raw( $v, array( 'https', 'http' ) );
						} else {
							$out[ $key ] = sanitize_text_field( $v );
						}
					}
					return $out;
				},
			)
		);
	}
);

add_action(
	'admin_menu',
	static function () {
		add_options_page( __( 'Studio Poda', 'poda-core' ), __( 'Studio Poda', 'poda-core' ), 'manage_options', 'poda-settings', 'poda_render_settings_page' );
	}
);

function poda_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$opts = get_option( 'poda_settings', array() );
	echo '<div class="wrap"><h1>' . esc_html__( 'Studio Poda', 'poda-core' ) . '</h1><form method="post" action="options.php">';
	settings_fields( 'poda_settings' );
	echo '<table class="form-table" role="presentation"><tbody>';
	foreach ( poda_settings_fields() as $key => $f ) {
		$val = isset( $opts[ $key ] ) ? $opts[ $key ] : $f[2];
		printf(
			'<tr><th scope="row"><label for="poda_%1$s">%2$s</label></th><td><input class="regular-text" type="%3$s" id="poda_%1$s" name="poda_settings[%1$s]" value="%4$s"></td></tr>',
			esc_attr( $key ),
			esc_html( $f[0] ),
			esc_attr( $f[1] ),
			esc_attr( $val )
		);
	}
	echo '</tbody></table>';
	submit_button();
	echo '</form></div>';
}
