<?php
/**
 * Newsroom content the theme and the app both read: breaking news, the
 * "Need to know" briefing, the lead story and most-read stories.
 */

defined( 'ABSPATH' ) || exit;

/**
 * The breaking news banner, or null when it is off or has expired.
 *
 * @return array{text: string, url: string, time: int}|null
 */
function liberty_breaking() {
	if ( ! liberty_opt( 'newsroom', 'breaking_on' ) ) {
		return null;
	}
	$since = (int) liberty_opt( 'newsroom', 'breaking_since', 0 );
	$hours = (int) liberty_opt( 'newsroom', 'breaking_hours', 6 );
	if ( $since && $hours && time() > $since + $hours * HOUR_IN_SECONDS ) {
		return null;
	}
	$post = (int) liberty_opt( 'newsroom', 'breaking_post', 0 );
	$text = (string) liberty_opt( 'newsroom', 'breaking_text', '' );
	if ( '' === $text && $post ) {
		$text = liberty_title_case( get_the_title( $post ) );
	}
	if ( '' === $text ) {
		return null;
	}
	return array(
		'text' => $text,
		'url'  => $post ? (string) get_permalink( $post ) : '',
		'time' => $since ? $since : time(),
	);
}

/**
 * "Need to know" items, split into the bold label and the sentence.
 *
 * @return array{title: string, items: array<int, array{label: string, text: string}>}
 */
function liberty_briefing( $exclude = array() ) {
	$items = array();
	for ( $i = 1; $i <= 3; $i++ ) {
		$line = trim( (string) liberty_opt( 'newsroom', 'ntk_' . $i, '' ) );
		if ( '' === $line ) {
			continue;
		}
		if ( preg_match( '/^([^.]{2,30})\.\s+(.+)$/u', $line, $m ) ) {
			$items[] = array( 'label' => $m[1], 'text' => $m[2] );
		} else {
			$items[] = array( 'label' => '', 'text' => $line );
		}
	}
	// Nothing written by the newsroom: fill the briefing from the week's most-read stories, then the newest.
	if ( ! $items ) {
		$exclude = array_map( 'intval', (array) $exclude );
		$picked  = array();
		$sets    = array(
			array( 'meta_key' => 'liberty_views_week', 'orderby' => 'meta_value_num', 'order' => 'DESC', 'date_query' => array( array( 'after' => '10 days ago' ) ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
			array( 'orderby' => 'date', 'order' => 'DESC' ),
		);
		foreach ( $sets as $args ) {
			if ( count( $picked ) >= 3 ) {
				break;
			}
			$ids = get_posts( array_merge( $args, array( 'post_type' => 'post', 'post_status' => 'publish', 'numberposts' => 3, 'fields' => 'ids', 'post__not_in' => array_merge( $exclude, $picked ), 'ignore_sticky_posts' => true, 'no_found_rows' => true ) ) );
			foreach ( $ids as $id ) {
				if ( count( $picked ) < 3 ) {
					$picked[] = (int) $id;
				}
			}
		}
		foreach ( $picked as $id ) {
			$items[] = array(
				'label' => function_exists( 'liberty_kicker' ) ? liberty_kicker( $id ) : '',
				'text'  => wp_strip_all_tags( get_the_title( $id ) ),
				'url'   => get_permalink( $id ),
				'auto'  => true,
			);
		}
	}
	$title = (string) liberty_opt( 'newsroom', 'ntk_title', '' );
	return array(
		/* translators: %s: day name */
		'title' => '' !== $title ? $title : sprintf( __( '%s briefing', 'liberty-core' ), wp_date( 'l' ) ),
		'items' => $items,
	);
}

/**
 * The lead story ID: chosen by the editor, else the newest sticky, else the newest story.
 */
function liberty_lead_story_id() {
	$id = (int) liberty_opt( 'newsroom', 'lead_post', 0 );
	if ( $id && 'publish' === get_post_status( $id ) ) {
		return $id;
	}
	$sticky = get_option( 'sticky_posts' );
	if ( $sticky ) {
		$q = get_posts( array( 'post__in' => $sticky, 'numberposts' => 1, 'fields' => 'ids', 'ignore_sticky_posts' => true ) );
		if ( $q ) {
			return (int) $q[0];
		}
	}
	$q = get_posts( array( 'numberposts' => 1, 'fields' => 'ids' ) );
	return $q ? (int) $q[0] : 0;
}

/* ------------------------------------------------------------ most read */

/**
 * Count one read. Called from the browser (pages are cached, so PHP alone
 * cannot count), once per story per visitor session.
 */
function liberty_count_view( $post_id ) {
	$post_id = absint( $post_id );
	if ( ! $post_id || 'post' !== get_post_type( $post_id ) || 'publish' !== get_post_status( $post_id ) ) {
		return false;
	}
	$week = 'liberty_views_w' . wp_date( 'oW' );
	update_post_meta( $post_id, 'liberty_views', (int) get_post_meta( $post_id, 'liberty_views', true ) + 1 );
	update_post_meta( $post_id, $week, (int) get_post_meta( $post_id, $week, true ) + 1 );
	update_post_meta( $post_id, 'liberty_views_week', wp_date( 'oW' ) );
	return true;
}

/**
 * Most-read story IDs for this week, filling up with last week and then the
 * newest stories so the box is never empty early on a Monday.
 *
 * @return int[]
 */
function liberty_most_read( $limit = 5, array $exclude = array() ) {
	$key = 'liberty_most_read_' . $limit;
	$ids = get_transient( $key );
	if ( ! is_array( $ids ) ) {
		$ids = array();
		foreach ( array( wp_date( 'oW' ), wp_date( 'oW', time() - WEEK_IN_SECONDS ) ) as $week ) {
			$more = get_posts(
				array(
					'numberposts'  => $limit * 2,
					'fields'       => 'ids',
					'meta_key'     => 'liberty_views_w' . $week, // phpcs:ignore WordPress.DB.SlowDBQuery
					'orderby'      => 'meta_value_num',
					'order'        => 'DESC',
					'post__not_in' => $ids,
				)
			);
			$ids = array_merge( $ids, array_map( 'intval', $more ) );
		}
		set_transient( $key, $ids, 15 * MINUTE_IN_SECONDS );
	}
	$ids = array_values( array_diff( $ids, $exclude ) );
	if ( count( $ids ) < $limit ) {
		$fill = get_posts( array( 'numberposts' => $limit * 2, 'fields' => 'ids', 'post__not_in' => array_merge( $ids, $exclude ) ) );
		$ids  = array_merge( $ids, array_map( 'intval', $fill ) );
	}
	return array_slice( $ids, 0, $limit );
}
