<?php
/**
 * Plugin Name:       Scroll Chapters
 * Plugin URI:        https://shekukoroma.com
 * Description:       Elementor addon for Kowloon Light Office: Chapter Rail, Typewriter, Marquee Band and Layered Image Stack widgets, plus seven small site helpers (filter motion, form error summary, grouped search, bend order, header hide, menu motion, service tag boxes).
 * Version:           1.0.9
 * Author:            Sevyn Labs
 * Author URI:        https://shekukoroma.com
 * Text Domain:       scroll-chapters
 * Domain Path:       /languages
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Elementor tested up to: 4.3.5
 *
 * @package ScrollChapters
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'SC_VERSION', '1.0.9' );
define( 'SC_MIN_ELEMENTOR_VERSION', '3.20.0' );
define( 'SC_MIN_PHP_VERSION', '8.1' );
define( 'SC_MIN_WP_VERSION', '6.5' );
define( 'SC_PLUGIN_FILE', __FILE__ );
define( 'SC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Admin notice helper.
 *
 * @param string $message Notice text (already escaped where needed).
 */
function sc_admin_notice( $message ) {
	add_action(
		'admin_notices',
		static function () use ( $message ) {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				wp_kses_post( $message )
			);
		}
	);
}

/**
 * Environment guards: PHP and WordPress versions.
 *
 * @return bool True when the environment can run the plugin.
 */
function sc_environment_ok() {
	if ( version_compare( PHP_VERSION, SC_MIN_PHP_VERSION, '<' ) ) {
		sc_admin_notice(
			sprintf(
				/* translators: 1: required PHP version, 2: running PHP version. */
				esc_html__( 'Scroll Chapters requires PHP %1$s or higher. This site runs PHP %2$s, so the plugin has not loaded.', 'scroll-chapters' ),
				SC_MIN_PHP_VERSION,
				PHP_VERSION
			)
		);
		return false;
	}

	global $wp_version;
	if ( isset( $wp_version ) && version_compare( $wp_version, SC_MIN_WP_VERSION, '<' ) ) {
		sc_admin_notice(
			sprintf(
				/* translators: 1: required WordPress version, 2: running WordPress version. */
				esc_html__( 'Scroll Chapters requires WordPress %1$s or higher. This site runs %2$s, so the plugin has not loaded.', 'scroll-chapters' ),
				SC_MIN_WP_VERSION,
				$wp_version
			)
		);
		return false;
	}

	return true;
}

/**
 * Elementor presence + version check, run once Elementor has loaded.
 */
function sc_elementor_check() {
	if ( ! did_action( 'elementor/loaded' ) ) {
		sc_admin_notice(
			esc_html__( 'Scroll Chapters needs Elementor to be installed and active. The widgets and helpers have not loaded.', 'scroll-chapters' )
		);
		return;
	}

	if ( defined( 'ELEMENTOR_VERSION' ) && version_compare( ELEMENTOR_VERSION, SC_MIN_ELEMENTOR_VERSION, '<' ) ) {
		sc_admin_notice(
			sprintf(
				/* translators: 1: required Elementor version, 2: running Elementor version. */
				esc_html__( 'Scroll Chapters requires Elementor %1$s or higher (containers on). This site runs %2$s, so the plugin has not loaded.', 'scroll-chapters' ),
				SC_MIN_ELEMENTOR_VERSION,
				ELEMENTOR_VERSION
			)
		);
		return;
	}

	sc_bootstrap();
}

/**
 * Bootstrap the plugin once the environment and Elementor check out.
 */
function sc_bootstrap() {
	require_once SC_PLUGIN_DIR . 'includes/class-settings.php';
	require_once SC_PLUGIN_DIR . 'includes/class-plugin.php';

	\SC_Plugin::instance();
}

/**
 * Set the sc_settings option to spec defaults on first activation.
 */
function sc_activate() {
	if ( false === get_option( 'sc_settings' ) ) {
		require_once SC_PLUGIN_DIR . 'includes/class-settings.php';
		add_option( 'sc_settings', \SC_Settings::defaults() );
	}
}
register_activation_hook( __FILE__, 'sc_activate' );

// Boot after all plugins are loaded so the Elementor check is meaningful.
add_action( 'plugins_loaded', static function () {
	if ( ! sc_environment_ok() ) {
		return;
	}
	sc_elementor_check();
} );
