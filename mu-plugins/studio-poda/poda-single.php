<?php
/**
 * Plugin Name: Poda Single (B3)
 * Description: Dynamic pieces for the poda_project single template:
 *              crumb / kicker / meta chips / intro / gallery / tags /
 *              explore link / featured-10 neighbour ticker shortcodes,
 *              plus the project-single stylesheet (uploads/poda/
 *              poda-single.css) printed inline on project singles.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ---------------- helpers ---------------- */

function poda_single_current_id() {
	$id = get_queried_object_id();
	if ( ! $id ) {
		$id = get_the_ID();
	}
	return $id ? (int) $id : 0;
}

function poda_single_kicker_text( $post_id ) {
	$client = trim( (string) get_post_meta( $post_id, '_poda_client', true ) );
	if ( '' === $client ) {
		return '';
	}
	$client = rtrim( $client, '.' );
	// Title-case per bible kickers ("Juice brand." -> "Juice Brand",
	// "E-commerce." -> "E-Commerce", "Sneaker shop." -> "Sneaker Shop").
	return mb_convert_case( mb_strtolower( $client ), MB_CASE_TITLE, 'UTF-8' );
}

/* ---------------- shortcodes ---------------- */

function poda_single_crumb() {
	$id = poda_single_current_id();
	if ( ! $id ) {
		return '';
	}
	return '<p class="crumb"><a href="/work/">Work</a><span class="sep">&rarr;</span>'
		. '<span aria-current="page">' . esc_html( get_the_title( $id ) ) . '</span></p>';
}
add_shortcode( 'poda_crumb', 'poda_single_crumb' );

function poda_single_kicker() {
	$id = poda_single_current_id();
	$k  = $id ? poda_single_kicker_text( $id ) : '';
	return '' === $k ? '' : '<p class="proj-kicker">' . esc_html( $k ) . '</p>';
}
add_shortcode( 'poda_kicker', 'poda_single_kicker' );

function poda_single_meta_chips() {
	$id = poda_single_current_id();
	if ( ! $id ) {
		return '';
	}
	$spans = '';
	foreach ( array( '_poda_city', '_poda_year' ) as $key ) {
		$v = trim( (string) get_post_meta( $id, $key, true ) );
		if ( '' !== $v ) {
			$spans .= '<span>' . esc_html( $v ) . '</span>';
		}
	}
	return '' === $spans ? '' : '<p class="meta-chip">' . $spans . '</p>';
}
add_shortcode( 'poda_meta_chips', 'poda_single_meta_chips' );

function poda_single_intro() {
	$id = poda_single_current_id();
	if ( ! $id ) {
		return '';
	}
	$excerpt = trim( (string) get_post_field( 'post_excerpt', $id ) );
	if ( '' === $excerpt ) {
		return '';
	}
	return '<p class="proj-intro">' . esc_html( $excerpt ) . '</p>';
}
add_shortcode( 'poda_intro', 'poda_single_intro' );

/**
 * Gallery: the post's attached SVG plates in menu_order, inlined.
 * Fallback: the featured cover as a single plate. Neither: no markup
 * (the zone collapses — no empty-plate artefacts).
 */
function poda_single_gallery() {
	$id = poda_single_current_id();
	if ( ! $id ) {
		return '';
	}
	$plates = array();

	// Only B3 case-study plates carry the _poda_b3_plate marker —
	// the featured cover is also an attached SVG (menu_order 0) and
	// must never leak into the gallery.
	$attachments = get_posts(
		array(
			'post_type'      => 'attachment',
			'post_parent'    => $id,
			'post_status'    => 'inherit',
			'post_mime_type' => 'image/svg+xml',
			'orderby'        => 'menu_order',
			'order'          => 'ASC',
			'posts_per_page' => -1,
			'meta_query'     => array(
				array(
					'key'     => '_poda_b3_plate',
					'compare' => 'EXISTS',
				),
			),
		)
	);
	foreach ( $attachments as $att ) {
		$file = get_attached_file( $att->ID );
		if ( $file && file_exists( $file ) ) {
			$svg = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- own uploads, inlined by design (specimen plates are inline SVG).
			if ( false !== $svg && false !== stripos( $svg, '<svg' ) ) {
				$plates[] = $svg;
			}
		}
	}

	if ( empty( $plates ) ) {
		$thumb = get_post_thumbnail_id( $id );
		if ( $thumb ) {
			$file = get_attached_file( $thumb );
			if ( $file && file_exists( $file ) && 'image/svg+xml' === get_post_mime_type( $thumb ) ) {
				$svg = file_get_contents( $file ); // phpcs:ignore -- see above.
				if ( false !== $svg && false !== stripos( $svg, '<svg' ) ) {
					$plates[] = $svg;
				}
			}
		}
	}

	if ( empty( $plates ) ) {
		return '';
	}
	$html = '<div class="p-gallery">';
	foreach ( $plates as $svg ) {
		$html .= '<div class="p-plate">' . $svg . '</div>';
	}
	$html .= '</div>';
	return $html;
}
add_shortcode( 'poda_gallery', 'poda_single_gallery' );

