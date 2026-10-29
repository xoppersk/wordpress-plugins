<?php
/**
 * Plugin Name:       Studio Poda Widgets
 * Plugin URI:        https://studiopoda.shekukoroma.com
 * Description:       Bespoke Elementor widgets, content types and shortcodes for Studio Poda — the route-board Ticker Band, the featured-work Cover Rail, the filterable Work Grid, the poda_project / poda_story content types and their taxonomies and meta.
 * Version:           1.1.0
 * Author:            Studio Poda
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       studio-poda-widgets
 * Requires at least: 6.8
 * Requires PHP:      7.4
 * Requires Plugins:  elementor
 *
 * @package StudioPodaWidgets
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

define( 'PODA_WIDGETS_VERSION', '1.1.0' );
define( 'PODA_WIDGETS_FILE', __FILE__ );
define( 'PODA_WIDGETS_DIR', plugin_dir_path( __FILE__ ) );
define( 'PODA_WIDGETS_URL', plugin_dir_url( __FILE__ ) );

require_once PODA_WIDGETS_DIR . 'includes/class-poda-cpt.php';
require_once PODA_WIDGETS_DIR . 'includes/class-poda-render.php';
require_once PODA_WIDGETS_DIR . 'includes/class-poda-shortcodes.php';

/**
 * Boot the plugin's own (Elementor-independent) features.
 *
 * CPTs, meta, upload mimes and shortcodes work with or without the
 * editor; only the two widgets need Elementor, and they hook in below.
 */
function poda_widgets_init() {
	Poda_CPT::register();
	Poda_Shortcodes::register();
}
add_action( 'init', 'poda_widgets_init' );

add_filter( 'upload_mimes', array( 'Poda_CPT', 'allow_svg_mime' ) );
add_filter( 'wp_check_filetype_and_ext', array( 'Poda_CPT', 'check_svg_filetype' ), 10, 4 );

/**
 * Activation: register everything once so rewrite rules and the
 * seeded taxonomy terms exist before the first flush.
 */
function poda_widgets_activate() {
	Poda_CPT::register();
	Poda_CPT::seed_terms();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'poda_widgets_activate' );

/**
 * Register the shared stylesheet + the grid script. Widgets declare
 * the style as a dependency; the shortcode renderers enqueue both
 * on demand.
 */
function poda_widgets_register_assets() {
	wp_register_style(
		'studio-poda-widgets',
		PODA_WIDGETS_URL . 'assets/css/studio-poda-widgets.css',
		array(),
		PODA_WIDGETS_VERSION
	);
	wp_register_script(
		'poda-grid',
		PODA_WIDGETS_URL . 'assets/js/poda-grid.js',
		array(),
		PODA_WIDGETS_VERSION,
		true
	);
}
add_action( 'wp_enqueue_scripts', 'poda_widgets_register_assets' );
add_action( 'elementor/frontend/after_register_styles', 'poda_widgets_register_assets' );

/**
 * Add the "Studio Poda" widget category to the Elementor panel.
 *
 * @param \Elementor\Elements_Manager $elements_manager Elements manager.
 */
function poda_widgets_register_category( $elements_manager ) {
	$elements_manager->add_category(
		'studio-poda',
		array(
			'title' => esc_html__( 'Studio Poda', 'studio-poda-widgets' ),
			'icon'  => 'fa fa-bus',
		)
	);
}
add_action( 'elementor/elements/categories_registered', 'poda_widgets_register_category' );

/**
 * Register the two bespoke widgets with Elementor.
 *
 * @param \Elementor\Widgets_Manager $widgets_manager Widgets manager.
 */
function poda_widgets_register_widgets( $widgets_manager ) {
	require_once PODA_WIDGETS_DIR . 'widgets/class-poda-ticker-widget.php';
	require_once PODA_WIDGETS_DIR . 'widgets/class-poda-cover-rail-widget.php';

	$widgets_manager->register( new Poda_Ticker_Widget() );
	$widgets_manager->register( new Poda_Cover_Rail_Widget() );
}
add_action( 'elementor/widgets/register', 'poda_widgets_register_widgets' );
