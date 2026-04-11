<?php
/**
 * Local performance heuristics on HTML (no PSI). Phase 5A.
 *
 * @package ScoreFix
 */

namespace ScoreFix\Scanner\Rules;

defined( 'ABSPATH' ) || exit;

/**
 * Class PerformanceHeuristicRule
 */
class PerformanceHeuristicRule {

	/**
	 * @param \DOMXPath $xpath   Bound document (expects #scorefix-root wrapper).
	 * @param int       $post_id Post ID.
	 * @param callable  $issue   function( string $type, string $severity, array $extra ): array
	 * @return array<int, array<string, mixed>>
	 */
	public static function collect( \DOMXPath $xpath, $post_id, callable $issue ) {
		$out = array();

		if ( ! apply_filters( 'scorefix_collect_performance_heuristics', true, $post_id ) ) {
			return $out;
		}

		$root = $xpath->query( '//*[@id="scorefix-root"]' )->item( 0 );
		if ( ! $root instanceof \DOMElement ) {
			return $out;
		}

		$order = self::preorder_map( $root );

		$script_nodes = $xpath->query( '//script[@src and string-length( normalize-space( @src ) ) > 0]' );
		$script_src_n = $script_nodes instanceof \DOMNodeList ? $script_nodes->length : 0;
		$threshold    = (int) apply_filters( 'scorefix_perf_script_src_threshold', 15 );
		$threshold    = max( 5, min( 80, $threshold ) );
		if ( $script_src_n >= $threshold ) {
			$out[] = $issue(
				'perf_many_external_scripts',
				'medium',
				array(
					'post_id'              => (int) $post_id,
					'context'              => 'performance',
					'impact'               => 'performance',
					'script_src_count'     => $script_src_n,
					'script_src_threshold' => $threshold,
				)
			);
		}

		$main = $xpath->query( '//main' )->item( 0 );
		if ( ! $main instanceof \DOMElement ) {
			$main = $xpath->query( '//*[@role="main"]' )->item( 0 );
		}

		$first_block_in_main = null;
		if ( $main instanceof \DOMElement ) {
			$first_block_in_main = $xpath->query(
				'.//*[self::p or self::figure or self::h1 or self::h2 or self::h3 or self::h4 or self::h5 or self::h6 or self::ul or self::ol or self::blockquote or self::article]',
				$main
			)->item( 0 );
		}

		$imgs = $xpath->query( '//img' );
		if ( ! $imgs instanceof \DOMNodeList ) {
			return $out;
		}

		$first_img_in_main = null;
		if ( $main instanceof \DOMElement ) {
			$nim = $xpath->query( './/img', $main );
			if ( $nim instanceof \DOMNodeList ) {
				foreach ( $nim as $cand ) {
					if ( ! $cand instanceof \DOMElement ) {
						continue;
					}
					$r = strtolower( (string) $cand->getAttribute( 'role' ) );
					if ( 'presentation' === $r || 'none' === $r ) {
						continue;
					}
					$first_img_in_main = $cand;
					break;
				}
			}
		}

		$content_img_index = 0;
		foreach ( $imgs as $img ) {
			if ( ! $img instanceof \DOMElement ) {
				continue;
			}

			$role = strtolower( (string) $img->getAttribute( 'role' ) );
			if ( 'presentation' === $role || 'none' === $role ) {
				continue;
			}

			if ( ! self::img_has_dimensions( $img ) ) {
				$out[] = $issue(
					'perf_img_missing_dimensions',
					'medium',
					array(
						'post_id' => (int) $post_id,
						'context' => 'performance',
						'impact'  => 'performance',
						'src'     => substr( (string) $img->getAttribute( 'src' ), 0, 120 ),
					)
				);
			}

			if ( self::img_should_use_lazy_heuristic( $img, $order, $content_img_index, $main, $first_block_in_main, $first_img_in_main ) ) {
				$out[] = $issue(
					'perf_img_missing_lazy',
					'medium',
					array(
						'post_id' => (int) $post_id,
						'context' => 'performance',
						'impact'  => 'performance',
						'src'     => substr( (string) $img->getAttribute( 'src' ), 0, 120 ),
					)
				);
			}

			++$content_img_index;
		}

		return $out;
	}

