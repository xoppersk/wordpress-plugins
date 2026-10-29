<?php
/**
 * Plugin Name: KLO Render (templates support)
 * Description: Shortcodes that compose multi-field values for the Kowloon Light Office
 *              Elementor templates (ACF repeaters, term stamps, options, search groups).
 *              Scalar fields in the templates bind Elementor ACF dynamic tags directly;
 *              these shortcodes cover only what a single dynamic tag cannot express.
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style(
        'klo-render',
        content_url('mu-plugins/klo-render.css'),
        [],
        '1.0.13'
    );
});

/* ---------------------------------------------------------------- helpers */

function klo_cur_id() {
    /* The current post in context: inside a Loop Grid item this is the
       loop post; on a single it is the post itself. get_queried_object_id
       must NOT win — on pages/archives it points at the page/term while a
       loop item is rendering someone else's fields. */
    $id = get_the_ID();
    if ($id) {
        return (int) $id;
    }
    $id = get_queried_object_id();
    if ($id) {
        return $id;
    }
    global $post;
    return $post ? (int) $post->ID : 0;
}

function klo_bend_term($bend_id) {
    $terms = get_the_terms($bend_id, 'bend_category');
    if (!$terms || is_wp_error($terms)) {
        return null;
    }
    return $terms[0];
}

function klo_term_stamp($term) {
    if (!$term) {
        return '';
    }
    $v = get_field('zh_stamp', 'bend_category_' . $term->term_id);
    return $v ? (string) $v : '';
}

function klo_cat_stamp($term) {
    if (!$term) {
        return '';
    }
    $v = get_field('zh_stamp', 'category_' . $term->term_id);
    return $v ? (string) $v : '';
}

function klo_opt($field, $fallback = '') {
    $v = function_exists('get_field') ? get_field($field, 'option') : null;
    if ($v === null || $v === '' || $v === false) {
        return $fallback;
    }
    return $v;
}

/* ------------------------------------------- Keep Reading (Single — Post) */

add_action('elementor/query/klo_keep_reading', function ($query) {
    $id = get_queried_object_id();
    $query->set('post_type', 'post');
    $query->set('post_status', 'publish');
    if ($id) {
        /* canon names the Keep Reading pair for the one designed
           entry ("Why neon red isn't red"): Back-Paint, then Tommy's Bench,
           Inventoried. A same-category query can only yield Back-Paint
           (Glass holds two posts), so the canon pair is pinned by slug;
           every other entry falls back to same-category below. */
        $canon_pairs = [
            'why-neon-red-isnt-red' => ['back-paint-the-art-of-switching-light-off', 'tommys-bench-inventoried'],
        ];
        $slug = get_post_field('post_name', $id);
        if (isset($canon_pairs[$slug])) {
            $ids = [];
            foreach ($canon_pairs[$slug] as $s) {
                $p = get_page_by_path($s, OBJECT, 'post');
                if ($p) $ids[] = $p->ID;
            }
            if (count($ids) === 2) {
                $query->set('post__in', $ids);
                $query->set('orderby', 'post__in');
                return;
            }
        }
        $cats = wp_get_post_categories($id);
        if ($cats) {
            $query->set('category__in', $cats);
        }
        $query->set('post__not_in', [$id]);
    }
});

/* ------------------------------------------------------- generic shortcodes */

add_shortcode('klo_field', function ($atts) {
    $atts = shortcode_atts(['field' => ''], $atts);
    if ($atts['field'] === '') {
        return '';
    }
    $v = get_field($atts['field'], klo_cur_id());
    if (is_array($v) || is_object($v)) {
        return '';
    }
    return esc_html((string) $v);
});

add_shortcode('klo_quote_by', function () {
    $id = klo_cur_id();
    $name = (string) get_field('quote_name', $id);
    $role = (string) get_field('quote_role', $id);
    return esc_html(trim($name . ($name && $role ? ' · ' : '') . $role));
});

add_shortcode('klo_post_cat', function () {
    $cat = klo_post_cat(klo_cur_id());
    return $cat ? esc_html($cat->name) : '';
});

