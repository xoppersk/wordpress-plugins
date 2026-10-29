<?php
/**
 * Widget B — Typewriter (sc-typewriter).
 *
 * Spec: §6.3 B. Types a rotating phrase after a fixed prefix.
 * The first phrase is rendered server-side, so no-JS and reduced-motion
 * visitors see a complete, static line. The animated span is aria-hidden;
 * the element itself carries aria-label = prefix + first phrase.
 *
 * @package ScrollChapters
 */

use Elementor\Controls_Manager;
use Elementor\Core\Kits\Documents\Tabs\Global_Colors;
use Elementor\Repeater;
use Elementor\Widget_Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Typewriter widget.
 */
class SC_Typewriter_Widget extends Widget_Base {

	public function get_name() {
		return 'sc-typewriter';
	}

	public function get_title() {
		return esc_html__( 'Typewriter', 'scroll-chapters' );
	}

	public function get_icon() {
		return 'eicon-type-tool';
	}

	public function get_categories() {
		return array( 'scroll-chapters' );
	}

	public function get_keywords() {
		return array( 'typewriter', 'typing', 'headline', 'klo' );
	}

	public function get_script_depends() {
		return array( 'sc-typewriter' );
	}

	public function get_style_depends() {
		return array( 'sc-widgets' );
	}

	protected function register_controls() {
		$defaults = SC_Settings::get();

		$this->start_controls_section(
			'section_typewriter',
			array( 'label' => esc_html__( 'Typewriter', 'scroll-chapters' ) )
		);

		$this->add_control(
			'prefix',
			array(
				'label'   => esc_html__( 'Prefix', 'scroll-chapters' ),
				'type'    => Controls_Manager::TEXT,
				'default' => esc_html__( 'We bent one for ', 'scroll-chapters' ),
			)
		);

		$repeater = new Repeater();
		$repeater->add_control(
			'phrase',
			array(
				'label'       => esc_html__( 'Phrase (max 24 characters)', 'scroll-chapters' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => '',
				'description' => esc_html__( 'Keep phrases to 24 characters.', 'scroll-chapters' ),
			)
		);

		$this->add_control(
			'phrases',
			array(
				'label'       => esc_html__( 'Phrases (1–6)', 'scroll-chapters' ),
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $repeater->get_controls(),
				'title_field' => '{{{ phrase }}}',
				'default'     => array(
					array( 'phrase' => esc_html__( 'a noodle bar.', 'scroll-chapters' ) ),
					array( 'phrase' => esc_html__( 'a cinema.', 'scroll-chapters' ) ),
					array( 'phrase' => esc_html__( 'a pawn shop.', 'scroll-chapters' ) ),
					array( 'phrase' => esc_html__( 'a ferry pier.', 'scroll-chapters' ) ),
				),
				'description' => esc_html__( 'One phrase types once and holds — it never deletes.', 'scroll-chapters' ),
			)
		);

		$this->add_control(
			'type_speed',
			array(
				'label'   => esc_html__( 'Type speed (ms)', 'scroll-chapters' ),
				'type'    => Controls_Manager::NUMBER,
				'min'     => 10,
				'max'     => 500,
				'step'    => 5,
				'default' => $defaults['typewriter_type_ms'],
			)
		);

		$this->add_control(
			'delete_speed',
			array(
				'label'   => esc_html__( 'Delete speed (ms)', 'scroll-chapters' ),
				'type'    => Controls_Manager::NUMBER,
				'min'     => 10,
				'max'     => 500,
				'step'    => 5,
				'default' => 30,
			)
		);

		$this->add_control(
			'hold',
			array(
				'label'   => esc_html__( 'Hold (ms)', 'scroll-chapters' ),
				'type'    => Controls_Manager::NUMBER,
				'min'     => 0,
				'max'     => 10000,
				'step'    => 100,
				'default' => 1800,
			)
		);

		$this->add_control(
			'gap',
			array(
				'label'   => esc_html__( 'Gap (ms)', 'scroll-chapters' ),
				'type'    => Controls_Manager::NUMBER,
				'min'     => 0,
				'max'     => 5000,
				'step'    => 50,
				'default' => 300,
			)
		);

		$this->add_control(
			'loop',
			array(
				'label'        => esc_html__( 'Loop', 'scroll-chapters' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Yes', 'scroll-chapters' ),
				'label_off'    => esc_html__( 'No', 'scroll-chapters' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'tag',
			array(
				'label'   => esc_html__( 'HTML tag', 'scroll-chapters' ),
				'type'    => Controls_Manager::SELECT,
				'options' => array(
					'p'  => 'p',
					'h2' => 'h2',
					'h3' => 'h3',
				),
				'default' => 'p',
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_style',
			array(
				'label' => esc_html__( 'Phrase', 'scroll-chapters' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'phrase_color',
			array(
				'label'   => esc_html__( 'Phrase colour', 'scroll-chapters' ),
				'type'    => Controls_Manager::COLOR,
				'default' => '#FF4FA3', // Tube Pink.
				'global'  => array(
					'default' => Global_Colors::COLOR_ACCENT,
				),
				'selectors' => array(
					'{{WRAPPER}} .sc-tw-typed' => 'color: {{VALUE}};',
				),
			)
		);

		$this->end_controls_section();
	}

	protected function render() {
		$settings = $this->get_settings_for_display();

		$prefix  = isset( $settings['prefix'] ) ? (string) $settings['prefix'] : '';
		$rows    = isset( $settings['phrases'] ) && is_array( $settings['phrases'] ) ? $settings['phrases'] : array();
		$phrases = array();
		foreach ( $rows as $row ) {
			if ( ! empty( $row['phrase'] ) ) {
				$phrases[] = (string) $row['phrase'];
			}
		}

		if ( empty( $phrases ) ) {
			return;
		}

		$tag = in_array( $settings['tag'] ?? 'p', array( 'p', 'h2', 'h3' ), true ) ? $settings['tag'] : 'p';

		$data = array(
			'phrases' => $phrases,
			'type'    => isset( $settings['type_speed'] ) ? (int) $settings['type_speed'] : 55,
			'delete'  => isset( $settings['delete_speed'] ) ? (int) $settings['delete_speed'] : 30,
			'hold'    => isset( $settings['hold'] ) ? (int) $settings['hold'] : 1800,
			'gap'     => isset( $settings['gap'] ) ? (int) $settings['gap'] : 300,
			'loop'    => 'yes' === ( $settings['loop'] ?? 'yes' ),
		);

		$this->add_render_attribute( 'tw', 'class', 'sc-typewriter' );
		$this->add_render_attribute( 'tw', 'aria-label', $prefix . $phrases[0] );
		$this->add_render_attribute( 'tw', 'data-sc-tw', wp_json_encode( $data ) );
		?>
		<<?php echo tag_escape( $tag ); ?> <?php $this->print_render_attribute_string( 'tw' ); ?>><span class="sc-tw-prefix"><?php echo esc_html( $prefix ); ?></span><span class="sc-tw-typed" aria-hidden="true"><?php echo esc_html( $phrases[0] ); ?></span><span class="sc-tw-caret" aria-hidden="true"></span></<?php echo tag_escape( $tag ); ?>>
		<?php
	}
}