	/**
	 * @param \DOMElement $root Root wrapper.
	 * @return \SplObjectStorage<\DOMElement, int>
	 */
	protected static function preorder_map( \DOMElement $root ) {
		$map = new \SplObjectStorage();
		$i   = 0;
		$walk = function ( \DOMNode $node ) use ( &$walk, &$map, &$i ) {
			if ( XML_ELEMENT_NODE === $node->nodeType ) {
				$map[ $node ] = $i++;
			}
			foreach ( $node->childNodes as $child ) {
				$walk( $child );
			}
		};
		$walk( $root );
		return $map;
	}

	/**
	 * @param \DOMElement $img Image element.
	 * @return bool True if width+height attrs or non-trivial width+height in style.
	 */
	protected static function img_has_dimensions( \DOMElement $img ) {
		$w = trim( (string) $img->getAttribute( 'width' ) );
		$h = trim( (string) $img->getAttribute( 'height' ) );
		if ( '' !== $w && '' !== $h ) {
			return true;
		}
		$style = (string) $img->getAttribute( 'style' );
		if ( '' === trim( $style ) ) {
			return false;
		}
		return self::style_has_axis( $style, 'width' ) && self::style_has_axis( $style, 'height' );
	}

	/**
	 * @param string $style Inline style.
	 * @param string $axis  width|height.
	 * @return bool
	 */
	protected static function style_has_axis( $style, $axis ) {
		$axis = 'width' === $axis ? 'width' : 'height';
		if ( ! preg_match( '/\b' . preg_quote( $axis, '/' ) . '\s*:\s*([^;]+)/i', $style, $m ) ) {
			return false;
		}
		$val = strtolower( trim( (string) $m[1] ) );
		if ( '' === $val || 'auto' === $val || '0' === $val || '0px' === $val ) {
			return false;
		}
		return true;
	}

	/**
	 * @param \DOMElement                    $img                 Image.
	 * @param \SplObjectStorage<\DOMElement, int> $order          Preorder positions under scorefix-root.
	 * @param int                          $content_img_index   Index among non-decorative imgs in document order.
	 * @param \DOMElement|null             $main                First main landmark.
	 * @param \DOMNode|null                $first_block_in_main First content-like block inside main.
	 * @param \DOMNode|null                $first_img_in_main   First img inside main.
	 * @return bool True if this image should be flagged for missing lazy loading.
	 */
	protected static function img_should_use_lazy_heuristic(
		\DOMElement $img,
		\SplObjectStorage $order,
		$content_img_index,
		$main,
		$first_block_in_main,
		$first_img_in_main
	) {
		$loading = strtolower( trim( (string) $img->getAttribute( 'loading' ) ) );
		if ( 'lazy' === $loading ) {
			return false;
		}
		if ( 'eager' === $loading ) {
			return false;
		}
		$fp = strtolower( trim( (string) $img->getAttribute( 'fetchpriority' ) ) );
		if ( 'high' === $fp ) {
			return false;
		}

		if ( 0 === $content_img_index ) {
			return false;
		}

		if ( ! $main instanceof \DOMElement ) {
			return 'lazy' !== $loading;
		}

		$inside = self::node_is_descendant_of( $img, $main );

		if ( ! $inside ) {
			return 'lazy' !== $loading;
		}

		if ( $first_img_in_main && $img === $first_img_in_main ) {
			return false;
		}

		if ( ! $order->contains( $img ) ) {
			return 'lazy' !== $loading;
		}
		$img_pos = $order[ $img ];

		if ( $first_block_in_main instanceof \DOMNode && $order->contains( $first_block_in_main ) ) {
			$block_pos = $order[ $first_block_in_main ];
			if ( $img_pos < $block_pos ) {
				return false;
			}
		}

		return 'lazy' !== $loading;
	}

	/**
	 * @param \DOMNode $node    Node.
	 * @param \DOMNode $ancestor Candidate ancestor.
	 * @return bool
	 */
	protected static function node_is_descendant_of( \DOMNode $node, \DOMNode $ancestor ) {
		for ( $p = $node->parentNode; $p; $p = $p->parentNode ) {
			if ( $p === $ancestor ) {
				return true;
			}
		}
		return false;
	}
}