/* the entry H1 (Single — Post). The design shows the title in
   display caps with its final word lit in pink neon ("…RED."), and a
   dynamic post-title tag cannot carry a span — so the title is composed
   here. The lit treatment is pinned to the one designed entry (canon
   names no lit word for any other entry); the trailing period is the
   design's display form of the title. Styled by .klo-entry-title in
   klo-render.css (the klo-d-l metrics). */
add_shortcode('klo_post_title', function () {
    $id = klo_cur_id();
    $title = get_the_title($id);
    $lit_slugs = ['why-neon-red-isnt-red'];
    if (in_array(get_post_field('post_name', $id), $lit_slugs, true)) {
        if (substr($title, -1) !== '.') {
            $title .= '.';
        }
        $words = explode(' ', esc_html($title));
        $last = array_pop($words);
        return '<h1 class="klo-entry-title">' . implode(' ', $words) . ' <span class="klo-lit">' . $last . '</span></h1>';
    }
    return '<h1 class="klo-entry-title">' . esc_html($title) . '</h1>';
});

/**
 * The whole "Website" panel of Single — Bend, rendered only when the bend
 * actually has screens and/or detail images (build spec 3.3, Bend — 03).
 * The panel markup carries the klo-panel class, so the kit CSS draws the
 * ground, grid and One Line exactly as on an Elementor-built panel.
 */
add_shortcode('klo_bend_media', function () {
    $id = klo_cur_id();
    $heading = (string) get_field('screens_heading', $id);
    $desk = get_field('screens_desktop', $id);
    $mob = get_field('screens_mobile', $id);
    $caption = (string) get_field('screens_caption', $id);
    $details = get_field('details', $id);
    $has_screens = $heading !== '' || is_array($desk) || is_array($mob);
    $has_details = is_array($details) && count($details) > 0;
    if (!$has_screens && !$has_details) {
        return '';
    }
    $out = '<section class="klo-panel klo-bend-media">';
    if ($has_screens) {
        $out .= '<div class="klo-label">The website · <span class="stamp">網站</span></div>';
        if ($heading !== '') {
            $out .= '<h2 class="klo-media-h2">' . esc_html($heading) . '</h2>';
        }
        $out .= '<div class="klo-screens-grid">';
        if (is_array($desk) && !empty($desk['url'])) {
            $src = $desk['sizes']['large'] ?? $desk['url'];
            $out .= '<figure class="klo-screen-card klo-screen-card--desk"><img src="' . esc_url($src) . '" alt="' . esc_attr($desk['alt'] ?? '') . '" loading="lazy"></figure>';
        }
        if (is_array($mob) && !empty($mob['url'])) {
            $src = $mob['sizes']['large'] ?? $mob['url'];
            $out .= '<figure class="klo-screen-card klo-screen-card--mob"><img src="' . esc_url($src) . '" alt="' . esc_attr($mob['alt'] ?? '') . '" loading="lazy"></figure>';
        }
        $out .= '</div>';
        if ($caption !== '') {
            $out .= '<p class="klo-media-caption">' . esc_html($caption) . '</p>';
        }
    }
    if ($has_details) {
        $out .= do_shortcode('[klo_details]');
    }
    return $out . '</section>';
});

add_shortcode('klo_search_static_summary', function () {
    return 'Results for &ldquo;' . esc_html(get_search_query()) . '&rdquo;';
});

add_shortcode('klo_search_empty_body', function () {
    return 'No bends, signs or notes match &ldquo;' . esc_html(get_search_query()) . '&rdquo;. '
        . 'Try a client name, a street, or a word like glass, type or sign.';
});

add_shortcode('klo_title', function () {
    return esc_html(get_the_title(klo_cur_id()));
});

add_shortcode('klo_count', function ($atts) {
    $atts = shortcode_atts(['type' => 'bend'], $atts);
    $c = wp_count_posts($atts['type']);
    return isset($c->publish) ? (string) (int) $c->publish : '0';
});

