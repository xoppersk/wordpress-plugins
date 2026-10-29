<?php
/**
 * Post types, taxonomy and registered meta.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', 'poda_register_content_types' );

function poda_register_content_types() {
	// Taxonomy first so its /work/route/… rules are matched before single projects.
	register_taxonomy(
		'poda_discipline',
		'poda_project',
		array(
			'labels'            => array(
				'name'          => __( 'Disciplines', 'poda-core' ),
				'singular_name' => __( 'Discipline', 'poda-core' ),
			),
			'public'            => true,
			'hierarchical'      => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rewrite'           => array( 'slug' => 'work/route' ),
		)
	);

	register_post_type(
		'poda_project',
		array(
			'labels'        => array(
				'name'          => __( 'Projects', 'poda-core' ),
				'singular_name' => __( 'Project', 'poda-core' ),
				'add_new_item'  => __( 'Add project', 'poda-core' ),
				'edit_item'     => __( 'Edit project', 'poda-core' ),
				'all_items'     => __( 'All projects', 'poda-core' ),
			),
			'public'        => true,
			'has_archive'   => 'work',
			'rewrite'       => array( 'slug' => 'work', 'with_front' => false ),
			'menu_icon'     => 'dashicons-portfolio',
			'menu_position' => 5,
			'supports'      => array( 'title', 'editor', 'excerpt', 'page-attributes', 'revisions' ),
			'show_in_rest'  => true,
		)
	);

	register_post_type(
		'poda_crew',
		array(
			'labels'       => array(
				'name'          => __( 'Crew', 'poda-core' ),
				'singular_name' => __( 'Crew member', 'poda-core' ),
				'add_new_item'  => __( 'Add crew member', 'poda-core' ),
			),
			'public'       => false,
			'show_ui'      => true,
			'menu_icon'    => 'dashicons-groups',
			'supports'     => array( 'title', 'page-attributes' ),
			'show_in_rest' => false,
		)
	);

	register_post_type(
		'poda_service',
		array(
			'labels'       => array(
				'name'          => __( 'Services', 'poda-core' ),
				'singular_name' => __( 'Service', 'poda-core' ),
				'add_new_item'  => __( 'Add service', 'poda-core' ),
			),
			'public'       => false,
			'show_ui'      => true,
			'menu_icon'    => 'dashicons-hammer',
			'supports'     => array( 'title', 'excerpt', 'page-attributes' ),
			'show_in_rest' => false,
		)
	);

	register_post_type(
		'poda_enquiry',
		array(
			'labels'          => array(
				'name'          => __( 'Enquiries', 'poda-core' ),
				'singular_name' => __( 'Enquiry', 'poda-core' ),
			),
			'public'          => false,
			'show_ui'         => true,
			'menu_icon'       => 'dashicons-email-alt',
			'supports'        => array( 'title' ),
			'capability_type' => 'post',
			'capabilities'    => array(
				'create_posts'       => 'do_not_allow',
				'edit_post'          => 'manage_options',
				'read_post'          => 'manage_options',
				'delete_post'        => 'manage_options',
				'edit_posts'         => 'manage_options',
				'edit_others_posts'  => 'manage_options',
				'delete_posts'       => 'manage_options',
				'publish_posts'      => 'manage_options',
				'read_private_posts' => 'manage_options',
			),
			'map_meta_cap'    => false,
			'show_in_rest'    => false,
		)
	);

	foreach ( poda_field_schema() as $type => $fields ) {
		foreach ( $fields as $key => $field ) {
			register_post_meta(
				$type,
				'poda_' . $key,
				array(
					'type'              => 'string',
					'single'            => true,
					'show_in_rest'      => false,
					'sanitize_callback' => static function ( $value ) use ( $field ) {
						return poda_sanitize_field( $value, $field );
					},
					'auth_callback'     => static function ( $allowed, $meta_key, $post_id ) {
						return current_user_can( 'edit_post', $post_id );
					},
				)
			);
		}
	}
}

/* Show every project on /work/ (the page filters them client-side). */
add_action(
	'pre_get_posts',
	static function ( WP_Query $q ) {
		if ( is_admin() || ! $q->is_main_query() ) {
			return;
		}
		if ( $q->is_post_type_archive( 'poda_project' ) || $q->is_tax( 'poda_discipline' ) ) {
			$q->set( 'posts_per_page', 60 );
			$q->set( 'orderby', array( 'menu_order' => 'ASC', 'date' => 'DESC' ) );
		}
	}
);
