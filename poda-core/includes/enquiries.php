<?php
/**
 * "Hop in" enquiry form: validation, spam protection, storage and email.
 */

defined( 'ABSPATH' ) || exit;

const PODA_NEEDS   = array( 'Identity', 'Website', 'Packaging', 'Campaign', 'Motion', 'Not sure yet' );
const PODA_BUDGETS = array( 'Under $10k', '$10k–25k', '$25k–60k', '$60k+' );

add_action( 'admin_post_nopriv_poda_enquiry', 'poda_handle_enquiry' );
add_action( 'admin_post_poda_enquiry', 'poda_handle_enquiry' );

/**
 * Signed timestamp so the handler can reject forms submitted too fast (bots)
 * or replayed long after the page loaded.
 */
function poda_form_stamp() {
	$t = time();
	return $t . '.' . wp_hash( 'poda-form|' . $t );
}

/**
 * @return string 'ok', 'bot' (forged or sent faster than a person can type) or 'stale'.
 */
function poda_check_stamp( $stamp ) {
	$parts = explode( '.', (string) $stamp );
	if ( 2 !== count( $parts ) || ! hash_equals( wp_hash( 'poda-form|' . $parts[0] ), $parts[1] ) ) {
		return 'bot';
	}
	$age = time() - (int) $parts[0];
	if ( $age < 3 ) {
		return 'bot';
	}
	return $age > DAY_IN_SECONDS ? 'stale' : 'ok';
}

function poda_enquiry_redirect( $status ) {
	$back = wp_get_referer();
	$page = get_page_by_path( 'contact' );
	$base = $back && wp_validate_redirect( $back, false ) ? $back : ( $page ? get_permalink( $page ) : home_url( '/' ) );
	$base = remove_query_arg( array( 'sent', 'err' ), $base );
	wp_safe_redirect( add_query_arg( $status, $base ) . '#hop-in' );
	exit;
}

function poda_handle_enquiry() {
	if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
		wp_die( esc_html__( 'Method not allowed.', 'poda-core' ), '', array( 'response' => 405 ) );
	}
	// phpcs:disable WordPress.Security.ValidatedSanitizedInput -- each value is sanitised below.
	$nonce = isset( $_POST['_poda_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_poda_nonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'poda_enquiry' ) ) {
		poda_enquiry_redirect( array( 'err' => 'expired' ) );
	}
	// Honeypot and timing trap: pretend success so bots learn nothing.
	$stamp = poda_check_stamp( wp_unslash( $_POST['_poda_t'] ?? '' ) );
	if ( ! empty( $_POST['website'] ) || 'bot' === $stamp ) {
		poda_enquiry_redirect( array( 'sent' => '1' ) );
	}
	if ( 'stale' === $stamp ) {
		poda_enquiry_redirect( array( 'err' => 'expired' ) );
	}
	// Rate limit: 5 enquiries per hour per address (hashed, never stored raw).
	$ip_key = 'poda_rl_' . substr( wp_hash( (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) ), 0, 20 );
	$count  = (int) get_transient( $ip_key );
	if ( $count >= 5 ) {
		poda_enquiry_redirect( array( 'err' => 'slow' ) );
	}

	$name    = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
	$email   = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
	$company = sanitize_text_field( wp_unslash( $_POST['company'] ?? '' ) );
	$message = sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) );
	$needs   = array_values( array_intersect( array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['needs'] ?? array() ) ), PODA_NEEDS ) );
	$budget  = sanitize_text_field( wp_unslash( $_POST['budget'] ?? '' ) );
	$budget  = in_array( $budget, PODA_BUDGETS, true ) ? $budget : '';
	// phpcs:enable

	if ( '' === $name || ! is_email( $email ) || mb_strlen( $message ) < 10 ) {
		poda_enquiry_redirect( array( 'err' => 'missing' ) );
	}
	$name    = mb_substr( $name, 0, 120 );
	$company = mb_substr( $company, 0, 160 );
	$message = mb_substr( $message, 0, 5000 );

	set_transient( $ip_key, $count + 1, HOUR_IN_SECONDS );

	$id = wp_insert_post(
		array(
			/* translators: 1: name, 2: company */
			'post_title'  => $company ? sprintf( __( '%1$s — %2$s', 'poda-core' ), $name, $company ) : $name,
			'post_type'   => 'poda_enquiry',
			'post_status' => 'private',
		),
		true
	);
	if ( ! is_wp_error( $id ) ) {
		foreach ( compact( 'name', 'email', 'company', 'message', 'budget' ) as $k => $v ) {
			update_post_meta( $id, '_poda_' . $k, $v );
		}
		update_post_meta( $id, '_poda_needs', implode( ', ', $needs ) );
	}

	$to = poda_setting( 'email', get_option( 'admin_email' ) );
	/* translators: %s: person's name */
	$subject = sprintf( __( 'New enquiry from %s', 'poda-core' ), $name );
	$body    = implode(
		"\n",
		array(
			__( 'Name:', 'poda-core' ) . ' ' . $name,
			__( 'Email:', 'poda-core' ) . ' ' . $email,
			__( 'Company:', 'poda-core' ) . ' ' . $company,
			__( 'Needs:', 'poda-core' ) . ' ' . implode( ', ', $needs ),
			__( 'Budget:', 'poda-core' ) . ' ' . $budget,
			'',
			$message,
		)
	);
	wp_mail( $to, $subject, $body, array( 'Reply-To: ' . $name . ' <' . $email . '>' ) );

	poda_enquiry_redirect( array( 'sent' => '1' ) );
}

add_filter(
	'manage_poda_enquiry_posts_columns',
	static function ( $cols ) {
		return array(
			'cb'          => $cols['cb'] ?? '',
			'title'       => __( 'From', 'poda-core' ),
			'poda_email'  => __( 'Email', 'poda-core' ),
			'poda_budget' => __( 'Budget', 'poda-core' ),
			'date'        => __( 'Received', 'poda-core' ),
		);
	}
);
add_action(
	'manage_poda_enquiry_posts_custom_column',
	static function ( $col, $id ) {
		if ( 'poda_email' === $col ) {
			echo esc_html( get_post_meta( $id, '_poda_email', true ) );
		} elseif ( 'poda_budget' === $col ) {
			echo esc_html( get_post_meta( $id, '_poda_budget', true ) );
		}
	},
	10,
	2
);
