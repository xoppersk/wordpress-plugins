<?php
/**
 * Shortcode fallbacks: [poda_ticker] and [poda_rail], plus the
 * work grid [poda_grid].
 *
 * These exist for surfaces that cannot hold the Elementor widgets —
 * most importantly Elementor V4 (Atomic) documents, which ship no
 * native shortcode element. Dropped into a classic Shortcode widget
 * (or any shortcode-rendering context) they produce exactly the
 * widget markup, via Poda_Render.
 *
 * @package StudioPodaWidgets
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Poda_Shortcodes {

	/**
	 * Register both shortcodes. Called on `init`.
	 */
	public static function register() {
		add_shortcode( 'poda_ticker', array( __CLASS__, 'ticker' ) );
		add_shortcode( 'poda_rail', array( __CLASS__, 'rail' ) );
		add_shortcode( 'poda_grid', array( __CLASS__, 'grid' ) );
	}

	/**
	 * [poda_grid] — the filterable work grid (all projects,
	 * taxonomy-counted chips, MW-motion filtering in poda-grid.js).
	 *
	 * @param array $atts Shortcode attributes (none defined).
	 * @return string
	 */
	public static function grid( $atts ) {
		unset( $atts );
		wp_enqueue_style( 'studio-poda-widgets' );
		wp_enqueue_script( 'poda-grid' );

		return Poda_Render::grid();
	}

	/**
	 * [poda_ticker items="Freetown · London · Lagos" scheme="palm"
	 *              speed="46" pause="yes" tab="Routes"]
	 *
	 * `items` is one string split on "·" (the route-board separator).
	 * Per-item links are a widget-only feature (repeater rows carry a
	 * link control); the shortcode renders text-only stops.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public static function ticker( $atts ) {
		$atts = shortcode_atts(
			array(
				'items'  => '',
				'scheme' => 'palm',
				'speed'  => 46,
				'pause'  => 'yes',
				'tab'    => '',
			),
			is_array( $atts ) ? $atts : array(),
			'poda_ticker'
		);

		$items = array();
		foreach ( explode( '·', (string) $atts['items'] ) as $piece ) {
			$piece = trim( $piece );
			if ( '' !== $piece ) {
				$items[] = $piece;
			}
		}
		if ( empty( $items ) ) {
			return '';
		}

		wp_enqueue_style( 'studio-poda-widgets' );

		return Poda_Render::ticker(
			array(
				'items'          => $items,
				'scheme'         => $atts['scheme'],
				'speed'          => (int) $atts['speed'],
				'pause_on_hover' => self::truthy( $atts['pause'] ),
				'tab'            => $atts['tab'],
			)
		);
	}

	/**
	 * [poda_rail category="branding" count="8" orderby="menu_order" order="ASC"]
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public static function rail( $atts ) {
		$atts = shortcode_atts(
			array(
				'category' => '',
				'count'    => 8,
				'orderby'  => 'menu_order',
				'order'    => 'ASC',
			),
			is_array( $atts ) ? $atts : array(),
			'poda_rail'
		);

		wp_enqueue_style( 'studio-poda-widgets' );

		return Poda_Render::rail(
			array(
				'category' => $atts['category'],
				'count'    => (int) $atts['count'],
				'orderby'  => $atts['orderby'],
				'order'    => $atts['order'],
			)
		);
	}

	/**
	 * Loose boolean parsing for shortcode attributes.
	 *
	 * @param mixed $value Attribute value.
	 * @return bool
	 */
	private static function truthy( $value ) {
		return in_array( strtolower( trim( (string) $value ) ), array( 'yes', 'true', '1', 'on' ), true );
	}
}
