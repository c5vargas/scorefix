<?php
/**
 * JSON-LD diagnostics on captured HTML (validation + optional expected schema.org types).
 *
 * @package ScoreFix
 */

namespace ScoreFix\Scanner\Rules;

defined( 'ABSPATH' ) || exit;

/**
 * Class JsonLdSeoRule
 */
class JsonLdSeoRule {

	/**
	 * Run JSON-LD checks on full-document HTML from URL capture.
	 *
	 * @param string   $document_html Raw HTML.
	 * @param callable $issue         Issue factory (rendered_url + capture_url).
	 * @param string   $capture_url   URL audited.
	 * @return array<int, array<string, mixed>>
	 */
	public static function collect( $document_html, callable $issue, $capture_url = '' ) {
		$out         = array();
		$capture_url = (string) $capture_url;

		if ( ! apply_filters( 'scorefix_jsonld_seo_enabled', true, $capture_url ) ) {
			return $out;
		}

		$html = (string) $document_html;
		if ( '' === trim( $html ) ) {
			return $out;
		}

		$dom = self::parse_html_document( $html );
		if ( null === $dom ) {
			return $out;
		}

		$scripts = $dom->getElementsByTagName( 'script' );
		if ( ! $scripts || $scripts->length < 1 ) {
			return $out;
		}

		$block_index  = 0;
		$all_types    = array();

		for ( $i = 0; $i < $scripts->length; $i++ ) {
			$node = $scripts->item( $i );
			if ( ! $node instanceof \DOMElement ) {
				continue;
			}
			$type_attr = strtolower( trim( (string) $node->getAttribute( 'type' ) ) );
			if ( 'application/ld+json' !== $type_attr ) {
				continue;
			}

			$raw = trim( (string) $node->textContent );
			++$block_index;

			if ( '' === $raw ) {
				$out[] = $issue(
					'seo_jsonld_invalid_json',
					'low',
					array(
						'context'              => 'rendered',
						'impact'               => 'business',
						'capture_hint'         => 'structured-data',
						'ld_json_block_index'  => $block_index,
						'json_error'           => 'empty',
					)
				);
				continue;
			}

			$decoded = json_decode( $raw, true );
			$err     = json_last_error();
			if ( JSON_ERROR_NONE !== $err || ( null === $decoded && 'null' !== strtolower( trim( $raw ) ) ) ) {
				$out[] = $issue(
					'seo_jsonld_invalid_json',
					'low',
					array(
						'context'              => 'rendered',
						'impact'               => 'business',
						'capture_hint'         => 'structured-data',
						'ld_json_block_index'  => $block_index,
						'json_error'           => function_exists( 'json_last_error_msg' ) ? json_last_error_msg() : 'json_error',
					)
				);
				continue;
			}

			self::collect_types_recursive( $decoded, $all_types );
		}

		$all_types = self::unique_types_normalized( $all_types );

		$expect = self::expected_types_for_url( $capture_url, $all_types );

		foreach ( $expect as $row ) {
			if ( ! is_array( $row ) || empty( $row['type'] ) ) {
				continue;
			}
			$want   = (string) $row['type'];
			$reason = isset( $row['reason'] ) ? (string) $row['reason'] : '';
			if ( 'Organization' === $want && 'home' === $reason ) {
				if ( self::home_site_identity_present( $all_types ) ) {
					continue;
				}
			} elseif ( self::types_contain( $all_types, $want ) ) {
				continue;
			}
			$out[] = $issue(
				'seo_jsonld_missing_expected_type',
				'low',
				array(
					'context'          => 'rendered',
					'impact'           => 'business',
					'capture_hint'     => 'structured-data',
					'expected_schema'  => $want,
					'expect_reason'    => $reason,
				)
			);
		}

		return $out;
	}

	/**
	 * Default expectations: Organization on home, BreadcrumbList on inner URLs, Product on WC single product.
	 *
	 * @param string $capture_url URL.
	 * @param array  $found_types Normalized type strings (e.g. product, breadcrumblist).
	 * @return array<int, array{type: string, reason: string}>
	 */
	protected static function expected_types_for_url( $capture_url, array $found_types ) {
		$url = (string) $capture_url;
		$out = array();

		$is_home = self::is_front_capture_url( $url );

		if ( apply_filters( 'scorefix_jsonld_expect_organization_on_home', true, $url ) && $is_home ) {
			$out[] = array(
				'type'   => 'Organization',
				'reason' => 'home',
			);
		}

		if ( apply_filters( 'scorefix_jsonld_expect_breadcrumb_inner', true, $url ) && ! $is_home ) {
			$out[] = array(
				'type'   => 'BreadcrumbList',
				'reason' => 'inner',
			);
		}

		$pid = (int) url_to_postid( $url );
		if ( $pid > 0 && 'product' === get_post_type( $pid ) && class_exists( 'WooCommerce' ) ) {
			if ( apply_filters( 'scorefix_jsonld_expect_product_on_wc_product', true, $url, $pid ) ) {
				$out[] = array(
					'type'   => 'Product',
					'reason' => 'woocommerce_product',
				);
			}
		}

		return apply_filters( 'scorefix_jsonld_expected_types', $out, $url, $found_types );
	}

