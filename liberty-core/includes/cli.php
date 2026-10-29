<?php
/**
 * WP-CLI commands for the move from the old site.
 *
 *   wp liberty audit     Report what the old plugins left behind. Changes nothing.
 *   wp liberty migrate   Apply the switch-over. Safe to run more than once, so the
 *                        same steps rehearsed on staging run again on launch day.
 *   wp liberty live start <url> [--url2=<url>] [--title=<t>] | wp liberty live end
 */

defined( 'ABSPATH' ) || exit;

/**
 * Write a line to the terminal (WP-CLI) or to the Tools → Liberty screen.
 */
function liberty_cli_log( $line ) {
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		WP_CLI::log( $line );
	} else {
		$GLOBALS['liberty_cli_out'][] = $line;
	}
}

class Liberty_CLI {

	/**
	 * Report content counts and anything from the old theme and plugins that
	 * the new site has to handle. Read-only.
	 *
	 * @subcommand audit
	 */
	public function audit() {
		global $wpdb;
		$count = static fn( $type, $status = 'publish' ) => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s", $type, $status ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		$own = liberty_db_is_own();
		liberty_cli_log( true === $own ? 'Database check: this site has its own database.' : 'WARNING: this database belongs to ' . $own . ' — two sites may share it. Do not run the switch-over.' );
		liberty_cli_log( '== Content ==' );
		foreach ( array( 'post', 'page', 'attachment', liberty_cpt( 'team' ), liberty_cpt( 'live' ) ) as $t ) {
			liberty_cli_log( sprintf( '%-16s %6d', $t, $count( $t, 'attachment' === $t ? 'inherit' : 'publish' ) ) );
		}
		liberty_cli_log( sprintf( '%-16s %6d', 'comments', (int) wp_count_comments()->approved ) );
		liberty_cli_log( sprintf( '%-16s %6d', 'categories', (int) wp_count_terms( array( 'taxonomy' => 'category' ) ) ) );

		liberty_cli_log( "\n== Post types defined in ACF ==" );
		delete_option( 'liberty_legacy_cpts' );
		liberty_cli_log( wp_json_encode( liberty_legacy_post_types() ) . '  → using team=' . liberty_cpt( 'team' ) . ', live=' . liberty_cpt( 'live' ) );
		liberty_cli_log( 'Member Details fields: ' . wp_json_encode( liberty_legacy_fields( LIBERTY_ACF_GROUP_TEAM ) ) );
		liberty_cli_log( 'YouTube Live fields:   ' . wp_json_encode( liberty_legacy_fields( LIBERTY_ACF_GROUP_LIVE ) ) );

		liberty_cli_log( "\n== Shortcodes inside published content ==" );
		$rows  = $wpdb->get_col( "SELECT post_content FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ('post','page') AND post_content LIKE '%[%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$codes = array();
		foreach ( $rows as $c ) {
			if ( preg_match_all( '/\[([a-z][a-z0-9_-]+)/i', $c, $m ) ) {
				foreach ( $m[1] as $code ) {
					$codes[ strtolower( $code ) ] = ( $codes[ strtolower( $code ) ] ?? 0 ) + 1;
				}
			}
		}
		arsort( $codes );
		foreach ( array_slice( $codes, 0, 30, true ) as $code => $n ) {
			liberty_cli_log( sprintf( '[%s] %d', $code, $n ) );
		}

		liberty_cli_log( "\n== Post meta keys used by stories (top 40, private keys hidden) ==" );
		$keys = $wpdb->get_results( "SELECT m.meta_key, COUNT(*) n FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE p.post_type = 'post' AND p.post_status = 'publish' AND m.meta_key NOT LIKE '\_%' GROUP BY m.meta_key ORDER BY n DESC LIMIT 40" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		foreach ( $keys as $k ) {
			liberty_cli_log( sprintf( '%-40s %d', $k->meta_key, $k->n ) );
		}
		$foxiz = $wpdb->get_results( "SELECT m.meta_key, COUNT(*) n FROM {$wpdb->postmeta} m WHERE m.meta_key LIKE '%ruby%' OR m.meta_key LIKE '%foxiz%' OR m.meta_key LIKE 'rb\_%' GROUP BY m.meta_key ORDER BY n DESC LIMIT 30" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		liberty_cli_log( "\n== Foxiz / Ruby meta ==" );
		foreach ( $foxiz as $k ) {
			liberty_cli_log( sprintf( '%-40s %d', $k->meta_key, $k->n ) );
		}

		liberty_cli_log( "\n== Built with Elementor ==" );
		$el = $wpdb->get_results( "SELECT p.ID, p.post_type, p.post_title FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_elementor_edit_mode' AND m.meta_value = 'builder' WHERE p.post_status = 'publish' ORDER BY p.post_type, p.post_title" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		foreach ( $el as $p ) {
			liberty_cli_log( sprintf( '%-10s #%-6d %s', $p->post_type, $p->ID, $p->post_title ) );
		}

		liberty_cli_log( "\n== Broadcasts and their links ==" );
		foreach ( get_posts( array( 'post_type' => liberty_cpt( 'live' ), 'numberposts' => -1 ) ) as $b ) {
			$url = liberty_broadcast_url( $b->ID );
			liberty_cli_log( sprintf( '#%-6d %-8s %s', $b->ID, liberty_parse_stream( $url ) ? 'ok' : 'NO LINK', $url ) );
		}
		liberty_cli_log( "\n== Story videos from the old theme ==" );
		$vids = get_posts( array( 'post_type' => 'post', 'numberposts' => -1, 'fields' => 'ids', 'meta_key' => 'field_name_post_video' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		$kinds = array( 'embed' => 0, 'file' => 0, 'none' => 0 );
		$miss  = array();
		foreach ( $vids as $vid ) {
			$v = liberty_legacy_story_video( $vid );
			$kinds[ $v ? $v['kind'] : 'none' ]++;
			if ( ! $v && count( $miss ) < 5 ) {
				$miss[] = $vid;
			}
		}
		liberty_cli_log( sprintf( '%d stories have a video field: %d YouTube/Facebook, %d video files, %d with nothing playable', count( $vids ), $kinds['embed'], $kinds['file'], $kinds['none'] ) );
		foreach ( $miss as $vid ) {
			liberty_cli_log( sprintf( '  #%d raw: %s', $vid, wp_json_encode( array_map( static fn( $k ) => get_post_meta( $vid, $k, true ), array( 'field_name_video_type_is', 'field_name_post_video', 'field_name_post_video_video_self_url', 'field_name_post_video_acf_name_external_video' ) ) ) ) );
		}
		liberty_cli_log( 'Success: Audit finished. Nothing was changed.' );
	}

	/**
	 * Apply the switch-over. Safe to run repeatedly.
	 *
	 * @subcommand migrate
	 */
	public function migrate() {
		$own = liberty_db_is_own();
		if ( true !== $own ) {
			liberty_cli_log( 'STOPPED: this database belongs to ' . $own . ', not to this site. Two sites appear to share one database. Give this site its own database, then run again. Nothing was changed.' );
			if ( defined( 'WP_CLI' ) && WP_CLI ) {
				WP_CLI::halt( 1 );
			}
			return;
		}
		delete_option( 'liberty_legacy_cpts' );
		// Liberty pages. The site's own existing page is used when there is one, so its address
		// (and any links to it) stays the same; otherwise a new page is created.
		$pages = array(
			array( array( 'live-tv', 'watch', 'live' ), __( 'Live TV', 'liberty-core' ), 'templates/watch.php' ),
			array( array( 'about-us', 'about' ), __( 'About us', 'liberty-core' ), 'templates/about.php' ),
			array( array( 'contact', 'contact-us' ), __( 'Contact', 'liberty-core' ), 'templates/contact.php' ),
			array( array( 'following' ), __( 'Following', 'liberty-core' ), 'templates/following.php' ),
		);
		$redirects = get_option( 'liberty_redirects', array() );
		$redirects = is_array( $redirects ) ? $redirects : array();
		foreach ( $pages as $def ) {
			$found = array();
			foreach ( $def[0] as $slug ) {
				$p = get_page_by_path( $slug );
				if ( $p && 'publish' === $p->post_status ) {
					$found[] = $p;
				}
			}
			// Prefer the site's original page over one an earlier run created (marked, or an empty page).
			$ours = static fn( $p ) => get_post_meta( $p->ID, '_liberty_created', true ) || '' === trim( $p->post_content ) ? 1 : 0;
			usort( $found, static fn( $x, $y ) => $ours( $x ) <=> $ours( $y ) );
			if ( $found ) {
				$keep = array_shift( $found );
				$id   = $keep->ID;
				foreach ( $found as $dupe ) {
					if ( $ours( $dupe ) ) {
						$redirects[ trailingslashit( (string) wp_parse_url( get_permalink( $dupe ), PHP_URL_PATH ) ) ] = (string) wp_parse_url( get_permalink( $keep ), PHP_URL_PATH );
						wp_update_post( array( 'ID' => $dupe->ID, 'post_status' => 'draft' ) );
						liberty_cli_log( 'Merged duplicate page /' . $dupe->post_name . '/ into /' . $keep->post_name . '/' );
					}
				}
			} else {
				$id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_name' => $def[0][0], 'post_title' => $def[1] ) );
				update_post_meta( $id, '_liberty_created', 1 );
				liberty_cli_log( "Created page /{$def[0][0]}/ (#{$id})" );
			}
			update_post_meta( $id, '_wp_page_template', $def[2] );
			delete_post_meta( $id, '_elementor_edit_mode' );
		}
		update_option( 'liberty_redirects', $redirects, false );
		// Home page renders the front-page template; the full list of stories lives at /news/.
		$home = get_page_by_path( 'home' );
		$home = $home ? $home->ID : wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_name' => 'home', 'post_title' => __( 'Home', 'liberty-core' ) ) );
		$news = get_page_by_path( 'news' );
		$news = $news ? $news->ID : wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_name' => 'news', 'post_title' => __( 'Latest news', 'liberty-core' ) ) );
		// Elementor-built pages: Support keeps its address (the app may link to it) with a proper template;
		// Meet The Team and Read History are retired and redirect.
		foreach ( array( 'support', 'app-support' ) as $slug ) {
			$p = get_page_by_path( $slug );
			if ( $p ) {
				update_post_meta( $p->ID, '_wp_page_template', 'templates/support.php' );
				delete_post_meta( $p->ID, '_elementor_edit_mode' );
			}
		}
		$redirects = get_option( 'liberty_redirects', array() );
		$redirects = is_array( $redirects ) ? $redirects : array();
		foreach ( array( 'Meet The Team' => '/about-us/#team', 'Read History' => '/' ) as $title => $target ) {
			$found = get_posts( array( 'post_type' => 'page', 'title' => $title, 'post_status' => 'publish', 'numberposts' => 1 ) );
			if ( $found ) {
				$redirects[ trailingslashit( (string) wp_parse_url( get_permalink( $found[0] ), PHP_URL_PATH ) ) ] = $target;
				wp_update_post( array( 'ID' => $found[0]->ID, 'post_status' => 'draft' ) );
				liberty_cli_log( "Retired page '{$title}' → redirects to {$target}" );
			}
		}
		update_option( 'liberty_redirects', $redirects, false );
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', (int) $home );
		update_option( 'page_for_posts', (int) $news );
		if ( ! liberty_opt( 'site', 'mail_support' ) ) {
			liberty_set_opt( 'site', 'mail_support', 'support@libertyonlinetv.com' );
		}
		if ( ! wp_next_scheduled( 'liberty_live_check' ) ) {
			wp_schedule_event( time() + 60, 'liberty_two_minutes', 'liberty_live_check' );
		}
		liberty_register_content_types();
		flush_rewrite_rules();
		liberty_purge_cache();
		liberty_cli_log( 'Success: Migration applied.' );
	}

	/**
	 * Start or end a live stream.
	 *
	 * ## OPTIONS
	 *
	 * <action>
	 * : start or end
	 *
	 * [<url>]
	 * : YouTube or Facebook link (for start)
	 *
	 * [--url2=<url>]
	 * : Second stream on the other platform
	 *
	 * [--title=<title>]
	 * : Show name
	 */
	public function live( $args, $assoc ) {
		if ( 'end' === $args[0] ) {
			liberty_live_end( 'manual' );
			WP_CLI::success( 'Live ended.' );
			return;
		}
		$r = liberty_live_start( array( 'url' => $args[1] ?? '', 'url2' => $assoc['url2'] ?? '', 'title' => $assoc['title'] ?? '', 'source' => 'manual' ) );
		is_wp_error( $r ) ? WP_CLI::error( $r->get_error_message() ) : WP_CLI::success( 'Live started: ' . wp_json_encode( liberty_live()['stream'] ) );
	}
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command( 'liberty', 'Liberty_CLI' );
}
