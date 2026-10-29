<?php
/**
 * Contact form, Morning Brief email sign-ups and spam protection for forms
 * and comments (replaces Contact Form 7 and Akismet).
 *
 * Spam protection is three quiet checks that real readers never notice: a
 * hidden field only bots fill in, a signed timestamp (bots submit instantly,
 * replayed forms are too old) and a per-connection rate limit.
 */

defined( 'ABSPATH' ) || exit;

const LIBERTY_TOPICS = array(
	'tip'        => 'Send a news tip',
	'correction' => 'Request a correction',
	'ads'        => 'Advertising and press releases',
	'support'    => 'App support',
	'other'      => 'Something else',
);

add_action(
	'init',
	static function () {
		register_post_type(
			'liberty_inbox',
			array(
				'labels'          => array(
					'name'          => __( 'Inbox', 'liberty-core' ),
					'singular_name' => __( 'Message', 'liberty-core' ),
					'all_items'     => __( 'Messages and sign-ups', 'liberty-core' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => true,
				'menu_icon'       => 'dashicons-email-alt',
				'menu_position'   => 25,
				'supports'        => array( 'title' ),
				'capability_type' => 'post',
				'capabilities'    => array( 'create_posts' => 'do_not_allow' ),
				'map_meta_cap'    => true,
			)
		);
	}
);

/* ----------------------------------------------------------- spam checks */

function liberty_form_stamp() {
	$t = time();
	return $t . '.' . wp_hash( 'liberty-form|' . $t );
}

/**
 * @return string 'ok', 'bot' or 'stale'.
 */
function liberty_check_stamp( $stamp, $min_seconds = 3 ) {
	$parts = explode( '.', (string) $stamp );
	if ( 2 !== count( $parts ) || ! hash_equals( wp_hash( 'liberty-form|' . $parts[0] ), $parts[1] ) ) {
		return 'bot';
	}
	$age = time() - (int) $parts[0];
	if ( $age < $min_seconds ) {
		return 'bot';
	}
	return $age > DAY_IN_SECONDS ? 'stale' : 'ok';
}

/**
 * @return bool True when this connection is over the limit.
 */
function liberty_rate_limited( $bucket, $max, $window = HOUR_IN_SECONDS ) {
	$key   = 'liberty_rl_' . $bucket . '_' . substr( wp_hash( (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) ), 0, 16 );
	$count = (int) get_transient( $key );
	if ( $count >= $max ) {
		return true;
	}
	set_transient( $key, $count + 1, $window );
	return false;
}

/**
 * Hidden fields every Liberty form carries.
 */
function liberty_form_traps() {
	return '<input type="hidden" name="_lc_t" value="' . esc_attr( liberty_form_stamp() ) . '">'
		. '<p class="lc-hp" aria-hidden="true" style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden"><label>' . esc_html__( 'Leave this empty', 'liberty-core' ) . ' <input type="text" name="lc_website" tabindex="-1" autocomplete="off"></label></p>';
}

/* ---------------------------------------------------------- contact form */

add_action( 'admin_post_nopriv_liberty_contact', 'liberty_handle_contact' );
add_action( 'admin_post_liberty_contact', 'liberty_handle_contact' );

function liberty_form_back( array $args, $anchor = '' ) {
	$back = wp_get_referer();
	$base = $back && wp_validate_redirect( $back, false ) ? $back : home_url( '/' );
	$base = remove_query_arg( array( 'sent', 'err', 'joined' ), $base );
	wp_safe_redirect( add_query_arg( $args, $base ) . $anchor );
	exit;
}

function liberty_handle_contact() {
	// phpcs:disable WordPress.Security.ValidatedSanitizedInput -- every value is sanitised below.
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_lc_nonce'] ?? '' ) ), 'liberty_contact' ) ) {
		liberty_form_back( array( 'err' => 'expired' ), '#contact-form' );
	}
	$stamp = liberty_check_stamp( wp_unslash( $_POST['_lc_t'] ?? '' ) );
	if ( ! empty( $_POST['lc_website'] ) || 'bot' === $stamp ) {
		liberty_form_back( array( 'sent' => '1' ), '#contact-form' ); // pretend success; bots learn nothing
	}
	if ( 'stale' === $stamp ) {
		liberty_form_back( array( 'err' => 'expired' ), '#contact-form' );
	}
	if ( liberty_rate_limited( 'contact', 5 ) ) {
		liberty_form_back( array( 'err' => 'slow' ), '#contact-form' );
	}
	$topic   = sanitize_key( wp_unslash( $_POST['topic'] ?? 'other' ) );
	$topic   = isset( LIBERTY_TOPICS[ $topic ] ) ? $topic : 'other';
	$name    = mb_substr( sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ), 0, 120 );
	$email   = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
	$phone   = mb_substr( preg_replace( '/[^0-9+ ()-]/', '', sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) ) ), 0, 30 );
	$message = mb_substr( sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) ), 0, 6000 );
	$anon    = ! empty( $_POST['anonymous'] );
	// phpcs:enable

	if ( '' === $name || ! is_email( $email ) || mb_strlen( $message ) < 10 ) {
		liberty_form_back( array( 'err' => 'missing' ), '#contact-form' );
	}

	$id = wp_insert_post(
		array(
			/* translators: 1: topic, 2: name */
			'post_title'  => sprintf( __( '%1$s — %2$s', 'liberty-core' ), LIBERTY_TOPICS[ $topic ], $name ),
			'post_type'   => 'liberty_inbox',
			'post_status' => 'private',
		)
	);
	if ( $id && ! is_wp_error( $id ) ) {
		foreach ( compact( 'topic', 'name', 'email', 'phone', 'message' ) as $k => $v ) {
			update_post_meta( $id, '_lc_' . $k, $v );
		}
		update_post_meta( $id, '_lc_kind', 'message' );
		update_post_meta( $id, '_lc_anonymous', $anon ? '1' : '' );
	}

	$route = array( 'tip' => 'mail_tips', 'correction' => 'mail_corrections', 'ads' => 'mail_ads', 'support' => 'mail_support' );
	$to    = isset( $route[ $topic ] ) ? (string) liberty_opt( 'site', $route[ $topic ], '' ) : '';
	$to    = liberty_mail_to( $to );
	$body  = implode(
		"\n",
		array(
			__( 'Topic:', 'liberty-core' ) . ' ' . LIBERTY_TOPICS[ $topic ],
			__( 'Name:', 'liberty-core' ) . ' ' . $name . ( $anon ? ' ' . __( '(asked to stay anonymous in any story)', 'liberty-core' ) : '' ),
			__( 'Email:', 'liberty-core' ) . ' ' . $email,
			__( 'Phone or WhatsApp:', 'liberty-core' ) . ' ' . $phone,
			'',
			$message,
		)
	);
	/* translators: 1: topic, 2: name */
	wp_mail( $to, sprintf( __( '[Liberty website] %1$s from %2$s', 'liberty-core' ), LIBERTY_TOPICS[ $topic ], $name ), $body, array( 'Reply-To: ' . $name . ' <' . $email . '>' ) );

	liberty_form_back( array( 'sent' => '1' ), '#contact-form' );
}

