<?php
/**
 * Plugin Name:       Liberty Core
 * Description:       Newsroom features for Liberty Online TV: live streaming from YouTube and Facebook, team and broadcast content types, breaking news, the daily briefing, video feeds, ads, forms with spam protection, the data saver and the app API.
 * Version:           1.2.3
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            Sevyn Labs
 * Author URI:        https://sevynlabs.io
 * License:           GPL-2.0-or-later
 * Text Domain:       liberty-core
 */

defined( 'ABSPATH' ) || exit;

define( 'LIBERTY_CORE_VERSION', '1.2.3' );
define( 'LIBERTY_CORE_DIR', plugin_dir_path( __FILE__ ) );
define( 'LIBERTY_CORE_URL', plugin_dir_url( __FILE__ ) );

foreach ( array( 'helpers', 'legacy', 'content-types', 'fields', 'meta-boxes', 'settings', 'live', 'video', 'newsroom', 'ads', 'forms', 'brief', 'follow', 'admin', 'rest' ) as $liberty_file ) {
	require_once LIBERTY_CORE_DIR . 'includes/' . $liberty_file . '.php';
}
require_once LIBERTY_CORE_DIR . 'includes/cli.php';
require_once LIBERTY_CORE_DIR . 'includes/tools.php';

register_activation_hook(
	__FILE__,
	static function () {
		liberty_register_content_types();
		flush_rewrite_rules();
		if ( ! wp_next_scheduled( 'liberty_live_check' ) ) {
			wp_schedule_event( time() + 60, 'liberty_two_minutes', 'liberty_live_check' );
		}
	}
);

register_deactivation_hook(
	__FILE__,
	static function () {
		wp_clear_scheduled_hook( 'liberty_live_check' );
		flush_rewrite_rules();
	}
);
