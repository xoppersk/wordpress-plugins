<?php
/**
 * Live streaming from YouTube and Facebook.
 *
 * The newsroom pastes any YouTube or Facebook link on the Go Live screen (or the
 * site notices the YouTube channel is live by itself) and the whole site switches
 * to live: header button, on-air banner, Watch page player and the app API.
 */

defined( 'ABSPATH' ) || exit;

const LIBERTY_LIVE_OPTION = 'liberty_live_state';
const LIBERTY_DEFAULT_CHANNEL = 'UCM4s49U3xS65OHAt_QybfLg';

/* ------------------------------------------------------------------ links */

/**
 * Turn any YouTube or Facebook video link into something the site can play.
 *
 * @return array{provider: string, id: string, embed: string, watch: string, thumb: string}|null
 */
function liberty_parse_stream( $url ) {
	$url = trim( (string) $url );
	if ( '' === $url ) {
		return null;
	}
	if ( ! preg_match( '#^https?://#i', $url ) ) {
		$url = 'https://' . $url;
	}
	$parts = wp_parse_url( $url );
	$host  = strtolower( preg_replace( '/^(www|m|web|mobile)\./', '', $parts['host'] ?? '' ) );
	$path  = $parts['path'] ?? '';
	parse_str( $parts['query'] ?? '', $q );

	// YouTube.
	$yt_id = '';
	if ( 'youtu.be' === $host ) {
		$yt_id = trim( $path, '/' );
	} elseif ( in_array( $host, array( 'youtube.com', 'youtube-nocookie.com', 'music.youtube.com' ), true ) ) {
		if ( ! empty( $q['v'] ) ) {
			$yt_id = $q['v'];
		} elseif ( preg_match( '#^/(?:live|embed|shorts|v)/([^/?]+)#', $path, $m ) ) {
			$yt_id = $m[1];
		}
	}
	if ( $yt_id ) {
		$yt_id = substr( preg_replace( '/[^A-Za-z0-9_-]/', '', $yt_id ), 0, 11 );
		if ( 11 !== strlen( $yt_id ) ) {
			return null;
		}
		return array(
			'provider' => 'youtube',
			'id'       => $yt_id,
			'embed'    => 'https://www.youtube-nocookie.com/embed/' . $yt_id . '?autoplay=1&rel=0&playsinline=1&modestbranding=1',
			'watch'    => 'https://www.youtube.com/watch?v=' . $yt_id,
			'thumb'    => 'https://i.ytimg.com/vi/' . $yt_id . '/hqdefault.jpg',
		);
	}

	// Facebook (Page videos and lives; fb.watch short links are expanded when saved).
	if ( in_array( $host, array( 'facebook.com', 'fb.watch', 'fb.com' ), true ) ) {
		if ( 'fb.watch' !== $host && ! preg_match( '#/(videos|watch|live|reel|share/v|share/r)\b|^/watch#', $path ) ) {
			return null;
		}
		$clean = 'fb.watch' === $host ? 'https://fb.watch' . $path : 'https://www.facebook.com' . $path;
		if ( ! empty( $q['v'] ) ) {
			$clean = add_query_arg( 'v', rawurlencode( $q['v'] ), $clean );
		}
		return array(
			'provider' => 'facebook',
			'id'       => md5( $clean ),
			'embed'    => 'https://www.facebook.com/plugins/video.php?href=' . rawurlencode( $clean ) . '&show_text=false&autoplay=true&width=1280&height=720',
			'watch'    => $clean,
			'thumb'    => '',
		);
	}
	return null;
}

/**
 * Expand fb.watch and share links to the full Facebook address, which is the
 * only form Facebook's embedded player accepts.
 */
function liberty_expand_facebook_link( $url ) {
	$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
	if ( ! str_contains( $host, 'fb.watch' ) && ! str_contains( (string) wp_parse_url( $url, PHP_URL_PATH ), '/share/' ) ) {
		return $url;
	}
	$res = wp_safe_remote_head( $url, array( 'redirection' => 5, 'timeout' => 8 ) );
	if ( is_wp_error( $res ) ) {
		return $url;
	}
	$final = $res['http_response'] ? $res['http_response']->get_response_object()->url : '';
	return $final && str_contains( $final, 'facebook.com' ) ? $final : $url;
}

/* ------------------------------------------------------------------ state */

/**
 * Current live state, or null when nothing is live. Expired sessions end here
 * too, so a forgotten stream never stays "live" all day.
 *
 * @return array|null
 */