/**
 * On the live site, the chosen inbox (else the site admin); on staging, only
 * the test address so the newsroom never receives test messages.
 */
function liberty_mail_to( $preferred ) {
	if ( ! liberty_is_production() ) {
		$test = (string) liberty_opt( 'site', 'test_email', '' );
		return $test ? $test : (string) get_option( 'admin_email' );
	}
	return is_email( $preferred ) ? $preferred : (string) get_option( 'admin_email' );
}

/* ------------------------------------------------------ brief sign-ups */

add_action( 'admin_post_nopriv_liberty_signup', 'liberty_handle_signup' );
add_action( 'admin_post_liberty_signup', 'liberty_handle_signup' );

function liberty_handle_signup() {
	// phpcs:disable WordPress.Security.ValidatedSanitizedInput -- sanitised below.
	$stamp  = liberty_check_stamp( wp_unslash( $_POST['_lc_t'] ?? '' ), 2 );
	$anchor = ! empty( $_POST['lc_from'] ) && 'following' === $_POST['lc_from'] ? '#follow-email' : '#brief';
	if ( ! empty( $_POST['lc_website'] ) || 'bot' === $stamp ) {
		liberty_form_back( array( 'joined' => 'check' ), $anchor );
	}
	if ( liberty_rate_limited( 'signup', 10 ) ) {
		liberty_form_back( array( 'joined' => 'slow' ), $anchor );
	}
	$email   = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
	$follows = array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['follows'] ?? array() ) );
	// phpcs:enable
	if ( ! is_email( $email ) ) {
		liberty_form_back( array( 'joined' => 'bad' ), $anchor );
	}
	$r = liberty_subscribe( $email, $follows );
	liberty_form_back( array( 'joined' => 'active' === $r ? 'active' : ( 'error' === $r ? 'bad' : 'check' ) ), $anchor );
}

