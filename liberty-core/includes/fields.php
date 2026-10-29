<?php
/**
 * Field schema: one source of truth for the edit-screen boxes, saving and the API.
 */

defined( 'ABSPATH' ) || exit;

/**
 * @return array<string, array<string, array{label: string, type: string, group: string, meta: string, help?: string}>>
 */
function liberty_field_schema() {
	static $schema = null;
	if ( null !== $schema ) {
		return $schema;
	}
	$f = static function ( $label, $type, $group, $meta, $help = '' ) {
		return array_filter( compact( 'label', 'type', 'group', 'meta', 'help' ), static fn( $v ) => '' !== $v );
	};

	$post = array(
		'standfirst'  => $f( __( 'Standfirst (one-sentence summary under the headline)', 'liberty-core' ), 'textarea', 'story', 'liberty_standfirst', __( 'Leave empty to use the excerpt.', 'liberty-core' ) ),
		'kicker'      => $f( __( 'Label above the headline', 'liberty-core' ), 'text', 'story', 'liberty_kicker', __( 'Optional. For example “Op-ed” or “Analysis”. Leave empty to show the category.', 'liberty-core' ) ),
		'key_points'  => $f( __( 'The story in 30 seconds (one point per line)', 'liberty-core' ), 'textarea', 'story', 'liberty_key_points', __( 'Two to four short lines. Shown in a box above the article.', 'liberty-core' ) ),
		'pull_quote'  => $f( __( 'Pull quote', 'liberty-core' ), 'textarea', 'quote', 'liberty_pull_quote' ),
		'pull_cite'   => $f( __( 'Quote by', 'liberty-core' ), 'text', 'quote', 'liberty_pull_cite' ),
		'pull_after'  => $f( __( 'Show the quote after paragraph', 'liberty-core' ), 'number', 'quote', 'liberty_pull_after', __( 'Default 3.', 'liberty-core' ) ),
		'video_url'   => $f( __( 'Story video (YouTube or Facebook link)', 'liberty-core' ), 'url', 'quote', 'liberty_video_url', __( 'Optional. Shown inside the article.', 'liberty-core' ) ),
		'nums_title'  => $f( __( 'Numbers box title', 'liberty-core' ), 'text', 'numbers', 'liberty_nums_title', __( 'For example “SLBC by the numbers”. Leave the figures empty to hide the box.', 'liberty-core' ) ),
		'nums_after'  => $f( __( 'Show the box after paragraph', 'liberty-core' ), 'number', 'numbers', 'liberty_nums_after', __( 'Default 5.', 'liberty-core' ) ),
		'nums_source' => $f( __( 'Source line', 'liberty-core' ), 'text', 'numbers', 'liberty_nums_source' ),
	);
	for ( $i = 1; $i <= 4; $i++ ) {
		/* translators: %d: figure number */
		$post[ "num{$i}" ] = $f( sprintf( __( 'Figure %d', 'liberty-core' ), $i ), 'text', 'numbers', "liberty_num{$i}", 1 === $i ? __( 'For example US$50m+', 'liberty-core' ) : '' );
		/* translators: %d: figure number */
		$post[ "num{$i}_l" ] = $f( sprintf( __( 'Figure %d means', 'liberty-core' ), $i ), 'text', 'numbers', "liberty_num{$i}_l" );
	}

	// Team: keep the old ACF "Member Details" fields exactly, under their original keys.
	$team   = array();
	$legacy = liberty_legacy_fields( LIBERTY_ACF_GROUP_TEAM );
	foreach ( $legacy as $field ) {
		// ACF stored phone numbers as a number field; keep them as text so a leading 0 or + survives.
		$type                   = in_array( $field['type'], array( 'url', 'email', 'textarea', 'image' ), true ) ? $field['type'] : 'text';
		$team[ $field['name'] ] = $f( $field['label'], $type, 'member', $field['name'] );
	}
	if ( ! $legacy ) {
		$team = array(
			'role'     => $f( __( 'Role', 'liberty-core' ), 'text', 'member', 'liberty_role' ),
			'facebook' => $f( __( 'Facebook link', 'liberty-core' ), 'url', 'member', 'liberty_facebook' ),
			'x'        => $f( __( 'X link', 'liberty-core' ), 'url', 'member', 'liberty_x' ),
			'email'    => $f( __( 'Email', 'liberty-core' ), 'email', 'member', 'liberty_email' ),
		);
	}

	$schema = array(
		'post'               => $post,
		liberty_cpt( 'team' ) => $team,
		liberty_cpt( 'live' ) => array(
			'stream_url' => $f( __( 'YouTube or Facebook link', 'liberty-core' ), 'url', 'broadcast', liberty_legacy_live_url_key(), __( 'Paste the link exactly as you copied it. Any YouTube or Facebook Page video link works.', 'liberty-core' ) ),
		),
	);
	return $schema;
}

/**
 * The team member's role, whichever field holds it.
 */
function liberty_team_role( $post_id ) {
	foreach ( liberty_field_schema()[ liberty_cpt( 'team' ) ] as $key => $field ) {
		if ( 'text' === $field['type'] && preg_match( '/role|position|title|job|designation/i', $key . ' ' . $field['label'] ) ) {
			return (string) get_post_meta( $post_id, $field['meta'], true );
		}
	}
	return (string) get_post_meta( $post_id, 'liberty_role', true );
}

/**
 * Sanitise one value against its field definition.
 */
function liberty_sanitize_field( $value, array $field ) {
	switch ( $field['type'] ) {
		case 'textarea':
			return sanitize_textarea_field( (string) $value );
		case 'url':
			return esc_url_raw( trim( (string) $value ), array( 'http', 'https' ) );
		case 'email':
			return sanitize_email( (string) $value );
		case 'number':
		case 'image':
			return '' === trim( (string) $value ) ? '' : (string) absint( $value );
		default:
			return sanitize_text_field( (string) $value );
	}
}

/**
 * Key points as a clean list.
 *
 * @return string[]
 */
function liberty_key_points( $post_id ) {
	$raw = (string) get_post_meta( $post_id, 'liberty_key_points', true );
	return array_values( array_filter( array_map( 'trim', preg_split( '/\R/', $raw ) ) ) );
}

/**
 * Figures for the numbers box.
 *
 * @return array<int, array{n: string, l: string}>
 */
function liberty_numbers( $post_id ) {
	$out = array();
	for ( $i = 1; $i <= 4; $i++ ) {
		$n = trim( (string) get_post_meta( $post_id, "liberty_num{$i}", true ) );
		if ( '' !== $n ) {
			$out[] = array(
				'n' => $n,
				'l' => trim( (string) get_post_meta( $post_id, "liberty_num{$i}_l", true ) ),
			);
		}
	}
	return $out;
}
