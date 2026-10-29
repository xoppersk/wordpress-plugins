<?php
/**
 * Scroll Chapters core: Elementor category, the four widgets, asset
 * registration and the helpers module.
 *
 * Spec: §6 — assets load only through each widget's
 * get_script_depends() / get_style_depends(); nothing here enqueues
 * widget assets globally.
 *
 * @package ScrollChapters
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin singleton.
 */
class SC_Plugin {

	/**
	 * Single instance.
	 *
	 * @var SC_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get (and build) the instance.
	 *
	 * @return SC_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Wire everything.
	 */
	private function __construct() {
		add_action( 'init', array( $this, 'load_textdomain' ) );

		// Settings page (Settings -> Scroll Chapters) + option.
		if ( is_admin() ) {
			new SC_Settings();
		}

		// Elementor integration.
		add_action( 'elementor/elements/categories_registered', array( $this, 'register_category' ) );
		add_action( 'elementor/widgets/register', array( $this, 'register_widgets' ) );
		add_action( 'elementor/frontend/after_register_scripts', array( $this, 'register_scripts' ) );
		add_action( 'elementor/frontend/after_register_styles', array( $this, 'register_styles' ) );

		// The helpers module self-gates each helper on its settings toggle.
		require_once SC_PLUGIN_DIR . 'includes/class-helpers.php';
		new SC_Helpers();
	}

	/**
	 * Load the text domain.
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'scroll-chapters',
			false,
			dirname( plugin_basename( SC_PLUGIN_FILE ) ) . '/languages'
		);
	}

	/**
	 * Register the "Scroll Chapters" editor category.
	 *
	 * @param \Elementor\Elements_Manager $manager Elements manager.
	 */
	public function register_category( $manager ) {
		$manager->add_category(
			'scroll-chapters',
			array(
				'title' => esc_html__( 'Scroll Chapters', 'scroll-chapters' ),
				'icon'  => 'fa fa-lightbulb-o',
			)
		);
	}

	/**
	 * Register the four widgets (§6.3 A–D).
	 *
	 * @param \Elementor\Widgets_Manager $manager Widgets manager.
	 */
	public function register_widgets( $manager ) {
		require_once SC_PLUGIN_DIR . 'includes/widgets/class-chapter-rail.php';
		require_once SC_PLUGIN_DIR . 'includes/widgets/class-typewriter.php';
		require_once SC_PLUGIN_DIR . 'includes/widgets/class-marquee.php';
		require_once SC_PLUGIN_DIR . 'includes/widgets/class-image-stack.php';

		$manager->register( new SC_Chapter_Rail_Widget() );
		$manager->register( new SC_Typewriter_Widget() );
		$manager->register( new SC_Marquee_Widget() );
		$manager->register( new SC_Image_Stack_Widget() );
	}

	/**
	 * Register (never enqueue) the per-widget script handles.
	 * Each widget names its handle in get_script_depends().
	 */
	public function register_scripts() {
		$js = SC_PLUGIN_URL . 'assets/js/';

		wp_register_script( 'sc-chapter-rail', $js . 'chapter-rail.js', array(), SC_VERSION, true );
		wp_register_script( 'sc-typewriter', $js . 'typewriter.js', array(), SC_VERSION, true );
		wp_register_script( 'sc-marquee', $js . 'marquee.js', array(), SC_VERSION, true );
		wp_register_script( 'sc-image-stack', $js . 'image-stack.js', array(), SC_VERSION, true );

		// Helper scripts (enqueued by SC_Helpers only when the helper is on).
		wp_register_script( 'sc-filter-motion', $js . 'filter-motion.js', array(), SC_VERSION, true );
		wp_register_script( 'sc-form-summary', $js . 'form-summary.js', array(), SC_VERSION, true );
		wp_register_script( 'sc-header', $js . 'header.js', array(), SC_VERSION, true );
		wp_register_script( 'sc-menu', $js . 'menu.js', array(), SC_VERSION, true );
	}

	/**
	 * Register (never enqueue) the style handles.
	 */
	public function register_styles() {
		$css = SC_PLUGIN_URL . 'assets/css/';

		wp_register_style( 'sc-widgets', $css . 'widgets.css', array(), SC_VERSION );
		wp_register_style( 'sc-helpers', $css . 'helpers.css', array(), SC_VERSION );
	}
}
