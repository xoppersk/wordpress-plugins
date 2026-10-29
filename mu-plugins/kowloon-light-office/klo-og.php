<?php
/**
 * Plugin Name: KLO Open Graph
 * Description: Emits Open Graph + Twitter card tags site-wide, using the designed
 * OG plate (attachment 76, og-default.jpg, 1200x630).
 * No SEO plugin is installed on this site by design; titles/descriptions ship
 * with WordPress/Elementor as before — this plugin only adds the social tags.
 */
if (!defined('ABSPATH')) { exit; }

add_action('wp_head', function () {
    $img = wp_get_attachment_url(76);
    if (!$img) { return; }
    $title = wp_get_document_title();
    $desc = '';
    if (is_singular()) {
        $obj = get_queried_object();
        if ($obj && !empty($obj->post_excerpt)) {
            $desc = wp_strip_all_tags($obj->post_excerpt);
        }
    }
    if ($desc === '') {
        $desc = 'Kowloon Light Office is a brand and web studio above a neon-bending workshop in Yau Ma Tei, Hong Kong. Brands bent from one line.';
    }
    $url = is_singular() ? get_permalink(get_queried_object_id()) : home_url('/');
    $alt = 'The studio sign lit, KLO in Tube Pink';
    echo "\n<!-- KLO Open Graph -->\n";
    echo '<meta property="og:type" content="website" />' . "\n";
    echo '<meta property="og:title" content="' . esc_attr($title) . '" />' . "\n";
    echo '<meta property="og:description" content="' . esc_attr($desc) . '" />' . "\n";
    echo '<meta property="og:url" content="' . esc_url($url) . '" />' . "\n";
    echo '<meta property="og:image" content="' . esc_url($img) . '" />' . "\n";
    echo '<meta property="og:image:width" content="1200" />' . "\n";
    echo '<meta property="og:image:height" content="630" />' . "\n";
    echo '<meta property="og:image:type" content="image/jpeg" />' . "\n";
    echo '<meta property="og:image:alt" content="' . esc_attr($alt) . '" />' . "\n";
    echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
    echo '<meta name="twitter:title" content="' . esc_attr($title) . '" />' . "\n";
    echo '<meta name="twitter:description" content="' . esc_attr($desc) . '" />' . "\n";
    echo '<meta name="twitter:image" content="' . esc_url($img) . '" />' . "\n";
}, 5);
