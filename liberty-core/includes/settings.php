<?php
/**
 * Settings screens: Newsroom (editors) and Site settings (administrators).
 * Both are generated from one schema so saving and sanitising stay consistent.
 */

defined( 'ABSPATH' ) || exit;

function liberty_settings_schema() {
	$recent = array( '' => __( '— Latest story (automatic) —', 'liberty-core' ) );
	if ( is_admin() ) {
		foreach ( get_posts( array( 'numberposts' => 40, 'post_status' => 'publish' ) ) as $p ) {
			$recent[ (string) $p->ID ] = wp_html_excerpt( liberty_title_case( $p->post_title ), 90, '…' );
		}
	}
	return array(
		'newsroom' => array(
			'title'    => __( 'Newsroom', 'liberty-core' ),
			'cap'      => 'edit_others_posts',
			'sections' => array(
				'breaking' => array(
					'title'  => __( 'Breaking news banner', 'liberty-core' ),
					'help'   => __( 'A red banner across the top of every page. Use it for the few stories that really are breaking.', 'liberty-core' ),
					'fields' => array(
						'breaking_on'    => array( 'checkbox', __( 'Show the breaking news banner', 'liberty-core' ) ),
						'breaking_post'  => array( 'select', __( 'Story', 'liberty-core' ), $recent ),
						'breaking_text'  => array( 'text', __( 'Or a short line of your own', 'liberty-core' ), __( 'Used instead of the story headline when filled in.', 'liberty-core' ) ),
						'breaking_hours' => array( 'number', __( 'Hide automatically after (hours)', 'liberty-core' ), __( 'Default 6.', 'liberty-core' ) ),
					),
				),
				'front'    => array(
					'title'  => __( 'Home page', 'liberty-core' ),
					'fields' => array(
						'lead_post' => array( 'select', __( 'Lead story', 'liberty-core' ), $recent ),
						'ntk_title' => array( 'text', __( '“Need to know” sub-title', 'liberty-core' ), __( 'For example “Saturday briefing”. Leave empty for the day name.', 'liberty-core' ) ),
						'ntk_1'     => array( 'text', __( 'Need to know 1', 'liberty-core' ), __( 'Start with a word and a full stop: “Mining. Marampa Mines has suspended operations.”', 'liberty-core' ) ),
						'ntk_2'     => array( 'text', __( 'Need to know 2', 'liberty-core' ) ),
						'ntk_3'     => array( 'text', __( 'Need to know 3', 'liberty-core' ) ),
					),
				),
				'shows'    => array(
					'title'  => __( 'Shows', 'liberty-core' ),
					'fields' => array(
						'shows' => array( 'textarea', __( 'One show per line', 'liberty-core' ), __( 'Name | label | one-line description | link (optional). Example: The Topic | Debate | One national question, argued out. | https://www.youtube.com/playlist?list=…', 'liberty-core' ) ),
					),
				),
			),
		),
		'site'     => array(
			'title'    => __( 'Site settings', 'liberty-core' ),
			'cap'      => 'manage_options',
			'sections' => array(
				'domain'  => array(
					'title'  => __( 'Live domain', 'liberty-core' ),
					'help'   => __( 'Ads, analytics and newsroom emails only run on this domain. On any other address (for example the staging copy) they stay off.', 'liberty-core' ),
					'fields' => array(
						'production_host' => array( 'text', __( 'Live domain', 'liberty-core' ), __( 'Default libertyonlinetv.com', 'liberty-core' ) ),
						'test_email'      => array( 'email', __( 'On staging, send form emails to', 'liberty-core' ) ),
					),
				),
				'ads'     => array(
					'title'  => __( 'Google AdSense and Analytics', 'liberty-core' ),
					'fields' => array(
						'adsense_client' => array( 'text', __( 'AdSense publisher ID', 'liberty-core' ), 'ca-pub-0000000000000000' ),
						'slot_leader'    => array( 'text', __( 'Wide banner slot ID', 'liberty-core' ), __( 'Leave empty to hide this ad.', 'liberty-core' ) ),
						'slot_mpu'       => array( 'text', __( 'Sidebar box slot ID', 'liberty-core' ) ),
						'slot_inline'    => array( 'text', __( 'In-article slot ID', 'liberty-core' ) ),
						'inline_after'   => array( 'number', __( 'In-article ad after paragraph', 'liberty-core' ), __( 'Default 6. Stories shorter than this get no in-article ad.', 'liberty-core' ) ),
						'ga4'            => array( 'text', __( 'Google Analytics 4 measurement ID', 'liberty-core' ), 'G-XXXXXXXXXX' ),
						'gsc'            => array( 'text', __( 'Search Console verification code', 'liberty-core' ), __( 'Only the content="…" value.', 'liberty-core' ) ),
					),
				),
				'contact' => array(
					'title'  => __( 'Contact form inboxes', 'liberty-core' ),
					'fields' => array(
						'mail_tips'        => array( 'email', __( 'News tips go to', 'liberty-core' ) ),
						'mail_corrections' => array( 'email', __( 'Corrections go to', 'liberty-core' ) ),
						'mail_ads'         => array( 'email', __( 'Advertising and press releases go to', 'liberty-core' ) ),
						'mail_support'     => array( 'email', __( 'App support goes to', 'liberty-core' ), 'support@libertyonlinetv.com' ),
					),
				),
				'links'   => array(
					'title'  => __( 'Links', 'liberty-core' ),
					'fields' => array(
						'whatsapp'   => array( 'url', __( 'WhatsApp Channel link', 'liberty-core' ), __( 'Leave empty to hide the WhatsApp buttons.', 'liberty-core' ) ),
						'app_ios'    => array( 'url', __( 'App Store link', 'liberty-core' ) ),
						'app_play'   => array( 'url', __( 'Google Play link', 'liberty-core' ) ),
						'facebook'   => array( 'url', __( 'Facebook Page', 'liberty-core' ) ),
						'youtube'    => array( 'url', __( 'YouTube channel', 'liberty-core' ) ),
						'tiktok'     => array( 'url', __( 'TikTok', 'liberty-core' ) ),
						'x'          => array( 'url', __( 'X', 'liberty-core' ) ),
						'media_kit'  => array( 'url', __( 'Media kit (PDF link)', 'liberty-core' ) ),
						'weather'    => array( 'text', __( 'Weather line in the top bar', 'liberty-core' ), __( 'Optional, for example “Freetown 27°C”. Leave empty to hide.', 'liberty-core' ) ),
						'reach'      => array( 'textarea', __( 'Audience numbers on the About page', 'liberty-core' ), __( 'One per line: number | what it counts. Example: 30k | TikTok followers', 'liberty-core' ) ),
					),
				),
			),
		),
	);
}

