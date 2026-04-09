<?php
/**
 * Inline style contrast heuristics for the scanner only (no DOM/CSS cascade).
 *
 * Limitations: only `post_content` inline `style`, no `currentColor`, no theme stylesheets.
 *
 * @package ScoreFix
 */

namespace ScoreFix\Scanner;

defined( 'ABSPATH' ) || exit;

/**
 * Class ContrastStyleAnalyzer
 */
class ContrastStyleAnalyzer {

	/**
	 * Analyze inline style for contrast risks.
	 *
	 * @param string $style Raw style attribute value.
	 * @return array<string, mixed>|null Issue payload keys: hint, ratio (optional), detail (optional).
	 */
	public static function analyze( $style ) {
		$style = trim( (string) $style );
		if ( '' === $style ) {
			return null;
		}

		$declarations = self::parse_declarations( $style );
		if ( empty( $declarations ) ) {
			return null;
		}

		$min_ratio = (float) apply_filters( 'scorefix_contrast_min_ratio', 4.5 );
		if ( $min_ratio < 1.0 ) {
			$min_ratio = 4.5;
		}

		$assumed_hex = (string) apply_filters( 'scorefix_contrast_assumed_page_background', '#ffffff' );
		$assumed     = self::parse_color_value( $assumed_hex );
		if ( null === $assumed || self::alpha( $assumed ) < 1.0 ) {
			$assumed = array( 'r' => 255, 'g' => 255, 'b' => 255, 'a' => 1.0 );
		}

		$fg_raw = isset( $declarations['color'] ) ? $declarations['color'] : null;
		$fg     = null !== $fg_raw ? self::parse_color_value( $fg_raw ) : null;

		$bg_color_prop = isset( $declarations['background-color'] ) ? $declarations['background-color'] : null;
		$bg_from_color = null !== $bg_color_prop ? self::parse_color_value( $bg_color_prop ) : null;

		$bg_shorthand = isset( $declarations['background'] ) ? $declarations['background'] : null;
		$bg_from_bg   = null !== $bg_shorthand ? self::extract_solid_color_from_background( $bg_shorthand ) : null;

		$bg_opaque = null;
		if ( null !== $bg_from_color && self::alpha( $bg_from_color ) >= 1.0 ) {
			$bg_opaque = $bg_from_color;
		} elseif ( null !== $bg_from_bg && self::alpha( $bg_from_bg ) >= 1.0 ) {
			$bg_opaque = $bg_from_bg;
		}

		// Image/gradient background without an opaque color from background-color: cannot estimate.
		if ( null === $bg_opaque && null !== $bg_shorthand && self::background_is_non_solid( $bg_shorthand ) ) {
			return null;
		}

		if ( null !== $fg && self::alpha( $fg ) < 1.0 ) {
			return null;
		}

		// Explicit opaque foreground + opaque background on the same element.
		if ( null !== $fg && null !== $bg_opaque ) {
			if ( self::rgb_equal( $fg, $bg_opaque ) ) {
				return array(
					'hint'   => 'same_color',
					'ratio'  => 1.0,
					'detail' => self::rgb_summary( $fg ),
				);
			}
			$ratio = self::contrast_ratio( $fg, $bg_opaque );
			if ( $ratio < $min_ratio ) {
				return array(
					'hint'   => 'low_ratio',
					'ratio'  => round( $ratio, 2 ),
					'detail' => self::rgb_summary( $fg ) . ' / ' . self::rgb_summary( $bg_opaque ),
				);
			}
			return null;
		}

		// No opaque background on element: compare text to assumed page background.
		if ( null !== $fg && self::alpha( $fg ) >= 1.0 ) {
			$ratio = self::contrast_ratio( $fg, $assumed );
			if ( $ratio < $min_ratio ) {
				return array(
					'hint'   => 'low_contrast_assumed_page',
					'ratio'  => round( $ratio, 2 ),
					'detail' => self::rgb_summary( $fg ),
				);
			}
		}

		return null;
	}

	/**
	 * @param string $style Style attribute.
	 * @return array<string, string> Lowercase property => value.
	 */
	protected static function parse_declarations( $style ) {
		$out = array();
		foreach ( explode( ';', $style ) as $chunk ) {
			$chunk = trim( $chunk );
			if ( '' === $chunk ) {
				continue;
			}
			$colon = strpos( $chunk, ':' );
			if ( false === $colon ) {
				continue;
			}
			$prop = strtolower( trim( substr( $chunk, 0, $colon ) ) );
			$val  = trim( substr( $chunk, $colon + 1 ) );
			if ( '' !== $prop ) {
				$out[ $prop ] = $val;
			}
		}
		return $out;
	}

