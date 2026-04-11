<?php
/**
 * One-time signed capture flag for loopback HTML fetch (disables ScoreFix output mutation).
 *
 * @package ScoreFix
 */

namespace ScoreFix\Scanner;

defined( 'ABSPATH' ) || exit;

/**
 * Class CaptureRequest
 */
class CaptureRequest {

	const QUERY_ID  = 'scorefix_cap';

	const QUERY_SIG = 'scorefix_sig';

	/**
	 * Early bootstrap: before RenderHooks register (plugins_loaded priority 2).
	 *
	 * @return void
	 */
	public static function bootstrap() {
		if ( is_admin() ) {
			return;
		}
		if ( empty( $_GET[ self::QUERY_ID ] ) || empty( $_GET[ self::QUERY_SIG ] ) ) {
			return;
		}

		$id = sanitize_text_field( wp_unslash( (string) $_GET[ self::QUERY_ID ] ) );
		$sig_in = sanitize_text_field( wp_unslash( (string) $_GET[ self::QUERY_SIG ] ) );
		if ( '' === $id || strlen( $id ) > 48 || '' === $sig_in ) {
			return;
		}

		$key = UrlHtmlCapture::transient_key_for_id( $id );
		$row = get_transient( $key );
		if ( ! is_array( $row ) || empty( $row['secret'] ) ) {
			return;
		}

		$expected = hash_hmac( 'sha256', $id . '|' . (string) $row['secret'], UrlHtmlCapture::hmac_key() );
		if ( ! hash_equals( $expected, $sig_in ) ) {
			return;
		}

		delete_transient( $key );

		if ( ! defined( 'SCOREFIX_RENDER_CAPTURE' ) ) {
			define( 'SCOREFIX_RENDER_CAPTURE', true );
		}

		add_filter( 'show_admin_bar', '__return_false', PHP_INT_MAX );
	}
}