add_shortcode('klo_option', function ($atts) {
    $atts = shortcode_atts(['field' => '', 'fallback' => '', 'link' => ''], $atts);
    if ($atts['field'] === 'address_lines') {
        $v = klo_opt('address_lines', "2/F, 41 Reclamation Street\nYau Ma Tei, Kowloon, Hong Kong");
        return nl2br(esc_html((string) $v));
    }
    if ($atts['field'] === 'email') {
        $v = (string) klo_opt('email', 'hello@lightoffice.hk');
        if ($atts['link'] === 'mailto') {
            return '<a href="mailto:' . esc_attr($v) . '">' . esc_html($v) . '</a>';
        }
        return esc_html($v);
    }
    if ($atts['field'] === 'hours') {
        return esc_html((string) klo_opt('hours', 'Mon–Fri, 10am–7pm'));
    }
    if ($atts['field'] === 'visit_note') {
        return esc_html((string) klo_opt('visit_note', 'Bench visits by appointment'));
    }
    return esc_html((string) klo_opt($atts['field'], $atts['fallback']));
});

/* ------------------------------------------------------------ bend pieces */

add_shortcode('klo_bend_card_label', function () {
    $id = klo_cur_id();
    $n = get_field('bend_number', $id);
    $stamp = klo_term_stamp(klo_bend_term($id));
    $out = '<span class="klo-bend-pre">Bend </span>No. ' . esc_html((string) $n);
    if ($stamp !== '') {
        $out .= ' · ' . esc_html($stamp);
    }
    return $out;
});

add_shortcode('klo_bend_card_sub', function () {
    $id = klo_cur_id();
    $term = klo_bend_term($id);
    $parts = [];
    if ($term) {
        $parts[] = $term->name;
    }
    $place = (string) get_field('place', $id);
    $year = (string) get_field('year', $id);
    $py = trim($place . ($place && $year ? ', ' : '') . $year);
    if ($py !== '') {
        $parts[] = $py;
    }
    return esc_html(implode(' · ', $parts));
});

add_shortcode('klo_bend_hero_label', function () {
    $id = klo_cur_id();
    $n = get_field('bend_number', $id);
    $term = klo_bend_term($id);
    $out = 'Bend No. ' . esc_html((string) $n);
    if ($term) {
        $stamp = klo_term_stamp($term);
        if ($stamp !== '') {
            $out .= ' · ' . esc_html($stamp);
        }
        $out .= ' · ' . esc_html($term->name);
    }
    return $out;
});

add_shortcode('klo_bend_crumb', function () {
    /* the Bend single breadcrumb is "Bends · {Category}" (design +
       canon), not "Bends → {post title}". Prints the category term name. */
    $term = klo_bend_term(klo_cur_id());
    return $term ? esc_html($term->name) : '';
});

add_shortcode('klo_prevnext', function () {
    /* prev/next as true cards (build spec 3.3 Bend — 05 anatomy +
       canon prev/next lines). The Post Navigation widget cannot print
       neighbour ACF data and its own stylesheet forces 50%-wide nowrap
       links, so the cards are rendered here. Neighbours by bend_number. */
    $id = klo_cur_id();
    if (get_post_type($id) !== 'bend') {
        return '';
    }
    $all = get_posts([
        'post_type' => 'bend', 'posts_per_page' => -1, 'post_status' => 'publish',
        'meta_key' => 'bend_number', 'orderby' => 'meta_value_num', 'order' => 'ASC',
    ]);
    $idx = null;
    foreach ($all as $i => $bp) {
        if ($bp->ID === $id) { $idx = $i; break; }
    }
    if ($idx === null) {
        return '';
    }
    $card = function ($bp, $dir) {
        $n = get_field('bend_number', $bp->ID);
        $term = klo_bend_term($bp->ID);
        $meta = [];
        if ($term) {
            $meta[] = $term->name;
        }
        $place = (string) get_field('place', $bp->ID);
        $year = (string) get_field('year', $bp->ID);
        $py = trim($place . ($place && $year ? ', ' : '') . $year);
        if ($py !== '') {
            $meta[] = $py;
        }
        $label = $dir === 'prev'
            ? '&larr; Previous &middot; Bend No. ' . esc_html((string) $n)
            : 'Bend No. ' . esc_html((string) $n) . ' &middot; Next &rarr;';
        return '<a class="klo-pn-card klo-pn-card--' . $dir . '" href="' . esc_url(get_permalink($bp->ID)) . '">'
            . '<span class="klo-pn-label">' . $label . '</span>'
            . '<span class="klo-pn-title">' . esc_html((string) get_field('client_name', $bp->ID)) . '</span>'
            . ($meta ? '<span class="klo-pn-meta">' . esc_html(implode(' · ', $meta)) . '</span>' : '')
            . '</a>';
    };
    $prev = $idx > 0 ? $all[$idx - 1] : null;
    $next = $idx < count($all) - 1 ? $all[$idx + 1] : null;
    if (!$prev && !$next) {
        return '';
    }
    $out = '<div class="klo-prevnext-cards">';
    if ($prev) {
        $out .= $card($prev, 'prev');
    }
    if ($next) {
        $c = $card($next, 'next');
        if (!$prev) {
            $c = str_replace('klo-pn-card--next', 'klo-pn-card--next klo-pn-card--only', $c);
        }
        $out .= $c;
    }
    return $out . '</div>';
});