function liberty_live() {
	$state = get_option( LIBERTY_LIVE_OPTION );
	if ( ! is_array( $state ) || empty( $state['on'] ) ) {
		return null;
	}
	if ( ! empty( $state['ends'] ) && time() > (int) $state['ends'] ) {
		liberty_live_end( 'expired' );
		return null;
	}
	return $state;
}

/**
 * Switch the site to live.
 *
 * @param array $args url (required), url2 (optional second stream), title, hours, source.
 * @return true|WP_Error
 */
function liberty_live_start( array $args ) {
	$url = liberty_expand_facebook_link( (string) ( $args['url'] ?? '' ) );
	$one = liberty_parse_stream( $url );
	if ( ! $one ) {
		return new WP_Error( 'liberty_bad_link', __( 'That is not a YouTube or Facebook video link.', 'liberty-core' ) );
	}
	$two = null;
	if ( ! empty( $args['url2'] ) ) {
		$two = liberty_parse_stream( liberty_expand_facebook_link( (string) $args['url2'] ) );
		if ( ! $two ) {
			return new WP_Error( 'liberty_bad_link2', __( 'The second link is not a YouTube or Facebook video link.', 'liberty-core' ) );
		}
		// YouTube plays on the site by default: smoother on mobile data, no Facebook login needed.
		if ( 'facebook' === $one['provider'] && 'youtube' === $two['provider'] ) {
			list( $one, $two ) = array( $two, $one );
		}
	}
	$hours = max( 1, min( 12, (int) ( $args['hours'] ?? liberty_opt( 'live', 'hours', 3 ) ) ) );
	$title = sanitize_text_field( (string) ( $args['title'] ?? '' ) );
	$title = '' !== $title ? $title : (string) liberty_opt( 'live', 'default_title', __( 'Liberty TV live', 'liberty-core' ) );

	$broadcast = liberty_save_broadcast( $one['watch'], $title, $one );
	$state     = array(
		'on'        => true,
		'stream'    => $one,
		'second'    => $two,
		'title'     => $title,
		'started'   => time(),
		'ends'      => time() + $hours * HOUR_IN_SECONDS,
		'source'    => in_array( $args['source'] ?? '', array( 'auto', 'manual', 'api' ), true ) ? $args['source'] : 'manual',
		'broadcast' => $broadcast,
		'misses'    => 0,
	);
	update_option( LIBERTY_LIVE_OPTION, $state, true );
	liberty_purge_cache();
	do_action( 'liberty_live_changed', true, $state );
	return true;
}

function liberty_live_end( $reason = 'manual' ) {
	$state = get_option( LIBERTY_LIVE_OPTION );
	if ( is_array( $state ) && ! empty( $state['on'] ) ) {
		// Remember which YouTube video was ended by hand so auto-detect does not restart it.
		if ( 'manual' === $reason && 'youtube' === ( $state['stream']['provider'] ?? '' ) ) {
			liberty_set_opt( 'live', 'dismissed', $state['stream']['id'] );
		}
		update_option( LIBERTY_LIVE_OPTION, array( 'on' => false, 'last' => $state, 'ended' => time(), 'reason' => $reason ), true );
		liberty_purge_cache();
		do_action( 'liberty_live_changed', false, $state );
	}
}

/**
 * Keep every live session as a past broadcast (the old "YouTube Live" entries),
 * reusing the entry when the same stream is restarted.
 */
