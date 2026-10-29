<?php
/**
 * Plugin Name: Poda Site
 * Description: Studio Poda site foundation — loads the Poda typefaces (Fraunces, Archivo, Space Mono) on every page and prints the global poda-wp.css inline at wp_head priority 1000 (house pattern: Hello Elementor + Elementor 4.3 never emit the kit's custom_css, and Hostinger 403s direct .css reads in custom uploads subfolders, so the stylesheet is read from disk and printed).
 * Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Typefaces — the site's load set (Fraunces 600/900 + italics,
// Archivo 400–800, Space Mono 400/700).
add_action( 'wp_enqueue_scripts', function () {
	wp_enqueue_style(
		'poda-fonts',
		'https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,600;0,9..144,900;1,9..144,600;1,9..144,900&family=Archivo:wght@400;500;600;700;800&family=Space+Mono:wght@400;700&display=swap',
		[],
		null
	);
}, 5 );

// Preconnect for the font hosts (cheap, standard).
add_action( 'wp_head', function () {
	echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
	echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
}, 2 );

// Global stylesheet, printed inline — the file lives at
// wp-content/uploads/poda/poda-wp.css.
add_action( 'wp_head', function () {
	$css_file = WP_CONTENT_DIR . '/uploads/poda/poda-wp.css';
	if ( file_exists( $css_file ) ) {
		$css = file_get_contents( $css_file );
		if ( false !== $css && '' !== trim( $css ) ) {
			echo '<style id="poda-wp-css">' . "\n" . $css . "\n</style>\n";
		}
	}
}, 1000 );
