<?php
/**
 * Plugin Name: KLO Archive Fix — Bends archive shows all 12
 * Description: The Archive — Bends Loop Grid (template 152)
 *              already carries posts_per_page=12, but its source is
 *              current_query, so the loop renders the MAIN archive query —
 *              which WordPress caps at the reading default of 10. Bend No. 11
 *              (Mahjong Hour) and No. 12 (Last Lights) never rendered.
 *              Lift the cap on the bend archive's main query only.
 */

if (!defined('ABSPATH')) exit;

add_action('pre_get_posts', function ($q) {
    if (is_admin() || !$q->is_main_query()) return;
    if ($q->is_post_type_archive('bend')) {
        $q->set('posts_per_page', -1);
    }
});

/**
 * bend filter chip order. The Taxonomy Filter widget fetches its
 * terms with no orderby (this Elementor Pro version exposes no order
 * control), so chips came out alphabetical (All, Identity, Motion,
 * Signage and Light, Web). The design's order is the design's creation
 * order: Identity, Web, Signage and Light, Motion = term_id ASC.
 * Scoped to bend_category only (Notebook's 'category' order is a separate
 * question, untouched here).
 */
add_filter('elementor/loop_taxonomy/args', function ($args) {
    if (isset($args['taxonomy']) && 'bend_category' === $args['taxonomy']) {
        $args['orderby'] = 'term_id';
        $args['order']   = 'ASC';
    }
    /* the Notebook chips had the same name-order defect: Glass,
       Studio, Type. The design's order is GLASS, TYPE, STUDIO, which is
       term_id ASC for the category terms (9, 10, 11). */
    if (isset($args['taxonomy']) && 'category' === $args['taxonomy']) {
        $args['orderby'] = 'term_id';
        $args['order']   = 'ASC';
    }
    return $args;
});

/**
 * the Bends Loop Grid (template 152, widget 96965e8) runs a
 * custom bend query with Query ID 'klo_bends_archive'. It cannot use
 * Current Query: Elementor_Post_Query returns the main query's vars
 * BEFORE the `elementor/query/query_args` filter runs, so the taxonomy
 * filter's term merge never applies to a Current Query loop — and in the
 * filter's REST refresh context the "current query" is the REST route's
 * own posts query. Pin this loop's order to bend_number ASC, the order
 * the archive has always served (No. 01 → No. 12).
 */
add_action('elementor/query/klo_bends_archive', function ($q) {
    $q->set('orderby', 'meta_value_num');
    $q->set('meta_key', 'bend_number');
    $q->set('order', 'ASC');
});

/**
 * the Notebook Loop Grid (template 153, widget 8bb9429) gets the
 * the same treatment: a custom post query with Query ID 'klo_notebook_archive'
 * replaces Current Query, whose vars return before the loop-filter module's
 * term merge ever runs (proven again on this template: the live refresh
 * endpoint returned all 6 entries for a "glass" filter). Pin the design's
 * order — newest first, the roster order the index has always shown.
 */
add_action('elementor/query/klo_notebook_archive', function ($q) {
    $q->set('post_type', 'post');
    $q->set('post_status', 'publish');
    $q->set('orderby', 'date');
    $q->set('order', 'DESC');
});