	/**
	 * @param string $value Background shorthand.
	 * @return bool
	 */
	protected static function background_is_non_solid( $value ) {
		$v = strtolower( $value );
		return false !== strpos( $v, 'url(' )
			|| false !== strpos( $v, 'gradient' )
			|| false !== strpos( $v, 'image-set(' );
	}

	/**
	 * First solid color from background shorthand, or null.
	 *
	 * @param string $value Background value.
	 * @return array{r: int, g: int, b: int, a: float}|null
	 */
	protected static function extract_solid_color_from_background( $value ) {
		if ( self::background_is_non_solid( $value ) ) {
			return null;
		}
		if ( preg_match( '/#([0-9a-f]{3}|[0-9a-f]{6})\b/i', $value, $m ) ) {
			return self::parse_hex( '#' . $m[1] );
		}
		if ( preg_match( '/(rgba?\s*\([^)]+\))/i', $value, $m ) ) {
			return self::parse_rgb_function( $m[1] );
		}
		$tokens = preg_split( '/\s+/', trim( $value ) );
		if ( is_array( $tokens ) && isset( $tokens[0] ) && preg_match( '/^[a-z]+$/i', $tokens[0] ) ) {
			$named = self::named_color_rgb( strtolower( $tokens[0] ) );
			if ( null !== $named ) {
				return array(
					'r' => $named[0],
					'g' => $named[1],
					'b' => $named[2],
					'a' => 1.0,
				);
			}
		}
		return null;
	}

	/**
	 * @param string $raw Color value.
	 * @return array{r: int, g: int, b: int, a: float}|null
	 */
	protected static function parse_color_value( $raw ) {
		$raw = trim( $raw );
		if ( '' === $raw ) {
			return null;
		}
		$lower = strtolower( $raw );
		if ( 'transparent' === $lower ) {
			return array( 'r' => 0, 'g' => 0, 'b' => 0, 'a' => 0.0 );
		}
		if ( 0 === strpos( $lower, '#' ) ) {
			return self::parse_hex( $raw );
		}
		if ( 0 === strpos( $lower, 'rgb' ) ) {
			return self::parse_rgb_function( $raw );
		}
		$named = self::named_color_rgb( $lower );
		if ( null !== $named ) {
			return array( 'r' => $named[0], 'g' => $named[1], 'b' => $named[2], 'a' => 1.0 );
		}
		return null;
	}

