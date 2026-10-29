<?php
/**
 * Content types for Studio Poda.
 *
 * Registers:
 *  - `poda_project` (public, front-end slug /work/<slug>/, no archive)
 *    + taxonomy `poda_project_cat` (Branding · Web · Packaging · Campaigns)
 *    + meta `_poda_client`, `_poda_city`, `_poda_year`, `_poda_tags`
 *  - `poda_story` (public, front-end slug /stories/<slug>/, no archive)
 *    + taxonomy `poda_story_cat` (Design · Development · Studio)
 *  - SVG as an allowed upload mime (project covers are SVG artwork).
 *
 * @package StudioPodaWidgets
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Poda_CPT {

	/**
	 * Project meta keys the rail / grid / case-study templates read.
	 *
	 * @var string[]
	 */
	const PROJECT_META = array( '_poda_client', '_poda_city', '_poda_year', '_poda_tags' );

	/**
	 * Hook everything. Called on `init`.
	 */
	public static function register() {
		self::register_project();
		self::register_story();
		self::register_project_meta();
	}

	/**
	 * poda_project — the 24-brand roster. Front-end slug "work" so a
	 * project's permalink is /work/<slug>/, matching the site nav.
	 */
	private static function register_project() {
		register_taxonomy(
			'poda_project_cat',
			array( 'poda_project' ),
			array(
				'labels'            => array(
					'name'          => __( 'Project Categories', 'studio-poda-widgets' ),
					'singular_name' => __( 'Project Category', 'studio-poda-widgets' ),
				),
				'public'            => true,
				'hierarchical'      => true,
				'show_in_rest'      => true,
				'show_admin_column' => true,
				'rewrite'           => array( 'slug' => 'work-category' ),
			)
		);

		register_post_type(
			'poda_project',
			array(
				'labels'       => array(
					'name'          => __( 'Projects', 'studio-poda-widgets' ),
					'singular_name' => __( 'Project', 'studio-poda-widgets' ),
					'add_new_item'  => __( 'Add New Project', 'studio-poda-widgets' ),
					'edit_item'     => __( 'Edit Project', 'studio-poda-widgets' ),
				),
				'public'       => true,
				'has_archive'  => false,
				'menu_icon'    => 'dashicons-portfolio',
				'show_in_rest' => true,
				'supports'     => array( 'title', 'editor', 'thumbnail', 'custom-fields', 'page-attributes', 'excerpt' ),
				'rewrite'      => array( 'slug' => 'work', 'with_front' => false ),
				'taxonomies'   => array( 'poda_project_cat' ),
			)
		);
	}

	/**
	 * poda_story — the journal. Front-end slug "stories".
	 */
	private static function register_story() {
		register_taxonomy(
			'poda_story_cat',
			array( 'poda_story' ),
			array(
				'labels'            => array(
					'name'          => __( 'Story Categories', 'studio-poda-widgets' ),
					'singular_name' => __( 'Story Category', 'studio-poda-widgets' ),
				),
				'public'            => true,
				'hierarchical'      => true,
				'show_in_rest'      => true,
				'show_admin_column' => true,
				'rewrite'           => array( 'slug' => 'stories-category' ),
			)
		);

		register_post_type(
			'poda_story',
			array(
				'labels'       => array(
					'name'          => __( 'Stories', 'studio-poda-widgets' ),
					'singular_name' => __( 'Story', 'studio-poda-widgets' ),
					'add_new_item'  => __( 'Add New Story', 'studio-poda-widgets' ),
					'edit_item'     => __( 'Edit Story', 'studio-poda-widgets' ),
				),
				'public'       => true,
				'has_archive'  => false,
				'menu_icon'    => 'dashicons-media-document',
				'show_in_rest' => true,
				'supports'     => array( 'title', 'editor', 'thumbnail', 'custom-fields', 'page-attributes', 'excerpt' ),
				'rewrite'      => array( 'slug' => 'stories', 'with_front' => false ),
				'taxonomies'   => array( 'poda_story_cat' ),
			)
		);
	}

	/**
	 * The project meta fields, exposed to REST (block editor / headless
	 * consumers) and editable through the Custom Fields panel.
	 *
	 *  _poda_client — the client / type line on the card ("Juice brand.")
	 *  _poda_city   — city line ("Freetown")
	 *  _poda_year   — year line ("2025")
	 *  _poda_tags   — free-form tag line for the case-study header
	 */
	private static function register_project_meta() {
		foreach ( self::PROJECT_META as $key ) {
			register_post_meta(
				'poda_project',
				$key,
				array(
					'type'              => 'string',
					'single'            => true,
					'default'           => '',
					'show_in_rest'      => true,
					'sanitize_callback' => 'sanitize_text_field',
					'auth_callback'     => static function () {
						return current_user_can( 'edit_posts' );
					},
				)
			);
		}
	}

	/**
	 * Seed the canon taxonomy terms (the design guide §3 + stories §6).
	 * Runs on activation; idempotent — wp_insert_term no-ops by slug.
	 */
	public static function seed_terms() {
		$project_cats = array(
			'branding'  => 'Branding',
			'web'       => 'Web',
			'packaging' => 'Packaging',
			'campaigns' => 'Campaigns',
		);
		foreach ( $project_cats as $slug => $name ) {
			if ( ! term_exists( $slug, 'poda_project_cat' ) ) {
				wp_insert_term( $name, 'poda_project_cat', array( 'slug' => $slug ) );
			}
		}

		$story_cats = array(
			'design'      => 'Design',
			'development' => 'Development',
			'studio'      => 'Studio',
		);
		foreach ( $story_cats as $slug => $name ) {
			if ( ! term_exists( $slug, 'poda_story_cat' ) ) {
				wp_insert_term( $name, 'poda_story_cat', array( 'slug' => $slug ) );
			}
		}
	}

	/**
	 * Allow SVG uploads (the project covers are flat SVG artwork).
	 *
	 * @param array $mimes Allowed mime types.
	 * @return array
	 */
	public static function allow_svg_mime( $mimes ) {
		$mimes['svg'] = 'image/svg+xml';
		return $mimes;
	}

	/**
	 * WordPress core refuses SVG in wp_check_filetype_and_ext even with
	 * the mime allowed; confirm the type when the mime is the SVG one.
	 *
	 * @param array  $data     Filetype data.
	 * @param string $file     File path.
	 * @param string $filename File name.
	 * @param array  $mimes    Allowed mimes.
	 * @return array
	 */
	public static function check_svg_filetype( $data, $file, $filename, $mimes ) {
		$ext = pathinfo( $filename, PATHINFO_EXTENSION );
		if ( 'svg' === strtolower( (string) $ext ) ) {
			$data['ext']  = 'svg';
			$data['type'] = 'image/svg+xml';
		}
		unset( $file, $mimes );
		return $data;
	}
}
