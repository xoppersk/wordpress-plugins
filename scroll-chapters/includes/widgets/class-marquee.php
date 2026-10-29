<?php
/**
 * Widget C — Marquee Band (sc-marquee).
 *
 * Spec: §6.3 C. The item list is a real <ul>, duplicated once
 * server-side (the copy is aria-hidden) and scrolled at a constant px/s
 * by marquee.js, which adds further copies when a short list cannot
 * fill twice the viewport. Without JS — or under reduced motion — the
 * list reads as a static wrapped list.
 *
 * @package ScrollChapters
 */

use Elementor\Controls_Manager;
use Elementor\Repeater;
use Elementor\Widget_Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Marquee Band widget.
 */
class SC_Marquee_Widget extends Widget_Base {

	public function get_name() {
		return 'sc-marquee';
	}

	public function get_title() {
		return esc_html__( 'Marquee Band', 'scroll-chapters' );
	}

	public function get_icon() {
		return 'eicon-animation-text';
	}

	public function get_categories() {
		return array( 'scroll-chapters' );
	}

	public function get_keywords() {
		return array( 'marquee', 'band', 'scroll', 'ticker', 'klo' );
	}

	public function get_script_depends() {
		return array( 'sc-marquee' );
	}

	public function get_style_depends() {
		return array( 'sc-widgets' );
	}

	protected function register_controls() {
		$defaults = SC_Settings::get();

		$this->start_controls_section(
			'section_marquee',
			array( 'label' => esc_html__( 'Marquee Band', 'scroll-chapters' ) )
		);

		$this->add_control(
			'tag_text',
			array(
				'label'   => esc_html__( 'Tag text', 'scroll-chapters' ),
				'type'    => Controls_Manager::TEXT,
				'default' => esc_html__( "Streets we've lit", 'scroll-chapters' ),
			)
		);

		$this->add_control(
			'tag_zh',
			array(
				'label'   => esc_html__( 'Tag — stamp', 'scroll-chapters' ),
				'type'    => Controls_Manager::TEXT,
				'default' => '街',
			)
		);

		$repeater = new Repeater();
		$repeater->add_control(
			'item',
			array(
				'label'   => esc_html__( 'Item', 'scroll-chapters' ),
				'type'    => Controls_Manager::TEXT,
				'default' => '',
			)
		);

		$streets = array(
			'Reclamation Street', 'Temple Street', 'Shanghai Street', 'Nathan Road',
			'Portland Street', 'Tung Choi Street', 'Fa Yuen Street', 'Canton Road',
			'Jordan Road', 'Waterloo Road', 'Argyle Street', 'Dundas Street',
			'Hamilton Street', 'Public Square Street',
		);
		$default_items = array();
		foreach ( $streets as $street ) {
			$default_items[] = array( 'item' => $street );
		}

		$this->add_control(
			'items',
			array(
				'label'       => esc_html__( 'Items (3–30)', 'scroll-chapters' ),
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $repeater->get_controls(),
				'title_field' => '{{{ item }}}',
				'default'     => $default_items,
				'description' => esc_html__( 'Short lists repeat until the track fills twice the viewport width.', 'scroll-chapters' ),
			)
		);

		$this->add_control(
			'speed',
			array(
				'label'   => esc_html__( 'Speed (px/s)', 'scroll-chapters' ),
				'type'    => Controls_Manager::NUMBER,
				'min'     => 10,
				'max'     => 300,
				'step'    => 5,
				'default' => $defaults['marquee_speed'],
			)
		);

		$this->add_control(
			'direction',
			array(
				'label'   => esc_html__( 'Direction', 'scroll-chapters' ),
				'type'    => Controls_Manager::SELECT,
				'options' => array(
					'left'  => esc_html__( 'Left', 'scroll-chapters' ),
					'right' => esc_html__( 'Right', 'scroll-chapters' ),
				),
				'default' => 'left',
			)
		);

		$this->add_control(
			'ground',
			array(
				'label'   => esc_html__( 'Ground', 'scroll-chapters' ),
				'type'    => Controls_Manager::SELECT,
				'options' => array(
					'jade'  => esc_html__( 'Shophouse Jade', 'scroll-chapters' ),
					'night' => esc_html__( 'Night Ink', 'scroll-chapters' ),
					'paper' => esc_html__( 'Rice Paper', 'scroll-chapters' ),
				),
				'default' => 'jade',
			)
		);

		$this->add_control(
			'separator',
			array(
				'label'   => esc_html__( 'Separator', 'scroll-chapters' ),
				'type'    => Controls_Manager::SELECT,
				'options' => array(
					'line' => esc_html__( 'One Line', 'scroll-chapters' ),
					'dot'  => esc_html__( 'Dot', 'scroll-chapters' ),
				),
				'default' => 'line',
			)
		);

		$this->add_responsive_control(
			'height',
			array(
				'label'      => esc_html__( 'Height (px)', 'scroll-chapters' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min' => 40,
						'max' => 200,
					),
				),
				'default'        => array(
					'unit' => 'px',
					'size' => 96,
				),
				'mobile_default' => array(
					'unit' => 'px',
					'size' => 64,
				),
				'selectors'  => array(
					'{{WRAPPER}} .sc-marquee' => '--sc-marquee-h: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();
	}

	protected function render() {
		$settings = $this->get_settings_for_display();

		$rows  = isset( $settings['items'] ) && is_array( $settings['items'] ) ? $settings['items'] : array();
		$items = array();
		foreach ( $rows as $row ) {
			if ( ! empty( $row['item'] ) ) {
				$items[] = (string) $row['item'];
			}
		}

		if ( empty( $items ) ) {
			return;
		}

		$ground    = in_array( $settings['ground'] ?? 'jade', array( 'jade', 'night', 'paper' ), true ) ? $settings['ground'] : 'jade';
		$direction = in_array( $settings['direction'] ?? 'left', array( 'left', 'right' ), true ) ? $settings['direction'] : 'left';
		$separator = in_array( $settings['separator'] ?? 'line', array( 'line', 'dot' ), true ) ? $settings['separator'] : 'line';
		$speed     = isset( $settings['speed'] ) ? (int) $settings['speed'] : 70;

		$this->add_render_attribute( 'band', 'class', array( 'sc-marquee', 'sc-marquee--' . $ground, 'sc-marquee--sep-' . $separator ) );
		$this->add_render_attribute( 'band', 'data-sc-speed', (string) $speed );
		$this->add_render_attribute( 'band', 'data-sc-direction', $direction );
		?>
		<div <?php $this->print_render_attribute_string( 'band' ); ?>>
			<?php if ( ! empty( $settings['tag_text'] ) || ! empty( $settings['tag_zh'] ) ) : ?>
				<span class="sc-marquee-tag">
					<?php echo esc_html( $settings['tag_text'] ?? '' ); ?>
					<?php if ( ! empty( $settings['tag_zh'] ) ) : ?>
						<span class="stamp sc-marquee-tag-stamp"><?php echo esc_html( $settings['tag_zh'] ); ?></span>
					<?php endif; ?>
				</span>
			<?php endif; ?>
			<div class="sc-marquee-viewport">
				<ul class="sc-marquee-track">
					<?php foreach ( $items as $item ) : ?>
						<li class="sc-marquee-item"><?php echo esc_html( $item ); ?></li>
					<?php endforeach; ?>
					<?php foreach ( $items as $item ) : ?>
						<li class="sc-marquee-item sc-marquee-dup" aria-hidden="true"><?php echo esc_html( $item ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
		</div>
		<?php
	}
}
