<?php
/**
 * Frontend hooks: attach FixEngine when fixes are enabled.
 *
 * @package ScoreFix
 */

namespace ScoreFix\Frontend;

use ScoreFix\Core\Loader;
use ScoreFix\Fixes\FixEngine;

defined( 'ABSPATH' ) || exit;

/**
 * Class RenderHooks
 */
class RenderHooks {

	/**
	 * Fix engine.
	 *
	 * @var FixEngine
	 */
	protected $engine;

	/**
	 * Whether output buffering was started by this class.
	 *
	 * @var bool
	 */
	protected $buffer_started = false;

	/**
	 * Constructor.
	 *
	 * @param FixEngine $engine Fix engine.
	 */
	public function __construct( FixEngine $engine ) {
		$this->engine = $engine;
	}

	/**
	 * Register filters on loader.
	 *
	 * @param Loader $loader Loader.
	 * @return void
	 */
	public function register( Loader $loader ) {
		// Full-page buffer after the main query (wraps theme + builders).
		$loader->add_action( 'wp', $this, 'start_output_buffer', 0, 0 );
		// Second chance if wp did not run (unusual setups).
		$loader->add_action( 'template_redirect', $this, 'start_output_buffer', -1000, 0 );

		// Post/widget HTML (classic editor, widgets, many themes).
		$loader->add_filter( 'the_content', $this, 'filter_fragment', 99, 1 );
		$loader->add_filter( 'widget_text', $this, 'filter_fragment', 99, 1 );

		// Elementor applies this filter to builder output (see elementor/includes/frontend.php).
		$loader->add_filter( 'elementor/frontend/the_content', $this, 'filter_fragment', 20, 1 );

		$loader->add_filter( 'wp_get_attachment_image_attributes', $this->engine, 'filter_attachment_image_attributes', 20, 2 );
	}

	/**
	 * Process a HTML fragment (not necessarily a full document).
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	public function filter_fragment( $html ) {
		if ( is_admin() ) {
			return $html;
		}
		$is_json = function_exists( 'wp_is_json_request' ) && wp_is_json_request();
		if ( $is_json || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return $html;
		}
		return $this->engine->process_content( (string) $html );
	}

	/**
	 * Start global frontend output buffering.
	 *
	 * @return void
	 */
	public function start_output_buffer() {
		if ( $this->buffer_started || ! $this->should_process_request() ) {
			return;
		}

		$this->buffer_started = true;
		ob_start( array( $this, 'filter_buffer' ) );
	}

	/**
	 * Process final frontend HTML buffer.
	 *
	 * @param string $html HTML buffer.
	 * @return string
	 */
	public function filter_buffer( $html, $phase = null ) {
		if ( ! $this->should_process_buffer( $html ) ) {
			return $html;
		}

		return $this->engine->process_content( $html );
	}

	/**
	 * Whether current request is eligible for global fixes.
	 *
	 * @return bool
	 */
	protected function should_process_request() {
		if ( is_admin() ) {
			return false;
		}
		if ( wp_doing_ajax() ) {
			return false;
		}
		if ( is_feed() || is_robots() || is_trackback() ) {
			return false;
		}

		$is_json = function_exists( 'wp_is_json_request' ) && wp_is_json_request();
		if ( $is_json || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Ensure the output buffer is HTML before transforming.
	 *
	 * @param string $html Output buffer.
	 * @return bool
	 */
	protected function should_process_buffer( $html ) {
		if ( '' === trim( (string) $html ) ) {
			return false;
		}

		$html = (string) $html;

		foreach ( headers_list() as $header ) {
			if ( 0 !== stripos( $header, 'Content-Type:' ) ) {
				continue;
			}
			$ct = strtolower( substr( $header, strlen( 'Content-Type:' ) ) );
			$ct = trim( $ct );

			// Explicit non-HTML responses: never mutate.
			if ( preg_match( '#^(application/(?:json|xml|[^;]+\+json)|text/xml|image/|audio/|video/)#', $ct ) ) {
				return false;
			}
			if ( false !== strpos( $ct, 'application/rss+xml' ) || false !== strpos( $ct, 'application/atom+xml' ) ) {
				return false;
			}
			if ( false !== strpos( $ct, 'text/html' ) || false !== strpos( $ct, 'application/xhtml+xml' ) ) {
				return true;
			}
			// text/plain, octet-stream, etc.: misconfigured hosts exist — decide from markup.
		}

		// No usable Content-Type in headers_list(), or ambiguous: use markup heuristic.
		return (bool) preg_match( '/<(?:!doctype|html|body)\b/i', $html );
	}
}
