<?php
/**
 * Widget: Ticker Band.
 *
 * The site's route-board marquee (the design guide §2 furniture): a band of
 * stops drifting in the site's ticker motion language. Items come from
 * a repeater (each stop may carry a link) or from one text field split
 * on " · ".
 *
 * @package StudioPodaWidgets
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Controls_Manager;
use Elementor\Repeater;
use Elementor\Widget_Base;

class Poda_Ticker_Widget extends Widget_Base {

	public function get_name() {
		return 'poda-ticker';
	}

	public function get_title() {
		return esc_html__( 'Ticker Band', 'studio-poda-widgets' );
	}

	public function get_icon() {
		return 'eicon-animation-text';
	}

	public function get_categories() {
		return array( 'studio-poda' );
	}

	public function get_keywords() {
		return array( 'poda', 'ticker', 'marquee', 'route', 'band' );
	}

	public function get_style_depends() {
		return array( 'studio-poda-widgets' );
	}

	protected function register_controls() {
		$this->start_controls_section(
			'section_items',
			array( 'label' => esc_html__( 'Stops', 'studio-poda-widgets' ) )
		);

		$this->add_control(
			'source',
			array(
				'label'   => esc_html__( 'Items source', 'studio-poda-widgets' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'repeater',
				'options' => array(
					'repeater' => esc_html__( 'Repeater (stops, optional links)', 'studio-poda-widgets' ),
					'text'     => esc_html__( 'Text field (split on " · ")', 'studio-poda-widgets' ),
				),
			)
		);

		$repeater = new Repeater();

		$repeater->add_control(
			'text',
			array(
				'label'       => esc_html__( 'Stop text', 'studio-poda-widgets' ),
				'type'        => Controls_Manager::TEXT,
				'placeholder' => esc_html__( 'Freetown', 'studio-poda-widgets' ),
				'label_block' => true,
			)
		);

		$repeater->add_control(
			'link',
			array(
				'label'       => esc_html__( 'Link (optional)', 'studio-poda-widgets' ),
				'type'        => Controls_Manager::URL,
				'placeholder' => 'https://',
				'dynamic'     => array( 'active' => true ),
			)
		);

		$this->add_control(
			'items_list',
			array(
				'label'       => esc_html__( 'Stops', 'studio-poda-widgets' ),
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $repeater->get_controls(),
				'default'     => array(
					array( 'text' => 'Freetown' ),
					array( 'text' => 'London' ),
					array( 'text' => 'Lagos' ),
					array( 'text' => 'New York' ),
					array( 'text' => 'Accra' ),
					array( 'text' => 'Toronto' ),
				),
				'title_field' => '{{{ text }}}',
				'condition'   => array( 'source' => 'repeater' ),
			)
		);

		$this->add_control(
			'items_text',
			array(
				'label'       => esc_html__( 'Stops (one line, " · " separated)', 'studio-poda-widgets' ),
				'type'        => Controls_Manager::TEXTAREA,
				'rows'        => 3,
				'default'     => 'Freetown · London · Lagos · New York · Accra · Toronto',
				'condition'   => array( 'source' => 'text' ),
				'label_block' => true,
			)
		);

		$this->add_control(
			'tab',
			array(
				'label'       => esc_html__( 'Tab label (optional)', 'studio-poda-widgets' ),
				'type'        => Controls_Manager::TEXT,
				'placeholder' => esc_html__( 'Routes', 'studio-poda-widgets' ),
				'description' => esc_html__( 'The small label riding the band edge, e.g. "Routes" or "Services". Leave empty for no tab.', 'studio-poda-widgets' ),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_motion',
			array( 'label' => esc_html__( 'Band', 'studio-poda-widgets' ) )
		);

		$this->add_control(
			'scheme',
			array(
				'label'   => esc_html__( 'Colour scheme', 'studio-poda-widgets' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'palm',
				'options' => array(
					'palm'          => esc_html__( 'Palm band, mint type (site default)', 'studio-poda-widgets' ),
					'ink-on-yellow' => esc_html__( 'Ink type on poda yellow', 'studio-poda-widgets' ),
					'yellow-on-ink' => esc_html__( 'Yellow type on warm ink', 'studio-poda-widgets' ),
				),
			)
		);

		$this->add_control(
			'speed',
			array(
				'label'       => esc_html__( 'Loop duration (seconds)', 'studio-poda-widgets' ),
				'type'        => Controls_Manager::SLIDER,
				'size_units'  => array( 's' ),
				'range'       => array(
					's' => array(
						'min'  => 10,
						'max'  => 120,
						'step' => 1,
					),
				),
				'default'     => array(
					'unit' => 's',
					'size' => 46,
				),
				'description' => esc_html__( 'Time for one full loop. The site ticker runs 46s.', 'studio-poda-widgets' ),
			)
		);

		$this->add_control(
			'pause_on_hover',
			array(
				'label'        => esc_html__( 'Pause on hover', 'studio-poda-widgets' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Yes', 'studio-poda-widgets' ),
				'label_off'    => esc_html__( 'No', 'studio-poda-widgets' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->end_controls_section();
	}

	protected function render() {
		$settings = $this->get_settings_for_display();

		$items = array();
		if ( 'text' === ( $settings['source'] ?? 'repeater' ) ) {
			foreach ( explode( '·', (string) ( $settings['items_text'] ?? '' ) ) as $piece ) {
				$piece = trim( $piece );
				if ( '' !== $piece ) {
					$items[] = array( 'text' => $piece, 'link' => '' );
				}
			}
		} else {
			foreach ( (array) ( $settings['items_list'] ?? array() ) as $row ) {
				$link = '';
				if ( ! empty( $row['link']['url'] ) ) {
					$link = $row['link']['url'];
				}
				$items[] = array(
					'text' => $row['text'] ?? '',
					'link' => $link,
				);
			}
		}

		$speed = 46;
		if ( ! empty( $settings['speed']['size'] ) ) {
			$speed = (int) $settings['speed']['size'];
		}

		echo Poda_Render::ticker( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- renderer escapes internally.
			array(
				'items'          => $items,
				'scheme'         => $settings['scheme'] ?? 'palm',
				'speed'          => $speed,
				'pause_on_hover' => 'yes' === ( $settings['pause_on_hover'] ?? 'yes' ),
				'tab'            => $settings['tab'] ?? '',
			)
		);
	}
}
