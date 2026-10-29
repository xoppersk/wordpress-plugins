<?php
/**
 * Edit-screen boxes generated from the field schema.
 */

defined( 'ABSPATH' ) || exit;

function liberty_meta_groups() {
	return array(
		'post'                => array(
			'story'   => array( __( 'Story extras', 'liberty-core' ), 'normal' ),
			'quote'   => array( __( 'Pull quote and video', 'liberty-core' ), 'normal' ),
			'numbers' => array( __( 'By the numbers', 'liberty-core' ), 'normal' ),
		),
		liberty_cpt( 'team' ) => array( 'member' => array( __( 'Member details', 'liberty-core' ), 'normal' ) ),
		liberty_cpt( 'live' ) => array( 'broadcast' => array( __( 'Stream link', 'liberty-core' ), 'normal' ) ),
	);
}

/**
 * While ACF Pro is still installed it shows its own boxes for these two types;
 * showing ours as well would let two forms write the same field.
 */
function liberty_acf_owns( $post_type ) {
	return class_exists( 'ACF' ) && in_array( $post_type, array( liberty_cpt( 'team' ), liberty_cpt( 'live' ) ), true );
}

add_action(
	'add_meta_boxes',
	static function ( $post_type ) {
		if ( liberty_acf_owns( $post_type ) ) {
			return;
		}
		foreach ( liberty_meta_groups()[ $post_type ] ?? array() as $group => $box ) {
			add_meta_box( "liberty_{$group}", $box[0], 'liberty_render_meta_box', $post_type, $box[1], 'high', array( 'group' => $group ) );
		}
	}
);

function liberty_render_meta_box( WP_Post $post, array $box ) {
	$group  = $box['args']['group'];
	$fields = liberty_field_schema()[ $post->post_type ] ?? array();
	wp_nonce_field( 'liberty_save_meta', 'liberty_meta_nonce' );
	echo '<table class="form-table" role="presentation"><tbody>';
	foreach ( $fields as $key => $field ) {
		if ( $field['group'] !== $group ) {
			continue;
		}
		$name  = 'liberty_f_' . $key;
		$value = get_post_meta( $post->ID, $field['meta'], true );
		echo '<tr><th scope="row"><label for="' . esc_attr( $name ) . '">' . esc_html( $field['label'] ) . '</label></th><td>';
		switch ( $field['type'] ) {
			case 'textarea':
				printf( '<textarea class="large-text" rows="3" id="%1$s" name="%1$s">%2$s</textarea>', esc_attr( $name ), esc_textarea( (string) $value ) );
				break;
			case 'number':
				printf( '<input type="number" min="0" class="small-text" id="%1$s" name="%1$s" value="%2$s">', esc_attr( $name ), esc_attr( (string) $value ) );
				break;
			case 'image':
				$src = $value ? wp_get_attachment_image_url( (int) $value, 'thumbnail' ) : '';
				printf( '<input type="number" min="0" class="small-text" id="%1$s" name="%1$s" value="%2$s"> ', esc_attr( $name ), esc_attr( (string) $value ) );
				echo $src ? '<img src="' . esc_url( $src ) . '" alt="" style="width:48px;height:48px;object-fit:cover;vertical-align:middle">' : '';
				echo '<p class="description">' . esc_html__( 'Media library ID of the image.', 'liberty-core' ) . '</p>';
				break;
			default:
				$type = in_array( $field['type'], array( 'url', 'email' ), true ) ? $field['type'] : 'text';
				printf( '<input type="%3$s" class="large-text" id="%1$s" name="%1$s" value="%2$s">', esc_attr( $name ), esc_attr( (string) $value ), esc_attr( $type ) );
		}
		if ( ! empty( $field['help'] ) ) {
			echo '<p class="description">' . esc_html( $field['help'] ) . '</p>';
		}
		echo '</td></tr>';
	}
	echo '</tbody></table>';
	if ( 'broadcast' === $group ) {
		$url    = liberty_broadcast_url( $post->ID );
		$stream = $url ? liberty_parse_stream( $url ) : null;
		if ( $url && ! $stream ) {
			echo '<p style="color:#b32d2e">' . esc_html__( 'This link is not a YouTube or Facebook video link, so it cannot play on the website.', 'liberty-core' ) . '</p>';
		} elseif ( $stream ) {
			/* translators: %s: YouTube or Facebook */
			echo '<p style="color:#1f8f4a">' . esc_html( sprintf( __( 'Link recognised: this %s video will play on the website.', 'liberty-core' ), 'youtube' === $stream['provider'] ? 'YouTube' : 'Facebook' ) ) . '</p>';
		}
	}
}

add_action(
	'save_post',
	static function ( $post_id, WP_Post $post ) {
		if ( ! isset( $_POST['liberty_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['liberty_meta_nonce'] ) ), 'liberty_save_meta' ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) || liberty_acf_owns( $post->post_type ) ) {
			return;
		}
		foreach ( liberty_field_schema()[ $post->post_type ] ?? array() as $key => $field ) {
			$name = 'liberty_f_' . $key;
			if ( ! array_key_exists( $name, $_POST ) ) {
				continue;
			}
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised by liberty_sanitize_field().
			$val = liberty_sanitize_field( wp_unslash( $_POST[ $name ] ), $field );
			if ( '' === $val ) {
				delete_post_meta( $post_id, $field['meta'] );
			} else {
				update_post_meta( $post_id, $field['meta'], $val );
			}
		}
	},
	10,
	2
);
