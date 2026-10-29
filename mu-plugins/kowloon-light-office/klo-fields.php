<?php
/**
 * Plugin Name: KLO Fields (MU)
 * Description: Kowloon Light Office content model - CPTs, taxonomy, ACF field
 *              groups and options page, registered in PHP.
 *              Lives in mu-plugins so the model survives theme/plugin changes.
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/* -------------------------------------------------------------------------
 * Post types and taxonomy (registered regardless of ACF, on init)
 * ---------------------------------------------------------------------- */

add_action('init', function () {
    register_post_type('bend', [
        'labels' => [
            'name' => 'Bends',
            'singular_name' => 'Bend',
            'add_new_item' => 'Add New Bend',
            'edit_item' => 'Edit Bend',
        ],
        'public' => true,
        'publicly_queryable' => true,
        'show_ui' => true,
        'show_in_menu' => true,
        'show_in_rest' => true,
        'menu_icon' => 'dashicons-lightbulb',
        'has_archive' => true,
        'rewrite' => ['slug' => 'bends', 'with_front' => false],
        'supports' => ['title', 'thumbnail', 'revisions'],
    ]);

    register_post_type('person', [
        'labels' => [
            'name' => 'People',
            'singular_name' => 'Person',
            'add_new_item' => 'Add New Person',
            'edit_item' => 'Edit Person',
        ],
        // Not public: no single pages, no archive, not queryable on the front end.
        'public' => false,
        'publicly_queryable' => false,
        'show_ui' => true,
        'show_in_menu' => true,
        'show_in_rest' => true,
        'has_archive' => false,
        'rewrite' => false,
        'supports' => ['title', 'thumbnail', 'revisions'],
    ]);

    register_post_type('archive_sign', [
        'labels' => [
            'name' => 'Sign Archive',
            'singular_name' => 'Archive Sign',
            'add_new_item' => 'Add New Sign',
            'edit_item' => 'Edit Sign',
        ],
        'public' => false,
        'publicly_queryable' => false,
        'show_ui' => true,
        'show_in_menu' => true,
        'show_in_rest' => true,
        'has_archive' => false,
        'rewrite' => false,
        'supports' => ['title', 'thumbnail', 'revisions'],
    ]);

    register_taxonomy('bend_category', ['bend'], [
        'labels' => [
            'name' => 'Bend Categories',
            'singular_name' => 'Bend Category',
        ],
        'hierarchical' => false,
        'public' => true,
        'show_ui' => true,
        'show_admin_column' => true,
        'show_in_rest' => true,
        'rewrite' => ['slug' => 'bend-category', 'with_front' => false],
    ]);
});

/* -------------------------------------------------------------------------
 * ACF registrations
 * ---------------------------------------------------------------------- */

