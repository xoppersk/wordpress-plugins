<?php
/**
 * Scroll Chapters settings: the `sc_settings` option + Settings API page.
 *
 * Spec: §6.1 (data model) and §6.2 (admin screen — Settings →
 * Scroll Chapters, 3 sections: Defaults, Mobile, Site helpers).
 *
 * @package ScrollChapters
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings store + admin page for Scroll Chapters.
 */
class SC_Settings {

	/**
	 * Option name.
	 */
	const OPTION = 'sc_settings';

	/**
	 * Helper keys, in the order they appear in §6.5 / §6.2.
	 *
	 * @return string[]
	 */
	public static function helper_keys() {
		return array(
			'filter_motion',
			'form_summary',
			'grouped_search',
			'bend_order',
			'header_hide',
			'menu_motion',
			'tag_boxes',
		);
	}

	/**
	 * Spec defaults (§6.1).
	 *
	 * @return array
	 */
	public static function defaults() {
		$helpers = array();
		foreach ( self::helper_keys() as $key ) {
			$helpers[ $key ] = true;
		}

		return array(
			'enable_on_mobile'   => true,
			'marquee_speed'      => 70,
			'typewriter_type_ms' => 55,
			'helpers'            => $helpers,
		);
	}

	/**
	 * All settings, merged over defaults so partial options stay valid.
	 *
	 * @return array
	 */
	public static function get() {
		static $settings = null;
		if ( null === $settings ) {
			$saved    = get_option( self::OPTION, array() );
			$settings = wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );

			// Helpers merge per-key so a newly added helper still defaults on.
			$saved_helpers          = ( isset( $saved['helpers'] ) && is_array( $saved['helpers'] ) ) ? $saved['helpers'] : array();
			$settings['helpers']    = wp_parse_args( $saved_helpers, self::defaults()['helpers'] );
			$settings['helpers']    = array_map( 'boolval', $settings['helpers'] );
			$settings['enable_on_mobile']   = (bool) $settings['enable_on_mobile'];
			$settings['marquee_speed']      = (int) $settings['marquee_speed'];
			$settings['typewriter_type_ms'] = (int) $settings['typewriter_type_ms'];
		}
		return $settings;
	}

	/**
	 * Whether one of the seven site helpers is switched on.
	 *
	 * @param string $key Helper key (see helper_keys()).
	 * @return bool
	 */
	public static function helper_enabled( $key ) {
		$settings = self::get();
		return ! empty( $settings['helpers'][ $key ] );
	}

	/**
	 * Register admin hooks.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_init', array( $this, 'register' ) );
	}

	/**
	 * Settings → Scroll Chapters.
	 */
	public function add_page() {
		add_options_page(
			__( 'Scroll Chapters', 'scroll-chapters' ),
			__( 'Scroll Chapters', 'scroll-chapters' ),
			'manage_options',
			'scroll-chapters',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Settings API registration: one option, three sections (§6.2).
	 */
	public function register() {
		register_setting(
			'sc_settings_group',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);

		// 1. Defaults ------------------------------------------------------------
		add_settings_section(
			'sc_defaults',
			__( 'Defaults', 'scroll-chapters' ),
			static function () {
				echo '<p>' . esc_html__( 'Starting values for new widgets. Widgets already on a page keep their own values.', 'scroll-chapters' ) . '</p>';
			},
			'scroll-chapters'
		);
		add_settings_field(
			'marquee_speed',
			__( 'Marquee speed (px/s)', 'scroll-chapters' ),
			array( $this, 'number_field' ),
			'scroll-chapters',
			'sc_defaults',
			array(
				'key'   => 'marquee_speed',
				'min'   => 10,
				'max'   => 300,
				'after' => 'px/s',
			)
		);
		add_settings_field(
			'typewriter_type_ms',
			__( 'Typewriter type speed (ms)', 'scroll-chapters' ),
			array( $this, 'number_field' ),
			'scroll-chapters',
			'sc_defaults',
			array(
				'key'   => 'typewriter_type_ms',
				'min'   => 10,
				'max'   => 500,
				'after' => 'ms per character',
			)
		);

		// 2. Mobile --------------------------------------------------------------
		add_settings_section(
			'sc_mobile',
			__( 'Mobile', 'scroll-chapters' ),
			static function () {
				echo '<p>' . esc_html__( 'How the Chapter Rail behaves under 768px.', 'scroll-chapters' ) . '</p>';
			},
			'scroll-chapters'
		);
		add_settings_field(
			'enable_on_mobile',
			__( 'Pin the Chapter Rail on phones', 'scroll-chapters' ),
			array( $this, 'toggle_field' ),
			'scroll-chapters',
			'sc_mobile',
			array(
				'key'   => 'enable_on_mobile',
				'label' => __( 'Run the pinned rail under 768px. Off = a native scroll-snap row.', 'scroll-chapters' ),
			)
		);

		// 3. Site helpers ----------------------------------------------------------
		add_settings_section(
			'sc_helpers',
			__( 'Site helpers', 'scroll-chapters' ),
			static function () {
				echo '<p>' . esc_html__( 'Seven small, isolated hooks. Switch one off and only that behaviour stops.', 'scroll-chapters' ) . '</p>';
			},
			'scroll-chapters'
		);

		$helper_fields = array(
			'filter_motion'  => array(
				__( 'Filter motion and counts', 'scroll-chapters' ),
				__( 'Exit/enter motion, count badges and the status line for the Taxonomy Filter and Loop Grid.', 'scroll-chapters' ),
			),
			'form_summary'   => array(
				__( 'Form error summary', 'scroll-chapters' ),
				__( 'A boxed "things to fix" summary at the top of the contact form when a submit fails.', 'scroll-chapters' ),
			),
			'grouped_search' => array(
				__( 'Grouped search results', 'scroll-chapters' ),
				__( 'Search results grouped into Bends, Notebook and Sign Archive, with a summary line.', 'scroll-chapters' ),
			),
			'bend_order'     => array(
				__( 'Order bends by number', 'scroll-chapters' ),
				__( 'Bend archives, loops and prev/next navigation step by bend_number.', 'scroll-chapters' ),
			),
			'header_hide'    => array(
				__( 'Header hide on scroll', 'scroll-chapters' ),
				__( 'The header slides away after 200px of scrolling down and returns on any scroll up.', 'scroll-chapters' ),
			),
			'menu_motion'    => array(
				__( 'Menu motion', 'scroll-chapters' ),
				__( 'Menu popup links rise in with a stagger; a fade under reduced motion.', 'scroll-chapters' ),
			),
			'tag_boxes'      => array(
				__( 'Service tag boxes', 'scroll-chapters' ),
				__( 'The [klo_tags] shortcode: place, year and service tags as boxes in the Bend hero.', 'scroll-chapters' ),
			),
		);

		foreach ( $helper_fields as $key => $labels ) {
			add_settings_field(
				'helper_' . $key,
				$labels[0],
				array( $this, 'helper_field' ),
				'scroll-chapters',
				'sc_helpers',
				array(
					'key'  => $key,
					'desc' => $labels[1],
				)
			);
		}
	}

	/**
	 * Number field renderer.
	 *
	 * @param array $args Field args.
	 */
	public function number_field( $args ) {
		$settings = self::get();
		printf(
			'<input type="number" name="%1$s[%2$s]" value="%3$d" min="%4$d" max="%5$d" step="1" class="small-text" /> %6$s',
			esc_attr( self::OPTION ),
			esc_attr( $args['key'] ),
			(int) $settings[ $args['key'] ],
			(int) $args['min'],
			(int) $args['max'],
			esc_html( $args['after'] )
		);
	}

	/**
	 * Single toggle renderer (Mobile section).
	 *
	 * @param array $args Field args.
	 */
	public function toggle_field( $args ) {
		$settings = self::get();
		printf(
			'<label><input type="checkbox" name="%1$s[%2$s]" value="1" %3$s /> %4$s</label>',
			esc_attr( self::OPTION ),
			esc_attr( $args['key'] ),
			checked( ! empty( $settings[ $args['key'] ] ), true, false ),
			esc_html( $args['label'] )
		);
	}

	/**
	 * Helper checkbox renderer.
	 *
	 * @param array $args Field args.
	 */
	public function helper_field( $args ) {
		$settings = self::get();
		printf(
			'<label><input type="checkbox" name="%1$s[helpers][%2$s]" value="1" %3$s /> %4$s</label>',
			esc_attr( self::OPTION ),
			esc_attr( $args['key'] ),
			checked( ! empty( $settings['helpers'][ $args['key'] ] ), true, false ),
			esc_html( $args['desc'] )
		);
	}

	/**
	 * Sanitize the option on save.
	 *
	 * @param mixed $input Raw input.
	 * @return array
	 */
	public function sanitize( $input ) {
		$input  = is_array( $input ) ? $input : array();
		$clean  = self::defaults();
		$clean['marquee_speed']      = min( 300, max( 10, absint( $input['marquee_speed'] ?? 70 ) ) );
		$clean['typewriter_type_ms'] = min( 500, max( 10, absint( $input['typewriter_type_ms'] ?? 55 ) ) );
		$clean['enable_on_mobile']   = ! empty( $input['enable_on_mobile'] );

		foreach ( self::helper_keys() as $key ) {
			$clean['helpers'][ $key ] = ! empty( $input['helpers'][ $key ] );
		}

		return $clean;
	}

	/**
	 * Render the settings page.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Scroll Chapters', 'scroll-chapters' ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( 'sc_settings_group' );
				do_settings_sections( 'scroll-chapters' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}
