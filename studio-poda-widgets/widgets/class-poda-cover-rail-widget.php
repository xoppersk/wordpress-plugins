<?php
/**
 * Widget: Cover Rail.
 *
 * The featured-work rail: a horizontal scroll-snap strip of project
 * covers (each poda_project's featured SVG) with name captions, every
 * card linking to /work/<slug>/. Canon: home featured rail = roster
 * 1–8 (the design guide §3/§4).
 *
 * @package StudioPodaWidgets
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Controls_Manager;
use Elementor\Widget_Base;

class Poda_Cover_Rail_Widget extends Widget_Base {

	public function get_name() {
		return 'poda-cover-rail';
	}

	public function get_title() {
		return esc_html__( 'Cover Rail', 'studio-poda-widgets' );
	}

	public function get_icon() {
		return 'eicon-slider-push';
	}

	public function get_categories() {
		return array( 'studio-poda' );
	}

	public function get_keywords() {
		return array( 'poda', 'cover', 'rail', 'projects', 'work' );
	}

	public function get_style_depends() {
		return array( 'studio-poda-widgets' );
	}

	protected function register_controls() {
		$this->start_controls_section(
			'section_query',
			array( 'label' => esc_html__( 'Projects', 'studio-poda-widgets' ) )
		);

		$cat_options = array( '' => esc_html__( 'All categories', 'studio-poda-widgets' ) );
		$terms       = get_terms(
			array(
				'taxonomy'   => 'poda_project_cat',
				'hide_empty' => false,
			)
		);
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$cat_options[ $term->slug ] = $term->name;
			}
		}

		$this->add_control(
			'category',
			array(
				'label'   => esc_html__( 'Category filter', 'studio-poda-widgets' ),
				'type'    => Controls_Manager::SELECT,
				'default' => '',
				'options' => $cat_options,
			)
		);

		$this->add_control(
			'count',
			array(
				'label'   => esc_html__( 'Number of projects', 'studio-poda-widgets' ),
				'type'    => Controls_Manager::NUMBER,
				'min'     => 1,
				'max'     => 24,
				'step'    => 1,
				'default' => 8,
			)
		);

		$this->add_control(
			'orderby',
			array(
				'label'   => esc_html__( 'Order by', 'studio-poda-widgets' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'menu_order',
				'options' => array(
					'menu_order' => esc_html__( 'Roster order (menu order)', 'studio-poda-widgets' ),
					'date'       => esc_html__( 'Date', 'studio-poda-widgets' ),
					'title'      => esc_html__( 'Title', 'studio-poda-widgets' ),
					'rand'       => esc_html__( 'Random', 'studio-poda-widgets' ),
				),
			)
		);

		$this->add_control(
			'order',
			array(
				'label'   => esc_html__( 'Order', 'studio-poda-widgets' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'ASC',
				'options' => array(
					'ASC'  => esc_html__( 'Ascending', 'studio-poda-widgets' ),
					'DESC' => esc_html__( 'Descending', 'studio-poda-widgets' ),
				),
			)
		);

		$this->end_controls_section();
	}

	protected function render() {
		$settings = $this->get_settings_for_display();

		echo Poda_Render::rail( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- renderer escapes internally.
			array(
				'category' => $settings['category'] ?? '',
				'count'    => isset( $settings['count'] ) ? (int) $settings['count'] : 8,
				'orderby'  => $settings['orderby'] ?? 'menu_order',
				'order'    => $settings['order'] ?? 'ASC',
			)
		);
	}
}
