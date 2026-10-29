<?php
/**
 * Field schema for every Poda content type. One source of truth for
 * registration, meta boxes, sanitising and the setup data.
 */

defined( 'ABSPATH' ) || exit;

/**
 * @return array<string, array<string, array>> post type => field key => definition
 */
function poda_field_schema() {
	$art_help = __( 'File name from the theme art folder, without .svg (for example cover-papayo).', 'poda-core' );

	$project = array(
		'kind'     => array( 'label' => __( 'Kind of client', 'poda-core' ), 'type' => 'text', 'group' => 'card', 'help' => __( 'For example: Juice brand', 'poda-core' ) ),
		'city'     => array( 'label' => __( 'City', 'poda-core' ), 'type' => 'text', 'group' => 'card' ),
		'year'     => array( 'label' => __( 'Year', 'poda-core' ), 'type' => 'text', 'group' => 'card' ),
		'line'     => array( 'label' => __( 'One-line summary', 'poda-core' ), 'type' => 'text', 'group' => 'card' ),
		'art'      => array( 'label' => __( 'Cover art', 'poda-core' ), 'type' => 'text', 'group' => 'card', 'help' => $art_help ),
		'art_wide' => array( 'label' => __( 'Wide hero art (optional)', 'poda-core' ), 'type' => 'text', 'group' => 'card', 'help' => $art_help ),
		'featured' => array( 'label' => __( 'Feature on the home page', 'poda-core' ), 'type' => 'checkbox', 'group' => 'card' ),
		'tagline'  => array( 'label' => __( 'Case study headline', 'poda-core' ), 'type' => 'textarea', 'group' => 'case' ),
		'client'   => array( 'label' => __( 'Client', 'poda-core' ), 'type' => 'text', 'group' => 'case' ),
		'where'    => array( 'label' => __( 'Where', 'poda-core' ), 'type' => 'text', 'group' => 'case' ),
		'what'     => array( 'label' => __( 'What we did', 'poda-core' ), 'type' => 'text', 'group' => 'case' ),
		'ride'     => array( 'label' => __( 'Ride time', 'poda-core' ), 'type' => 'text', 'group' => 'case' ),
		'quote'    => array( 'label' => __( 'Client quote', 'poda-core' ), 'type' => 'textarea', 'group' => 'case' ),
		'cite'     => array( 'label' => __( 'Quote by', 'poda-core' ), 'type' => 'text', 'group' => 'case' ),
		'riders'   => array( 'label' => __( 'Crew on this project', 'poda-core' ), 'type' => 'crew', 'group' => 'case' ),
	);
	for ( $i = 1; $i <= 3; $i++ ) {
		/* translators: %d: result number */
		$project[ "r{$i}_n" ] = array( 'label' => sprintf( __( 'Result %d — number', 'poda-core' ), $i ), 'type' => 'text', 'group' => 'results' );
		/* translators: %d: result number */
		$project[ "r{$i}_t" ] = array( 'label' => sprintf( __( 'Result %d — what it means', 'poda-core' ), $i ), 'type' => 'text', 'group' => 'results' );
	}
	for ( $i = 1; $i <= 3; $i++ ) {
		/* translators: %d: chapter number */
		$project[ "c{$i}_k" ] = array( 'label' => sprintf( __( 'Chapter %d — label', 'poda-core' ), $i ), 'type' => 'text', 'group' => 'chapters' );
		/* translators: %d: chapter number */
		$project[ "c{$i}_h" ] = array( 'label' => sprintf( __( 'Chapter %d — heading', 'poda-core' ), $i ), 'type' => 'text', 'group' => 'chapters' );
		/* translators: %d: chapter number */
		$project[ "c{$i}_b" ] = array( 'label' => sprintf( __( 'Chapter %d — text (blank line between paragraphs)', 'poda-core' ), $i ), 'type' => 'textarea', 'group' => 'chapters' );
	}
	for ( $i = 1; $i <= 4; $i++ ) {
		/* translators: %d: gallery item number */
		$project[ "g{$i}_a" ] = array( 'label' => sprintf( __( 'Piece %d — art', 'poda-core' ), $i ), 'type' => 'text', 'group' => 'gallery', 'help' => $art_help );
		/* translators: %d: gallery item number */
		$project[ "g{$i}_t" ] = array( 'label' => sprintf( __( 'Piece %d — title', 'poda-core' ), $i ), 'type' => 'text', 'group' => 'gallery' );
		/* translators: %d: gallery item number */
		$project[ "g{$i}_d" ] = array( 'label' => sprintf( __( 'Piece %d — caption', 'poda-core' ), $i ), 'type' => 'text', 'group' => 'gallery' );
		/* translators: %d: gallery item number */
		$project[ "g{$i}_w" ] = array( 'label' => sprintf( __( 'Piece %d — show wide', 'poda-core' ), $i ), 'type' => 'checkbox', 'group' => 'gallery' );
	}
	for ( $i = 1; $i <= 4; $i++ ) {
		/* translators: %d: colour number */
		$project[ "p{$i}_n" ] = array( 'label' => sprintf( __( 'Colour %d — name', 'poda-core' ), $i ), 'type' => 'text', 'group' => 'kit' );
		/* translators: %d: colour number */
		$project[ "p{$i}_h" ] = array( 'label' => sprintf( __( 'Colour %d — hex', 'poda-core' ), $i ), 'type' => 'color', 'group' => 'kit' );
	}
	$project['type_note'] = array( 'label' => __( 'Type note', 'poda-core' ), 'type' => 'textarea', 'group' => 'kit' );
	$project['kit_line']  = array( 'label' => __( 'Kit sign-off line', 'poda-core' ), 'type' => 'text', 'group' => 'kit' );

	return array(
		'poda_project' => $project,
		'poda_crew'    => array(
			'role' => array( 'label' => __( 'Role', 'poda-core' ), 'type' => 'text', 'group' => 'main' ),
			'city' => array( 'label' => __( 'City', 'poda-core' ), 'type' => 'text', 'group' => 'main' ),
			'bio'  => array( 'label' => __( 'One-line bio', 'poda-core' ), 'type' => 'text', 'group' => 'main' ),
			'art'  => array( 'label' => __( 'Portrait art', 'poda-core' ), 'type' => 'text', 'group' => 'main', 'help' => $art_help ),
		),
		'poda_service' => array(
			'weeks' => array( 'label' => __( 'Typical timeframe', 'poda-core' ), 'type' => 'text', 'group' => 'main', 'help' => __( 'For example: 6–8 wks', 'poda-core' ) ),
		),
		'post'         => array(
			'author_crew' => array( 'label' => __( 'Written by (crew member)', 'poda-core' ), 'type' => 'crew_one', 'group' => 'main' ),
		),
	);
}

/**
 * Sanitise one value against its field definition.
 */
function poda_sanitize_field( $value, array $field ) {
	switch ( $field['type'] ) {
		case 'checkbox':
			return $value ? '1' : '';
		case 'textarea':
			return sanitize_textarea_field( (string) $value );
		case 'color':
			$hex = sanitize_hex_color( (string) $value );
			return $hex ? $hex : '';
		case 'crew':
			$ids = array_filter( array_map( 'absint', is_array( $value ) ? $value : explode( ',', (string) $value ) ) );
			return implode( ',', $ids );
		case 'crew_one':
			return (string) absint( $value );
		default:
			return sanitize_text_field( (string) $value );
	}
}
