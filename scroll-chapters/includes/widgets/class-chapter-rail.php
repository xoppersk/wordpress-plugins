<?php
/**
 * Widget A — Chapter Rail (sc-chapter-rail).
 *
 * Spec: §6.3 A. Pinned horizontal scroll: the wrapper is
 * n × scroll-vh tall, a sticky stage holds the track, and vertical
 * scroll progress drives translateX. Static fallbacks: fewer than 3
 * chapters render a plain row; mobile (setting off) renders a native
 * scroll-snap row; reduced motion renders a vertical stack, all lit.
 *
 * @package ScrollChapters
 */

use Elementor\Controls_Manager;
use Elementor\Group_Control_Image_Size;
use Elementor\Repeater;
use Elementor\Widget_Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Chapter Rail widget.
 */
class SC_Chapter_Rail_Widget extends Widget_Base {

	public function get_name() {
		return 'sc-chapter-rail';
	}

	public function get_title() {
		return esc_html__( 'Chapter Rail', 'scroll-chapters' );
	}

	public function get_icon() {
		return 'eicon-horizontal-scroll';
	}

	public function get_categories() {
		return array( 'scroll-chapters' );
	}

	public function get_keywords() {
		return array( 'chapter', 'rail', 'scroll', 'horizontal', 'klo' );
	}

	public function get_script_depends() {
		return array( 'sc-chapter-rail' );
	}

	public function get_style_depends() {
		return array( 'sc-widgets' );
	}

