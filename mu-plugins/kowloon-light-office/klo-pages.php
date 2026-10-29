<?php
/**
 * Plugin Name: KLO Pages
 * Description: Deterministic ordering for the page-level Loop widgets built by
 *              the page templates (people by ACF `order`, signs by ACF `sign_number`).
 *              Bend ordering is already handled by Scroll Chapters helper 4.
 */

add_action('elementor/query/klo_people_order', function ($query) {
    $query->set('meta_key', 'order');
    $query->set('orderby', 'meta_value_num');
    $query->set('order', 'ASC');
});

add_action('elementor/query/klo_signs_order', function ($query) {
    $query->set('meta_key', 'sign_number');
    $query->set('orderby', 'meta_value_num');
    $query->set('order', 'ASC');
});

/**
 * Thanks-page personalised line.
 *
 * Two empirics forced this route (PAGES-LOG): (1) the spec's ?name= redirect
 * collides with WordPress's reserved post-slug query var `name` and 404s the
 * Thanks page for real submissions, so the form passes ?visitor= instead;
 * (2) Pro 4.3.2's Request Parameter tag renders the bare value/fallback only
 * on the text-editor path — its before/after settings never reach the output.
 */
add_shortcode('klo_thanks_line', function () {
    $name = isset($_GET['visitor']) ? sanitize_text_field(wp_unslash($_GET['visitor'])) : '';
    if ($name === '') $name = 'there';
    return 'Thanks, ' . esc_html($name) . '. We read every message and reply within two working days. A copy of what you sent is on its way to your inbox.';
});
