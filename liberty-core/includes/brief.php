<?php
/**
 * The Liberty Morning Brief by email.
 *
 * Free to run: it sends through the website's own email server (or any SMTP
 * service the newsroom adds later, such as a free Brevo account), in small
 * batches so the host's hourly email limit is never hit.
 *
 * - Sign-ups confirm by email (double opt-in), which keeps the list clean and spam-free.
 * - Every email has a one-click unsubscribe link and header.
 * - The brief writes itself at 7am on weekdays from the Need to know items, the lead
 *   story, the newest stories and each reader's followed sections.
 * - Nothing is ever sent to real readers from the staging copy.
 */

defined( 'ABSPATH' ) || exit;

const LIBERTY_SUB = 'liberty_subscriber';

add_action(
	'init',
	static function () {
		register_post_type(
			LIBERTY_SUB,
			array(
				'labels'          => array(
					'name'          => __( 'Subscribers', 'liberty-core' ),
					'singular_name' => __( 'Subscriber', 'liberty-core' ),
					'all_items'     => __( 'Subscribers', 'liberty-core' ),
					'search_items'  => __( 'Search subscribers', 'liberty-core' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => 'liberty-brief',
				'supports'        => array( 'title' ),
				'capability_type' => 'post',
				'capabilities'    => array( 'create_posts' => 'do_not_allow' ),
				'map_meta_cap'    => true,
			)
		);
	}
);

/* ----------------------------------------------------------- subscribers */

/**
 * Find a subscriber by email or token.
 */
function liberty_find_subscriber( $field, $value ) {
	$q = get_posts(
		array(
			'post_type'   => LIBERTY_SUB,
			'post_status' => 'private',
			'numberposts' => 1,
			'fields'      => 'ids',
			'meta_query'  => array( array( 'key' => 'email' === $field ? '_lc_email' : '_lc_token', 'value' => $value ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
		)
	);
	return $q ? (int) $q[0] : 0;
}

/**
 * Add or update a subscriber and send the confirmation email when needed.
 *
 * @param string   $email
 * @param string[] $follows Followed sections and topics as "c:12" / "t:5".
 * @return string 'confirm' (email sent), 'active' (already subscribed) or 'error'.
 */
function liberty_subscribe( $email, array $follows = array() ) {
	$email = strtolower( sanitize_email( $email ) );
	if ( ! is_email( $email ) ) {
		return 'error';
	}
	$follows = array_values( array_unique( array_filter( $follows, static fn( $f ) => (bool) preg_match( '/^[ct]:\d+$/', $f ) ) ) );
	$id      = liberty_find_subscriber( 'email', $email );
	if ( ! $id ) {
		$id = wp_insert_post( array( 'post_type' => LIBERTY_SUB, 'post_status' => 'private', 'post_title' => $email ), true );
		if ( is_wp_error( $id ) ) {
			return 'error';
		}
		update_post_meta( $id, '_lc_email', $email );
		update_post_meta( $id, '_lc_token', wp_generate_password( 32, false, false ) );
		update_post_meta( $id, '_lc_status', 'pending' );
	}
	if ( $follows ) {
		$old = array_filter( explode( ',', (string) get_post_meta( $id, '_lc_follows', true ) ) );
		update_post_meta( $id, '_lc_follows', implode( ',', array_unique( array_merge( $old, $follows ) ) ) );
	}
	$status = (string) get_post_meta( $id, '_lc_status', true );
	if ( 'active' === $status ) {
		return 'active';
	}
	// Someone who unsubscribed and signs up again confirms again.
	update_post_meta( $id, '_lc_status', 'pending' );
	$last = (int) get_post_meta( $id, '_lc_confirm_sent', true );
	if ( time() - $last > 10 * MINUTE_IN_SECONDS ) {
		liberty_send_confirmation( $id );
	}
	return 'confirm';
}

function liberty_brief_link( $action, $id ) {
	return add_query_arg( array( 'lc_brief' => $action, 't' => get_post_meta( $id, '_lc_token', true ) ), home_url( '/' ) );
}

function liberty_send_confirmation( $id ) {
	$email = (string) get_post_meta( $id, '_lc_email', true );
	$link  = liberty_brief_link( 'confirm', $id );
	$body  = liberty_email_shell(
		'<h1 style="margin:0 0 12px;font:600 26px/1.2 Georgia,serif;color:#071023">' . esc_html__( 'One tap to confirm', 'liberty-core' ) . '</h1>'
		. '<p style="margin:0 0 22px;font:16px/1.55 Arial,sans-serif;color:#2B3446">' . esc_html__( 'Confirm your email and the Liberty Morning Brief will arrive every weekday at 7am: five stories, one minute.', 'liberty-core' ) . '</p>'
		. liberty_email_button( $link, __( 'Yes, send me the Morning Brief', 'liberty-core' ) )
		. '<p style="margin:22px 0 0;font:13px/1.5 Arial,sans-serif;color:#5B6475">' . esc_html__( 'Didn’t sign up? Ignore this email and you won’t hear from us again.', 'liberty-core' ) . '</p>',
		0
	);
	update_post_meta( $id, '_lc_confirm_sent', time() );
	return liberty_send_mail( $email, __( 'Confirm your Liberty Morning Brief', 'liberty-core' ), $body, liberty_html_to_text( $body ) . "\n\n" . $link );
}

/**
 * Confirm and unsubscribe links in emails.
 */
add_action(
	'template_redirect',
	static function () {
		// phpcs:disable WordPress.Security.NonceVerification -- token links from email; the token itself is the secret.
		$action = isset( $_GET['lc_brief'] ) ? sanitize_key( wp_unslash( $_GET['lc_brief'] ) ) : '';
		if ( ! $action ) {
			return;
		}
		$token = isset( $_GET['t'] ) ? preg_replace( '/[^A-Za-z0-9]/', '', sanitize_text_field( wp_unslash( $_GET['t'] ) ) ) : '';
		$id    = $token ? liberty_find_subscriber( 'token', $token ) : 0;
		nocache_headers();
		if ( 'confirm' === $action && $id ) {
			update_post_meta( $id, '_lc_status', 'active' );
			update_post_meta( $id, '_lc_confirmed', time() );
			wp_safe_redirect( add_query_arg( 'joined', 'confirmed', home_url( '/' ) ) . '#brief' );
			exit;
		}
		if ( 'unsub' === $action ) {
			$done = false;
			// One-click unsubscribe (mail apps send a POST; the email link opens a page with a button).
			if ( $id && 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
				update_post_meta( $id, '_lc_status', 'unsubscribed' );
				update_post_meta( $id, '_lc_unsubscribed', time() );
				$done = true;
			}
			if ( $done && ! empty( $_POST['List-Unsubscribe'] ) ) {
				status_header( 200 );
				exit;
			}
			$title = $done ? __( 'You’re unsubscribed', 'liberty-core' ) : __( 'Stop the Morning Brief?', 'liberty-core' );
			$html  = '<div style="max-width:520px;margin:12vh auto;padding:32px;font-family:Arial,sans-serif;background:#fff;border-top:4px solid #F87012"><h1 style="font:600 28px Georgia,serif;margin:0 0 12px;color:#071023">' . esc_html( $title ) . '</h1>';
			if ( ! $id ) {
				$html .= '<p>' . esc_html__( 'This link has expired. If you still receive the brief, reply to it and we will remove you.', 'liberty-core' ) . '</p>';
			} elseif ( $done ) {
				$html .= '<p>' . esc_html__( 'You will not receive any more Morning Brief emails. You can sign up again on the website at any time.', 'liberty-core' ) . '</p>';
			} else {
				$html .= '<p>' . esc_html__( 'Press the button and we will stop sending you the Morning Brief.', 'liberty-core' ) . '</p><form method="post"><button style="margin-top:12px;padding:14px 22px;border:0;background:#071023;color:#fff;font:700 15px Arial,sans-serif;cursor:pointer">' . esc_html__( 'Unsubscribe', 'liberty-core' ) . '</button></form>';
			}
			$html .= '<p style="margin-top:24px"><a href="' . esc_url( home_url( '/' ) ) . '" style="color:#071023">' . esc_html__( 'Go to Liberty Online TV', 'liberty-core' ) . '</a></p></div>';
			status_header( 200 );
			echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><title>' . esc_html( $title ) . '</title></head><body style="margin:0;background:#FAF8F4">' . $html . '</body></html>'; // phpcs:ignore WordPress.Security.EscapeOutput -- built with escaping.
			exit;
		}
		// phpcs:enable
	}
);

/* --------------------------------------------------------------- email */

function liberty_email_button( $url, $label ) {
	return '<table role="presentation" cellpadding="0" cellspacing="0"><tr><td style="background:#F87012"><a href="' . esc_url( $url ) . '" style="display:inline-block;padding:14px 22px;font:700 15px Arial,sans-serif;color:#ffffff;text-decoration:none">' . esc_html( $label ) . '</a></td></tr></table>';
}

/**
 * Wrap content in the branded email layout (tables and inline styles, which is
 * what email apps understand).
 */
function liberty_email_shell( $inner, $subscriber_id = 0, $preheader = '' ) {
	$logo    = function_exists( 'liberty_logo_url' ) ? liberty_logo_url( 'white' ) : '';
	$address = (string) liberty_opt( 'brief', 'address', '' );
	$foot    = esc_html__( 'You get this because you signed up for the Liberty Morning Brief on libertyonlinetv.com.', 'liberty-core' );
	if ( $subscriber_id ) {
		$foot .= ' <a href="' . esc_url( liberty_brief_link( 'unsub', $subscriber_id ) ) . '" style="color:#5B6475">' . esc_html__( 'Unsubscribe', 'liberty-core' ) . '</a>';
	}
	return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="color-scheme" content="light"></head>'
		. '<body style="margin:0;padding:0;background:#F1EEE7">'
		. ( $preheader ? '<div style="display:none;max-height:0;overflow:hidden">' . esc_html( $preheader ) . '</div>' : '' )
		. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F1EEE7"><tr><td align="center" style="padding:20px 10px">'
		. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#FFFFFF">'
		. '<tr><td style="background:#071023;padding:18px 24px">' . ( $logo ? '<img src="' . esc_url( $logo ) . '" alt="Liberty Online TV" width="150" style="display:block;height:auto;border:0">' : '<b style="color:#fff;font:700 18px Arial">Liberty Online TV</b>' ) . '</td></tr>'
		. '<tr><td style="padding:28px 24px 8px">' . $inner . '</td></tr>'
		. '<tr><td style="padding:22px 24px;border-top:1px solid #E3DED4;font:12px/1.5 Arial,sans-serif;color:#5B6475">' . $foot . ( $address ? '<br>' . esc_html( $address ) : '' ) . '</td></tr>'
		. '</table></td></tr></table></body></html>';
}

function liberty_html_to_text( $html ) {
	$html = preg_replace( '#<(br|/p|/h\d|/tr|/li)[^>]*>#i', "\n", $html );
	$html = preg_replace( '#<a [^>]*href="([^"]+)"[^>]*>(.*?)</a>#i', '$2 ($1)', $html );
	return trim( preg_replace( "/\n{3,}/", "\n\n", html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES ) ) );
}

/**
 * Send one email. Uses SMTP when one is set up under Morning Brief → Settings,
 * otherwise the website's own mail server.
 */
function liberty_send_mail( $to, $subject, $html, $text = '', array $extra_headers = array() ) {
	$from_name  = (string) liberty_opt( 'brief', 'from_name', 'Liberty Online TV' );
	$from_email = (string) liberty_opt( 'brief', 'from_email', '' );
	$headers    = array( 'Content-Type: text/html; charset=UTF-8' );
	if ( is_email( $from_email ) ) {
		$headers[] = 'From: ' . $from_name . ' <' . $from_email . '>';
	}
	$alt = static function ( $mailer ) use ( $text ) {
		$mailer->AltBody = $text; // phpcs:ignore WordPress.NamingConventions.ValidVariableName
	};
	add_action( 'phpmailer_init', $alt );
	$ok = wp_mail( $to, $subject, $html, array_merge( $headers, $extra_headers ) );
	remove_action( 'phpmailer_init', $alt );
	return $ok;
}

add_action(
	'phpmailer_init',
	static function ( $mailer ) {
		$host = (string) liberty_opt( 'brief', 'smtp_host', '' );
		if ( ! $host ) {
			return;
		}
		$pass = defined( 'LIBERTY_SMTP_PASS' ) ? LIBERTY_SMTP_PASS : (string) liberty_opt( 'brief', 'smtp_pass', '' );
		// phpcs:disable WordPress.NamingConventions.ValidVariableName -- PHPMailer properties.
		$mailer->isSMTP();
		$mailer->Host       = $host;
		$mailer->Port       = (int) liberty_opt( 'brief', 'smtp_port', 587 );
		$mailer->SMTPAuth   = '' !== (string) liberty_opt( 'brief', 'smtp_user', '' );
		$mailer->Username   = (string) liberty_opt( 'brief', 'smtp_user', '' );
		$mailer->Password   = $pass;
		$mailer->SMTPSecure = 465 === $mailer->Port ? 'ssl' : 'tls';
		// phpcs:enable
	}
);

/* ------------------------------------------------------------- compose */

/**
 * Stories for today's brief.
 *
 * @return array{lead: int, top: int[]}
 */
function liberty_brief_stories() {
	$lead  = liberty_lead_story_id();
	$fresh = get_posts(
		array(
			'numberposts'         => 4,
			'fields'              => 'ids',
			'post__not_in'        => array( $lead ),
			'ignore_sticky_posts' => true,
			'date_query'          => array( array( 'after' => '36 hours ago' ) ),
		)
	);
	if ( count( $fresh ) < 4 ) {
		$fresh = array_merge( $fresh, get_posts( array( 'numberposts' => 4 - count( $fresh ), 'fields' => 'ids', 'post__not_in' => array_merge( array( $lead ), $fresh ), 'ignore_sticky_posts' => true ) ) );
	}
	return array( 'lead' => $lead, 'top' => array_map( 'intval', $fresh ) );
}

function liberty_brief_story_row( $id, $big = false ) {
	$cats  = get_the_category( $id );
	$label = $cats ? $cats[0]->name : '';
	$sum   = (string) get_post_meta( $id, 'liberty_standfirst', true );
	$sum   = $sum ? $sum : trim( wp_strip_all_tags( (string) get_post_meta( $id, 'ruby_tagline', true ) ) );
	$sum   = $sum ? $sum : wp_html_excerpt( wp_strip_all_tags( get_the_excerpt( $id ) ), 160, '…' );
	$img   = $big ? get_the_post_thumbnail_url( $id, 'liberty-card' ) : '';
	return '<tr><td style="padding:0 0 22px">'
		. ( $img ? '<a href="' . esc_url( get_permalink( $id ) ) . '"><img src="' . esc_url( $img ) . '" alt="" width="552" style="display:block;width:100%;height:auto;border:0;margin:0 0 14px"></a>' : '' )
		. '<p style="margin:0 0 6px;font:700 11px/1 Arial,sans-serif;letter-spacing:1px;text-transform:uppercase;color:#E05F08">' . esc_html( $label ) . '</p>'
		. '<a href="' . esc_url( get_permalink( $id ) ) . '" style="font:600 ' . ( $big ? '24px/1.2' : '19px/1.25' ) . ' Georgia,serif;color:#071023;text-decoration:none">' . esc_html( liberty_title_case( html_entity_decode( get_the_title( $id ), ENT_QUOTES ) ) ) . '</a>'
		. ( $sum ? '<p style="margin:8px 0 0;font:15px/1.5 Arial,sans-serif;color:#2B3446">' . esc_html( $sum ) . '</p>' : '' )
		. '</td></tr>';
}

/**
 * Build the brief, optionally personalised with a subscriber's followed sections.
 *
 * @return array{subject: string, html: string, text: string}
 */
function liberty_brief_compose( $subscriber_id = 0 ) {
	$stories  = liberty_brief_stories();
	$briefing = liberty_briefing();
	$used     = array_merge( array( $stories['lead'] ), $stories['top'] );
	$prefix   = (string) liberty_opt( 'brief', 'subject', __( 'Morning Brief', 'liberty-core' ) );
	$lead_t   = $stories['lead'] ? liberty_title_case( html_entity_decode( get_the_title( $stories['lead'] ), ENT_QUOTES ) ) : '';
	$subject  = $prefix . ( $lead_t ? ': ' . $lead_t : '' );

	$in  = '<p style="margin:0 0 4px;font:600 12px/1 Arial,sans-serif;letter-spacing:1px;text-transform:uppercase;color:#5B6475">' . esc_html( wp_date( 'l j F Y' ) ) . '</p>';
	$in .= '<h1 style="margin:0 0 22px;font:600 28px/1.15 Georgia,serif;color:#071023">' . esc_html__( 'Good morning. Here’s what you need to know.', 'liberty-core' ) . '</h1>';
	if ( $briefing['items'] ) {
		$in .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 26px;background:#FAF8F4;border-left:4px solid #F87012"><tr><td style="padding:16px 18px">';
		foreach ( $briefing['items'] as $i => $item ) {
			$in .= '<p style="margin:' . ( $i ? '10px' : '0' ) . ' 0 0;font:15px/1.5 Arial,sans-serif;color:#2B3446"><b style="color:#E05F08">' . ( $i + 1 ) . '.</b> ' . ( $item['label'] ? '<b style="color:#071023">' . esc_html( $item['label'] ) . '.</b> ' : '' ) . esc_html( $item['text'] ) . '</p>';
		}
		$in .= '</td></tr></table>';
	}
	$in .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0">';
	if ( $stories['lead'] ) {
		$in .= liberty_brief_story_row( $stories['lead'], true );
	}
	foreach ( $stories['top'] as $id ) {
		$in .= liberty_brief_story_row( $id );
	}
	$in .= '</table>';

	// Personal part: newest stories from the sections and topics this reader follows.
	if ( $subscriber_id ) {
		$follows = array_filter( explode( ',', (string) get_post_meta( $subscriber_id, '_lc_follows', true ) ) );
		$cats    = array();
		$tags    = array();
		foreach ( $follows as $f ) {
			list( $type, $tid ) = explode( ':', $f );
			if ( 'c' === $type ) {
				$cats[] = (int) $tid;
			} else {
				$tags[] = (int) $tid;
			}
		}
		if ( $cats || $tags ) {
			$tax = array( 'relation' => 'OR' );
			if ( $cats ) {
				$tax[] = array( 'taxonomy' => 'category', 'terms' => $cats );
			}
			if ( $tags ) {
				$tax[] = array( 'taxonomy' => 'post_tag', 'terms' => $tags );
			}
			$mine = get_posts( array( 'numberposts' => 3, 'fields' => 'ids', 'post__not_in' => $used, 'tax_query' => $tax, 'date_query' => array( array( 'after' => '7 days ago' ) ), 'ignore_sticky_posts' => true ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
			if ( $mine ) {
				$in .= '<p style="margin:6px 0 16px;padding-top:14px;border-top:2px solid #071023;font:800 12px/1 Arial,sans-serif;letter-spacing:1.5px;text-transform:uppercase;color:#E05F08">' . esc_html__( 'From what you follow', 'liberty-core' ) . '</p><table role="presentation" width="100%" cellpadding="0" cellspacing="0">';
				foreach ( $mine as $id ) {
					$in .= liberty_brief_story_row( $id );
				}
				$in .= '</table>';
			}
		}
	}

	$watch = function_exists( 'liberty_watch_url' ) ? liberty_watch_url() : home_url( '/' );
	$in   .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:6px 0 22px;background:#071023"><tr><td style="padding:18px 20px"><p style="margin:0 0 4px;font:700 11px/1 Arial,sans-serif;letter-spacing:1px;text-transform:uppercase;color:#FFB27A">' . esc_html__( 'Liberty TV', 'liberty-core' ) . '</p><a href="' . esc_url( $watch ) . '" style="font:600 19px/1.3 Georgia,serif;color:#ffffff;text-decoration:none">' . esc_html__( 'Watch the latest bulletin and live shows →', 'liberty-core' ) . '</a></td></tr></table>';

	$html = liberty_email_shell( $in, $subscriber_id, $lead_t );
	return array(
		'subject' => $subject,
		'html'    => $html,
		'text'    => liberty_html_to_text( $html ),
	);
}

/**
 * The same brief as a WhatsApp message, for posting to the WhatsApp Channel.
 */
function liberty_brief_whatsapp() {
	$stories  = liberty_brief_stories();
	$briefing = liberty_briefing();
	$lines    = array( '*' . __( 'Liberty Morning Brief', 'liberty-core' ) . '* · ' . wp_date( 'l j F' ), '' );
	foreach ( $briefing['items'] as $i => $item ) {
		$lines[] = ( $i + 1 ) . '. ' . ( $item['label'] ? '*' . $item['label'] . '.* ' : '' ) . $item['text'];
	}
	if ( $briefing['items'] ) {
		$lines[] = '';
	}
	foreach ( array_merge( array( $stories['lead'] ), $stories['top'] ) as $id ) {
		if ( $id ) {
			$lines[] = '▪️ ' . liberty_title_case( html_entity_decode( get_the_title( $id ), ENT_QUOTES ) );
			$lines[] = get_permalink( $id );
		}
	}
	$lines[] = '';
	$lines[] = __( 'Watch Liberty TV:', 'liberty-core' ) . ' ' . ( function_exists( 'liberty_watch_url' ) ? liberty_watch_url() : home_url( '/' ) );
	return implode( "\n", $lines );
}

/* ------------------------------------------------------------- sending */

add_filter(
	'cron_schedules',
	static function ( $s ) {
		$s['liberty_five_minutes'] = array( 'interval' => 300, 'display' => 'Every five minutes (Liberty Morning Brief)' );
		return $s;
	}
);

add_action(
	'init',
	static function () {
		if ( ! wp_next_scheduled( 'liberty_brief_tick' ) ) {
			wp_schedule_event( time() + 120, 'liberty_five_minutes', 'liberty_brief_tick' );
		}
	}
);

add_action( 'liberty_brief_tick', 'liberty_brief_tick' );

/**
 * Every five minutes: start today's brief when it is time, then send the next batch.
 */
function liberty_brief_tick() {
	$job = get_option( 'liberty_brief_job' );
	if ( ( ! is_array( $job ) || 'done' === $job['status'] ) && liberty_brief_due() ) {
		liberty_brief_start( 'auto' );
		$job = get_option( 'liberty_brief_job' );
	}
	if ( is_array( $job ) && 'sending' === $job['status'] ) {
		liberty_brief_send_batch();
	}
}

function liberty_brief_due() {
	if ( ! liberty_opt( 'brief', 'enabled' ) || ! liberty_is_production() ) {
		return false;
	}
	$now  = new DateTimeImmutable( 'now', wp_timezone() );
	$days = (string) liberty_opt( 'brief', 'days', '12345' );
	if ( ! str_contains( $days, $now->format( 'N' ) ) ) {
		return false;
	}
	$time = (string) liberty_opt( 'brief', 'time', '07:00' );
	if ( $now->format( 'H:i' ) < $time ) {
		return false;
	}
	return liberty_opt( 'brief', 'last_date' ) !== $now->format( 'Y-m-d' );
}

/**
 * Queue today's brief for every confirmed subscriber.
 */
function liberty_brief_start( $source = 'manual' ) {
	$ids = get_posts(
		array(
			'post_type'   => LIBERTY_SUB,
			'post_status' => 'private',
			'numberposts' => -1,
			'fields'      => 'ids',
			'meta_query'  => array( array( 'key' => '_lc_status', 'value' => 'active' ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
		)
	);
	$job = array(
		'status'  => 'sending',
		'queue'   => array_map( 'intval', $ids ),
		'total'   => count( $ids ),
		'sent'    => 0,
		'failed'  => 0,
		'started' => time(),
		'source'  => $source,
	);
	update_option( 'liberty_brief_job', $job, false );
	liberty_set_opt( 'brief', 'last_date', wp_date( 'Y-m-d' ) );
	return $job;
}

/**
 * Send the next batch, sized so the hourly limit is never exceeded.
 */
function liberty_brief_send_batch() {
	$job = get_option( 'liberty_brief_job' );
	if ( ! is_array( $job ) || 'sending' !== $job['status'] ) {
		return;
	}
	$per_hour = max( 12, (int) liberty_opt( 'brief', 'per_hour', 200 ) );
	$batch    = (int) ceil( $per_hour / 12 );
	for ( $i = 0; $i < $batch && $job['queue']; $i++ ) {
		$id = array_shift( $job['queue'] );
		if ( 'active' !== get_post_meta( $id, '_lc_status', true ) ) {
			continue;
		}
		$email = (string) get_post_meta( $id, '_lc_email', true );
		$brief = liberty_brief_compose( $id );
		$unsub = liberty_brief_link( 'unsub', $id );
		$to    = liberty_is_production() ? $email : liberty_mail_to( '' ); // staging never emails readers
		$ok    = liberty_send_mail( $to, $brief['subject'], $brief['html'], $brief['text'], array( 'List-Unsubscribe: <' . $unsub . '>', 'List-Unsubscribe-Post: List-Unsubscribe=One-Click' ) );
		$ok ? $job['sent']++ : $job['failed']++;
		if ( ! liberty_is_production() ) {
			$job['queue'] = array(); // one copy to the test inbox is enough
		}
	}
	if ( ! $job['queue'] ) {
		$job['status']   = 'done';
		$job['finished'] = time();
	}
	update_option( 'liberty_brief_job', $job, false );
}

/* ---------------------------------------------------------- admin page */

add_action(
	'admin_menu',
	static function () {
		add_menu_page( __( 'Morning Brief', 'liberty-core' ), __( 'Morning Brief', 'liberty-core' ), 'edit_others_posts', 'liberty-brief', 'liberty_render_brief_page', 'dashicons-email-alt2', 5 );
		add_submenu_page( 'liberty-brief', __( 'Morning Brief', 'liberty-core' ), __( 'Send and settings', 'liberty-core' ), 'edit_others_posts', 'liberty-brief', 'liberty_render_brief_page' );
	}
);

function liberty_count_subscribers( $status ) {
	$q = new WP_Query(
		array(
			'post_type'      => LIBERTY_SUB,
			'post_status'    => 'private',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_query'     => array( array( 'key' => '_lc_status', 'value' => $status ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
		)
	);
	return (int) $q->found_posts;
}

function liberty_render_brief_page() {
	if ( ! current_user_can( 'edit_others_posts' ) ) {
		return;
	}
	$job    = get_option( 'liberty_brief_job' );
	$msg    = isset( $_GET['lc_msg'] ) ? sanitize_key( wp_unslash( $_GET['lc_msg'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$notes  = array(
		'test'    => __( 'Test brief sent. Check that inbox (and its spam folder).', 'liberty-core' ),
		'testbad' => __( 'Enter a valid email address for the test.', 'liberty-core' ),
		'started' => __( 'Sending has started. It goes out in small batches every five minutes.', 'liberty-core' ),
		'staging' => __( 'This is not the live site, so the brief went only to the staging test address.', 'liberty-core' ),
		'paused'  => __( 'Sending paused.', 'liberty-core' ),
		'resumed' => __( 'Sending resumed.', 'liberty-core' ),
		'saved'   => __( 'Settings saved.', 'liberty-core' ),
	);
	$admin  = current_user_can( 'manage_options' );
	$form   = static function ( $action, $label, $class = 'button', $fields = '' ) {
		return '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block;margin:0 6px 6px 0">' . wp_nonce_field( 'liberty_brief', '_wpnonce', true, false ) . '<input type="hidden" name="action" value="liberty_brief_' . esc_attr( $action ) . '">' . $fields . '<button class="' . esc_attr( $class ) . '">' . esc_html( $label ) . '</button></form>';
	};
	echo '<div class="wrap"><h1>' . esc_html__( 'Morning Brief', 'liberty-core' ) . '</h1>';
	if ( isset( $notes[ $msg ] ) ) {
		echo '<div class="notice notice-' . ( in_array( $msg, array( 'testbad' ), true ) ? 'error' : 'success' ) . ' is-dismissible"><p>' . esc_html( $notes[ $msg ] ) . '</p></div>';
	}
	if ( ! liberty_is_production() ) {
		echo '<div class="notice notice-info"><p>' . esc_html__( 'Staging copy: the brief never goes to real readers from here, only to the test address in Settings → Liberty site.', 'liberty-core' ) . '</p></div>';
	}
	echo '<style>.lc-card{background:#fff;border:1px solid #dcdcde;padding:18px 22px;margin:16px 0;max-width:980px}.lc-card h2{margin-top:0}.lc-stats{display:flex;gap:28px;flex-wrap:wrap}.lc-stats b{display:block;font-size:26px;line-height:1.1}</style>';

	// Status.
	$next = liberty_opt( 'brief', 'enabled' ) ? sprintf( /* translators: 1: days, 2: time */ __( 'Next: %1$s at %2$s (Freetown time)', 'liberty-core' ), liberty_brief_days_label(), liberty_opt( 'brief', 'time', '07:00' ) ) : __( 'Automatic sending is off.', 'liberty-core' );
	echo '<div class="lc-card"><div class="lc-stats">';
	echo '<span><b>' . (int) liberty_count_subscribers( 'active' ) . '</b>' . esc_html__( 'confirmed readers', 'liberty-core' ) . '</span>';
	echo '<span><b>' . (int) liberty_count_subscribers( 'pending' ) . '</b>' . esc_html__( 'waiting to confirm', 'liberty-core' ) . '</span>';
	echo '<span><b>' . (int) liberty_count_subscribers( 'unsubscribed' ) . '</b>' . esc_html__( 'unsubscribed', 'liberty-core' ) . '</span></div>';
	echo '<p>' . esc_html( $next ) . '</p>';
	if ( is_array( $job ) ) {
		/* translators: 1: sent, 2: total, 3: failed */
		$line = sprintf( __( 'Last brief: %1$d of %2$d sent, %3$d failed.', 'liberty-core' ), $job['sent'], $job['total'], $job['failed'] );
		echo '<p><strong>' . esc_html( 'sending' === $job['status'] ? __( 'Sending now.', 'liberty-core' ) : ( 'paused' === $job['status'] ? __( 'Paused.', 'liberty-core' ) : '' ) ) . '</strong> ' . esc_html( $line ) . ' ' . esc_html( wp_date( 'j M H:i', $job['started'] ) ) . '</p>';
	}
	echo '<p>';
	echo $form( 'start', __( 'Send today’s brief now', 'liberty-core' ), 'button button-primary' ); // phpcs:ignore WordPress.Security.EscapeOutput
	if ( is_array( $job ) && 'sending' === $job['status'] ) {
		echo $form( 'pause', __( 'Pause sending', 'liberty-core' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
	} elseif ( is_array( $job ) && 'paused' === $job['status'] ) {
		echo $form( 'resume', __( 'Resume sending', 'liberty-core' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo $form( 'test', __( 'Send a test to', 'liberty-core' ), 'button', '<input type="email" name="to" required placeholder="you@example.com" value="' . esc_attr( wp_get_current_user()->user_email ) . '" style="margin-right:6px"> ' ); // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=liberty_brief_export' ), 'liberty_brief' ) ) . '">' . esc_html__( 'Download subscribers (CSV)', 'liberty-core' ) . '</a> ';
	echo '<a class="button" href="' . esc_url( admin_url( 'edit.php?post_type=' . LIBERTY_SUB ) ) . '">' . esc_html__( 'Manage subscribers', 'liberty-core' ) . '</a></p></div>';

	// Preview and WhatsApp version.
	echo '<div class="lc-card"><h2>' . esc_html__( 'Today’s brief', 'liberty-core' ) . '</h2><p>' . esc_html__( 'Written automatically from the Need to know items, the lead story and the newest stories. Change those on the Newsroom screen and this updates.', 'liberty-core' ) . '</p>';
	echo '<iframe title="' . esc_attr__( 'Preview', 'liberty-core' ) . '" src="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=liberty_brief_preview' ), 'liberty_brief' ) ) . '" style="width:100%;max-width:660px;height:640px;border:1px solid #dcdcde;background:#F1EEE7"></iframe>';
	echo '<h3>' . esc_html__( 'WhatsApp Channel version', 'liberty-core' ) . '</h3><p>' . esc_html__( 'Copy this and post it in the Liberty WhatsApp Channel.', 'liberty-core' ) . '</p>';
	echo '<textarea id="lc-wa" readonly rows="12" class="large-text code" style="max-width:660px">' . esc_textarea( liberty_brief_whatsapp() ) . '</textarea><p><button type="button" class="button" onclick="var t=document.getElementById(\'lc-wa\');t.select();navigator.clipboard&&navigator.clipboard.writeText(t.value);this.textContent=\'' . esc_js( __( 'Copied', 'liberty-core' ) ) . '\'">' . esc_html__( 'Copy WhatsApp text', 'liberty-core' ) . '</button></p></div>';

	// Settings (administrators).
	if ( $admin ) {
		$o = static fn( $k, $d = '' ) => esc_attr( (string) liberty_opt( 'brief', $k, $d ) );
		echo '<div class="lc-card"><h2>' . esc_html__( 'Settings', 'liberty-core' ) . '</h2><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'liberty_brief' );
		echo '<input type="hidden" name="action" value="liberty_brief_settings"><table class="form-table" role="presentation"><tbody>';
		echo '<tr><th>' . esc_html__( 'Send automatically', 'liberty-core' ) . '</th><td><label><input type="checkbox" name="enabled" value="1" ' . checked( (bool) liberty_opt( 'brief', 'enabled' ), true, false ) . '> ' . esc_html__( 'Send the brief on the days and time below', 'liberty-core' ) . '</label></td></tr>';
		$days = (string) liberty_opt( 'brief', 'days', '12345' );
		echo '<tr><th>' . esc_html__( 'Days', 'liberty-core' ) . '</th><td>';
		foreach ( array( 1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun' ) as $n => $d ) {
			echo '<label style="margin-right:12px"><input type="checkbox" name="days[]" value="' . (int) $n . '" ' . checked( str_contains( $days, (string) $n ), true, false ) . '> ' . esc_html( $d ) . '</label>';
		}
		echo '</td></tr>';
		echo '<tr><th><label for="b_time">' . esc_html__( 'Time (Freetown)', 'liberty-core' ) . '</label></th><td><input id="b_time" type="time" name="time" value="' . $o( 'time', '07:00' ) . '"></td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<tr><th><label for="b_sub">' . esc_html__( 'Subject starts with', 'liberty-core' ) . '</label></th><td><input id="b_sub" class="regular-text" name="subject" value="' . $o( 'subject', 'Morning Brief' ) . '"></td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<tr><th><label for="b_fn">' . esc_html__( 'From name', 'liberty-core' ) . '</label></th><td><input id="b_fn" class="regular-text" name="from_name" value="' . $o( 'from_name', 'Liberty Online TV' ) . '"></td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<tr><th><label for="b_fe">' . esc_html__( 'From email', 'liberty-core' ) . '</label></th><td><input id="b_fe" type="email" class="regular-text" name="from_email" value="' . $o( 'from_email' ) . '" placeholder="brief@libertyonlinetv.com"><p class="description">' . esc_html__( 'Use an address on libertyonlinetv.com so emails are not marked as spam.', 'liberty-core' ) . '</p></td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<tr><th><label for="b_ph">' . esc_html__( 'Emails per hour', 'liberty-core' ) . '</label></th><td><input id="b_ph" type="number" min="12" max="5000" class="small-text" name="per_hour" value="' . $o( 'per_hour', '200' ) . '"><p class="description">' . esc_html__( 'Keep this under your email host’s hourly limit (many shared hosts allow 200–500).', 'liberty-core' ) . '</p></td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<tr><th><label for="b_ad">' . esc_html__( 'Postal address in the footer', 'liberty-core' ) . '</label></th><td><input id="b_ad" class="regular-text" name="address" value="' . $o( 'address' ) . '"><p class="description">' . esc_html__( 'Email providers expect a contact address in newsletters.', 'liberty-core' ) . '</p></td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<tr><th colspan="2"><h3 style="margin:8px 0 0">' . esc_html__( 'SMTP (optional)', 'liberty-core' ) . '</h3><p class="description" style="font-weight:400">' . esc_html__( 'Leave empty to send through the website’s own mail server. For better delivery, a free Brevo account (300 emails a day) works here.', 'liberty-core' ) . '</p></th></tr>';
		echo '<tr><th><label for="b_sh">' . esc_html__( 'SMTP server', 'liberty-core' ) . '</label></th><td><input id="b_sh" class="regular-text" name="smtp_host" value="' . $o( 'smtp_host' ) . '" placeholder="smtp-relay.brevo.com"> <input type="number" class="small-text" name="smtp_port" value="' . $o( 'smtp_port', '587' ) . '" aria-label="' . esc_attr__( 'Port', 'liberty-core' ) . '"></td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<tr><th><label for="b_su">' . esc_html__( 'SMTP login', 'liberty-core' ) . '</label></th><td><input id="b_su" class="regular-text" name="smtp_user" value="' . $o( 'smtp_user' ) . '" autocomplete="off"></td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<tr><th><label for="b_sp">' . esc_html__( 'SMTP password', 'liberty-core' ) . '</label></th><td>' . ( defined( 'LIBERTY_SMTP_PASS' ) ? esc_html__( 'Set in wp-config.php.', 'liberty-core' ) : '<input id="b_sp" type="password" class="regular-text" name="smtp_pass" value="" autocomplete="new-password" placeholder="' . esc_attr( liberty_opt( 'brief', 'smtp_pass' ) ? __( '(saved — leave empty to keep)', 'liberty-core' ) : '' ) . '"><p class="description">' . esc_html__( 'Safer: put define( \'LIBERTY_SMTP_PASS\', \'…\' ); in wp-config.php instead.', 'liberty-core' ) . '</p>' ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '</tbody></table>';
		submit_button( __( 'Save settings', 'liberty-core' ) );
		echo '</form></div>';
	}
	echo '</div>';
}

function liberty_brief_days_label() {
	$days = (string) liberty_opt( 'brief', 'days', '12345' );
	if ( '12345' === $days ) {
		return __( 'weekdays', 'liberty-core' );
	}
	if ( '1234567' === $days ) {
		return __( 'every day', 'liberty-core' );
	}
	$names = array( 1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun' );
	return implode( ', ', array_map( static fn( $d ) => $names[ (int) $d ], str_split( $days ) ) );
}

function liberty_brief_guard( $cap = 'edit_others_posts' ) {
	check_admin_referer( 'liberty_brief' );
	if ( ! current_user_can( $cap ) ) {
		wp_die( esc_html__( 'You are not allowed to do that.', 'liberty-core' ), '', array( 'response' => 403 ) );
	}
}

function liberty_brief_back( $msg ) {
	wp_safe_redirect( admin_url( 'admin.php?page=liberty-brief&lc_msg=' . $msg ) );
	exit;
}

add_action(
	'admin_post_liberty_brief_test',
	static function () {
		liberty_brief_guard();
		$to = sanitize_email( wp_unslash( $_POST['to'] ?? '' ) );
		if ( ! is_email( $to ) ) {
			liberty_brief_back( 'testbad' );
		}
		$brief = liberty_brief_compose( 0 );
		liberty_send_mail( $to, '[TEST] ' . $brief['subject'], $brief['html'], $brief['text'] );
		liberty_brief_back( 'test' );
	}
);
add_action(
	'admin_post_liberty_brief_start',
	static function () {
		liberty_brief_guard();
		liberty_brief_start( 'manual' );
		liberty_brief_send_batch();
		liberty_brief_back( liberty_is_production() ? 'started' : 'staging' );
	}
);
add_action(
	'admin_post_liberty_brief_pause',
	static function () {
		liberty_brief_guard();
		$job = get_option( 'liberty_brief_job' );
		if ( is_array( $job ) ) {
			$job['status'] = 'paused';
			update_option( 'liberty_brief_job', $job, false );
		}
		liberty_brief_back( 'paused' );
	}
);
add_action(
	'admin_post_liberty_brief_resume',
	static function () {
		liberty_brief_guard();
		$job = get_option( 'liberty_brief_job' );
		if ( is_array( $job ) ) {
			$job['status'] = $job['queue'] ? 'sending' : 'done';
			update_option( 'liberty_brief_job', $job, false );
		}
		liberty_brief_back( 'resumed' );
	}
);
add_action(
	'admin_post_liberty_brief_preview',
	static function () {
		liberty_brief_guard();
		echo liberty_brief_compose( 0 )['html']; // phpcs:ignore WordPress.Security.EscapeOutput -- built with escaping.
		exit;
	}
);
add_action(
	'admin_post_liberty_brief_export',
	static function () {
		liberty_brief_guard( 'manage_options' );
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="liberty-brief-subscribers-' . wp_date( 'Y-m-d' ) . '.csv"' );
		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		fputcsv( $out, array( 'email', 'status', 'follows', 'joined' ) );
		foreach ( get_posts( array( 'post_type' => LIBERTY_SUB, 'post_status' => 'private', 'numberposts' => -1 ) ) as $p ) {
			fputcsv( $out, array( get_post_meta( $p->ID, '_lc_email', true ), get_post_meta( $p->ID, '_lc_status', true ), liberty_follow_labels( (string) get_post_meta( $p->ID, '_lc_follows', true ) ), get_the_date( 'Y-m-d', $p ) ) );
		}
		exit;
	}
);
add_action(
	'admin_post_liberty_brief_settings',
	static function () {
		liberty_brief_guard( 'manage_options' );
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput -- sanitised per field.
		$o = get_option( 'liberty_brief', array() );
		$o = is_array( $o ) ? $o : array();
		$days            = array_map( 'absint', (array) ( $_POST['days'] ?? array() ) );
		$o['enabled']    = empty( $_POST['enabled'] ) ? '' : '1';
		$o['days']       = implode( '', array_filter( $days, static fn( $d ) => $d >= 1 && $d <= 7 ) );
		$o['time']       = preg_match( '/^\d{2}:\d{2}$/', (string) wp_unslash( $_POST['time'] ?? '' ) ) ? wp_unslash( $_POST['time'] ) : '07:00';
		$o['subject']    = sanitize_text_field( wp_unslash( $_POST['subject'] ?? '' ) );
		$o['from_name']  = sanitize_text_field( wp_unslash( $_POST['from_name'] ?? '' ) );
		$o['from_email'] = sanitize_email( wp_unslash( $_POST['from_email'] ?? '' ) );
		$o['per_hour']   = (string) max( 12, min( 5000, absint( $_POST['per_hour'] ?? 200 ) ) );
		$o['address']    = sanitize_text_field( wp_unslash( $_POST['address'] ?? '' ) );
		$o['smtp_host']  = preg_replace( '/[^A-Za-z0-9.\-]/', '', sanitize_text_field( wp_unslash( $_POST['smtp_host'] ?? '' ) ) );
		$o['smtp_port']  = (string) absint( $_POST['smtp_port'] ?? 587 );
		$o['smtp_user']  = sanitize_text_field( wp_unslash( $_POST['smtp_user'] ?? '' ) );
		$pass            = (string) wp_unslash( $_POST['smtp_pass'] ?? '' );
		if ( '' !== $pass ) {
			$o['smtp_pass'] = $pass;
		}
		// phpcs:enable
		update_option( 'liberty_brief', $o, false );
		liberty_brief_back( 'saved' );
	}
);

/* --------------------------------------------------- subscriber screen */

add_filter(
	'manage_' . LIBERTY_SUB . '_posts_columns',
	static fn( $cols ) => array(
		'cb'        => $cols['cb'] ?? '',
		'title'     => __( 'Email', 'liberty-core' ),
		'lc_status' => __( 'Status', 'liberty-core' ),
		'lc_follow' => __( 'Follows', 'liberty-core' ),
		'date'      => __( 'Joined', 'liberty-core' ),
	)
);
add_action(
	'manage_' . LIBERTY_SUB . '_posts_custom_column',
	static function ( $col, $id ) {
		if ( 'lc_status' === $col ) {
			$labels = array( 'active' => __( 'Confirmed', 'liberty-core' ), 'pending' => __( 'Waiting to confirm', 'liberty-core' ), 'unsubscribed' => __( 'Unsubscribed', 'liberty-core' ) );
			echo esc_html( $labels[ get_post_meta( $id, '_lc_status', true ) ] ?? '' );
		} elseif ( 'lc_follow' === $col ) {
			echo esc_html( liberty_follow_labels( (string) get_post_meta( $id, '_lc_follows', true ) ) );
		}
	},
	10,
	2
);

/**
 * "c:12,t:5" → "Justice & Law, SLBC".
 */
function liberty_follow_labels( $raw ) {
	$names = array();
	foreach ( array_filter( explode( ',', $raw ) ) as $f ) {
		list( $type, $tid ) = array_pad( explode( ':', $f ), 2, 0 );
		$term = get_term( (int) $tid, 'c' === $type ? 'category' : 'post_tag' );
		if ( $term && ! is_wp_error( $term ) ) {
			$names[] = $term->name;
		}
	}
	return implode( ', ', $names );
}