/* Sign-ups saved by the first version (in the Inbox) move to the subscriber list, unconfirmed. */
add_action(
	'admin_init',
	static function () {
		if ( get_option( 'liberty_signups_moved' ) || ! function_exists( 'liberty_subscribe' ) ) {
			return;
		}
		foreach ( get_posts( array( 'post_type' => 'liberty_inbox', 'post_status' => 'private', 'numberposts' => -1, 'meta_key' => '_lc_kind', 'meta_value' => 'signup' ) ) as $p ) { // phpcs:ignore WordPress.DB.SlowDBQuery
			$email = (string) get_post_meta( $p->ID, '_lc_signup', true );
			if ( is_email( $email ) && ! liberty_find_subscriber( 'email', $email ) ) {
				$id = wp_insert_post( array( 'post_type' => LIBERTY_SUB, 'post_status' => 'private', 'post_title' => $email ) );
				update_post_meta( $id, '_lc_email', $email );
				update_post_meta( $id, '_lc_token', wp_generate_password( 32, false, false ) );
				update_post_meta( $id, '_lc_status', 'pending' );
			}
			wp_delete_post( $p->ID, true );
		}
		update_option( 'liberty_signups_moved', 1, false );
	}
);

/* ------------------------------------------------------------- comments */

add_action( 'comment_form_after_fields', static function () {
	echo liberty_form_traps(); // phpcs:ignore WordPress.Security.EscapeOutput -- built with escaping.
} );

add_filter(
	'preprocess_comment',
	static function ( $data ) {
		if ( is_user_logged_in() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( $data['comment_type'] ?? '' ) !== '' && 'comment' !== $data['comment_type'] ) {
			return $data;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput -- core comment form; values only compared.
		$trapped = ! empty( $_POST['lc_website'] ) || 'ok' !== liberty_check_stamp( wp_unslash( $_POST['_lc_t'] ?? '' ), 4 );
		// phpcs:enable
		if ( $trapped || liberty_rate_limited( 'comment', 6 ) ) {
			add_filter( 'pre_comment_approved', static fn() => 'spam', 99 );
		}
		return $data;
	}
);

/* ------------------------------------------------------- inbox screen */

add_filter(
	'manage_liberty_inbox_posts_columns',
	static fn( $cols ) => array(
		'cb'       => $cols['cb'] ?? '',
		'title'    => __( 'Message', 'liberty-core' ),
		'lc_email' => __( 'Email', 'liberty-core' ),
		'date'     => __( 'Received', 'liberty-core' ),
	)
);
add_action(
	'manage_liberty_inbox_posts_custom_column',
	static function ( $col, $id ) {
		if ( 'lc_email' === $col ) {
			$e = get_post_meta( $id, '_lc_email', true );
			echo esc_html( $e ? $e : get_post_meta( $id, '_lc_signup', true ) );
		}
	},
	10,
	2
);
add_action(
	'add_meta_boxes_liberty_inbox',
	static function () {
		add_meta_box(
			'lc_message',
			__( 'Details', 'liberty-core' ),
			static function ( WP_Post $post ) {
				$rows = array( 'topic' => __( 'Topic', 'liberty-core' ), 'name' => __( 'Name', 'liberty-core' ), 'email' => __( 'Email', 'liberty-core' ), 'phone' => __( 'Phone or WhatsApp', 'liberty-core' ), 'signup' => __( 'Sign-up email', 'liberty-core' ) );
				echo '<table class="form-table" role="presentation"><tbody>';
				foreach ( $rows as $k => $label ) {
					$v = get_post_meta( $post->ID, '_lc_' . $k, true );
					if ( '' !== $v ) {
						$v = 'topic' === $k ? ( LIBERTY_TOPICS[ $v ] ?? $v ) : $v;
						echo '<tr><th>' . esc_html( $label ) . '</th><td>' . esc_html( $v ) . '</td></tr>';
					}
				}
				if ( get_post_meta( $post->ID, '_lc_anonymous', true ) ) {
					echo '<tr><th>' . esc_html__( 'Anonymity', 'liberty-core' ) . '</th><td><strong>' . esc_html__( 'Keep this person’s name out of any story.', 'liberty-core' ) . '</strong></td></tr>';
				}
				$msg = get_post_meta( $post->ID, '_lc_message', true );
				if ( $msg ) {
					echo '<tr><th>' . esc_html__( 'Message', 'liberty-core' ) . '</th><td>' . nl2br( esc_html( $msg ) ) . '</td></tr>';
				}
				echo '</tbody></table>';
			},
			'liberty_inbox',
			'normal',
			'high'
		);
	}
);