add_action('acf/init', function () {
    if (!function_exists('acf_add_local_field_group')) {
        return;
    }

    /* ---- CPT bend ---------------------------------------------------- */
    acf_add_local_field_group([
        'key' => 'group_klo_bend',
        'title' => 'Bend',
        'location' => [[['param' => 'post_type', 'operator' => '==', 'value' => 'bend']]],
        'menu_order' => 0,
        'position' => 'normal',
        'style' => 'default',
        'label_placement' => 'top',
        'instruction_placement' => 'label',
        'active' => true,
        'fields' => [
            [
                'key' => 'field_klo_bend_number', 'label' => 'Bend number', 'name' => 'bend_number',
                'type' => 'number', 'required' => 1, 'min' => 1, 'max' => 99, 'step' => 1,
                'instructions' => 'Shown as "No. 04". Must be unique across all bends (1-99).',
            ],
            [
                'key' => 'field_klo_client_name', 'label' => 'Client name', 'name' => 'client_name',
                'type' => 'text', 'required' => 1,
                'instructions' => 'Mirrors the post title.',
            ],
            [
                'key' => 'field_klo_descriptor', 'label' => 'Descriptor', 'name' => 'descriptor',
                'type' => 'text', 'required' => 1, 'maxlength' => 90,
                'instructions' => 'One line, max 90 characters. E.g. "A single-screen cinema on Temple Street, open again after sixteen years."',
            ],
            [
                'key' => 'field_klo_place', 'label' => 'Place', 'name' => 'place',
                'type' => 'text', 'required' => 1,
                'instructions' => '"Jordan, Kowloon", or "Title sequence" for Motion work.',
            ],
            [
                'key' => 'field_klo_year', 'label' => 'Year', 'name' => 'year',
                'type' => 'number', 'required' => 1,
            ],
            [
                'key' => 'field_klo_one_line', 'label' => 'One line', 'name' => 'one_line',
                'type' => 'text', 'maxlength' => 80,
                'instructions' => 'Used in search results and the bible roster. Max 80 characters.',
            ],
            [
                'key' => 'field_klo_card_media', 'label' => 'Card media', 'name' => 'card_media',
                'type' => 'image', 'required' => 1, 'return_format' => 'array', 'preview_size' => 'medium',
                'min_width' => 900, 'min_height' => 1350,
                'instructions' => 'Card art, 2:3 portrait, minimum 900 x 1350 px.',
            ],
            [
                'key' => 'field_klo_card_video', 'label' => 'Card video', 'name' => 'card_video',
                'type' => 'file', 'return_format' => 'array', 'mime_types' => 'mp4',
                'instructions' => 'Optional muted loop over the card. MP4, max 4MB.',
            ],
            [
                'key' => 'field_klo_service_tags', 'label' => 'Service tags', 'name' => 'service_tags',
                'type' => 'checkbox',
                'choices' => [
                    'Brand Identity' => 'Brand Identity',
                    'Bilingual Lettering' => 'Bilingual Lettering',
                    'Website' => 'Website',
                    'Signage and Light' => 'Signage and Light',
                    'Motion' => 'Motion',
                    'Ongoing Care' => 'Ongoing Care',
                ],
                'layout' => 'vertical', 'return_format' => 'value',
                'instructions' => 'Tag boxes shown in the bend hero.',
            ],
            [
                'key' => 'field_klo_hero_image', 'label' => 'Hero image', 'name' => 'hero_image',
                'type' => 'image', 'required' => 1, 'return_format' => 'array', 'preview_size' => 'medium',
                'min_width' => 2400,
                'instructions' => '16:9, minimum 2400 px wide.',
            ],
            [
                'key' => 'field_klo_hero_caption', 'label' => 'Hero caption', 'name' => 'hero_caption',
                'type' => 'text',
            ],
            [
                'key' => 'field_klo_brief_label_zh', 'label' => 'Brief label (Chinese)', 'name' => 'brief_label_zh',
                'type' => 'text', 'default_value' => '簡介',
                'instructions' => 'Defaults to the Brief stamp.',
            ],
            [
                'key' => 'field_klo_brief_heading', 'label' => 'Brief heading', 'name' => 'brief_heading',
                'type' => 'text', 'required' => 1,
                'instructions' => 'E.g. "Sell a seat. Light the street." The template breaks the line where the period is.',
            ],
            [
                'key' => 'field_klo_brief_body', 'label' => 'Brief body', 'name' => 'brief_body',
                'type' => 'wysiwyg', 'required' => 1, 'toolbar' => 'basic', 'media_upload' => 0, 'delay' => 0,
                'instructions' => 'Three paragraphs.',
            ],
            [
                'key' => 'field_klo_stats', 'label' => 'Stats', 'name' => 'stats',
                'type' => 'repeater', 'min' => 0, 'max' => 4, 'layout' => 'table',
                'button_label' => 'Add stat',
                'sub_fields' => [
                    ['key' => 'field_klo_stat_value', 'label' => 'Value', 'name' => 'value', 'type' => 'text'],
                    ['key' => 'field_klo_stat_label', 'label' => 'Label', 'name' => 'label', 'type' => 'text'],
                ],
            ],
            [
                'key' => 'field_klo_screens_heading', 'label' => 'Screens heading', 'name' => 'screens_heading',
                'type' => 'text',
                'instructions' => 'E.g. "Pick a film. Pick a seat."',
            ],
            [
                'key' => 'field_klo_screens_desktop', 'label' => 'Screens - desktop', 'name' => 'screens_desktop',
                'type' => 'image', 'return_format' => 'array', 'preview_size' => 'medium',
                'instructions' => 'Website screenshot, 16:10.',
            ],
            [
                'key' => 'field_klo_screens_mobile', 'label' => 'Screens - mobile', 'name' => 'screens_mobile',
                'type' => 'image', 'return_format' => 'array', 'preview_size' => 'medium',
                'instructions' => 'Website screenshot, 9:19.5.',
            ],
            [
                'key' => 'field_klo_screens_caption', 'label' => 'Screens caption', 'name' => 'screens_caption',
                'type' => 'text',
            ],
            [
                'key' => 'field_klo_details', 'label' => 'Details', 'name' => 'details',
                'type' => 'repeater', 'min' => 0, 'max' => 2, 'layout' => 'block',
                'button_label' => 'Add detail image',
                'sub_fields' => [
                    [
                        'key' => 'field_klo_detail_image', 'label' => 'Image', 'name' => 'image',
                        'type' => 'image', 'return_format' => 'array', 'preview_size' => 'medium',
                        'instructions' => '4:5 portrait.',
                    ],
                    ['key' => 'field_klo_detail_caption', 'label' => 'Caption', 'name' => 'caption', 'type' => 'text'],
                ],
            ],
            [
                'key' => 'field_klo_quote_text', 'label' => 'Quote', 'name' => 'quote_text',
                'type' => 'textarea', 'rows' => 3,
            ],
            [
                'key' => 'field_klo_quote_name', 'label' => 'Quote - name', 'name' => 'quote_name',
                'type' => 'text',
            ],
            [
                'key' => 'field_klo_quote_role', 'label' => 'Quote - role', 'name' => 'quote_role',
                'type' => 'text',
            ],
            [
                'key' => 'field_klo_what_we_did', 'label' => 'What we did', 'name' => 'what_we_did',
                'type' => 'textarea', 'rows' => 5,
                'instructions' => 'One item per line.',
            ],
            [
                'key' => 'field_klo_stack', 'label' => 'Stack', 'name' => 'stack',
                'type' => 'text',
            ],
            [
                'key' => 'field_klo_team', 'label' => 'Team', 'name' => 'team',
                'type' => 'repeater', 'min' => 0, 'layout' => 'table',
                'button_label' => 'Add team member',
                'sub_fields' => [
                    [
                        'key' => 'field_klo_team_person', 'label' => 'Person', 'name' => 'person',
                        'type' => 'post_object', 'post_type' => ['person'],
                        'return_format' => 'object', 'ui' => 1,
                    ],
                    ['key' => 'field_klo_team_role', 'label' => 'Role on job', 'name' => 'role_on_job', 'type' => 'text'],
                ],
            ],
            [
                'key' => 'field_klo_visit_url', 'label' => 'Visit URL', 'name' => 'visit_url',
                'type' => 'url',
            ],
            [
                'key' => 'field_klo_visit_label', 'label' => 'Visit label', 'name' => 'visit_label',
                'type' => 'text',
            ],
        ],
    ]);

    /* ---- CPT person --------------------------------------------------- */
    acf_add_local_field_group([
        'key' => 'group_klo_person',
        'title' => 'Person',
        'location' => [[['param' => 'post_type', 'operator' => '==', 'value' => 'person']]],
        'menu_order' => 0,
        'position' => 'normal',
        'style' => 'default',
        'label_placement' => 'top',
        'instruction_placement' => 'label',
        'active' => true,
        'fields' => [
            ['key' => 'field_klo_person_short', 'label' => 'Short name', 'name' => 'short_name', 'type' => 'text', 'required' => 1,
             'instructions' => 'E.g. "Sze".'],
            ['key' => 'field_klo_person_full', 'label' => 'Full name', 'name' => 'full_name', 'type' => 'text', 'required' => 1],
            ['key' => 'field_klo_person_role', 'label' => 'Role', 'name' => 'role', 'type' => 'text', 'required' => 1],
            ['key' => 'field_klo_person_caption', 'label' => 'Caption', 'name' => 'caption', 'type' => 'text', 'required' => 1, 'maxlength' => 60],
            ['key' => 'field_klo_person_portrait', 'label' => 'Portrait', 'name' => 'portrait', 'type' => 'image', 'required' => 1,
             'return_format' => 'array', 'preview_size' => 'medium', 'min_width' => 1200, 'min_height' => 1200,
             'instructions' => 'Square, minimum 1200 x 1200 px.'],
            ['key' => 'field_klo_person_order', 'label' => 'Order', 'name' => 'order', 'type' => 'number', 'required' => 1,
             'instructions' => 'People are ordered by this number.'],
        ],
    ]);

    /* ---- CPT archive_sign ---------------------------------------------- */
    acf_add_local_field_group([
        'key' => 'group_klo_archive_sign',
        'title' => 'Archive Sign',
        'location' => [[['param' => 'post_type', 'operator' => '==', 'value' => 'archive_sign']]],
        'menu_order' => 0,
        'position' => 'normal',
        'style' => 'default',
        'label_placement' => 'top',
        'instruction_placement' => 'label',
        'active' => true,
        'fields' => [
            ['key' => 'field_klo_sign_number', 'label' => 'Sign number', 'name' => 'sign_number', 'type' => 'number', 'required' => 1,
             'instructions' => 'Signs are ordered by this number.'],
            ['key' => 'field_klo_sign_name', 'label' => 'Sign name', 'name' => 'sign_name', 'type' => 'text', 'required' => 1],
            ['key' => 'field_klo_sign_text', 'label' => 'Sign text', 'name' => 'sign_text', 'type' => 'text',
             'instructions' => 'The characters on the sign; used for alt text.'],
            ['key' => 'field_klo_sign_street', 'label' => 'Street', 'name' => 'street', 'type' => 'text', 'required' => 1],
            ['key' => 'field_klo_sign_lit_from', 'label' => 'Lit from (year)', 'name' => 'lit_from', 'type' => 'number', 'required' => 1],
            ['key' => 'field_klo_sign_lit_to', 'label' => 'Lit to (year)', 'name' => 'lit_to', 'type' => 'number', 'required' => 1],
            ['key' => 'field_klo_sign_rescued', 'label' => 'Rescued (year)', 'name' => 'rescued_year', 'type' => 'number', 'required' => 1],
            ['key' => 'field_klo_sign_photo', 'label' => 'Photo', 'name' => 'photo', 'type' => 'image', 'required' => 1,
             'return_format' => 'array', 'preview_size' => 'medium',
             'instructions' => '4:3 landscape.'],
        ],
    ]);

    /* ---- Notebook posts ------------------------------------------------- */
    acf_add_local_field_group([
        'key' => 'group_klo_post',
        'title' => 'Notebook Entry',
        'location' => [[['param' => 'post_type', 'operator' => '==', 'value' => 'post']]],
        'menu_order' => 0,
        'position' => 'normal',
        'style' => 'default',
        'label_placement' => 'top',
        'instruction_placement' => 'label',
        'active' => true,
        'fields' => [
            ['key' => 'field_klo_post_author', 'label' => 'Author', 'name' => 'author_person', 'type' => 'post_object',
             'required' => 1, 'post_type' => ['person'], 'return_format' => 'object', 'ui' => 1,
             'instructions' => 'Drives the byline, e.g. "By Tommy Leung - Master Bender". Keep the core excerpt to 160 characters or fewer.'],
            ['key' => 'field_klo_post_read_time', 'label' => 'Read time (minutes)', 'name' => 'read_time', 'type' => 'number', 'required' => 1, 'min' => 1],
            ['key' => 'field_klo_post_cover_caption', 'label' => 'Cover caption', 'name' => 'cover_caption', 'type' => 'text',
             'instructions' => 'The featured image is the 3:2 cover.'],
        ],
    ]);

    /* ---- Term fields: bend_category -------------------------------------- */
    acf_add_local_field_group([
        'key' => 'group_klo_bend_category',
        'title' => 'Bend Category',
        'location' => [[['param' => 'taxonomy', 'operator' => '==', 'value' => 'bend_category']]],
        'menu_order' => 0,
        'position' => 'normal',
        'style' => 'default',
        'label_placement' => 'top',
        'instruction_placement' => 'label',
        'active' => true,
        'fields' => [
            ['key' => 'field_klo_bcat_stamp', 'label' => 'Chinese stamp', 'name' => 'zh_stamp', 'type' => 'text',
             'instructions' => 'Fixed per term: Identity / Web / Signage and Light / Motion.'],
            ['key' => 'field_klo_bcat_ground', 'label' => 'Card ground', 'name' => 'card_ground', 'type' => 'select',
             'choices' => ['night' => 'Night', 'soot' => 'Soot', 'jade' => 'Jade'],
             'default_value' => 'night', 'return_format' => 'value', 'ui' => 1,
             'instructions' => 'Identity = night, Web = soot, Signage and Light = night, Motion = jade.'],
        ],
    ]);

    /* ---- Term fields: post categories ------------------------------------- */
    acf_add_local_field_group([
        'key' => 'group_klo_category',
        'title' => 'Notebook Category',
        'location' => [[['param' => 'taxonomy', 'operator' => '==', 'value' => 'category']]],
        'menu_order' => 0,
        'position' => 'normal',
        'style' => 'default',
        'label_placement' => 'top',
        'instruction_placement' => 'label',
        'active' => true,
        'fields' => [
            ['key' => 'field_klo_cat_stamp', 'label' => 'Chinese stamp', 'name' => 'zh_stamp', 'type' => 'text',
             'instructions' => 'Fixed per term: Glass / Type / Studio.'],
        ],
    ]);

    /* ---- Options page ------------------------------------------------------ */
    acf_add_options_page([
        'page_title' => 'KLO Settings',
        'menu_title' => 'KLO Settings',
        'menu_slug' => 'klo-settings',
        'capability' => 'edit_posts',
        'redirect' => false,
        'icon_url' => 'dashicons-lightbulb',
    ]);

    acf_add_local_field_group([
        'key' => 'group_klo_options',
        'title' => 'KLO Settings',
        'location' => [[['param' => 'options_page', 'operator' => '==', 'value' => 'klo-settings']]],
        'menu_order' => 0,
        'position' => 'normal',
        'style' => 'default',
        'label_placement' => 'top',
        'instruction_placement' => 'label',
        'active' => true,
        'fields' => [
            ['key' => 'field_klo_opt_address', 'label' => 'Address lines', 'name' => 'address_lines', 'type' => 'textarea', 'rows' => 3],
            ['key' => 'field_klo_opt_email', 'label' => 'Email', 'name' => 'email', 'type' => 'email',
             'instructions' => 'hello@lightoffice.hk'],
            ['key' => 'field_klo_opt_hours', 'label' => 'Hours', 'name' => 'hours', 'type' => 'text'],
            ['key' => 'field_klo_opt_visit_note', 'label' => 'Visit note', 'name' => 'visit_note', 'type' => 'text'],
            [
                'key' => 'field_klo_opt_socials', 'label' => 'Socials', 'name' => 'socials',
                'type' => 'repeater', 'min' => 0, 'layout' => 'table', 'button_label' => 'Add social link',
                'sub_fields' => [
                    ['key' => 'field_klo_social_label', 'label' => 'Label', 'name' => 'label', 'type' => 'text'],
                    ['key' => 'field_klo_social_url', 'label' => 'URL', 'name' => 'url', 'type' => 'url'],
                ],
            ],
            [
                'key' => 'field_klo_opt_services', 'label' => 'Services', 'name' => 'services',
                'type' => 'repeater', 'min' => 0, 'max' => 6, 'layout' => 'block', 'button_label' => 'Add service',
                'instructions' => 'Exactly 6 services per the bible count. The Home list can also be built as static rows; use this repeater to edit them in one place.',
                'sub_fields' => [
                    ['key' => 'field_klo_service_number', 'label' => 'Number', 'name' => 'number', 'type' => 'text'],
                    ['key' => 'field_klo_service_name', 'label' => 'Name', 'name' => 'name', 'type' => 'text'],
                    ['key' => 'field_klo_service_desc', 'label' => 'Description', 'name' => 'description', 'type' => 'textarea', 'rows' => 2],
                ],
            ],
        ],
    ]);
});

/* -------------------------------------------------------------------------
 * bend_number uniqueness (the content model marks it unique)
 * ---------------------------------------------------------------------- */

add_filter('acf/validate_value/key=field_klo_bend_number', function ($valid, $value, $field, $input) {
    if ($valid !== true || $value === '' || $value === null) {
        return $valid;
    }
    $post_id = isset($_POST['post_ID']) ? (int) $_POST['post_ID'] : 0; // phpcs:ignore WordPress.Security.NonceVerification
    $found = get_posts([
        'post_type' => 'bend',
        'post_status' => 'any',
        'posts_per_page' => 1,
        'exclude' => $post_id ? [$post_id] : [],
        'meta_query' => [[
            'key' => 'bend_number',
            'value' => $value,
            'compare' => '=',
        ]],
        'fields' => 'ids',
    ]);
    if (!empty($found)) {
        return 'Bend number ' . $value . ' is already used by another bend. Numbers must be unique (1-99).';
    }
    return $valid;
}, 10, 4);