	protected function register_controls() {
		$this->start_controls_section(
			'section_chapters',
			array( 'label' => esc_html__( 'Chapters', 'scroll-chapters' ) )
		);

		$repeater = new Repeater();

		$repeater->add_control(
			'number',
			array(
				'label'   => esc_html__( 'Number', 'scroll-chapters' ),
				'type'    => Controls_Manager::TEXT,
				'default' => '01',
			)
		);
		$repeater->add_control(
			'title',
			array(
				'label'       => esc_html__( 'Title (max 16 characters)', 'scroll-chapters' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => esc_html__( 'Chapter', 'scroll-chapters' ),
				'description' => esc_html__( 'Keep it to 16 characters so the card never wraps to three lines.', 'scroll-chapters' ),
			)
		);
		$repeater->add_control(
			'title_zh',
			array(
				'label'   => esc_html__( 'Title — stamp (max 4 characters)', 'scroll-chapters' ),
				'type'    => Controls_Manager::TEXT,
				'default' => '',
			)
		);
		$repeater->add_control(
			'line',
			array(
				'label'       => esc_html__( 'Line (max 60 characters)', 'scroll-chapters' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => '',
				'description' => esc_html__( 'One sentence, up to 60 characters.', 'scroll-chapters' ),
			)
		);
		$repeater->add_control(
			'media',
			array(
				'label' => esc_html__( 'Media (4:3, optional)', 'scroll-chapters' ),
				'type'  => Controls_Manager::MEDIA,
			)
		);

		$this->add_control(
			'chapters',
			array(
				'label'       => esc_html__( 'Chapters (3–8)', 'scroll-chapters' ),
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $repeater->get_controls(),
				'title_field' => '{{{ number }}} — {{{ title }}}',
				'default'     => array(
					array(
						'number' => '01',
						'title'  => esc_html__( 'Chapter one', 'scroll-chapters' ),
						'line'   => '',
					),
					array(
						'number' => '02',
						'title'  => esc_html__( 'Chapter two', 'scroll-chapters' ),
						'line'   => '',
					),
					array(
						'number' => '03',
						'title'  => esc_html__( 'Chapter three', 'scroll-chapters' ),
						'line'   => '',
					),
				),
				'description' => esc_html__( 'Fewer than 3 chapters renders a static row with no pin.', 'scroll-chapters' ),
			)
		);

		$this->add_control(
			'scroll_per_chapter',
			array(
				'label'   => esc_html__( 'Scroll per chapter (vh)', 'scroll-chapters' ),
				'type'    => Controls_Manager::NUMBER,
				'min'     => 30,
				'max'     => 200,
				'step'    => 5,
				'default' => 70,
			)
		);

		$this->add_responsive_control(
			'card_width',
			array(
				'label'      => esc_html__( 'Card width', 'scroll-chapters' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'vw' ),
				'range'      => array(
					'px' => array(
						'min' => 240,
						'max' => 640,
					),
					'vw' => array(
						'min' => 40,
						'max' => 100,
					),
				),
				'default'    => array(
					'unit' => 'px',
					'size' => 420,
				),
				'mobile_default' => array(
					'unit' => 'vw',
					'size' => 80,
				),
				'selectors'  => array(
					'{{WRAPPER}} .sc-chapter' => 'width: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'show_arrows',
			array(
				'label'        => esc_html__( 'Show arrows', 'scroll-chapters' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Yes', 'scroll-chapters' ),
				'label_off'    => esc_html__( 'No', 'scroll-chapters' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'show_counter',
			array(
				'label'        => esc_html__( 'Show counter', 'scroll-chapters' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Yes', 'scroll-chapters' ),
				'label_off'    => esc_html__( 'No', 'scroll-chapters' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'accent',
			array(
				'label'   => esc_html__( 'Accent', 'scroll-chapters' ),
				'type'    => Controls_Manager::SELECT,
				'options' => array(
					'pink' => esc_html__( 'Tube Pink', 'scroll-chapters' ),
					'lime' => esc_html__( 'Uranium Lime', 'scroll-chapters' ),
					'cyan' => esc_html__( 'Argon Cyan', 'scroll-chapters' ),
				),
				'default' => 'pink',
			)
		);

		$this->end_controls_section();
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		$chapters = isset( $settings['chapters'] ) && is_array( $settings['chapters'] ) ? $settings['chapters'] : array();
		$n        = count( $chapters );

		if ( 0 === $n ) {
			return;
		}

		$scroll_vh   = isset( $settings['scroll_per_chapter'] ) ? (int) $settings['scroll_per_chapter'] : 70;
		$accent      = isset( $settings['accent'] ) ? $settings['accent'] : 'pink';
		$show_arrows = 'yes' === ( $settings['show_arrows'] ?? 'yes' );
		$show_counter = 'yes' === ( $settings['show_counter'] ?? 'yes' );
		$static      = $n < 3;
		$pin_mobile  = SC_Settings::get()['enable_on_mobile'] ? '1' : '0';
		$wrap_height = ( $n * $scroll_vh ) + 100;

		$classes = array( 'sc-rail', 'sc-rail--' . sanitize_html_class( $accent ) );
		if ( $static ) {
			$classes[] = 'sc-rail--static';
		}

		$this->add_render_attribute( 'rail', 'class', $classes );
		$this->add_render_attribute( 'rail', 'aria-label', esc_attr__( 'Chapters', 'scroll-chapters' ) );
		$this->add_render_attribute( 'rail', 'data-sc-chapters', (string) $n );
		$this->add_render_attribute( 'rail', 'data-sc-pin-mobile', $pin_mobile );
		?>
		<section <?php $this->print_render_attribute_string( 'rail' ); ?>>
			<div class="sc-rail-wrap"<?php echo $static ? '' : ' style="--sc-wrap-h:' . esc_attr( $wrap_height ) . 'vh"'; ?>>
				<div class="sc-rail-sticky">
					<div class="sc-rail-track">
						<?php foreach ( $chapters as $i => $chapter ) : ?>
							<article class="sc-chapter<?php echo 0 === $i ? ' is-active is-lit' : ''; ?>" data-index="<?php echo esc_attr( $i ); ?>">
								<span class="sc-chapter-num"><?php echo esc_html( $chapter['number'] ?? '' ); ?></span>
								<?php
								if ( ! empty( $chapter['media']['id'] ) || ! empty( $chapter['media']['url'] ) ) {
									echo wp_get_attachment_image(
										isset( $chapter['media']['id'] ) ? (int) $chapter['media']['id'] : 0,
										'large',
										false,
										array(
											'class' => 'sc-chapter-media',
											// no hardcoded alt — core serves the
											// attachment's own alt text (the canon alts).
										)
									);
									if ( empty( $chapter['media']['id'] ) && ! empty( $chapter['media']['url'] ) ) {
										// URL-only media (no attachment id): print the img directly.
										printf(
											'<img class="sc-chapter-media" src="%s" alt="" loading="lazy" />',
											esc_url( $chapter['media']['url'] )
										);
									}
								}
								?>
								<h3 class="sc-chapter-title">
									<?php echo esc_html( $chapter['title'] ?? '' ); ?>
									<?php if ( ! empty( $chapter['title_zh'] ) ) : ?>
										<span class="stamp sc-chapter-stamp"><?php echo esc_html( $chapter['title_zh'] ); ?></span>
									<?php endif; ?>
								</h3>
								<?php if ( ! empty( $chapter['line'] ) ) : ?>
									<p class="sc-chapter-line"><?php echo esc_html( $chapter['line'] ); ?></p>
								<?php endif; ?>
							</article>
						<?php endforeach; ?>
					</div>

					<?php if ( ! $static ) : ?>
						<div class="sc-rail-ui">
							<div class="sc-rail-progress" aria-hidden="true">
								<span class="sc-rail-progress-fill" style="width:<?php echo esc_attr( round( 100 / $n, 2 ) ); ?>%"></span>
							</div>
							<?php if ( $show_counter ) : ?>
								<span class="sc-rail-counter" aria-live="polite">01 / <?php echo esc_html( str_pad( (string) $n, 2, '0', STR_PAD_LEFT ) ); ?></span>
							<?php endif; ?>
							<?php if ( $show_arrows ) : ?>
								<div class="sc-rail-nav">
									<button type="button" class="sc-rail-btn sc-rail-prev" aria-label="<?php esc_attr_e( 'Previous chapter', 'scroll-chapters' ); ?>" disabled>
										<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path d="M15 4l-8 8 8 8" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
									</button>
									<button type="button" class="sc-rail-btn sc-rail-next" aria-label="<?php esc_attr_e( 'Next chapter', 'scroll-chapters' ); ?>">
										<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path d="M9 4l8 8-8 8" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
									</button>
								</div>
							<?php endif; ?>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</section>
		<?php
	}
}
