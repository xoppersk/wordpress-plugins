<?php
/**
 * Liberty-branded admin: login screen, a newsroom dashboard and a tidier
 * admin (replaces Ultimate Dashboard and Ultimate Dashboard Pro).
 */

defined( 'ABSPATH' ) || exit;

/* -------------------------------------------------------------- login */

add_action(
	'login_enqueue_scripts',
	static function () {
		$logo = function_exists( 'liberty_logo_url' ) ? liberty_logo_url( 'color' ) : '';
		?>
		<style>
			body.login{background:#FAF8F4}
			#login h1 a{<?php echo $logo ? 'background-image:url(' . esc_url( $logo ) . ');' : ''; ?>background-size:contain;width:260px;height:70px}
			.login form{border:1px solid #E3DED4;box-shadow:none;border-top:4px solid #F87012}
			.wp-core-ui .button-primary{background:#F87012;border-color:#F87012}
			.wp-core-ui .button-primary:hover,.wp-core-ui .button-primary:focus{background:#E05F08;border-color:#E05F08}
			.login #backtoblog a,.login #nav a{color:#071023}
		</style>
		<?php
	}
);
add_filter( 'login_headerurl', static fn() => home_url( '/' ) );
add_filter( 'login_headertext', static fn() => get_bloginfo( 'name' ) );

/* ---------------------------------------------------------- dashboard */

add_action(
	'wp_dashboard_setup',
	static function () {
		foreach ( array( 'dashboard_primary', 'dashboard_quick_press', 'dashboard_site_health', 'dashboard_php_nag' ) as $w ) {
			remove_meta_box( $w, 'dashboard', 'side' );
			remove_meta_box( $w, 'dashboard', 'normal' );
		}
		remove_action( 'welcome_panel', 'wp_welcome_panel' );
		wp_add_dashboard_widget( 'liberty_newsroom', __( 'Liberty newsroom', 'liberty-core' ), 'liberty_dashboard_widget', null, null, 'normal', 'high' );
	}
);

function liberty_dashboard_widget() {
	$live     = liberty_live();
	$breaking = liberty_breaking();
	$inbox    = (int) wp_count_posts( 'liberty_inbox' )->private;
	$btn      = static function ( $url, $label, $primary = false ) {
		printf( '<a class="button%3$s" href="%1$s" style="margin:0 6px 6px 0">%2$s</a>', esc_url( $url ), esc_html( $label ), $primary ? ' button-primary' : '' );
	};
	echo '<p style="font-size:14px">';
	echo $live ? '<strong style="color:#b32d2e">● ' . esc_html__( 'On air:', 'liberty-core' ) . '</strong> ' . esc_html( $live['title'] ) : esc_html__( 'Not live right now.', 'liberty-core' );
	echo '<br>' . ( $breaking ? '<strong style="color:#b32d2e">' . esc_html__( 'Breaking banner on:', 'liberty-core' ) . '</strong> ' . esc_html( $breaking['text'] ) : esc_html__( 'No breaking news banner.', 'liberty-core' ) ) . '</p><p>';
	$btn( admin_url( 'post-new.php' ), __( 'Write a story', 'liberty-core' ), true );
	if ( current_user_can( 'edit_others_posts' ) ) {
		$btn( admin_url( 'admin.php?page=liberty-live' ), $live ? __( 'Live settings', 'liberty-core' ) : __( 'Go live', 'liberty-core' ) );
		$btn( admin_url( 'admin.php?page=liberty-newsroom' ), __( 'Breaking news and briefing', 'liberty-core' ) );
	}
	/* translators: %d: number of messages */
	$btn( admin_url( 'edit.php?post_type=liberty_inbox' ), sprintf( _n( 'Inbox (%d)', 'Inbox (%d)', $inbox, 'liberty-core' ), $inbox ) );
	echo '</p><h3 style="margin:14px 0 6px">' . esc_html__( 'Latest stories', 'liberty-core' ) . '</h3><ul style="margin:0">';
	foreach ( get_posts( array( 'numberposts' => 5, 'post_status' => array( 'publish', 'draft', 'pending', 'future' ) ) ) as $p ) {
		$status = 'publish' === $p->post_status ? '' : ' <em>(' . esc_html( get_post_status_object( $p->post_status )->label ) . ')</em>';
		echo '<li><a href="' . esc_url( get_edit_post_link( $p ) ) . '">' . esc_html( liberty_title_case( $p->post_title ) ) . '</a>' . $status . ' · ' . esc_html( human_time_diff( get_post_time( 'U', true, $p ) ) ) . ' ' . esc_html__( 'ago', 'liberty-core' ) . '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput -- $status escaped above.
	}
	echo '</ul>';
}

add_filter( 'admin_footer_text', static fn() => esc_html__( 'Liberty Online TV · built by Sevyn Labs', 'liberty-core' ) );
