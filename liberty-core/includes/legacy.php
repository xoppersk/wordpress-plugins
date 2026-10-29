<?php
/**
 * Compatibility with what the old site left in the database: post types and
 * fields defined through ACF Pro, and shortcodes from the Foxiz theme.
 *
 * ACF keeps its definitions as ordinary posts ('acf-post-type', 'acf-field-group',
 * 'acf-field'), and those stay in the database after the plugin is removed. We
 * read them so existing data keeps its exact keys and nothing has to be renamed.
 */

defined( 'ABSPATH' ) || exit;

/** ACF field group keys on the live site (from the ACF Field Groups screen). */
const LIBERTY_ACF_GROUP_TEAM = 'group_67ed7f2fb0c98';
const LIBERTY_ACF_GROUP_LIVE = 'group_67eafcd2c2985';

/**
 * Post type keys the old site registered through ACF.
 *
 * @return array{team?: string, live?: string}
 */
function liberty_legacy_post_types() {
	$cached = get_option( 'liberty_legacy_cpts' );
	if ( is_array( $cached ) ) {
		return $cached;
	}
	$found = array();
	global $wpdb;
	// Direct query: runs before post types are registered, so WP_Query cannot see 'acf-post-type'.
	$rows = $wpdb->get_results( "SELECT post_content FROM {$wpdb->posts} WHERE post_type = 'acf-post-type' AND post_status IN ('publish','acf-disabled')" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	foreach ( (array) $rows as $row ) {
		$def = maybe_unserialize( $row->post_content );
		if ( ! is_array( $def ) || empty( $def['post_type'] ) ) {
			continue;
		}
		$label = strtolower( ( $def['labels']['name'] ?? '' ) . ' ' . ( $def['labels']['singular_name'] ?? '' ) . ' ' . $def['post_type'] );
		if ( str_contains( $label, 'team' ) ) {
			$found['team'] = sanitize_key( $def['post_type'] );
		} elseif ( str_contains( $label, 'live' ) || str_contains( $label, 'youtube' ) ) {
			$found['live'] = sanitize_key( $def['post_type'] );
		}
	}
	update_option( 'liberty_legacy_cpts', $found, true );
	return $found;
}

/**
 * Field names (meta keys) inside one ACF field group, in screen order.
 *
 * @return array<int, array{name: string, label: string, type: string}>
 */
function liberty_legacy_fields( $group_key ) {
	static $cache = array();
	if ( isset( $cache[ $group_key ] ) ) {
		return $cache[ $group_key ];
	}
	global $wpdb;
	$group_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'acf-field-group' AND post_name = %s LIMIT 1", $group_key ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$fields   = array();
	if ( $group_id ) {
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT post_title, post_excerpt, post_content FROM {$wpdb->posts} WHERE post_type = 'acf-field' AND post_parent = %d ORDER BY menu_order ASC", $group_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		foreach ( (array) $rows as $r ) {
			$def      = maybe_unserialize( $r->post_content );
			$fields[] = array(
				'name'  => sanitize_key( $r->post_excerpt ),
				'label' => $r->post_title,
				'type'  => is_array( $def ) ? (string) ( $def['type'] ?? 'text' ) : 'text',
			);
		}
	}
	$cache[ $group_key ] = $fields;
	return $fields;
}

/**
 * The meta key the old YouTube Live field saved into.
 */
function liberty_legacy_live_url_key() {
	foreach ( liberty_legacy_fields( LIBERTY_ACF_GROUP_LIVE ) as $f ) {
		if ( in_array( $f['type'], array( 'url', 'text', 'oembed' ), true ) ) {
			return $f['name'];
		}
	}
	return 'youtube_live_url';
}

/**
 * Old pages retired in the move keep working as redirects (saved by `wp liberty migrate`).
 */
add_action(
	'template_redirect',
	static function () {
		if ( ! is_404() ) {
			return;
		}
		$map  = get_option( 'liberty_redirects', array() );
		$path = trailingslashit( (string) wp_parse_url( add_query_arg( array() ), PHP_URL_PATH ) );
		if ( is_array( $map ) && isset( $map[ $path ] ) ) {
			wp_safe_redirect( home_url( $map[ $path ] ), 301 );
			exit;
		}
	},
	1
);

