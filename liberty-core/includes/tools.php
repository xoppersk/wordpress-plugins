<?php
/**
 * Tools → Liberty: run the audit and the switch-over from the dashboard, for
 * hosts where nobody has a terminal (same code as `wp liberty audit|migrate`).
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'admin_menu',
	static function () {
		add_management_page( __( 'Liberty switch-over', 'liberty-core' ), __( 'Liberty switch-over', 'liberty-core' ), 'manage_options', 'liberty-tools', 'liberty_render_tools' );
	}
);

function liberty_render_tools() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$out = get_transient( 'liberty_tools_out_' . get_current_user_id() );
	echo '<div class="wrap"><h1>' . esc_html__( 'Liberty switch-over', 'liberty-core' ) . '</h1>';
	echo '<p>' . esc_html__( 'Audit only reads and reports. Apply switch-over sets up the Liberty pages, the home page, redirects for retired pages and the live check. Both are safe to run more than once.', 'liberty-core' ) . '</p><p>';
	foreach ( array( 'audit' => __( 'Run audit', 'liberty-core' ), 'migrate' => __( 'Apply switch-over', 'liberty-core' ) ) as $k => $label ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block;margin-right:8px">';
		wp_nonce_field( 'liberty_tools' );
		echo '<input type="hidden" name="action" value="liberty_tools"><input type="hidden" name="run" value="' . esc_attr( $k ) . '"><button class="button' . ( 'audit' === $k ? ' button-primary' : '' ) . '">' . esc_html( $label ) . '</button></form>';
	}
	echo '</p>';
	if ( $out ) {
		echo '<h2>' . esc_html__( 'Result', 'liberty-core' ) . '</h2><textarea readonly class="large-text code" rows="30" onclick="this.select()">' . esc_textarea( $out ) . '</textarea>';
	}
	echo '</div>';
}

add_action(
	'admin_post_liberty_tools',
	static function () {
		check_admin_referer( 'liberty_tools' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'liberty-core' ), '', array( 'response' => 403 ) );
		}
		$GLOBALS['liberty_cli_out'] = array();
		$cli = new Liberty_CLI();
		'migrate' === ( $_POST['run'] ?? '' ) ? $cli->migrate() : $cli->audit();
		set_transient( 'liberty_tools_out_' . get_current_user_id(), implode( "\n", $GLOBALS['liberty_cli_out'] ), HOUR_IN_SECONDS );
		wp_safe_redirect( admin_url( 'tools.php?page=liberty-tools' ) );
		exit;
	}
);
