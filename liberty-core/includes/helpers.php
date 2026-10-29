<?php
/**
 * Shared helpers.
 */

defined( 'ABSPATH' ) || exit;

/**
 * One saved setting from a Liberty settings group, with a fallback.
 *
 * @param string $group Option group without the liberty_ prefix (live, newsroom, ads, site).
 */
function liberty_opt( $group, $key, $default = '' ) {
	$opts = get_option( 'liberty_' . $group, array() );
	return ( is_array( $opts ) && isset( $opts[ $key ] ) && '' !== $opts[ $key ] ) ? $opts[ $key ] : $default;
}

function liberty_set_opt( $group, $key, $value ) {
	$opts         = get_option( 'liberty_' . $group, array() );
	$opts         = is_array( $opts ) ? $opts : array();
	$opts[ $key ] = $value;
	update_option( 'liberty_' . $group, $opts, false );
}

/**
 * Post meta with a fallback.
 */
function liberty_meta( $key, $post_id = null, $default = '' ) {
	$post_id = $post_id ? $post_id : get_the_ID();
	$value   = get_post_meta( $post_id, 'liberty_' . $key, true );
	return ( '' === $value || null === $value || false === $value ) ? $default : $value;
}

/**
 * Post type key for a Liberty content type. The old site created these with
 * ACF; the keys are read from ACF's saved definitions when they exist so old
 * entries and URLs survive, otherwise the defaults are used.
 *
 * @param string $which 'team' or 'live'.
 */
function liberty_cpt( $which ) {
	static $keys = null;
	if ( null === $keys ) {
		$keys = array(
			'team' => defined( 'LIBERTY_CPT_TEAM' ) ? LIBERTY_CPT_TEAM : '',
			'live' => defined( 'LIBERTY_CPT_LIVE' ) ? LIBERTY_CPT_LIVE : '',
		);
		$found = liberty_legacy_post_types();
		foreach ( array( 'team' => 'team-member', 'live' => 'youtube-live' ) as $k => $fallback ) {
			if ( '' === $keys[ $k ] ) {
				$keys[ $k ] = $found[ $k ] ?? $fallback;
			}
		}
	}
	return $keys[ $which ] ?? '';
}

/**
 * True on the real public site. Ads, analytics and newsroom email only run
 * there, so the staging copy never shows ads on an unapproved domain or
 * emails the newsroom with test messages.
 */
function liberty_is_production() {
	$prod = (string) liberty_opt( 'site', 'production_host', 'libertyonlinetv.com' );
	$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
	$prod = preg_replace( '/^www\./', '', strtolower( $prod ) );
	$host = preg_replace( '/^www\./', '', strtolower( $host ) );
	return '' !== $host && $host === $prod;
}

/**
 * Clear the page cache after a site-wide change (live on or off, breaking
 * news). Works with LiteSpeed Cache when it is active and is harmless without it.
 */
function liberty_purge_cache() {
	do_action( 'litespeed_purge_all' );
	if ( function_exists( 'wp_cache_flush' ) ) {
		wp_cache_flush();
	}
	do_action( 'liberty_cache_purged' );
}

/**
 * Title case for headlines the newsroom typed in capitals.
 */
function liberty_title_case( $text ) {
	$letters = preg_match_all( '/\p{L}/u', $text );
	$upper   = preg_match_all( '/\p{Lu}/u', $text );
	if ( ! $letters || $upper < 0.7 * $letters ) {
		return $text;
	}
	$small = array( 'a', 'an', 'and', 'as', 'at', 'but', 'by', 'for', 'in', 'of', 'on', 'or', 'the', 'to', 'vs', 'with', 'from', 'into' );
	$words = explode( ' ', mb_strtolower( $text ) );
	foreach ( $words as $i => $w ) {
		if ( $i && in_array( $w, $small, true ) ) {
			continue;
		}
		$words[ $i ] = mb_strtoupper( mb_substr( $w, 0, 1 ) ) . mb_substr( $w, 1 );
	}
	return implode( ' ', $words );
}

/**
 * Is this database really this site's own database?
 *
 * Reads the site address stored in the database itself (ignoring any WP_HOME
 * override in wp-config.php) and compares it with the address WordPress is
 * running at. If two sites share one database (a staging copy wired to the live
 * database), at least one of them sees a different address here. In that case
 * the switch-over refuses to run and administrators see a warning.
 *
 * @return true|string True when safe, otherwise the address the database belongs to.
 */
function liberty_db_is_own() {
	global $wpdb;
	$stored = (string) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1", 'home' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$norm   = static fn( $u ) => preg_replace( '/^www\./', '', strtolower( (string) wp_parse_url( $u, PHP_URL_HOST ) ) );
	// The address the request actually came in on. home_url() alone would just echo the database back.
	$host   = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
	$here   = $host ? 'https://' . $host : ( defined( 'WP_HOME' ) ? WP_HOME : home_url() );
	return ( '' === $stored || $norm( $stored ) === $norm( $here ) ) ? true : $stored;
}

add_action(
	'admin_notices',
	static function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$own = liberty_db_is_own();
		if ( true !== $own ) {
			echo '<div class="notice notice-error"><p><strong>' . esc_html__( 'Liberty: this site may be sharing its database with another site.', 'liberty-core' ) . '</strong> ' . esc_html( sprintf( /* translators: 1: stored address, 2: current address */ __( 'The database says it belongs to %1$s, but this site runs at %2$s. Changes here may change that site too. The Liberty switch-over is blocked until each site has its own database.', 'liberty-core' ), $own, isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : home_url() ) ) . '</p></div>';
		}
	}
);