add_shortcode('klo_card_video', function () {
    $id = klo_cur_id();
    $v = get_field('card_video', $id);
    if (!is_array($v) || empty($v['url'])) {
        return '';
    }
    return '<video class="klo-card-video" src="' . esc_url($v['url']) . '" autoplay muted loop playsinline preload="metadata" aria-hidden="true"></video>';
});

add_shortcode('klo_stats', function () {
    $id = klo_cur_id();
    $rows = get_field('stats', $id);
    if (!is_array($rows) || !$rows) {
        return '';
    }
    $out = '<div class="klo-stats-grid">';
    foreach ($rows as $r) {
        $out .= '<div class="klo-stat-cell"><div class="klo-stat"><span class="elementor-heading-title">'
            . esc_html((string) ($r['value'] ?? '')) . '</span></div><div class="klo-label klo-muted">'
            . esc_html((string) ($r['label'] ?? '')) . '</div></div>';
    }
    return $out . '</div>';
});

add_shortcode('klo_details', function () {
    $id = klo_cur_id();
    $rows = get_field('details', $id);
    if (!is_array($rows) || !$rows) {
        return '';
    }
    $out = '<div class="klo-details-grid">';
    foreach ($rows as $r) {
        $img = $r['image'] ?? null;
        if (!is_array($img) || empty($img['url'])) {
            continue;
        }
        $src = $img['sizes']['large'] ?? $img['url'];
        $out .= '<figure class="klo-detail"><img src="' . esc_url($src) . '" alt="' . esc_attr($img['alt'] ?? '') . '" loading="lazy">'
            . '<figcaption>' . esc_html((string) ($r['caption'] ?? '')) . '</figcaption></figure>';
    }
    return $out . '</div>';
});

add_shortcode('klo_what_we_did', function () {
    $id = klo_cur_id();
    $v = (string) get_field('what_we_did', $id);
    if (trim($v) === '') {
        return '';
    }
    $lines = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $v)));
    $out = '<div class="klo-lines">';
    foreach ($lines as $l) {
        $out .= '<div>' . esc_html($l) . '</div>';
    }
    return $out . '</div>';
});

add_shortcode('klo_team', function () {
    $id = klo_cur_id();
    $rows = get_field('team', $id);
    if (!is_array($rows) || !$rows) {
        return '';
    }
    $out = '<div class="klo-lines">';
    foreach ($rows as $r) {
        $person = $r['person'] ?? null;
        if (!is_object($person)) {
            continue;
        }
        $name = get_field('short_name', $person->ID);
        if (!$name) {
            $name = $person->post_title;
        }
        $role = (string) ($r['role_on_job'] ?? '');
        $out .= '<div><span class="klo-team-name">' . esc_html((string) $name) . '</span>'
            . ($role !== '' ? ' — ' . esc_html($role) : '') . '</div>';
    }
    return $out . '</div>';
});

