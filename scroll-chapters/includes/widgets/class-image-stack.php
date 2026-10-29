<?php
/**
 * Widget D — Layered Image Stack (sc-image-stack).
 *
 * Spec: §6.3 D. Layers positioned in % on a fixed-height
 * stage; each layer parallaxes by depth × travel as the stage crosses
 * the viewport. The hero layer carries the 3px Tube Pink frame and
 * glow. Reduced motion renders the static composition at p = 0.5
 * (no transform), which is exactly what the server markup shows.
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
 * Layered Image Stack widget.
 */
class SC_Image_Stack_Widget extends Widget_Base {

	public function get_name() {
		return 'sc-image-stack';
	}

	public function get_title() {
		return esc_html__( 'Layered Image Stack', 'scroll-chapters' );
	}

	public function get_icon() {
		return 'eicon-gallery-grid';
	}

	public function get_categories() {
		return array( 'scroll-chapters' );
	}

	public function get_keywords() {
		return array( 'image', 'stack', 'layer', 'parallax', 'klo' );
	}

	public function get_script_depends() {
		return array( 'sc-image-stack' );
	}

	public function get_style_depends() {
		return array( 'sc-widgets' );
	}

	protected function register_controls() {
		$this->start_controls_section(
			'section_stack',
			array( 'label' => esc_html__( 'Layered Image Stack', 'scroll-chapters' ) )
		);

		$repeater = new Repeater();

		$repeater->add_control(
			'image',
			array(
				'label' => esc_html__( 'Image', 'scroll-chapters' ),
				'type'  => Controls_Manager::MEDIA,
			)
		);
		$repeater->add_control(
			'alt',
			array(
				'label' => esc_html__( 'Alt text', 'scroll-chapters' ),
				'type'  => Controls_Manager::TEXT,
			)
		);
		$repeater->add_control(
			'width_pct',
			array(
				'label'   => esc_html__( 'Width (%)', 'scroll-chapters' ),
				'type'    => Controls_Manager::NUMBER,
				'min'     => 5,
				'max'     => 100,
				'step'    => 1,
				'default' => 50,
			)
		);
		$repeater->add_control(
			'x_pct',
			array(
				'label'   => esc_html__( 'Position X (%)', 'scroll-chapters' ),
				'type'    => Controls_Manager::NUMBER,
				'min'     => 0,
				'max'     => 100,
				'step'    => 1,
				'default' => 0,
			)
		);
		$repeater->add_control(
			'y_pct',
			array(
				'label'   => esc_html__( 'Position Y (%)', 'scroll-chapters' ),
				'type'    => Controls_Manager::NUMBER,
				'min'     => 0,
				'max'     => 100,
				'step'    => 1,
				'default' => 0,
			)
		);
		$repeater->add_control(
			'depth',
			array(
				'label'   => esc_html__( 'Depth (0–1)', 'scroll-chapters' ),
				'type'    => Controls_Manager::NUMBER,
				'min'     => 0,
				'max'     => 1,
				'step'    => 0.05,
				'default' => 0.5,
				'description' => esc_html__( 'How far this layer travels. 0 = locked to the page, 1 = full travel.', 'scroll-chapters' ),
			)
		);
		$repeater->add_control(
			'is_hero',
			array(
				'label'        => esc_html__( 'Hero layer', 'scroll-chapters' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Yes', 'scroll-chapters' ),
				'label_off'    => esc_html__( 'No', 'scroll-chapters' ),
				'return_value' => 'yes',
				'default'      => '',
				'description'  => esc_html__( 'One layer only: it gets the pink frame and glow. If several are marked, the first wins.', 'scroll-chapters' ),
			)
		);

		$this->add_control(
			'layers',
			array(
				'label'       => esc_html__( 'Layers (2–5)', 'scroll-chapters' ),
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $repeater->get_controls(),
				'title_field' => '{{{ alt }}}',
				'description' => esc_html__( 'Two layers minimum, five maximum.', 'scroll-chapters' ),
			)
		);

		$this->add_responsive_control(
			'stage_height',
			array(
				'label'      => esc_html__( 'Stage height (px)', 'scroll-chapters' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min' => 240,
						'max' => 1000,
					),
				),
				'default'         => array(
					'unit' => 'px',
					'size' => 640,
				),
				'tablet_default'  => array(
					'unit' => 'px',
					'size' => 560,
				),
				'mobile_default'  => array(
					'unit' => 'px',
					'size' => 440,
				),
				'selectors' => array(
					'{{WRAPPER}} .sc-stack-stage' => 'height: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'travel',
			array(
				'label'   => esc_html__( 'Travel (px)', 'scroll-chapters' ),
				'type'    => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'   => array(
					'px' => array(
						'min' => 0,
						'max' => 400,
					),
				),
				'default' => array(
					'unit' => 'px',
					'size' => 160,
				),
			)
		);

		$this->add_control(
			'cursor_tilt',
			array(
				'label'        => esc_html__( 'Cursor tilt', 'scroll-chapters' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'On', 'scroll-chapters' ),
				'label_off'    => esc_html__( 'Off', 'scroll-chapters' ),
				'return_value' => 'yes',
				'default'      => '',
				'description'  => esc_html__( 'Pointer devices only; off on mobile.', 'scroll-chapters' ),
			)
		);

		$this->add_control(
			'caption',
			array(
				'label'   => esc_html__( 'Caption', 'scroll-chapters' ),
				'type'    => Controls_Manager::TEXT,
				'default' => '',
			)
		);

		$this->end_controls_section();
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		$layers   = isset( $settings['layers'] ) && is_array( $settings['layers'] ) ? $settings['layers'] : array();

		// Spec: 2–5 layers. Render at most 5; under 2 there is no stack.
		$layers = array_slice( $layers, 0, 5 );
		if ( count( $layers ) < 2 ) {
			return;
		}

		$travel = isset( $settings['travel']['size'] ) ? (int) $settings['travel']['size'] : 160;
		$tilt   = 'yes' === ( $settings['cursor_tilt'] ?? '' );

		$hero_used = false;

		$this->add_render_attribute( 'stack', 'class', 'sc-stack' );
		$this->add_render_attribute( 'stack', 'data-sc-travel', (string) $travel );
		$this->add_render_attribute( 'stack', 'data-sc-tilt', $tilt ? '1' : '0' );
		?>
		<figure <?php $this->print_render_attribute_string( 'stack' ); ?>>
			<div class="sc-stack-stage">
				<?php foreach ( $layers as $layer ) : ?>
					<?php
					$is_hero = false;
					if ( ! $hero_used && 'yes' === ( $layer['is_hero'] ?? '' ) ) {
						$is_hero   = true;
						$hero_used = true;
					}
					$depth = isset( $layer['depth'] ) ? max( 0, min( 1, (float) $layer['depth'] ) ) : 0.5;
					$style = sprintf(
						'left:%s%%;top:%s%%;width:%s%%;z-index:%d',
						esc_attr( $layer['x_pct'] ?? 0 ),
						esc_attr( $layer['y_pct'] ?? 0 ),
						esc_attr( $layer['width_pct'] ?? 50 ),
						$is_hero ? 3 : 1
					);
					?>
					<div class="sc-stack-layer<?php echo $is_hero ? ' is-hero' : ''; ?>" style="<?php echo esc_attr( $style ); ?>" data-depth="<?php echo esc_attr( $depth ); ?>">
						<?php
						if ( ! empty( $layer['image']['id'] ) ) {
							echo wp_get_attachment_image(
								(int) $layer['image']['id'],
								'large',
								false,
								array( 'alt' => isset( $layer['alt'] ) ? $layer['alt'] : '' )
							);
						} elseif ( ! empty( $layer['image']['url'] ) ) {
							printf(
								'<img src="%s" alt="%s" loading="lazy" />',
								esc_url( $layer['image']['url'] ),
								esc_attr( $layer['alt'] ?? '' )
							);
						}
						?>
					</div>
				<?php endforeach; ?>
			</div>
			<?php if ( ! empty( $settings['caption'] ) ) : ?>
				<figcaption class="sc-stack-caption"><?php echo esc_html( $settings['caption'] ); ?></figcaption>
			<?php endif; ?>
		</figure>
		<?php
	}
}