function poda_single_tags() {
	$id = poda_single_current_id();
	if ( ! $id ) {
		return '';
	}
	$tags = trim( (string) get_post_meta( $id, '_poda_tags', true ) );
	if ( '' === $tags ) {
		$terms = get_the_terms( $id, 'poda_project_cat' );
		if ( $terms && ! is_wp_error( $terms ) ) {
			$tags = implode( ' • ', wp_list_pluck( $terms, 'name' ) );
		}
	}
	if ( '' === $tags ) {
		return '';
	}
	// Specimen renders the tags line with " • " bullets (the stack
	// line keeps " · " middots); normalise whichever separator the
	// stored meta carries.
	$tags = str_replace( '·', '•', $tags );
	return '<p class="tags-line">' . esc_html( $tags ) . '</p>';
}
add_shortcode( 'poda_tags', 'poda_single_tags' );

function poda_single_explore() {
	$id = poda_single_current_id();
	if ( ! $id ) {
		return '';
	}
	$url   = trim( (string) get_post_meta( $id, '_poda_explore_url', true ) );
	$label = trim( (string) get_post_meta( $id, '_poda_explore_label', true ) );
	if ( '' === $url || '' === $label ) {
		return '';
	}
	return '<a class="explore-link" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
}
add_shortcode( 'poda_explore', 'poda_single_explore' );

/**
 * Featured-10 ticker: the 10 roster neighbours of the current
 * project — a window of 11 consecutive projects (menu_order =
 * bible §3 roster order) centred on the current one where the
 * roster allows, clamped at the edges, self excluded. Reproduces
 * the §5/§13 specimen lists exactly (Papayo 2–11 · Luma 1,3–11 ·
 * Krio 9–13,15–19). Rendered through the plugin's Poda_Render so
 * markup + motion match the poda-ticker widget.
 */
function poda_single_ticker() {
	$id = poda_single_current_id();
	if ( ! $id || ! class_exists( 'Poda_Render' ) ) {
		return '';
	}
	$roster = get_posts(
		array(
			'post_type'      => 'poda_project',
			'post_status'    => 'publish',
			'orderby'        => 'menu_order',
			'order'          => 'ASC',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		)
	);
	$idx = array_search( $id, $roster, true );
	if ( false === $idx ) {
		return '';
	}
	$n     = count( $roster );
	$start = max( 0, min( $idx - 5, max( 0, $n - 11 ) ) );
	$items = array();
	foreach ( array_slice( $roster, $start, 11 ) as $pid ) {
		if ( $pid === $id ) {
			continue;
		}
		$items[] = array(
			'text' => get_the_title( $pid ),
			'link' => get_permalink( $pid ),
		);
	}
	if ( empty( $items ) ) {
		return '';
	}
	wp_enqueue_style( 'studio-poda-widgets' );
	$band = Poda_Render::ticker(
		array(
			'items'          => $items,
			'scheme'         => 'palm',
			'speed'          => 46,
			'pause_on_hover' => true,
			'tab'            => 'Featured Work',
		)
	);
	return '' === $band ? '' : '<div class="poda-single-ticker">' . $band . '</div>';
}
add_shortcode( 'poda_single_ticker', 'poda_single_ticker' );

/* ---------------- stylesheet (project singles only) ---------------- */

add_action(
	'wp_head',
	function () {
		if ( ! is_singular( 'poda_project' ) ) {
			return;
		}
		$css_file = WP_CONTENT_DIR . '/uploads/poda/poda-single.css';
		if ( file_exists( $css_file ) ) {
			$css = file_get_contents( $css_file ); // phpcs:ignore -- local file, printed inline per the KIT mu-plugin pattern.
			if ( false !== $css && '' !== trim( $css ) ) {
				echo "\n<style id=\"poda-single-css\">\n" . $css . "\n</style>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted local stylesheet.
			}
		}
	},
	1001
);
