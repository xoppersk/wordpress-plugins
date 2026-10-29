<?php
/**
 * Plugin Name: KLO Fonts — self-hosted @font-face + Google Fonts off
 * Description: Elementor Pro's Custom Fonts registration for
 *              KLO exists (elementor_font posts 23/25/28 with correct
 *              elementor_font_face meta) but its CSS never reaches the front
 *              end on this install, while Elementor simultaneously classifies
 *              the same family names as Google Fonts and enqueues them from
 *              fonts.googleapis.com (whose Bricolage has no wdth axis).
 *              This mu-plugin is the estate-proven Poda pattern: print the
 *              @font-face rules for the three self-hosted families from
 *              uploads/klo-fonts/ at wp_head, and strip every Google Fonts
 *              stylesheet (enqueue, print and resource-hint layers).
 */

if (!defined('ABSPATH')) exit;

function klo_fonts_is_editor() {
    if (isset($_GET['elementor-preview'])) return true; // phpcs:ignore
    if (class_exists('\Elementor\Plugin') && \Elementor\Plugin::$instance->editor
        && \Elementor\Plugin::$instance->editor->is_edit_mode()) return true;
    return false;
}

add_action('wp_head', function () {
    if (klo_fonts_is_editor()) return;
    $base = content_url('uploads/klo-fonts');
    ?>
<style id="klo-fonts">
@font-face{font-family:'Bricolage Grotesque';font-style:normal;font-weight:200 800;font-stretch:75% 100%;font-display:swap;src:url('<?php echo esc_url($base . '/BricolageGrotesque-Variable.woff2'); ?>') format('woff2')}
@font-face{font-family:'Instrument Sans';font-style:normal;font-weight:400;font-display:swap;src:url('<?php echo esc_url($base . '/InstrumentSans-Regular.woff2'); ?>') format('woff2')}
@font-face{font-family:'Instrument Sans';font-style:normal;font-weight:600;font-display:swap;src:url('<?php echo esc_url($base . '/InstrumentSans-SemiBold.woff2'); ?>') format('woff2')}
@font-face{font-family:'Noto Sans TC';font-style:normal;font-weight:900;font-display:swap;src:url('<?php echo esc_url($base . '/NotoSansTC-Black-stamps.woff2'); ?>') format('woff2')}
</style>
    <?php
}, 1);

function klo_fonts_kill_google() {
    if (klo_fonts_is_editor()) return;
    global $wp_styles;
    if (!($wp_styles instanceof WP_Styles)) return;
    foreach ((array) $wp_styles->queue as $handle) {
        $reg = $wp_styles->registered[$handle] ?? null;
        $src = ($reg && isset($reg->src)) ? (string) $reg->src : '';
        if (strpos($handle, 'elementor-gf-') === 0 || strpos($src, 'fonts.googleapis.com') !== false) {
            wp_dequeue_style($handle);
            wp_deregister_style($handle);
        }
    }
}
add_action('wp_enqueue_scripts', 'klo_fonts_kill_google', 9999);
add_action('wp_print_styles', 'klo_fonts_kill_google', 0);

add_filter('wp_resource_hints', function ($hints, $relation) {
    if ($relation === 'preconnect' || $relation === 'dns-prefetch') {
        foreach ($hints as $k => $h) {
            $url = is_array($h) ? ($h['href'] ?? '') : (string) $h;
            if (strpos($url, 'fonts.googleapis.com') !== false || strpos($url, 'fonts.gstatic.com') !== false) {
                unset($hints[$k]);
            }
        }
    }
    return $hints;
}, 10, 2);

add_filter('style_loader_src', function ($src, $handle) {
    if ($src && strpos((string) $src, 'fonts.googleapis.com') !== false && !klo_fonts_is_editor()) {
        return false;
    }
    return $src;
}, 10, 2);
