<?php
/**
 * Liberty API (liberty/v1). The website's own scripts use it today; the Phase 2
 * app will read the same endpoints so site and app always agree. It runs next to
 * the GoodBarber API and does not touch it.
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'rest_api_init',
	static function () {
		$ns   = 'liberty/v1';
		$open = '__return_true';
		register_rest_route( $ns, '/live', array( 'methods' => 'GET', 'permission_callback' => $open, 'callback' => 'liberty_rest_live' ) );
		register_rest_route( $ns, '/breaking', array( 'methods' => 'GET', 'permission_callback' => $open, 'callback' => static fn() => rest_ensure_response( array( 'breaking' => liberty_breaking() ) ) ) );
		register_rest_route( $ns, '/briefing', array( 'methods' => 'GET', 'permission_callback' => $open, 'callback' => static fn() => rest_ensure_response( liberty_briefing() ) ) );
		register_rest_route( $ns, '/home', array( 'methods' => 'GET', 'permission_callback' => $open, 'callback' => 'liberty_rest_home' ) );
		register_rest_route(
			$ns,
			'/videos',
			array(
				'methods'             => 'GET',
				'permission_callback' => $open,
				'args'                => array( 'kind' => array( 'enum' => array( 'videos', 'shorts' ), 'default' => 'videos' ) ),
				'callback'            => static fn( WP_REST_Request $r ) => rest_ensure_response( array( 'videos' => liberty_youtube_videos( $r['kind'], 24 ) ) ),
			)
		);
		register_rest_route(
			$ns,
			'/view/(?P<id>\d+)',
			array(
				'methods'             => 'POST',
				'permission_callback' => $open,
				'callback'            => 'liberty_rest_view',
			)
		);
	}
);

function liberty_rest_live() {
	$s = liberty_live();
	if ( ! $s ) {
		return rest_ensure_response( array( 'live' => false ) );
	}
	$pick = static fn( $st ) => $st ? array_intersect_key( $st, array_flip( array( 'provider', 'embed', 'watch', 'thumb' ) ) ) : null;
	return rest_ensure_response(
		array(
			'live'    => true,
			'title'   => $s['title'],
			'started' => gmdate( 'c', (int) $s['started'] ),
			'stream'  => $pick( $s['stream'] ),
			'also'    => $pick( $s['second'] ?? null ),
		)
	);
}

/**
 * A story in the compact shape the app and the site scripts use.
 */
function liberty_rest_story( $id ) {
	$cats = get_the_category( $id );
	return array(
		'id'       => (int) $id,
		'title'    => html_entity_decode( liberty_title_case( get_the_title( $id ) ), ENT_QUOTES ),
		'link'     => get_permalink( $id ),
		'date'     => get_post_time( 'c', true, $id ),
		'category' => $cats ? html_entity_decode( $cats[0]->name, ENT_QUOTES ) : '',
		'image'    => (string) get_the_post_thumbnail_url( $id, 'large' ),
		'summary'  => wp_strip_all_tags( (string) ( get_post_meta( $id, 'liberty_standfirst', true ) ? get_post_meta( $id, 'liberty_standfirst', true ) : ( get_post_meta( $id, 'ruby_tagline', true ) ? get_post_meta( $id, 'ruby_tagline', true ) : get_the_excerpt( $id ) ) ) ),
	);
}

function liberty_rest_home() {
	$lead   = liberty_lead_story_id();
	$latest = get_posts( array( 'numberposts' => 12, 'fields' => 'ids', 'post__not_in' => array( $lead ) ) );
	return rest_ensure_response(
		array(
			'lead'      => $lead ? liberty_rest_story( $lead ) : null,
			'latest'    => array_map( 'liberty_rest_story', $latest ),
			'briefing'  => liberty_briefing(),
			'breaking'  => liberty_breaking(),
			'live'      => (bool) liberty_live(),
			'most_read' => array_map( 'liberty_rest_story', liberty_most_read( 5 ) ),
		)
	);
}

function liberty_rest_view( WP_REST_Request $r ) {
	$id  = absint( $r['id'] );
	$ua  = strtolower( (string) $r->get_header( 'user_agent' ) );
	$bot = '' === $ua || preg_match( '/bot|crawl|spider|slurp|preview|facebookexternalhit|whatsapp/', $ua );
	$key = 'liberty_v_' . $id . '_' . substr( wp_hash( (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) ), 0, 12 );
	if ( $bot || get_transient( $key ) ) {
		return rest_ensure_response( array( 'counted' => false ) );
	}
	set_transient( $key, 1, 30 * MINUTE_IN_SECONDS );
	return rest_ensure_response( array( 'counted' => liberty_count_view( $id ) ) );
}
