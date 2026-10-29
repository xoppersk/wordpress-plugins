<?php
/**
 * Plugin Name: SK Portfolio FX
 * Description: Styles and interactions for the Sheku Koroma portfolio (Hello Elementor + Elementor Pro): scrolling name marquee, spinning globe, magnetic buttons, cursor-follow project previews, page-transition curtain and live local time.
 * Version: 1.0.0
 * Author: Sheku Koroma
 * Requires at least: 6.2
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SKP_VERSION', '1.0.1' );

add_action( 'wp_enqueue_scripts', function () {
	wp_enqueue_style( 'skp-archivo', 'https://fonts.googleapis.com/css2?family=Archivo:wght@400;600;800&display=swap', array(), null );
	wp_enqueue_style( 'skp', plugins_url( 'assets/sk-portfolio.css', __FILE__ ), array( 'skp-archivo' ), SKP_VERSION );
	wp_enqueue_script( 'skp', plugins_url( 'assets/sk-portfolio.js', __FILE__ ), array(), SKP_VERSION, true );
	wp_localize_script( 'skp', 'SKP', array(
		'timezone' => 'America/New_York', // Local-time clock zone.
		'curtain'  => true,               // Page-transition curtain on internal links.
	) );
}, 20 );

// Scope every style under body.sk-site so nothing leaks into wp-admin.
add_filter( 'body_class', function ( $classes ) {
	$classes[] = 'sk-site';
	return $classes;
} );

// Page-transition curtain (Hello Elementor fires wp_body_open).
add_action( 'wp_body_open', function () {
	if ( isset( $_GET['elementor-preview'] ) ) {
		return;
	}
	echo '<div class="sk-curtain" aria-hidden="true"><div class="sk-curtain-label"></div></div>';
} );
