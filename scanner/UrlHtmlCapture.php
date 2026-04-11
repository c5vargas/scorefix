<?php
/**
 * Loopback HTTP fetch of same-host HTML with one-time HMAC gate (CaptureRequest).
 *
 * @package ScoreFix
 */

namespace ScoreFix\Scanner;

defined( 'ABSPATH' ) || exit;

/**
 * Class UrlHtmlCapture
 */
class UrlHtmlCapture {

	/**
	 * Shared HMAC material (must match CaptureRequest verification).
	 *
	 * @return string
	 */
	public static function hmac_key() {
		return (string) wp_hash( 'scorefix_render_capture_v1' );
	}

	/**
	 * Transient key for capture id.
	 *
	 * @param string $id Capture id.
	 * @return string
	 */
	public static function transient_key_for_id( $id ) {
		return 'sf_cap_' . md5( (string) $id );
	}

	/**
	 * Fetch public HTML for a same-host URL (anonymous GET).
	 *
	 * @param string $url             Raw URL.
	 * @param string $delegated_token When set and matches the active render-queue transient, allows fetch without a logged-in user (WP-Cron / internal queue).
	 * @return string|\WP_Error Body HTML or error.
	 */
	public static function fetch( $url, $delegated_token = '' ) {
		$delegated_token = (string) $delegated_token;
		$delegated_ok    = false;
		if ( '' !== $delegated_token ) {
			$stored = get_transient( RenderScanQueue::TRANSIENT_FETCH );
			if ( is_string( $stored ) && '' !== $stored && hash_equals( $stored, $delegated_token ) ) {
				$delegated_ok = true;
			}
		}
		if ( ! $delegated_ok && ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error( 'scorefix_cap_denied', __( 'Not allowed to run URL capture.', 'scorefix' ) );
		}

		$normalized = self::normalize_same_host_url( $url );
		if ( is_wp_error( $normalized ) ) {
			return $normalized;
		}

		$id     = wp_generate_password( 16, false, false );
		$secret = wp_generate_password( 12, false, false );
		$key    = self::transient_key_for_id( $id );
		set_transient(
			$key,
			array(
				'secret' => $secret,
				'url'    => $normalized,
			),
			90
		);

		$sig = hash_hmac( 'sha256', $id . '|' . $secret, self::hmac_key() );
		$fetch_url = add_query_arg(
			array(
				CaptureRequest::QUERY_ID  => $id,
				CaptureRequest::QUERY_SIG => $sig,
			),
			$normalized
		);

		$timeout = (int) apply_filters(
			'scorefix_render_capture_timeout',
			max( 5, min( 60, (int) RenderCaptureConfig::LOOPBACK_TIMEOUT_SECONDS ) )
		);


		$args = array(
			'timeout'     => $timeout,
			'redirection' => 5,
			'headers'     => array(
				'Accept' => 'text/html,application/xhtml+xml;q=0.9,*/*;q=0.8',
			),
		);

		if ( apply_filters( 'scorefix_render_capture_sslverify', true, $normalized ) ) {
			$args['sslverify'] = true;
		} else {
			$args['sslverify'] = false;
		}

		$response = wp_remote_get( $fetch_url, $args );
		if ( is_wp_error( $response ) && apply_filters( 'scorefix_render_capture_retry_without_sslverify', true, $normalized, $response ) ) {
			$msg = $response->get_error_message();
			if ( false !== stripos( $msg, 'SSL' ) || false !== stripos( $msg, 'certificate' ) || false !== stripos( $msg, 'cURL error 60' ) ) {
				$args['sslverify'] = false;
				$response          = wp_remote_get( $fetch_url, $args );
			}
		}
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 400 ) {
			return new \WP_Error(
				'scorefix_cap_http',
				sprintf(
					/* translators: %d: HTTP status code */
					__( 'Capture request returned HTTP %d.', 'scorefix' ),
					$code
				)
			);
		}

		$body = (string) wp_remote_retrieve_body( $response );
		if ( '' === trim( $body ) ) {
			return new \WP_Error( 'scorefix_cap_empty', __( 'Empty HTML response.', 'scorefix' ) );
		}

		return $body;
	}

	/**
	 * Normalize URL and ensure same host as site home.
	 *
	 * @param string $url Input URL.
	 * @return string|\WP_Error Canonical URL string or error.
	 */
	public static function normalize_same_host_url( $url ) {
		$url = esc_url_raw( (string) $url );
		if ( '' === $url ) {
			return new \WP_Error( 'scorefix_cap_url', __( 'Invalid URL.', 'scorefix' ) );
		}

		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
			return new \WP_Error( 'scorefix_cap_parse', __( 'Could not parse URL.', 'scorefix' ) );
		}

		$home_parts = wp_parse_url( home_url( '/' ) );
		if ( ! is_array( $home_parts ) || empty( $home_parts['host'] ) ) {
			return new \WP_Error( 'scorefix_cap_home', __( 'Could not parse home URL.', 'scorefix' ) );
		}

		$h1 = strtolower( (string) $parts['host'] );
		$h2 = strtolower( (string) $home_parts['host'] );
		if ( $h1 !== $h2 ) {
			return new \WP_Error( 'scorefix_cap_host', __( 'URL must use the same host as this WordPress site.', 'scorefix' ) );
		}

		return $url;
	}

	/**
	 * Extract inner HTML of first body for scanner fragment pipeline.
	 *
	 * @param string $html Full or partial document.
	 * @return string
	 */
	public static function extract_body_inner_html( $html ) {
		$html = (string) $html;
		if ( '' === trim( $html ) ) {
			return '';
		}

		libxml_use_internal_errors( true );
		$dom = new \DOMDocument();
		$loaded = $dom->loadHTML( '<?xml encoding="utf-8"?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();

		if ( ! $loaded ) {
			return $html;
		}

		$bodies = $dom->getElementsByTagName( 'body' );
		if ( 0 === $bodies->length ) {
			return $html;
		}

		$body = $bodies->item( 0 );
		if ( ! $body instanceof \DOMElement ) {
			return $html;
		}

		$out = '';
		foreach ( $body->childNodes as $child ) {
			$out .= $dom->saveHTML( $child );
		}

		return $out;
	}

	/**
	 * Extract inner HTML of first `<head>` for head-level SEO rules (same parsing strategy as body).
	 *
	 * @param string $html Full or partial document.
	 * @return string Inner HTML of head children, or empty string if no head.
	 */
	public static function extract_head_inner_html( $html ) {
		$html = (string) $html;
		if ( '' === trim( $html ) ) {
			return '';
		}

		libxml_use_internal_errors( true );
		$dom    = new \DOMDocument();
		$loaded = $dom->loadHTML( '<?xml encoding="utf-8"?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();

		if ( ! $loaded ) {
			return '';
		}

		$heads = $dom->getElementsByTagName( 'head' );
		if ( 0 === $heads->length ) {
			return '';
		}

		$head = $heads->item( 0 );
		if ( ! $head instanceof \DOMElement ) {
			return '';
		}

		$out = '';
		foreach ( $head->childNodes as $child ) {
			$out .= $dom->saveHTML( $child );
		}

		return $out;
	}
}