function liberty_save_broadcast( $watch_url, $title, array $stream ) {
	$type     = liberty_cpt( 'live' );
	$meta_key = liberty_legacy_live_url_key();
	$existing = get_posts(
		array(
			'post_type'   => $type,
			'post_status' => 'any',
			'numberposts' => 1,
			'fields'      => 'ids',
			'meta_query'  => array( array( 'key' => 'liberty_stream_id', 'value' => $stream['id'] ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
		)
	);
	if ( $existing ) {
		return (int) $existing[0];
	}
	$id = wp_insert_post(
		array(
			'post_type'   => $type,
			'post_status' => 'publish',
			'post_title'  => $title . ' — ' . wp_date( get_option( 'date_format' ) ),
		),
		true
	);
	if ( is_wp_error( $id ) ) {
		return 0;
	}
	update_post_meta( $id, $meta_key, esc_url_raw( $watch_url ) );
	update_post_meta( $id, 'liberty_stream_id', $stream['id'] );
	return (int) $id;
}

/* ------------------------------------------------------ YouTube auto-detect */

add_filter(
	'cron_schedules',
	static function ( $s ) {
		$s['liberty_two_minutes'] = array( 'interval' => 120, 'display' => 'Every two minutes (Liberty live check)' );
		return $s;
	}
);

add_action( 'liberty_live_check', 'liberty_live_check' );

/**
 * Ask YouTube whether Liberty's channel is live right now. Uses the public
 * channel /live page, so it needs no API key and costs nothing.
 *
 * @return string|null|false Live video ID, null when not live, false when YouTube could not be reached.
 */
function liberty_youtube_live_now( $channel ) {
	$res = wp_remote_get(
		'https://www.youtube.com/channel/' . rawurlencode( $channel ) . '/live',
		array(
			'timeout'    => 12,
			'user-agent' => 'Mozilla/5.0 (compatible; LibertyOnlineTV/1.0; +' . home_url( '/' ) . ')',
			'headers'    => array( 'Accept-Language' => 'en' ),
			'cookies'    => array( new WP_Http_Cookie( array( 'name' => 'CONSENT', 'value' => 'YES+1' ) ) ),
		)
	);
	if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
		return false;
	}
	$html = wp_remote_retrieve_body( $res );
	if ( ! preg_match( '#<link rel="canonical" href="https://www\.youtube\.com/watch\?v=([A-Za-z0-9_-]{11})"#', $html, $m ) ) {
		return null;
	}
	// A scheduled (upcoming) stream also has a watch page; only count it when it is actually on air.
	return str_contains( $html, '"isLiveNow":true' ) ? $m[1] : null;
}

function liberty_live_check() {
	if ( ! liberty_opt( 'live', 'auto', '1' ) ) {
		return;
	}
	$tz    = wp_timezone();
	$hour  = (int) ( new DateTimeImmutable( 'now', $tz ) )->format( 'G' );
	$from  = (int) liberty_opt( 'live', 'from', 5 );
	$until = (int) liberty_opt( 'live', 'until', 23 );
	if ( $hour < $from || $hour > $until ) {
		return;
	}
	$video = liberty_youtube_live_now( (string) liberty_opt( 'live', 'channel', LIBERTY_DEFAULT_CHANNEL ) );
	if ( false === $video ) {
		return; // YouTube unreachable: change nothing.
	}
	$state = liberty_live();
	if ( $video && ! $state && liberty_opt( 'live', 'dismissed' ) !== $video ) {
		liberty_live_start(
			array(
				'url'    => 'https://www.youtube.com/watch?v=' . $video,
				'source' => 'auto',
				'title'  => liberty_opt( 'live', 'default_title', '' ),
			)
		);
	} elseif ( $state && 'auto' === $state['source'] ) {
		if ( $video ) {
			$state['misses'] = 0;
		} else {
			$state['misses'] = (int) $state['misses'] + 1;
		}
		// Two checks in a row without a live stream: the show has ended.
		if ( $state['misses'] >= 2 ) {
			liberty_live_end( 'auto' );
		} else {
			update_option( LIBERTY_LIVE_OPTION, $state, true );
		}
	}
}

/* ----------------------------------------------------------- the player */

/**
 * Player markup. It starts as a lightweight poster (no YouTube or Facebook
 * code is downloaded until the viewer presses play), which saves a lot of
 * mobile data. A plain link is always there in case the stream cannot play
 * inside the site.
 *
 * @param array $stream From liberty_parse_stream().
 * @param array $args   label, title, autoplay (bool: load the stream straight away on desktop), class.
 */
function liberty_player( array $stream, array $args = array() ) {
	$args  = wp_parse_args( $args, array( 'label' => '', 'title' => '', 'autoplay' => false, 'class' => '' ) );
	$where = 'youtube' === $stream['provider'] ? 'YouTube' : 'Facebook';
	ob_start();
	?>
	<div class="player lc-player <?php echo esc_attr( $args['class'] ); ?>" data-embed="<?php echo esc_url( $stream['embed'] ); ?>" data-provider="<?php echo esc_attr( $stream['provider'] ); ?>"<?php echo $args['autoplay'] ? ' data-autoplay="1"' : ''; ?>>
		<?php if ( $stream['thumb'] ) : ?>
			<img class="lc-poster" src="<?php echo esc_url( $stream['thumb'] ); ?>" alt="" loading="lazy" decoding="async">
		<?php else : ?>
			<span class="lc-poster lc-poster--fb" aria-hidden="true"><?php esc_html_e( 'Live on Facebook', 'liberty-core' ); ?></span>
		<?php endif; ?>
		<button type="button" class="pbtn lc-play" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: video title */ __( 'Play %s', 'liberty-core' ), $args['title'] ? $args['title'] : $where ) ); ?>"><i></i></button>
		<?php if ( $args['label'] ) : ?>
			<div class="bar"><?php echo wp_kses_post( $args['label'] ); ?></div>
		<?php endif; ?>
	</div>
	<p class="lc-fallback"><a href="<?php echo esc_url( $stream['watch'] ); ?>" target="_blank" rel="noopener">
		<?php echo esc_html( sprintf( /* translators: %s: YouTube or Facebook */ __( 'Not playing? Watch on %s', 'liberty-core' ), $where ) ); ?> ↗</a></p>
	<?php
	return (string) ob_get_clean();
}

