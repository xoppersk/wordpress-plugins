<?php
/**
 * Shared renderers for the Ticker Band and the Cover Rail.
 *
 * Both the Elementor widgets and the shortcodes build their HTML here,
 * so the markup (and the motion language) can never drift between the
 * two routes. All classes are scoped `.poda-*`; CSS lives in
 * assets/css/studio-poda-widgets.css.
 *
 * @package StudioPodaWidgets
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Poda_Render {

	/**
	 * Ticker schemes (the design guide §1.4 palette roles).
	 *
	 * @var string[]
	 */
	const TICKER_SCHEMES = array( 'palm', 'ink-on-yellow', 'yellow-on-ink' );

	/**
	 * Render the route-board ticker band.
	 *
	 * Motion language mirrors the site's ticker (screens-v2 §07): a
	 * flex track of width max-content holding exactly TWO identical
	 * sets, animated translateX(0 -> -50%) linearly; the duplicate set
	 * is aria-hidden so assistive tech reads the route once.
	 *
	 * @param array $args {
	 *     @type array  $items         List of [ 'text' => string, 'link' => string ].
	 *     @type string $scheme        palm | ink-on-yellow | yellow-on-ink.
	 *     @type int    $speed         Seconds for one full loop (46 = site default).
	 *     @type bool   $pause_on_hover Pause the drift while hovered.
	 *     @type string $tab           Optional tab label ("Routes", "Services"...).
	 * }
	 * @return string HTML.
	 */
	public static function ticker( array $args ) {
		$args = wp_parse_args(
			$args,
			array(
				'items'         => array(),
				'scheme'        => 'palm',
				'speed'         => 46,
				'pause_on_hover' => true,
				'tab'           => '',
			)
		);

		// Normalise items: plain strings become text-only items.
		$items = array();
		foreach ( (array) $args['items'] as $item ) {
			if ( is_string( $item ) ) {
				$item = array( 'text' => $item, 'link' => '' );
			}
			$text = isset( $item['text'] ) ? trim( (string) $item['text'] ) : '';
			if ( '' === $text ) {
				continue;
			}
			$items[] = array(
				'text' => $text,
				'link' => isset( $item['link'] ) ? trim( (string) $item['link'] ) : '',
			);
		}
		if ( empty( $items ) ) {
			return '';
		}

		$scheme = in_array( $args['scheme'], self::TICKER_SCHEMES, true ) ? $args['scheme'] : 'palm';
		$speed  = max( 5, min( 240, (int) $args['speed'] ) );

		$set_html = self::ticker_set( $items, false );
		$dup_html = self::ticker_set( $items, true );

		$classes = 'poda-ticker poda-ticker--' . $scheme;
		if ( $args['pause_on_hover'] ) {
			$classes .= ' poda-ticker--pause';
		}

		$html = '<div class="' . esc_attr( $classes ) . '" style="--poda-ticker-duration:' . $speed . 's">';
		if ( '' !== trim( (string) $args['tab'] ) ) {
			$html .= '<span class="poda-ticker__tab">' . esc_html( $args['tab'] ) . '</span>';
		}
		$html .= '<div class="poda-ticker__row"><div class="poda-ticker__track">'
			. $set_html . $dup_html
			. '</div></div></div>';

		return $html;
	}

	/**
	 * One ticker set: every item followed by the "•" separator, in the
	 * site's order (item, sep, item, sep … trailing sep included).
	 *
	 * @param array $items Normalised items.
	 * @param bool  $hidden True for the aria-hidden duplicate set.
	 * @return string
	 */
	private static function ticker_set( array $items, $hidden ) {
		$html = '<div class="poda-ticker__set"' . ( $hidden ? ' aria-hidden="true"' : '' ) . '>';
		foreach ( $items as $item ) {
			$text = esc_html( $item['text'] );
			if ( '' !== $item['link'] ) {
				$text = '<a class="poda-ticker__link" href="' . esc_url( $item['link'] ) . '">' . $text . '</a>';
			}
			$html .= '<span class="poda-ticker__item">' . $text . '</span>'
				. '<span class="poda-ticker__sep" aria-hidden="true">&bull;</span>';
		}
		$html .= '</div>';
		return $html;
	}

	/**
	 * Card aspect ratios from the design guide §3 (the work grid's --r
	 * values; 1.87 = tall card). Keyed by project slug — this is
	 * design data, so it lives with the design code, not the posts.
	 *
	 * @var float[]
	 */
	const GRID_RATIOS = array(
		'papayo'             => 1.87,
		'luma-market'        => 1.33,
		'tok-radio'          => 1.87,
		'ferry-and-fern'     => 1.5,
		'mamies-kitchen'     => 1.33,
		'bendu-swim'         => 1.33,
		'kola-kola'          => 1.5,
		'city-loop'          => 1.33,
		'peppe-pot'          => 1.33,
		'waka-waka-tours'    => 1.5,
		'tide-and-timber'    => 1.33,
		'gbinti-records'     => 1.33,
		'salone-sweets'      => 1.87,
		'krio-kicks'         => 1.33,
		'bunce-botanicals'   => 1.33,
		'mango-money'        => 1.33,
		'tokeh-tents'        => 1.87,
		'diplomat-tailors'   => 1.33,
		'rainy-season-books' => 1.5,
		'fambul-farms'       => 1.33,
		'lumley-lights'      => 1.33,
		'wharf-and-ember'     => 1.5,
		'kekeh-kids'         => 1.87,
		'abacha-athletics'   => 1.33,
	);

	/**
	 * Filter-chip order on the work grid (the design guide §3).
	 *
	 * @var string[]
	 */
	const GRID_CHIP_ORDER = array( 'branding', 'web', 'packaging', 'campaigns' );

	/**
	 * Render the filterable work grid — ALL published poda_project
	 * posts as masonry cards with taxonomy-counted filter chips.
	 *
	 * The markup mirrors the design package's work screen: CSS-column
	 * masonry, each card a full-bleed cover with a bottom scrim and
	 * the name + client + city · year caption overlaid. The JS layer
	 * (assets/js/poda-grid.js) owns filtering and ports the design's
	 * MW motion pattern; with JS off, every card simply shows.
	 *
	 * @return string HTML ('' when there are no projects).
	 */
	public static function grid() {
		$projects = new WP_Query(
			array(
				'post_type'      => 'poda_project',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'menu_order',
				'order'          => 'ASC',
			)
		);
		if ( ! $projects->have_posts() ) {
			return '';
		}

		// Chips: counts computed from the taxonomy itself (term
		// counts track the published posts assigned to each term).
		$chips = '<div class="poda-grid__chips" role="group" aria-label="'
			. esc_attr__( 'Filter projects by category', 'studio-poda-widgets' ) . '">';
		$chips .= '<button type="button" class="poda-grid__chip is-selected" data-filter="all" aria-pressed="true">'
			. esc_html__( 'All', 'studio-poda-widgets' )
			. ' <span class="poda-grid__count">' . (int) $projects->found_posts . '</span></button>';
		foreach ( self::GRID_CHIP_ORDER as $slug ) {
			$term = get_term_by( 'slug', $slug, 'poda_project_cat' );
			if ( ! $term ) {
				continue;
			}
			$chips .= '<button type="button" class="poda-grid__chip" data-filter="' . esc_attr( $slug ) . '" aria-pressed="false">'
				. esc_html( $term->name )
				. ' <span class="poda-grid__count">' . (int) $term->count . '</span></button>';
		}
		$chips .= '</div>';

		$cards = '<div class="poda-grid__cards">';
		while ( $projects->have_posts() ) {
			$projects->the_post();
			$post_id = get_the_ID();
			$slug    = get_post_field( 'post_name', $post_id );
			$ratio   = isset( self::GRID_RATIOS[ $slug ] ) ? self::GRID_RATIOS[ $slug ] : 1.33;
			$cats    = wp_get_object_terms( $post_id, 'poda_project_cat', array( 'fields' => 'slugs' ) );
			$cats    = is_wp_error( $cats ) ? array() : $cats;
			$client  = get_post_meta( $post_id, '_poda_client', true );
			$city    = get_post_meta( $post_id, '_poda_city', true );
			$year    = get_post_meta( $post_id, '_poda_year', true );

			$place = array_filter(
				array( $city, $year ),
				static function ( $bit ) {
					return '' !== trim( (string) $bit );
				}
			);

			$cards .= '<a class="poda-grid__card" style="--r:' . esc_attr( $ratio ) . '" data-cats="'
				. esc_attr( implode( ' ', $cats ) ) . '" href="' . esc_url( get_permalink() ) . '">';
			$cards .= '<span class="poda-grid__cover">'
				. get_the_post_thumbnail( $post_id, 'full', array( 'class' => 'poda-grid__img', 'loading' => 'lazy', 'alt' => '' ) )
				. '</span>';
			$cards .= '<span class="poda-grid__scrim" aria-hidden="true"></span>';
			$cards .= '<span class="poda-grid__caption">'
				. '<span class="poda-grid__name">' . esc_html( get_the_title() ) . '</span>';
			if ( '' !== trim( (string) $client ) ) {
				$cards .= '<span class="poda-grid__client">' . esc_html( $client ) . '</span>';
			}
			if ( ! empty( $place ) ) {
				$cards .= '<span class="poda-grid__place">' . esc_html( implode( ' · ', $place ) ) . '</span>';
			}
			$cards .= '</span>';
			$cards .= '<span class="sr-only">' . esc_html__( 'Learn more', 'studio-poda-widgets' ) . '</span>';
			$cards .= '</a>';
		}
		$cards .= '</div>';
		wp_reset_postdata();

		return '<div class="poda-grid">' . $chips . $cards . '</div>';
	}

	/**
	 * Render the featured-work cover rail.
	 *
	 * Loops published poda_project posts; each card is the project's
	 * featured image (its SVG cover) + name caption, linking to the
	 * project's permalink (/work/<slug>/ via the CPT rewrite).
	 *
	 * @param array $args {
	 *     @type string $category poda_project_cat slug, '' = all.
	 *     @type int    $count    Max projects (default 8, the home rail).
	 *     @type string $orderby  menu_order | date | title | rand.
	 *     @type string $order    ASC | DESC.
	 * }
	 * @return string HTML ('' when there are no projects).
	 */
	public static function rail( array $args ) {
		$args = wp_parse_args(
			$args,
			array(
				'category' => '',
				'count'    => 8,
				'orderby'  => 'menu_order',
				'order'    => 'ASC',
			)
		);

		$query_args = array(
			'post_type'      => 'poda_project',
			'post_status'    => 'publish',
			'posts_per_page' => max( 1, min( 24, (int) $args['count'] ) ),
			'orderby'        => in_array( $args['orderby'], array( 'menu_order', 'date', 'title', 'rand' ), true ) ? $args['orderby'] : 'menu_order',
			'order'          => 'DESC' === strtoupper( (string) $args['order'] ) ? 'DESC' : 'ASC',
		);
		if ( '' !== trim( (string) $args['category'] ) ) {
			$query_args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => 'poda_project_cat',
					'field'    => 'slug',
					'terms'    => sanitize_title( $args['category'] ),
				),
			);
		}

		$projects = new WP_Query( $query_args );
		if ( ! $projects->have_posts() ) {
			return '';
		}

		$html = '<div class="poda-rail">';
		while ( $projects->have_posts() ) {
			$projects->the_post();
			$post_id = get_the_ID();

			$meta_bits = array_filter(
				array(
					get_post_meta( $post_id, '_poda_client', true ),
					get_post_meta( $post_id, '_poda_city', true ),
					get_post_meta( $post_id, '_poda_year', true ),
				),
				static function ( $bit ) {
					return '' !== trim( (string) $bit );
				}
			);

			$html .= '<article class="poda-rail__card">';
			$html .= '<a class="poda-rail__link" href="' . esc_url( get_permalink() ) . '">';
			$html .= '<span class="poda-rail__cover">'
				. get_the_post_thumbnail( $post_id, 'full', array( 'class' => 'poda-rail__img', 'loading' => 'lazy' ) )
				. '</span>';
			$html .= '<span class="poda-rail__caption">'
				. '<span class="poda-rail__name">' . esc_html( get_the_title() ) . '</span>';
			if ( ! empty( $meta_bits ) ) {
				$html .= '<span class="poda-rail__meta">' . esc_html( implode( ' · ', $meta_bits ) ) . '</span>';
			}
			$html .= '</span></a></article>';
		}
		$html .= '</div>';
		wp_reset_postdata();

		return $html;
	}
}
