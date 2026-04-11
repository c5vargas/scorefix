<?php
/**
 * Default URLs for rendered-page scan (home, blog index, WooCommerce pages).
 *
 * @package ScoreFix
 */

namespace ScoreFix\Scanner;

defined( 'ABSPATH' ) || exit;

/**
 * Class RenderUrlDefaults
 */
class RenderUrlDefaults {

	/**
	 * @return array<int, string> Unique absolute URLs.
	 */
	public static function get() {
		$urls = array();

		$urls[] = home_url( '/' );

		$pfp = (int) get_option( 'page_for_posts' );
		if ( $pfp > 0 ) {
			$u = get_permalink( $pfp );
			if ( is_string( $u ) && '' !== $u ) {
				$urls[] = $u;
			}
		}

		if ( class_exists( 'WooCommerce', false ) && function_exists( 'wc_get_page_id' ) ) {
			foreach ( array( 'shop', 'cart', 'checkout', 'myaccount' ) as $slug ) {
				$pid = (int) wc_get_page_id( $slug );
				if ( $pid > 0 && 'publish' === get_post_status( $pid ) ) {
					$u = get_permalink( $pid );
					if ( is_string( $u ) && '' !== $u ) {
						$urls[] = $u;
					}
				}
			}
		}

		$urls = array_values( array_unique( array_filter( array_map( 'esc_url_raw', $urls ) ) ) );
		return apply_filters( 'scorefix_default_render_scan_urls', $urls );
	}
}
