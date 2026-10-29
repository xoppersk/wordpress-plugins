<?php
/**
 * Meta boxes generated from the field schema.
 */

defined( 'ABSPATH' ) || exit;

function poda_meta_groups() {
	return array(
		'poda_project' => array(
			'card'     => __( 'Card and cover', 'poda-core' ),
			'case'     => __( 'Case study: facts and quote', 'poda-core' ),
			'results'  => __( 'Case study: results', 'poda-core' ),
			'chapters' => __( 'Case study: the story', 'poda-core' ),
			'gallery'  => __( 'Case study: what we made', 'poda-core' ),
			'kit'      => __( 'Case study: the kit (palette and type)', 'poda-core' ),
		),
		'poda_crew'    => array( 'main' => __( 'Crew details', 'poda-core' ) ),
		'poda_service' => array( 'main' => __( 'Service details', 'poda-core' ) ),
		'post'         => array( 'main' => __( 'Story byline', 'poda-core' ) ),
	);
}

add_action(
	'add_meta_boxes',
	static function () {
		foreach ( poda_meta_groups() as $type => $groups ) {
			foreach ( $groups as $group => $title ) {
				add_meta_box(
					"poda_{$group}",
					$title,
					'poda_render_meta_box',
					$type,
					'post' === $type ? 'side' : 'normal',
					'high',
					array( 'group' => $group )
				);
			}
		}
		add_meta_box( 'poda_enquiry_view', __( 'Enquiry', 'poda-core' ), 'poda_render_enquiry_box', 'poda_enquiry', 'normal', 'high' );
	}
);

function poda_render_meta_box( WP_Post $post, array $box ) {
	$group  = $box['args']['group'];
	$fields = poda_field_schema()[ $post->post_type ] ?? array();
	wp_nonce_field( 'poda_save_meta', 'poda_meta_nonce' );
	echo '<table class="form-table" role="presentation"><tbody>';
	foreach ( $fields as $key => $field ) {
		if ( $field['group'] !== $group ) {
			continue;
		}
		$name  = 'poda_' . $key;
		$value = get_post_meta( $post->ID, $name, true );
		echo '<tr><th scope="row"><label for="' . esc_attr( $name ) . '">' . esc_html( $field['label'] ) . '</label></th><td>';
		switch ( $field['type'] ) {
			case 'textarea':
				printf( '<textarea class="large-text" rows="4" id="%1$s" name="%1$s">%2$s</textarea>', esc_attr( $name ), esc_textarea( $value ) );
				break;
			case 'checkbox':
				printf( '<input type="checkbox" id="%1$s" name="%1$s" value="1" %2$s>', esc_attr( $name ), checked( $value, '1', false ) );
				break;
			case 'color':
				printf( '<input type="text" class="regular-text" id="%1$s" name="%1$s" value="%2$s" placeholder="#FFC400" pattern="#[0-9A-Fa-f]{6}">', esc_attr( $name ), esc_attr( $value ) );
				break;
			case 'crew':
			case 'crew_one':
				$crew     = get_posts( array( 'post_type' => 'poda_crew', 'numberposts' => -1, 'orderby' => 'menu_order', 'order' => 'ASC' ) );
				$selected = array_map( 'absint', explode( ',', (string) $value ) );
				if ( 'crew_one' === $field['type'] ) {
					printf( '<select id="%1$s" name="%1$s"><option value="0">%2$s</option>', esc_attr( $name ), esc_html__( '— none —', 'poda-core' ) );
					foreach ( $crew as $c ) {
						printf( '<option value="%1$d" %2$s>%3$s</option>', (int) $c->ID, selected( in_array( $c->ID, $selected, true ), true, false ), esc_html( $c->post_title ) );
					}
					echo '</select>';
				} else {
					foreach ( $crew as $c ) {
						printf( '<label style="display:inline-block;margin:0 14px 6px 0"><input type="checkbox" name="%1$s[]" value="%2$d" %3$s> %4$s</label>', esc_attr( $name ), (int) $c->ID, checked( in_array( $c->ID, $selected, true ), true, false ), esc_html( $c->post_title ) );
					}
				}
				break;
			default:
				printf( '<input type="text" class="regular-text" id="%1$s" name="%1$s" value="%2$s">', esc_attr( $name ), esc_attr( $value ) );
		}
		if ( ! empty( $field['help'] ) ) {
			echo '<p class="description">' . esc_html( $field['help'] ) . '</p>';
		}
		echo '</td></tr>';
	}
	echo '</tbody></table>';
}

add_action(
	'save_post',
	static function ( $post_id, WP_Post $post ) {
		if ( ! isset( $_POST['poda_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['poda_meta_nonce'] ) ), 'poda_save_meta' ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$fields = poda_field_schema()[ $post->post_type ] ?? array();
		foreach ( $fields as $key => $field ) {
			$name = 'poda_' . $key;
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised by poda_sanitize_field().
			$raw = isset( $_POST[ $name ] ) ? wp_unslash( $_POST[ $name ] ) : '';
			$val = poda_sanitize_field( $raw, $field );
			if ( '' === $val || '0' === $val && 'crew_one' === $field['type'] ) {
				delete_post_meta( $post_id, $name );
			} else {
				update_post_meta( $post_id, $name, $val );
			}
		}
	},
	10,
	2
);

function poda_render_enquiry_box( WP_Post $post ) {
	$rows = array(
		'name'    => __( 'Name', 'poda-core' ),
		'email'   => __( 'Email', 'poda-core' ),
		'company' => __( 'Company or project', 'poda-core' ),
		'needs'   => __( 'Needs', 'poda-core' ),
		'budget'  => __( 'Budget', 'poda-core' ),
	);
	echo '<table class="form-table" role="presentation"><tbody>';
	foreach ( $rows as $key => $label ) {
		$v = get_post_meta( $post->ID, '_poda_' . $key, true );
		echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>';
		echo 'email' === $key && is_email( $v ) ? '<a href="mailto:' . esc_attr( $v ) . '">' . esc_html( $v ) . '</a>' : esc_html( $v );
		echo '</td></tr>';
	}
	echo '<tr><th scope="row">' . esc_html__( 'Message', 'poda-core' ) . '</th><td>' . nl2br( esc_html( get_post_meta( $post->ID, '_poda_message', true ) ) ) . '</td></tr>';
	echo '</tbody></table>';
}
