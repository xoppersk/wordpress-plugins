<?php
/**
 * One-click setup: Tools > Studio Poda setup (or `wp poda setup`).
 * Creates the pages, menus, crew, services, 24 projects and 12 stories.
 * Safe to run twice: anything that already exists is left alone.
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'admin_menu',
	static function () {
		add_management_page( __( 'Studio Poda setup', 'poda-core' ), __( 'Studio Poda setup', 'poda-core' ), 'manage_options', 'poda-setup', 'poda_render_setup_page' );
	}
);

function poda_render_setup_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$log = null;
	if ( isset( $_POST['poda_run_setup'] ) ) {
		check_admin_referer( 'poda_setup' );
		$log = poda_run_setup();
	}
	echo '<div class="wrap"><h1>' . esc_html__( 'Studio Poda setup', 'poda-core' ) . '</h1>';
	echo '<p>' . esc_html__( 'Creates the Home, Stories, About, Mood, Contact and Links pages, the menus, the crew, the services, all 24 projects and the 12 stories, and sets pretty permalinks. Anything that already exists is skipped.', 'poda-core' ) . '</p>';
	if ( get_option( 'poda_setup_done' ) ) {
		echo '<p><strong>' . esc_html__( 'Setup has already run on this site.', 'poda-core' ) . '</strong></p>';
	}
	if ( $log ) {
		echo '<div class="notice notice-success"><ul>';
		foreach ( $log as $line ) {
			echo '<li>' . esc_html( $line ) . '</li>';
		}
		echo '</ul></div>';
	}
	echo '<form method="post">';
	wp_nonce_field( 'poda_setup' );
	submit_button( __( 'Run setup', 'poda-core' ), 'primary', 'poda_run_setup' );
	echo '</form></div>';
}

function poda_find( $title, $type ) {
	$q = new WP_Query(
		array(
			'post_type'              => $type,
			'title'                  => $title,
			'post_status'            => 'any',
			'posts_per_page'         => 1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
		)
	);
	return $q->posts ? (int) $q->posts[0] : 0;
}

function poda_run_setup() {
	$data   = require PODA_CORE_DIR . 'seed/data.php';
	$bodies = require PODA_CORE_DIR . 'seed/bodies.php';
	$log    = array();

	poda_register_content_types();

	// Settings defaults.
	if ( ! get_option( 'poda_settings' ) ) {
		$defaults = array();
		foreach ( poda_settings_fields() as $k => $f ) {
			$defaults[ $k ] = $f[2];
		}
		update_option( 'poda_settings', $defaults );
	}

	// Remove the stock WordPress sample content on a fresh install.
	foreach ( array( array( 'Hello world!', 'post' ), array( 'Sample Page', 'page' ), array( 'Privacy Policy', 'page' ) ) as $sample ) {
		$id = poda_find( $sample[0], $sample[1] );
		if ( $id && 'Privacy Policy' !== $sample[0] ) {
			wp_delete_post( $id, true );
		}
	}

	// Crew.
	$crew_ids = array();
	foreach ( $data['crew'] as $i => $c ) {
		$id = poda_find( $c[0], 'poda_crew' );
		if ( ! $id ) {
			$id = wp_insert_post( array( 'post_type' => 'poda_crew', 'post_title' => $c[0], 'post_status' => 'publish', 'menu_order' => $i + 1 ) );
			update_post_meta( $id, 'poda_role', $c[1] );
			update_post_meta( $id, 'poda_city', $c[2] );
			update_post_meta( $id, 'poda_bio', $c[3] );
			update_post_meta( $id, 'poda_art', $c[4] );
		}
		$crew_ids[ $c[0] ] = (int) $id;
	}
	$log[] = sprintf( 'Crew: %d', count( $crew_ids ) );

	// Services.
	foreach ( $data['services'] as $i => $s ) {
		if ( ! poda_find( $s[0], 'poda_service' ) ) {
			$id = wp_insert_post( array( 'post_type' => 'poda_service', 'post_title' => $s[0], 'post_excerpt' => $s[2], 'post_status' => 'publish', 'menu_order' => $i + 1 ) );
			update_post_meta( $id, 'poda_weeks', $s[1] );
		}
	}
	$log[] = sprintf( 'Services: %d', count( $data['services'] ) );

	// Disciplines and projects.
	foreach ( array( 'Identity', 'Web', 'Packaging', 'Campaigns' ) as $d ) {
		if ( ! term_exists( $d, 'poda_discipline' ) ) {
			wp_insert_term( $d, 'poda_discipline' );
		}
	}
	foreach ( $data['projects'] as $i => $p ) {
		if ( poda_find( $p[0], 'poda_project' ) ) {
			continue;
		}
		$id = wp_insert_post( array( 'post_type' => 'poda_project', 'post_title' => $p[0], 'post_excerpt' => $p[6], 'post_status' => 'publish', 'menu_order' => $i + 1 ) );
		update_post_meta( $id, 'poda_kind', $p[1] );
		update_post_meta( $id, 'poda_city', $p[2] );
		update_post_meta( $id, 'poda_year', $p[3] );
		update_post_meta( $id, 'poda_art', $p[5] );
		update_post_meta( $id, 'poda_line', $p[6] );
		if ( $p[7] ) {
			update_post_meta( $id, 'poda_featured', '1' );
		}
		wp_set_object_terms( $id, $p[4], 'poda_discipline' );
		if ( 'Papayo' === $p[0] ) {
			foreach ( $data['papayo'] as $k => $v ) {
				if ( 'riders' === $k ) {
					$v = implode( ',', array_filter( array_map( static fn( $n ) => $crew_ids[ $n ] ?? 0, $v ) ) );
				}
				update_post_meta( $id, 'poda_' . $k, $v );
			}
		}
	}
	$log[] = sprintf( 'Projects: %d', count( $data['projects'] ) );

	// Stories (posts).
	foreach ( $data['stories'] as $s ) {
		if ( poda_find( $s[1], 'post' ) ) {
			continue;
		}
		$cat = term_exists( $s[0], 'category' );
		$cat = $cat ? $cat : wp_insert_term( $s[0], 'category' );
		$content = $s[5];
		if ( ! $content && isset( $bodies[ $s[1] ] ) ) {
			foreach ( $bodies[ $s[1] ] as $para ) {
				$content .= '<!-- wp:paragraph --><p>' . esc_html( $para ) . '</p><!-- /wp:paragraph -->';
			}
		}
		$id = wp_insert_post(
			array(
				'post_title'    => $s[1],
				'post_excerpt'  => $s[2],
				'post_content'  => $content,
				'post_status'   => 'publish',
				'post_date'     => $s[4] . ' 09:00:00',
				'post_category' => array( (int) ( is_array( $cat ) ? $cat['term_id'] : $cat ) ),
			)
		);
		if ( isset( $crew_ids[ $s[3] ] ) ) {
			update_post_meta( $id, 'poda_author_crew', (string) $crew_ids[ $s[3] ] );
		}
	}
	$uncat = get_term_by( 'slug', 'uncategorized', 'category' );
	if ( $uncat && 0 === (int) $uncat->count && (int) get_option( 'default_category' ) === (int) $uncat->term_id ) {
		$process = get_term_by( 'name', 'Process', 'category' );
		if ( $process ) {
			update_option( 'default_category', $process->term_id );
			wp_delete_term( $uncat->term_id, 'category' );
		}
	}
	$log[] = sprintf( 'Stories: %d', count( $data['stories'] ) );

	// Pages.
	$pages = array(
		'home'    => 'Home',
		'stories' => 'Stories',
		'about'   => 'About',
		'mood'    => 'Mood',
		'contact' => 'Contact',
		'links'   => 'Links',
	);
	$page_ids = array();
	foreach ( $pages as $slug => $title ) {
		$existing = get_page_by_path( $slug );
		$page_ids[ $slug ] = $existing ? $existing->ID : wp_insert_post( array( 'post_type' => 'page', 'post_title' => $title, 'post_name' => $slug, 'post_status' => 'publish' ) );
	}
	update_option( 'show_on_front', 'page' );
	update_option( 'page_on_front', $page_ids['home'] );
	update_option( 'page_for_posts', $page_ids['stories'] );
	update_option( 'posts_per_page', 12 );
	$log[] = 'Pages: Home, Stories, About, Mood, Contact, Links';

	// Menus.
	$work_url = home_url( '/work/' );
	$menus    = array(
		'primary' => 'Main',
		'footer'  => 'Footer',
	);
	$locations = get_theme_mod( 'nav_menu_locations', array() );
	foreach ( $menus as $loc => $name ) {
		$menu = wp_get_nav_menu_object( $name );
		if ( ! $menu ) {
			$menu_id = wp_create_nav_menu( $name );
			wp_update_nav_menu_item( $menu_id, 0, array( 'menu-item-title' => 'Work', 'menu-item-url' => $work_url, 'menu-item-type' => 'custom', 'menu-item-status' => 'publish' ) );
			foreach ( array( 'stories', 'about', 'mood', 'contact' ) as $slug ) {
				wp_update_nav_menu_item( $menu_id, 0, array( 'menu-item-object-id' => $page_ids[ $slug ], 'menu-item-object' => 'page', 'menu-item-type' => 'post_type', 'menu-item-status' => 'publish' ) );
			}
		} else {
			$menu_id = $menu->term_id;
		}
		$locations[ $loc ] = $menu_id;
	}
	set_theme_mod( 'nav_menu_locations', $locations );
	$log[] = 'Menus: Main and Footer';

	// Permalinks.
	global $wp_rewrite;
	$wp_rewrite->set_permalink_structure( '/%postname%/' );
	poda_register_content_types(); // re-register so the /work/ rules exist under the new structure
	flush_rewrite_rules();
	$log[] = 'Permalinks: /%postname%/';

	update_option( 'blogname', 'Studio Poda' );
	update_option( 'blogdescription', 'Brands that refuse to whisper.' );
	update_option( 'poda_setup_done', time() );
	return $log;
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command(
		'poda setup',
		static function () {
			foreach ( poda_run_setup() as $line ) {
				WP_CLI::log( $line );
			}
			WP_CLI::success( 'Studio Poda is set up.' );
		}
	);
}
