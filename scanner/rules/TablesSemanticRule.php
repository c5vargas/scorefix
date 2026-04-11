<?php
/**
 * Data table heuristics (th, caption).
 *
 * @package ScoreFix
 */

namespace ScoreFix\Scanner\Rules;

defined( 'ABSPATH' ) || exit;

/**
 * Class TablesSemanticRule
 */
class TablesSemanticRule {

	const ROOT = "//*[@id='scorefix-root']";

	/**
	 * @param \DOMXPath $xpath   Document.
	 * @param int       $post_id Post ID.
	 * @param callable  $issue   Issue factory.
	 * @return array<int, array<string, mixed>>
	 */
	public static function collect( \DOMXPath $xpath, $post_id, callable $issue ) {
		$out     = array();
		$max     = (int) apply_filters( 'scorefix_scan_max_table_issues', 20, $post_id );
		$max     = max( 1, min( 60, $max ) );
		$nodes   = $xpath->query( self::ROOT . '//table[not(ancestor::table)]' );
		$ordinal = 0;
		if ( ! $nodes ) {
			return $out;
		}

		foreach ( $nodes as $table ) {
			if ( ! $table instanceof \DOMElement ) {
				continue;
			}
			$role = strtolower( (string) $table->getAttribute( 'role' ) );
			if ( 'presentation' === $role || 'none' === $role ) {
				continue;
			}

			$tds = $table->getElementsByTagName( 'td' );
			if ( 0 === $tds->length ) {
				continue;
			}

			$ths = $table->getElementsByTagName( 'th' );
			if ( 0 === $ths->length ) {
				if ( count( $out ) >= $max ) {
					break;
				}
				++$ordinal;
				$out[] = $issue(
					'table_missing_th',
					'medium',
					array(
						'post_id'       => (int) $post_id,
						'context'       => 'content',
						'impact'        => 'readability',
						'table_ordinal' => (int) $ordinal,
					)
				);
			}

			$captions = $table->getElementsByTagName( 'caption' );
			if ( 0 === $captions->length && self::table_suggests_caption( $table ) ) {
				if ( count( $out ) >= $max ) {
					break;
				}
				++$ordinal;
				$out[] = $issue(
					'table_missing_caption',
					'low',
					array(
						'post_id'       => (int) $post_id,
						'context'       => 'content',
						'impact'        => 'readability',
						'table_ordinal' => (int) $ordinal,
					)
				);
			}
		}

		return $out;
	}

	/**
	 * @param \DOMElement $table Table element.
	 * @return bool
	 */
	protected static function table_suggests_caption( \DOMElement $table ) {
		$rows = $table->getElementsByTagName( 'tr' );
		if ( $rows->length >= 2 ) {
			return true;
		}
		return $table->getElementsByTagName( 'td' )->length >= 4;
	}
}