/* --------------------------------------------------------- admin screens */

add_action(
	'admin_menu',
	static function () {
		$cap = 'edit_others_posts';
		add_menu_page( __( 'Go Live', 'liberty-core' ), liberty_live() ? __( '● On air', 'liberty-core' ) : __( 'Go Live', 'liberty-core' ), $cap, 'liberty-live', 'liberty_render_live_page', 'dashicons-video-alt2', 3 );
	}
);

add_action(
	'admin_bar_menu',
	static function ( WP_Admin_Bar $bar ) {
		if ( ! current_user_can( 'edit_others_posts' ) ) {
			return;
		}
		$on = (bool) liberty_live();
		$bar->add_node(
			array(
				'id'    => 'liberty-live',
				'title' => $on ? '<span style="color:#ff5a4f">● ' . esc_html__( 'On air', 'liberty-core' ) . '</span>' : esc_html__( 'Go live', 'liberty-core' ),
				'href'  => admin_url( 'admin.php?page=liberty-live' ),
			)
		);
	},
	80
);

function liberty_render_live_page() {
	if ( ! current_user_can( 'edit_others_posts' ) ) {
		return;
	}
	$state = liberty_live();
	$msg   = isset( $_GET['lc_msg'] ) ? sanitize_key( wp_unslash( $_GET['lc_msg'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$notes = array(
		'started' => array( 'success', __( 'You are live. The website now shows the stream.', 'liberty-core' ) ),
		'ended'   => array( 'success', __( 'Live ended. The website is back to normal.', 'liberty-core' ) ),
		'saved'   => array( 'success', __( 'Settings saved.', 'liberty-core' ) ),
		'badlink' => array( 'error', __( 'That link is not a YouTube or Facebook video link. Copy the link from the live video and try again.', 'liberty-core' ) ),
		'checked' => array( 'info', __( 'Checked YouTube just now.', 'liberty-core' ) ),
	);
	echo '<div class="wrap lc-live-admin"><h1>' . esc_html__( 'Go Live', 'liberty-core' ) . '</h1>';
	if ( isset( $notes[ $msg ] ) ) {
		printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $notes[ $msg ][0] ), esc_html( $notes[ $msg ][1] ) );
	}
	echo '<style>.lc-card{background:#fff;border:1px solid #dcdcde;padding:20px 24px;margin:16px 0;max-width:820px}.lc-on{border-left:6px solid #e5241b}.lc-dot{display:inline-block;width:10px;height:10px;border-radius:50%;background:#e5241b;margin-right:8px}.lc-card h2{margin-top:0}.lc-big{font-size:15px;padding:6px 18px!important;height:auto!important}.lc-grid td{padding:6px 0}</style>';

	if ( $state ) {
		$where = 'youtube' === $state['stream']['provider'] ? 'YouTube' : 'Facebook';
		echo '<div class="lc-card lc-on"><h2><span class="lc-dot"></span>' . esc_html__( 'On air now', 'liberty-core' ) . '</h2>';
		echo '<p><strong>' . esc_html( $state['title'] ) . '</strong> · ' . esc_html( $where );
		if ( ! empty( $state['second'] ) ) {
			echo ' + ' . esc_html( 'youtube' === $state['second']['provider'] ? 'YouTube' : 'Facebook' );
		}
		echo ' · ' . esc_html( 'auto' === $state['source'] ? __( 'started automatically', 'liberty-core' ) : __( 'started by hand', 'liberty-core' ) ) . '</p>';
		/* translators: 1: start time, 2: end time */
		echo '<p>' . esc_html( sprintf( __( 'Live since %1$s. Ends by itself at %2$s if nobody ends it first.', 'liberty-core' ), wp_date( 'H:i', $state['started'] ), wp_date( 'H:i', $state['ends'] ) ) ) . '</p>';
		echo '<p><a href="' . esc_url( $state['stream']['watch'] ) . '" target="_blank" rel="noopener">' . esc_html__( 'Open the stream', 'liberty-core' ) . '</a> · <a href="' . esc_url( liberty_watch_url() ) . '" target="_blank" rel="noopener">' . esc_html__( 'See it on the website', 'liberty-core' ) . '</a></p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'liberty_live_end' );
		echo '<input type="hidden" name="action" value="liberty_live_end"><button class="button button-primary lc-big" style="background:#b32d2e;border-color:#b32d2e">' . esc_html__( 'End live', 'liberty-core' ) . '</button></form></div>';
	}

	echo '<div class="lc-card"><h2>' . esc_html( $state ? __( 'Switch to a different stream', 'liberty-core' ) : __( 'Start a live stream', 'liberty-core' ) ) . '</h2>';
	echo '<p>' . esc_html__( 'Start the live on YouTube or on the Liberty Facebook Page first, copy its link, and paste it here. Any link format works.', 'liberty-core' ) . '</p>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><table class="lc-grid" role="presentation">';
	wp_nonce_field( 'liberty_live_start' );
	echo '<input type="hidden" name="action" value="liberty_live_start">';
	echo '<tr><td><label for="lc_url"><strong>' . esc_html__( 'YouTube or Facebook link', 'liberty-core' ) . '</strong></label><br><input id="lc_url" name="url" type="url" class="large-text" required placeholder="https://www.youtube.com/live/…"></td></tr>';
	echo '<tr><td><label for="lc_url2">' . esc_html__( 'Also streaming on the other platform? Paste that link too (optional)', 'liberty-core' ) . '</label><br><input id="lc_url2" name="url2" type="url" class="large-text" placeholder="https://www.facebook.com/…/videos/…"></td></tr>';
	echo '<tr><td><label for="lc_title">' . esc_html__( 'Show name', 'liberty-core' ) . '</label><br><input id="lc_title" name="title" type="text" class="regular-text" value="' . esc_attr( liberty_opt( 'live', 'default_title', 'Liberty News Update' ) ) . '"></td></tr>';
	echo '<tr><td><label for="lc_hours">' . esc_html__( 'End by itself after', 'liberty-core' ) . '</label> <input id="lc_hours" name="hours" type="number" min="1" max="12" class="small-text" value="' . esc_attr( (string) liberty_opt( 'live', 'hours', 3 ) ) . '"> ' . esc_html__( 'hours', 'liberty-core' ) . '</td></tr>';
	echo '</table><p><button class="button button-primary lc-big">' . esc_html__( 'Go live on the website', 'liberty-core' ) . '</button></p></form>';
	echo '<p class="description">' . esc_html__( 'YouTube: keep “Allow embedding” on. Facebook: the live must be on the Liberty Page (not a personal profile) and set to Public.', 'liberty-core' ) . '</p></div>';

	$auto = (bool) liberty_opt( 'live', 'auto', '1' );
	echo '<div class="lc-card"><h2>' . esc_html__( 'Automatic YouTube live', 'liberty-core' ) . '</h2>';
	echo '<p>' . esc_html__( 'When this is on, the website checks Liberty’s YouTube channel every few minutes and goes live by itself when a stream starts, then ends when it stops. Facebook lives still need the link pasted above.', 'liberty-core' ) . '</p>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><table class="lc-grid" role="presentation">';
	wp_nonce_field( 'liberty_live_settings' );
	echo '<input type="hidden" name="action" value="liberty_live_settings">';
	echo '<tr><td><label><input type="checkbox" name="auto" value="1" ' . checked( $auto, true, false ) . '> ' . esc_html__( 'Go live automatically when the YouTube channel is live', 'liberty-core' ) . '</label></td></tr>';
	echo '<tr><td><label for="lc_ch">' . esc_html__( 'YouTube channel ID', 'liberty-core' ) . '</label><br><input id="lc_ch" name="channel" class="regular-text" value="' . esc_attr( liberty_opt( 'live', 'channel', LIBERTY_DEFAULT_CHANNEL ) ) . '"></td></tr>';
	echo '<tr><td>' . esc_html__( 'Only check between', 'liberty-core' ) . ' <input name="from" type="number" min="0" max="23" class="small-text" value="' . esc_attr( (string) liberty_opt( 'live', 'from', 5 ) ) . '">:00 ' . esc_html__( 'and', 'liberty-core' ) . ' <input name="until" type="number" min="0" max="23" class="small-text" value="' . esc_attr( (string) liberty_opt( 'live', 'until', 23 ) ) . '">:59 ' . esc_html__( '(Freetown time)', 'liberty-core' ) . '</td></tr>';
	echo '<tr><td><label for="lc_dt">' . esc_html__( 'Default show name', 'liberty-core' ) . '</label><br><input id="lc_dt" name="default_title" class="regular-text" value="' . esc_attr( liberty_opt( 'live', 'default_title', 'Liberty News Update' ) ) . '"></td></tr>';
	echo '<tr><td><label for="lc_h">' . esc_html__( 'End by itself after (hours)', 'liberty-core' ) . '</label> <input id="lc_h" name="hours" type="number" min="1" max="12" class="small-text" value="' . esc_attr( (string) liberty_opt( 'live', 'hours', 3 ) ) . '"></td></tr>';
	echo '</table><p><button class="button">' . esc_html__( 'Save', 'liberty-core' ) . '</button> <button class="button" name="check_now" value="1">' . esc_html__( 'Save and check YouTube now', 'liberty-core' ) . '</button></p></form></div></div>';
}

add_action(
	'admin_post_liberty_live_start',
	static function () {
		check_admin_referer( 'liberty_live_start' );
		if ( ! current_user_can( 'edit_others_posts' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'liberty-core' ), '', array( 'response' => 403 ) );
		}
		$r = liberty_live_start(
			array(
				'url'    => esc_url_raw( wp_unslash( $_POST['url'] ?? '' ) ),
				'url2'   => esc_url_raw( wp_unslash( $_POST['url2'] ?? '' ) ),
				'title'  => sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ),
				'hours'  => absint( $_POST['hours'] ?? 3 ),
				'source' => 'manual',
			)
		);
		wp_safe_redirect( admin_url( 'admin.php?page=liberty-live&lc_msg=' . ( is_wp_error( $r ) ? 'badlink' : 'started' ) ) );
		exit;
	}
);

