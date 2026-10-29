<?php
/**
 * Plugin Name:       Poda Core
 * Description:       Content model, enquiry handling and one-click setup for the Studio Poda theme: projects, crew, services, enquiries and studio settings.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * Author:            Sevyn Labs
 * Author URI:        https://sevynlabs.io
 * License:           GPL-2.0-or-later
 * Text Domain:       poda-core
 */

defined( 'ABSPATH' ) || exit;

define( 'PODA_CORE_VERSION', '1.0.0' );
define( 'PODA_CORE_DIR', plugin_dir_path( __FILE__ ) );
define( 'PODA_CORE_URL', plugin_dir_url( __FILE__ ) );

require_once PODA_CORE_DIR . 'includes/fields.php';
require_once PODA_CORE_DIR . 'includes/content-types.php';
require_once PODA_CORE_DIR . 'includes/meta-boxes.php';
require_once PODA_CORE_DIR . 'includes/settings.php';
require_once PODA_CORE_DIR . 'includes/enquiries.php';
require_once PODA_CORE_DIR . 'includes/setup.php';

register_activation_hook( __FILE__, static function () {
	poda_register_content_types();
	flush_rewrite_rules();
} );

register_deactivation_hook( __FILE__, static function () {
	flush_rewrite_rules();
} );

/**
 * Read a Poda meta value with a fallback.
 */
function poda_meta( $key, $post_id = null, $default = '' ) {
	$post_id = $post_id ?: get_the_ID();
	$value   = get_post_meta( $post_id, 'poda_' . $key, true );
	return ( '' === $value || null === $value ) ? $default : $value;
}

/**
 * Studio-wide setting with a fallback.
 */
function poda_setting( $key, $default = '' ) {
	$opts = get_option( 'poda_settings', array() );
	return isset( $opts[ $key ] ) && '' !== $opts[ $key ] ? $opts[ $key ] : $default;
}
