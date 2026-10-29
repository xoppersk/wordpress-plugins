<?php
/**
 * Follow sections and topics without an account.
 *
 * What a reader follows is kept on their own phone or computer (no sign-up, no
 * personal data on the server). The Following page asks this endpoint for the
 * newest stories in those sections. Readers who also want them by email add
 * their address and the Morning Brief includes a "From what you follow" part.
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'rest_api_init',
	static function () {
		register_rest_route(
			'liberty/v1',
			'/following',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'args'                => array(
					'f'     => array( 'type' => 'string', 'default' => '' ),
					'since' => array( 'type' => 'integer', 'default' => 0 ),
				),
				'callback'            => 'liberty_rest_following',
			)
		);
	}
);

/**
 * @param WP_REST_Request $r f = "c:12,t:5" (max 20), since = unix time for the "new" count.
 */
function liberty_rest_following( WP_REST_Request $r ) {
	$cats  = array();
	$tags  = array();
	$terms = array();
	foreach ( array_slice( array_filter( explode( ',', (string) $r['f'] ) ), 0, 20 ) as $f ) {
		if ( ! preg_match( '/^([ct]):(\d+)$/', trim( $f ), $m ) ) {
			continue;
		}
		$tax  = 'c' === $m[1] ? 'category' : 'post_tag';
		$term = get_term( (int) $m[2], $tax );
		if ( ! $term || is_wp_error( $term ) ) {
			continue;
		}
		if ( 'c' === $m[1] ) {
			$cats[] = $term->term_id;
		} else {
			$tags[] = $term->term_id;
		}
		$terms[] = array( 'key' => $m[1] . ':' . $term->term_id, 'name' => html_entity_decode( $term->name, ENT_QUOTES ), 'link' => get_term_link( $term ) );
	}
	if ( ! $cats && ! $tags ) {
		return rest_ensure_response( array( 'terms' => array(), 'stories' => array(), 'new' => 0 ) );
	}
	$tax = array( 'relation' => 'OR' );
	if ( $cats ) {
		$tax[] = array( 'taxonomy' => 'category', 'terms' => $cats );
	}
	if ( $tags ) {
		$tax[] = array( 'taxonomy' => 'post_tag', 'terms' => $tags );
	}
	$ids   = get_posts( array( 'numberposts' => 20, 'fields' => 'ids', 'tax_query' => $tax, 'ignore_sticky_posts' => true ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	$since = absint( $r['since'] );
	$new   = 0;
	$out   = array();
	foreach ( $ids as $id ) {
		$story          = liberty_rest_story( $id );
		$story['image'] = (string) get_the_post_thumbnail_url( $id, 'liberty-card' );
		$story['ts']    = (int) get_post_time( 'U', true, $id );
		if ( $since && $story['ts'] > $since ) {
			++$new;
		}
		$out[] = $story;
	}
	$res = rest_ensure_response( array( 'terms' => $terms, 'stories' => $out, 'new' => $new ) );
	$res->header( 'Cache-Control', 'public, max-age=120' );
	return $res;
}