add_action(
	'admin_post_liberty_live_end',
	static function () {
		check_admin_referer( 'liberty_live_end' );
		if ( ! current_user_can( 'edit_others_posts' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'liberty-core' ), '', array( 'response' => 403 ) );
		}
		liberty_live_end( 'manual' );
		wp_safe_redirect( admin_url( 'admin.php?page=liberty-live&lc_msg=ended' ) );
		exit;
	}
);

add_action(
	'admin_post_liberty_live_settings',
	static function () {
		check_admin_referer( 'liberty_live_settings' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Only an administrator can change these settings.', 'liberty-core' ), '', array( 'response' => 403 ) );
		}
		$opts                  = get_option( 'liberty_live', array() );
		$opts                  = is_array( $opts ) ? $opts : array();
		$opts['auto']          = empty( $_POST['auto'] ) ? '0' : '1';
		$opts['channel']       = preg_replace( '/[^A-Za-z0-9_-]/', '', sanitize_text_field( wp_unslash( $_POST['channel'] ?? '' ) ) );
		$opts['from']          = (string) min( 23, absint( $_POST['from'] ?? 5 ) );
		$opts['until']         = (string) min( 23, absint( $_POST['until'] ?? 23 ) );
		$opts['default_title'] = sanitize_text_field( wp_unslash( $_POST['default_title'] ?? '' ) );
		$opts['hours']         = (string) max( 1, min( 12, absint( $_POST['hours'] ?? 3 ) ) );
		update_option( 'liberty_live', $opts, false );
		$msg = 'saved';
		if ( ! empty( $_POST['check_now'] ) ) {
			liberty_set_opt( 'live', 'dismissed', '' );
			liberty_live_check();
			$msg = 'checked';
		}
		wp_safe_redirect( admin_url( 'admin.php?page=liberty-live&lc_msg=' . $msg ) );
		exit;
	}
);

/**
 * Where the Watch page lives (a page using the Watch template, else /live-tv/).
 */
function liberty_watch_url() {
	$pages = get_pages( array( 'meta_key' => '_wp_page_template', 'meta_value' => 'templates/watch.php', 'number' => 1 ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	if ( $pages ) {
		return get_permalink( $pages[0] );
	}
	foreach ( array( 'live-tv', 'watch', 'live' ) as $slug ) {
		$p = get_page_by_path( $slug );
		if ( $p ) {
			return get_permalink( $p );
		}
	}
	return home_url( '/live-tv/' );
}