	/**
	 * @param string $hex #rgb or #rrggbb.
	 * @return array{r: int, g: int, b: int, a: float}|null
	 */
	protected static function parse_hex( $hex ) {
		$hex = trim( $hex );
		if ( ! preg_match( '/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $hex, $m ) ) {
			return null;
		}
		$h = $m[1];
		if ( strlen( $h ) === 3 ) {
			$r = hexdec( $h[0] . $h[0] );
			$g = hexdec( $h[1] . $h[1] );
			$b = hexdec( $h[2] . $h[2] );
		} else {
			$r = hexdec( substr( $h, 0, 2 ) );
			$g = hexdec( substr( $h, 2, 2 ) );
			$b = hexdec( substr( $h, 4, 2 ) );
		}
		return array(
			'r' => (int) min( 255, max( 0, $r ) ),
			'g' => (int) min( 255, max( 0, $g ) ),
			'b' => (int) min( 255, max( 0, $b ) ),
			'a' => 1.0,
		);
	}

	/**
	 * @param string $raw rgb() or rgba().
	 * @return array{r: int, g: int, b: int, a: float}|null
	 */
	protected static function parse_rgb_function( $raw ) {
		if ( ! preg_match( '/rgba?\s*\(\s*([^)]+)\s*\)/i', $raw, $m ) ) {
			return null;
		}
		$inner = $m[1];
		$parts = preg_split( '/\s*,\s*/', $inner );
		if ( ! is_array( $parts ) || count( $parts ) < 3 ) {
			return null;
		}
		$r = self::parse_rgb_component( trim( $parts[0] ) );
		$g = self::parse_rgb_component( trim( $parts[1] ) );
		$b = self::parse_rgb_component( trim( $parts[2] ) );
		if ( null === $r || null === $g || null === $b ) {
			return null;
		}
		$a = 1.0;
		if ( isset( $parts[3] ) ) {
			$ap = trim( $parts[3] );
			if ( is_numeric( $ap ) ) {
				$a = (float) $ap;
			}
		}
		return array(
			'r' => (int) min( 255, max( 0, $r ) ),
			'g' => (int) min( 255, max( 0, $g ) ),
			'b' => (int) min( 255, max( 0, $b ) ),
			'a' => min( 1.0, max( 0.0, $a ) ),
		);
	}

	/**
	 * @param string $c Component with optional %.
	 * @return int|null 0-255
	 */
	protected static function parse_rgb_component( $c ) {
		if ( preg_match( '/^(\d+(?:\.\d+)?)\s*%$/', $c, $m ) ) {
			return (int) round( 255 * ( (float) $m[1] ) / 100 );
		}
		if ( is_numeric( $c ) ) {
			return (int) round( (float) $c );
		}
		return null;
	}

	/**
	 * @param string $name Lowercase name.
	 * @return array{0: int, 1: int, 2: int}|null
	 */
	protected static function named_color_rgb( $name ) {
		$map = array(
			'white'         => array( 255, 255, 255 ),
			'black'         => array( 0, 0, 0 ),
			'silver'        => array( 192, 192, 192 ),
			'gray'          => array( 128, 128, 128 ),
			'grey'          => array( 128, 128, 128 ),
			'red'           => array( 255, 0, 0 ),
			'green'         => array( 0, 128, 0 ),
			'blue'          => array( 0, 0, 255 ),
			'yellow'        => array( 255, 255, 0 ),
			'orange'        => array( 255, 165, 0 ),
			'purple'        => array( 128, 0, 128 ),
			'navy'          => array( 0, 0, 128 ),
			'teal'          => array( 0, 128, 128 ),
			'aqua'          => array( 0, 255, 255 ),
			'lime'          => array( 0, 255, 0 ),
			'maroon'        => array( 128, 0, 0 ),
			'fuchsia'       => array( 255, 0, 255 ),
			'lightgray'     => array( 211, 211, 211 ),
			'lightgrey'     => array( 211, 211, 211 ),
			'gainsboro'     => array( 220, 220, 220 ),
			'whitesmoke'    => array( 245, 245, 245 ),
		);
		return isset( $map[ $name ] ) ? $map[ $name ] : null;
	}

	/**
	 * @param array{r: int, g: int, b: int, a: float} $c Color.
	 * @return float
	 */
	protected static function alpha( array $c ) {
		return isset( $c['a'] ) ? (float) $c['a'] : 1.0;
	}

	/**
	 * @param array{r: int, g: int, b: int, a?: float} $a Color.
	 * @param array{r: int, g: int, b: int, a?: float} $b Color.
	 * @return bool
	 */
	protected static function rgb_equal( array $a, array $b ) {
		return (int) $a['r'] === (int) $b['r']
			&& (int) $a['g'] === (int) $b['g']
			&& (int) $a['b'] === (int) $b['b'];
	}

	/**
	 * @param array{r: int, g: int, b: int} $c Color.
	 * @return string
	 */
	protected static function rgb_summary( array $c ) {
		return sprintf( 'rgb(%d,%d,%d)', (int) $c['r'], (int) $c['g'], (int) $c['b'] );
	}

	/**
	 * WCAG 2.x relative luminance (sRGB).
	 *
	 * @param array{r: int, g: int, b: int} $c Color.
	 * @return float
	 */
	protected static function relative_luminance( array $c ) {
		$channels = array( $c['r'] / 255, $c['g'] / 255, $c['b'] / 255 );
		$lin      = array();
		foreach ( $channels as $i => $v ) {
			$lin[ $i ] = $v <= 0.03928 ? $v / 12.92 : pow( ( $v + 0.055 ) / 1.055, 2.4 );
		}
		return 0.2126 * $lin[0] + 0.7152 * $lin[1] + 0.0722 * $lin[2];
	}

	/**
	 * @param array{r: int, g: int, b: int} $fg Foreground.
	 * @param array{r: int, g: int, b: int} $bg Background.
	 * @return float Contrast ratio (>= 1).
	 */
	protected static function contrast_ratio( array $fg, array $bg ) {
		$l1 = self::relative_luminance( $fg );
		$l2 = self::relative_luminance( $bg );
		$lighter = max( $l1, $l2 );
		$darker  = min( $l1, $l2 );
		return ( $lighter + 0.05 ) / ( $darker + 0.05 );
	}
}
