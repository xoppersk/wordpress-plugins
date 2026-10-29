<?php
/**
 * Plugin Name: Global Styles Inline
 * Description: Prints a global stylesheet inline at wp_head priority 1000, after everything the theme and builder generate. For sites where the builder's custom CSS is never emitted and the host refuses direct .css reads from custom uploads subfolders, the stylesheet is read from disk and printed instead.
 * Version: 1.0.0
 * Author: Sheku Koroma
 * Author URI: https://shekukoroma.com
 * License: GPL-2.0-or-later
 * Text Domain: global-styles-inline
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Path to the stylesheet, relative to wp-content/uploads.
 * Filter: global_styles_inline_stylesheet — return a path relative to the
 * uploads directory (default: global-styles.css).
 */
function global_styles_inline_path() {
	$relative = apply_filters( 'global_styles_inline_stylesheet', 'global-styles.css' );
	$relative = ltrim( (string) $relative, '/' );
	$upload   = wp_upload_dir();
	return trailingslashit( $upload['basedir'] ) . $relative;
}

add_action( 'wp_head', function () {
	$css_file = global_styles_inline_path();
	if ( ! file_exists( $css_file ) ) {
		return;
	}
	$css = file_get_contents( $css_file );
	if ( false !== $css && '' !== trim( $css ) ) {
		echo '<style id="global-styles-inline">' . "\n" . $css . "\n</style>\n";
	}
}, 1000 );