/**
 * Shortcodes from the old theme. Posts that still contain them would print the
 * raw [code] once Foxiz is gone, so known ones are rendered sensibly and any
 * other Foxiz/Ruby shortcode is quietly removed. The audit command lists the
 * ones actually present so we can decide case by case.
 */
add_filter(
	'the_content',
	static function ( $content ) {
		if ( ! str_contains( $content, '[' ) ) {
			return $content;
		}
		$content = preg_replace( '/\[\/?(?:ruby|foxiz|rb)_[a-z0-9_]+[^\]]*\]/i', '', $content );
		// Shortcodes of plugins that are being removed print as raw text once the plugin is gone.
		foreach ( array( 'contact-form-7', 'contact-form', 'youtube-feed', 'elementor-template' ) as $code ) {
			if ( ! shortcode_exists( $code ) ) {
				$content = preg_replace( '/\[' . preg_quote( $code, '/' ) . '\b[^\]]*\]/i', '', $content );
			}
		}
		return $content;
	},
	9
);

/**
 * Story videos saved by the old Foxiz theme (131 stories on the live site).
 *
 * Foxiz kept them in these fields; the exact format varies by version, so every
 * shape we know of is accepted: a URL, a media-library ID, an embed code
 * (<iframe>), or a serialised array holding any of those.
 *
 * @return array{kind: string, src: string, stream?: array, live: bool}|null
 *         kind 'embed' (YouTube/Facebook, has 'stream') or 'file' (video file URL).
 */
function liberty_legacy_story_video( $post_id ) {
	$live = (bool) get_post_meta( $post_id, 'field_name_is_video_live', true );
	$keys = array( 'field_name_post_video_acf_name_external_video', 'field_name_post_video_video_self_url', 'field_name_post_video' );
	foreach ( $keys as $key ) {
		$raw = get_post_meta( $post_id, $key, true );
		foreach ( liberty_video_candidates( $raw ) as $c ) {
			$stream = function_exists( 'liberty_parse_stream' ) ? liberty_parse_stream( $c ) : null;
			if ( $stream ) {
				return array( 'kind' => 'embed', 'src' => $c, 'stream' => $stream, 'live' => $live );
			}
			if ( preg_match( '#^https?://\S+\.(mp4|webm|m4v|mov|ogv|m3u8)(\?\S*)?$#i', $c ) ) {
				return array( 'kind' => 'file', 'src' => $c, 'live' => $live );
			}
		}
	}
	return null;
}

/**
 * Every URL-looking value inside one stored field.
 *
 * @return string[]
 */
function liberty_video_candidates( $raw, $depth = 0 ) {
	if ( $depth > 3 || null === $raw || '' === $raw || false === $raw ) {
		return array();
	}
	if ( is_array( $raw ) || is_object( $raw ) ) {
		$out = array();
		foreach ( (array) $raw as $v ) {
			$out = array_merge( $out, liberty_video_candidates( $v, $depth + 1 ) );
		}
		return $out;
	}
	$raw = trim( (string) $raw );
	if ( ctype_digit( $raw ) ) {
		$url = wp_get_attachment_url( (int) $raw );
		return $url ? array( $url ) : array();
	}
	if ( is_serialized( $raw ) ) {
		return liberty_video_candidates( maybe_unserialize( $raw ), $depth + 1 );
	}
	if ( str_starts_with( $raw, '{' ) || str_starts_with( $raw, '[' ) ) {
		$json = json_decode( $raw, true );
		if ( is_array( $json ) ) {
			return liberty_video_candidates( $json, $depth + 1 );
		}
	}
	$out = array();
	// URLs inside an embed code (iframe src) or plain text.
	if ( preg_match_all( '#(?:src=["\'])?(https?:)?//[^\s"\'<>]+#i', html_entity_decode( $raw ), $m ) ) {
		foreach ( $m[0] as $u ) {
			$u     = preg_replace( '#^src=["\']#i', '', $u );
			$out[] = str_starts_with( $u, '//' ) ? 'https:' . $u : $u;
		}
	}
	// Facebook's embed player wraps the real video address in ?href=.
	foreach ( $out as $u ) {
		if ( str_contains( $u, 'facebook.com/plugins/video.php' ) ) {
			parse_str( (string) wp_parse_url( $u, PHP_URL_QUERY ), $q );
			if ( ! empty( $q['href'] ) ) {
				array_unshift( $out, $q['href'] );
			}
		}
	}
	return $out;
}