add_shortcode('klo_visit', function () {
    $id = klo_cur_id();
    $url = (string) get_field('visit_url', $id);
    $label = (string) get_field('visit_label', $id);
    if ($url === '' && $label === '') {
        return '';
    }
    $out = '';
    if ($label !== '') {
        $out .= '<div class="klo-visit-label">' . esc_html($label) . '</div>';
    }
    if ($url !== '') {
        $out .= '<a class="klo-visit-link" href="' . esc_url($url) . '" target="_blank" rel="noopener">Visit the site</a>';
    }
    return $out;
});

/* ------------------------------------------------------------ post pieces */

function klo_post_cat($post_id) {
    $cats = get_the_category($post_id);
    return $cats ? $cats[0] : null;
}

function klo_author_name($post_id) {
    $ap = get_field('author_person', $post_id);
    if (is_object($ap)) {
        $n = get_field('full_name', $ap->ID);
        return $n ? (string) $n : $ap->post_title;
    }
    return '';
}

add_shortcode('klo_post_byline', function () {
    $id = klo_cur_id();
    $ap = get_field('author_person', $id);
    $parts = [];
    $name = klo_author_name($id);
    if ($name !== '') {
        $by = 'By ' . $name;
        if (is_object($ap)) {
            $role = get_field('role', $ap->ID);
            if ($role) {
                $by .= ' · ' . $role;
            }
        }
        $parts[] = $by;
    }
    $parts[] = get_the_date('j M Y', $id);
    $rt = get_field('read_time', $id);
    if ($rt) {
        $parts[] = (string) $rt . ' min read';
    }
    return esc_html(implode(' · ', $parts));
});

add_shortcode('klo_entry_label', function () {
    $id = klo_cur_id();
    $parts = [];
    $cat = klo_post_cat($id);
    if ($cat) {
        $parts[] = $cat->name;
        $stamp = klo_cat_stamp($cat);
        if ($stamp !== '') {
            $parts[] = $stamp;
        }
    }
    $parts[] = get_the_date('j M Y', $id);
    $rt = get_field('read_time', $id);
    if ($rt) {
        $parts[] = (string) $rt . ' min read';
    }
    return esc_html(implode(' · ', $parts));
});

add_shortcode('klo_entry_label_short', function () {
    $id = klo_cur_id();
    $parts = [];
    $cat = klo_post_cat($id);
    if ($cat) {
        $parts[] = $cat->name;
    }
    $parts[] = get_the_date('j M Y', $id);
    $rt = get_field('read_time', $id);
    if ($rt) {
        $parts[] = (string) $rt . ' min read';
    }
    return esc_html(implode(' · ', $parts));
});

add_shortcode('klo_entry_author', function () {
    $name = klo_author_name(klo_cur_id());
    return $name !== '' ? esc_html('By ' . $name) : '';
});

add_shortcode('klo_filed_under', function () {
    $id = klo_cur_id();
    $cat = klo_post_cat($id);
    if (!$cat) {
        return '';
    }
    $out = 'Filed under ' . $cat->name;
    $stamp = klo_cat_stamp($cat);
    if ($stamp !== '') {
        $out .= ' · ' . $stamp;
    }
    return esc_html($out);
});

/* ------------------------------------------------------------ sign pieces */

add_shortcode('klo_sign_label', function () {
    $id = klo_cur_id();
    $n = get_field('sign_number', $id);
    $street = (string) get_field('street', $id);
    $out = 'No. ' . esc_html((string) $n);
    if ($street !== '') {
        $out .= ' · ' . esc_html($street);
    }
    return $out;
});

add_shortcode('klo_sign_lit', function () {
    $id = klo_cur_id();
    return 'Lit ' . esc_html((string) get_field('lit_from', $id)) . '–' . esc_html((string) get_field('lit_to', $id));
});

add_shortcode('klo_sign_rescued', function () {
    $id = klo_cur_id();
    return 'Rescued ' . esc_html((string) get_field('rescued_year', $id));
});

/* ----------------------------------------------------------- person piece */

