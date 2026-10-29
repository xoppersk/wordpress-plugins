<?php
/**
 * Google AdSense, Analytics and Search Console (replaces Site Kit).
 * Everything here only runs on the live domain.
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'wp_head',
	static function () {
		$gsc = (string) liberty_opt( 'site', 'gsc', '' );
		if ( $gsc ) {
			printf( '<meta name="google-site-verification" content="%s">' . "\n", esc_attr( $gsc ) );
		}
		if ( ! liberty_is_production() ) {
			return;
		}
		$client = (string) liberty_opt( 'site', 'adsense_client', '' );
		if ( $client ) {
			printf( '<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=%s" crossorigin="anonymous"></script>' . "\n", esc_attr( $client ) );
		}
		$ga = (string) liberty_opt( 'site', 'ga4', '' );
		if ( $ga && ! is_user_logged_in() ) {
			printf( '<script async src="https://www.googletagmanager.com/gtag/js?id=%1$s"></script><script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments)}gtag("js",new Date());gtag("config","%1$s");</script>' . "\n", esc_attr( $ga ) );
		}
	},
	5
);

/**
 * Staging must never be indexed, whatever the Reading setting says.
 */
add_filter( 'wp_robots', static fn( $r ) => liberty_is_production() ? $r : array_merge( $r, array( 'noindex' => true, 'nofollow' => true ) ) );

/**
 * One ad space. On the live site it is a real AdSense unit (or nothing when no
 * slot ID is set); anywhere else it is a labelled placeholder so the layout can
 * be reviewed.
 *
 * @param string $kind leader, mpu or inline.
 */
function liberty_ad( $kind ) {
	$sizes = array(
		'leader' => array( '970 × 90', __( 'Wide banner', 'liberty-core' ) ),
		'mpu'    => array( '300 × 250', __( 'Sidebar box', 'liberty-core' ) ),
		'inline' => array( '728 × 90', __( 'In-article', 'liberty-core' ) ),
	);
	if ( ! isset( $sizes[ $kind ] ) ) {
		return '';
	}
	$label = '<span class="ad-l">' . esc_html__( 'Advertisement', 'liberty-core' ) . '</span>';
	if ( liberty_is_production() ) {
		$client = (string) liberty_opt( 'site', 'adsense_client', '' );
		$slot   = (string) liberty_opt( 'site', 'slot_' . $kind, '' );
		if ( ! $client || ! $slot ) {
			return '';
		}
		$format = 'mpu' === $kind ? 'rectangle' : 'horizontal';
		return sprintf(
			'<aside class="ad ad-%1$s" aria-label="%2$s">%3$s<ins class="adsbygoogle" style="display:block" data-ad-client="%4$s" data-ad-slot="%5$s" data-ad-format="%6$s" data-full-width-responsive="true"></ins><script>(adsbygoogle=window.adsbygoogle||[]).push({});</script></aside>',
			esc_attr( $kind ),
			esc_attr__( 'Advertisement', 'liberty-core' ),
			$label,
			esc_attr( $client ),
			esc_attr( $slot ),
			esc_attr( $format )
		);
	}
	return sprintf(
		'<aside class="ad ad-%1$s" aria-label="%2$s">%3$s<span class="ad-box"><b>%4$s</b><span>%5$s · %6$s</span></span></aside>',
		esc_attr( $kind ),
		esc_attr__( 'Advertisement', 'liberty-core' ),
		$label,
		esc_html( $sizes[ $kind ][0] ),
		esc_html( $sizes[ $kind ][1] ),
		esc_html__( 'Google AdSense (shows on the live site)', 'liberty-core' )
	);
}