	/**
	 * @param string $url Full URL of captured page.
	 * @return bool
	 */
	protected static function is_front_capture_url( $url ) {
		$url = (string) $url;
		if ( '' === $url ) {
			return false;
		}

		$show = (string) get_option( 'show_on_front' );
		if ( 'page' === $show ) {
			$front_id = (int) get_option( 'page_on_front' );
			if ( $front_id > 0 ) {
				$plink = get_permalink( $front_id );
				if ( is_string( $plink ) && '' !== $plink && self::urls_loosely_match( $url, $plink ) ) {
					return true;
				}
			}
		}

		return self::urls_loosely_match( $url, home_url( '/' ) ) || self::urls_loosely_match( $url, home_url() );
	}

	/**
	 * Compare URLs ignoring trivial trailing slash differences.
	 *
	 * @param string $a URL.
	 * @param string $b URL.
	 * @return bool
	 */
	protected static function urls_loosely_match( $a, $b ) {
		$a = untrailingslashit( (string) $a );
		$b = untrailingslashit( (string) $b );
		return $a === $b;
	}

	/**
	 * @param array<int|string, mixed> $data Decoded JSON.
	 * @param array<int, string>       $types Out list of raw @type strings.
	 * @return void
	 */
	protected static function collect_types_recursive( $data, array &$types ) {
		if ( null === $data ) {
			return;
		}
		if ( is_object( $data ) ) {
			$data = (array) $data;
		}
		if ( ! is_array( $data ) ) {
			return;
		}

		if ( isset( $data['@graph'] ) && is_array( $data['@graph'] ) ) {
			foreach ( $data['@graph'] as $node ) {
				self::collect_types_recursive( $node, $types );
			}
		}

		if ( isset( $data['@type'] ) ) {
			$t = $data['@type'];
			if ( is_string( $t ) && '' !== trim( $t ) ) {
				$types[] = $t;
			} elseif ( is_array( $t ) ) {
				foreach ( $t as $one ) {
					if ( is_string( $one ) && '' !== trim( $one ) ) {
						$types[] = $one;
					}
				}
			}
		}

		$is_list = array_keys( $data ) === range( 0, count( $data ) - 1 );
		if ( $is_list ) {
			foreach ( $data as $item ) {
				self::collect_types_recursive( $item, $types );
			}
		}
	}

	/**
	 * @param array<int, string> $types Raw types.
	 * @return array<int, string> Normalized lowercase names (e.g. product, http://schema.org/product → product).
	 */
	protected static function unique_types_normalized( array $types ) {
		$out = array();
		foreach ( $types as $t ) {
			$n = self::normalize_schema_type( (string) $t );
			if ( '' !== $n ) {
				$out[] = $n;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * @param string $t Schema type string.
	 * @return string Normalized token (lowercase).
	 */
	protected static function normalize_schema_type( $t ) {
		$t = trim( (string) $t );
		if ( '' === $t ) {
			return '';
		}
		if ( preg_match( '#schema\.org/([^/\s#]+)#i', $t, $m ) ) {
			return strtolower( $m[1] );
		}
		if ( false !== strpos( $t, ':' ) ) {
			$parts = explode( ':', $t );
			$last  = trim( (string) end( $parts ) );
			return strtolower( $last );
		}
		return strtolower( $t );
	}

	/**
	 * @param array<int, string> $normalized Lowercase normalized types.
	 * @param string             $want       Expected schema name (e.g. Product).
	 * @return bool
	 */
	protected static function types_contain( array $normalized, $want ) {
		$w = strtolower( trim( (string) $want ) );
		if ( '' === $w ) {
			return false;
		}
		foreach ( $normalized as $n ) {
			if ( $n === $w ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Home expectation: common site-level JSON-LD (Organization alone or via WebSite, etc.).
	 *
	 * @param array<int, string> $normalized Lowercase schema names.
	 * @return bool
	 */
	protected static function home_site_identity_present( array $normalized ) {
		$candidates = apply_filters(
			'scorefix_jsonld_home_identity_types',
			array( 'organization', 'website', 'localbusiness', 'corporation', 'store' ),
			$normalized
		);
		if ( ! is_array( $candidates ) ) {
			$candidates = array( 'organization', 'website' );
		}
		foreach ( $candidates as $c ) {
			if ( ! is_string( $c ) ) {
				continue;
			}
			if ( self::types_contain( $normalized, $c ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param string $html Raw HTML.
	 * @return \DOMDocument|null
	 */
	protected static function parse_html_document( $html ) {
		libxml_use_internal_errors( true );
		$dom = new \DOMDocument();
		$ok  = $dom->loadHTML( '<?xml encoding="utf-8"?>' . (string) $html );
		libxml_clear_errors();
		if ( ! $ok ) {
			return null;
		}
		return $dom;
	}
}