add_shortcode('klo_person_label', function () {
    $id = klo_cur_id();
    $order = get_field('order', $id);
    $role = (string) get_field('role', $id);
    $out = 'No. ' . esc_html((string) $order);
    if ($role !== '') {
        $out .= ' · ' . esc_html($role);
    }
    return $out;
});

/* ------------------------------------------------------------ search bits */

function klo_search_count_for($post_type) {
    $term = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : get_search_query();
    $q = new WP_Query([
        'post_type' => $post_type,
        'post_status' => 'publish',
        's' => $term,
        'posts_per_page' => 1,
        'fields' => 'ids',
        'no_found_rows' => false,
    ]);
    $n = (int) $q->found_posts;
    wp_reset_postdata();
    return $n;
}

add_shortcode('klo_search_group_label', function ($atts) {
    $atts = shortcode_atts(['type' => 'bends'], $atts);
    $map = [
        'bends' => ['Bends', 'bend'],
        'posts' => ['Notebook', 'post'],
        'signs' => ['Sign Archive', 'archive_sign'],
    ];
    if (!isset($map[$atts['type']])) {
        return '';
    }
    [$label, $pt] = $map[$atts['type']];
    return esc_html($label . ' · ' . klo_search_count_for($pt));
});

add_shortcode('klo_search_query', function () {
    return esc_html(get_search_query());
});

add_shortcode('klo_search_meta', function () {
    $id = klo_cur_id();
    $type = get_post_type($id);
    if ($type === 'bend') {
        $term = klo_bend_term($id);
        $out = 'Bend No. ' . (string) get_field('bend_number', $id);
        if ($term) {
            $out .= ' · ' . $term->name;
        }
        return esc_html($out);
    }
    if ($type === 'archive_sign') {
        return esc_html('Sign No. ' . (string) get_field('sign_number', $id) . ' · ' . (string) get_field('street', $id));
    }
    $cat = klo_post_cat($id);
    $out = 'Notebook';
    if ($cat) {
        $out .= ' · ' . $cat->name;
    }
    $out .= ' · ' . get_the_date('j M Y', $id);
    return esc_html($out);
});

add_shortcode('klo_search_dek', function () {
    $id = klo_cur_id();
    $type = get_post_type($id);
    if ($type === 'bend') {
        $text = (string) get_field('one_line', $id);
    } elseif ($type === 'archive_sign') {
        $text = 'Lit ' . (string) get_field('lit_from', $id) . '–' . (string) get_field('lit_to', $id)
            . ' · Rescued ' . (string) get_field('rescued_year', $id) . '.';
    } else {
        $text = get_the_excerpt($id);
    }
    $html = esc_html($text);
    // the matched word lights Tube Pink in the snippet.
    // Highlight on the ESCAPED text, longest query words first.
    $q = trim((string) get_search_query(false));
    if ($q !== '') {
        $words = array_filter(preg_split('/\s+/', $q), function ($w) {
            return mb_strlen($w) >= 2;
        });
        if ($words) {
            usort($words, function ($a, $b) {
                return mb_strlen($b) - mb_strlen($a);
            });
            $alts = array_map(function ($w) {
                return preg_quote(esc_html($w), '/');
            }, $words);
            $html = preg_replace('/(' . implode('|', $alts) . ')/iu', '<mark class="klo-hl">$1</mark>', $html);
        }
    }
    return $html;
});

add_shortcode('klo_search_thumb', function () {
    $id = klo_cur_id();
    $type = get_post_type($id);
    $att = 0;
    if ($type === 'bend') {
        $m = get_field('card_media', $id);
        $att = is_array($m) ? (int) ($m['ID'] ?? 0) : (int) $m;
    } elseif ($type === 'archive_sign') {
        $m = get_field('photo', $id);
        $att = is_array($m) ? (int) ($m['ID'] ?? 0) : (int) $m;
    } else {
        $att = (int) get_post_thumbnail_id($id);
    }
    if (!$att) {
        return '';
    }
    $src = wp_get_attachment_image_src($att, 'medium');
    if (!$src) {
        return '';
    }
    return '<img class="klo-search-thumb" src="' . esc_url($src[0]) . '" alt="" loading="lazy">';
});