add_action(
	'admin_init',
	static function () {
		foreach ( array( 'newsroom', 'site' ) as $group ) {
			register_setting(
				'liberty_' . $group,
				'liberty_' . $group,
				array(
					'type'              => 'array',
					'sanitize_callback' => static function ( $input ) use ( $group ) {
						return liberty_sanitize_settings( $group, is_array( $input ) ? $input : array() );
					},
				)
			);
		}
	}
);

function liberty_sanitize_settings( $group, array $input ) {
	$schema = liberty_settings_schema()[ $group ];
	$out    = array();
	foreach ( $schema['sections'] as $section ) {
		foreach ( $section['fields'] as $key => $f ) {
			$v = $input[ $key ] ?? '';
			switch ( $f[0] ) {
				case 'checkbox':
					$out[ $key ] = $v ? '1' : '';
					break;
				case 'number':
					$out[ $key ] = '' === $v ? '' : (string) absint( $v );
					break;
				case 'email':
					$out[ $key ] = sanitize_email( $v );
					break;
				case 'url':
					$out[ $key ] = esc_url_raw( $v, array( 'http', 'https' ) );
					break;
				case 'textarea':
					$out[ $key ] = sanitize_textarea_field( $v );
					break;
				case 'select':
					$out[ $key ] = array_key_exists( (string) $v, $f[2] ) ? (string) $v : '';
					break;
				default:
					$out[ $key ] = sanitize_text_field( $v );
			}
		}
	}
	if ( 'newsroom' === $group ) {
		// Breaking news switched on now: remember when, for the automatic hide.
		$was = liberty_opt( 'newsroom', 'breaking_on' );
		$out['breaking_since'] = $out['breaking_on'] ? ( $was ? (string) liberty_opt( 'newsroom', 'breaking_since', time() ) : (string) time() ) : '';
		liberty_purge_cache();
	}
	if ( 'site' === $group ) {
		$out['adsense_client'] = preg_match( '/^ca-pub-\d{10,20}$/', $out['adsense_client'] ) ? $out['adsense_client'] : '';
		$out['ga4']            = preg_match( '/^G-[A-Z0-9]{4,14}$/', $out['ga4'] ) ? $out['ga4'] : '';
		foreach ( array( 'slot_leader', 'slot_mpu', 'slot_inline' ) as $s ) {
			$out[ $s ] = preg_replace( '/\D/', '', $out[ $s ] );
		}
		$out['production_host'] = preg_replace( '#^https?://|/.*$#', '', strtolower( $out['production_host'] ) );
	}
	return $out;
}

