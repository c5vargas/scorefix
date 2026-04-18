<?php
/**
 * Viewport meta content helpers (Lighthouse SEO: zoom / maximum-scale).
 *
 * @package ScoreFix
 */

namespace ScoreFix\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Class ViewportMeta
 */
class ViewportMeta {

	/**
	 * Whether content matches Lighthouse "restricts zoom" (user-scalable=no or maximum-scale < 5).
	 *
	 * @param string $content Meta viewport content attribute.
	 * @return bool
	 */
	public static function content_restricts_zoom( $content ) {
		foreach ( self::parse_directives( $content ) as $item ) {
			if ( 'user-scalable' === $item['k'] ) {
				$uv = strtolower( $item['v'] );
				if ( in_array( $uv, array( 'no', '0', 'false' ), true ) ) {
					return true;
				}
			}
			if ( 'maximum-scale' === $item['k'] ) {
				$max = floatval( $item['v'] );
				if ( $max > 0 && $max < 5 ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Remove blocking user-scalable and bump maximum-scale to at least 5 when between 0 and 5.
	 * Preserves directive order. Fallback when nothing left: width=device-width, initial-scale=1.
	 *
	 * @param string $content Meta viewport content attribute.
	 * @return string
	 */
	public static function normalize_content( $content ) {
		$original = trim( (string) $content );
		if ( '' === $original ) {
			return '';
		}
		$ordered = self::parse_directives( $content );
		if ( empty( $ordered ) ) {
			return $original;
		}
		$parts = array();
		foreach ( $ordered as $item ) {
			$k = $item['k'];
			$v = $item['v'];
			if ( 'user-scalable' === $k ) {
				$uv = strtolower( $v );
				if ( in_array( $uv, array( 'no', '0', 'false' ), true ) ) {
					continue;
				}
			}
			if ( 'maximum-scale' === $k ) {
				$max = floatval( $v );
				if ( $max > 0 && $max < 5 ) {
					$v = '5';
				}
			}
			$parts[] = $k . '=' . $v;
		}
		$out = implode( ', ', $parts );
		if ( '' === trim( $out ) ) {
			return 'width=device-width, initial-scale=1';
		}
		return $out;
	}

	/**
	 * @param string $content Raw content.
	 * @return array<int, array{k: string, v: string}>
	 */
	protected static function parse_directives( $content ) {
		$out = array();
		$raw = trim( (string) $content );
		$split = preg_split( '/\s*,\s*/', $raw, -1, PREG_SPLIT_NO_EMPTY );
		if ( ! is_array( $split ) ) {
			return $out;
		}
		foreach ( $split as $seg ) {
			$seg    = trim( $seg );
			$eq_pos = strpos( $seg, '=' );
			if ( false === $eq_pos ) {
				continue;
			}
			$key = strtolower( trim( substr( $seg, 0, $eq_pos ) ) );
			$val = trim( substr( $seg, $eq_pos + 1 ) );
			$val = trim( $val, " \t\"'" );
			if ( '' === $key ) {
				continue;
			}
			$out[] = array( 'k' => $key, 'v' => $val );
		}
		return $out;
	}
}
