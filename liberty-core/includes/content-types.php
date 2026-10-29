<?php
/**
 * Content types: Team Members and YouTube Live broadcasts.
 *
 * While ACF Pro is still active it registers these itself; we only register
 * them when nothing else has, so the switch-over has no gap and no duplicates.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', 'liberty_register_content_types', 20 );

function liberty_register_content_types() {
	$team = liberty_cpt( 'team' );
	if ( ! post_type_exists( $team ) ) {
		register_post_type(
			$team,
			array(
				'labels'        => array(
					'name'          => __( 'Team Members', 'liberty-core' ),
					'singular_name' => __( 'Team Member', 'liberty-core' ),
					'add_new_item'  => __( 'Add team member', 'liberty-core' ),
					'edit_item'     => __( 'Edit team member', 'liberty-core' ),
					'all_items'     => __( 'All team members', 'liberty-core' ),
				),
				'public'        => true,
				'has_archive'   => false,
				'menu_icon'     => 'dashicons-groups',
				'menu_position' => 21,
				'supports'      => array( 'title', 'editor', 'thumbnail', 'page-attributes' ),
				'show_in_rest'  => true,
				'rewrite'       => array( 'slug' => $team, 'with_front' => false ),
			)
		);
	}

	$live = liberty_cpt( 'live' );
	if ( ! post_type_exists( $live ) ) {
		register_post_type(
			$live,
			array(
				'labels'        => array(
					'name'          => __( 'YouTube Live', 'liberty-core' ),
					'singular_name' => __( 'Broadcast', 'liberty-core' ),
					'add_new_item'  => __( 'Add past broadcast', 'liberty-core' ),
					'edit_item'     => __( 'Edit broadcast', 'liberty-core' ),
					'all_items'     => __( 'Past broadcasts', 'liberty-core' ),
					'menu_name'     => __( 'Broadcasts', 'liberty-core' ),
				),
				'public'        => true,
				'has_archive'   => true,
				'menu_icon'     => 'dashicons-video-alt3',
				'menu_position' => 6,
				'supports'      => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
				'show_in_rest'  => true,
				'rewrite'       => array( 'slug' => $live, 'with_front' => false ),
			)
		);
	}
}

/**
 * The stream link saved on a broadcast, whichever field it came from: our own
 * field, or the old ACF "YouTube Live URL" field.
 */
function liberty_broadcast_url( $post_id ) {
	$url = get_post_meta( $post_id, liberty_legacy_live_url_key(), true );
	if ( ! $url ) {
		$url = get_post_meta( $post_id, 'liberty_stream_url', true );
	}
	return is_string( $url ) ? trim( $url ) : '';
}