add_action(
	'admin_menu',
	static function () {
		$schema = liberty_settings_schema();
		add_menu_page( $schema['newsroom']['title'], $schema['newsroom']['title'], $schema['newsroom']['cap'], 'liberty-newsroom', static fn() => liberty_render_settings( 'newsroom' ), 'dashicons-megaphone', 4 );
		add_options_page( $schema['site']['title'], __( 'Liberty site', 'liberty-core' ), $schema['site']['cap'], 'liberty-site', static fn() => liberty_render_settings( 'site' ) );
	}
);

/**
 * Editors may save the Newsroom screen; WordPress only lets them through
 * options.php when this capability filter says so.
 */
add_filter( 'option_page_capability_liberty_newsroom', static fn() => 'edit_others_posts' );

function liberty_render_settings( $group ) {
	$schema = liberty_settings_schema()[ $group ];
	if ( ! current_user_can( $schema['cap'] ) ) {
		return;
	}
	$opts = get_option( 'liberty_' . $group, array() );
	echo '<div class="wrap"><h1>' . esc_html( $schema['title'] ) . '</h1>';
	settings_errors();
	echo '<form method="post" action="options.php">';
	settings_fields( 'liberty_' . $group );
	foreach ( $schema['sections'] as $section ) {
		echo '<h2 class="title">' . esc_html( $section['title'] ) . '</h2>';
		if ( ! empty( $section['help'] ) ) {
			echo '<p>' . esc_html( $section['help'] ) . '</p>';
		}
		echo '<table class="form-table" role="presentation"><tbody>';
		foreach ( $section['fields'] as $key => $f ) {
			$id   = 'liberty_' . $group . '_' . $key;
			$name = 'liberty_' . $group . '[' . $key . ']';
			$val  = $opts[ $key ] ?? '';
			echo '<tr><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $f[1] ) . '</label></th><td>';
			switch ( $f[0] ) {
				case 'checkbox':
					printf( '<input type="checkbox" id="%1$s" name="%2$s" value="1" %3$s>', esc_attr( $id ), esc_attr( $name ), checked( $val, '1', false ) );
					break;
				case 'textarea':
					printf( '<textarea class="large-text" rows="6" id="%1$s" name="%2$s">%3$s</textarea>', esc_attr( $id ), esc_attr( $name ), esc_textarea( $val ) );
					break;
				case 'select':
					printf( '<select id="%1$s" name="%2$s" style="max-width:560px">', esc_attr( $id ), esc_attr( $name ) );
					foreach ( $f[2] as $k => $label ) {
						printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( (string) $k ), selected( (string) $val, (string) $k, false ), esc_html( $label ) );
					}
					echo '</select>';
					break;
				default:
					$type = in_array( $f[0], array( 'number', 'email', 'url' ), true ) ? $f[0] : 'text';
					printf( '<input type="%4$s" class="regular-text" id="%1$s" name="%2$s" value="%3$s">', esc_attr( $id ), esc_attr( $name ), esc_attr( $val ), esc_attr( $type ) );
			}
			$help = 'select' === $f[0] ? '' : ( $f[2] ?? '' );
			if ( $help ) {
				echo '<p class="description">' . esc_html( $help ) . '</p>';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table>';
	}
	submit_button();
	echo '</form></div>';
}
