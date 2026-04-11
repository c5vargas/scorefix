<?php
/**
 * Form semantics: radio groups, required hints, autocomplete.
 *
 * @package ScoreFix
 */

namespace ScoreFix\Scanner\Rules;

use ScoreFix\Scanner\HtmlScanHelpers;

defined( 'ABSPATH' ) || exit;

/**
 * Class FormsSemanticsRule
 */
class FormsSemanticsRule {

	const ROOT = "//*[@id='scorefix-root']";

	/**
	 * @param \DOMXPath $xpath   Document.
	 * @param int       $post_id Post ID.
	 * @param callable  $issue   Issue factory.
	 * @return array<int, array<string, mixed>>
	 */
	public static function collect( \DOMXPath $xpath, $post_id, callable $issue ) {
		$out = array_merge(
			self::collect_radio_groups( $xpath, (int) $post_id, $issue ),
			self::collect_required_hints( $xpath, (int) $post_id, $issue ),
			self::collect_autocomplete_gaps( $xpath, (int) $post_id, $issue )
		);
		return $out;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	protected static function collect_radio_groups( \DOMXPath $xpath, $post_id, callable $issue ) {
		$out        = array();
		$max        = (int) apply_filters( 'scorefix_scan_max_radio_group_issues', 15, $post_id );
		$max        = max( 1, min( 50, $max ) );
		$seen_names = array();

		$inputs = $xpath->query( self::ROOT . '//input[translate(@type,"RADIO","radio")="radio"]' );
		if ( ! $inputs ) {
			return $out;
		}

		foreach ( $inputs as $input ) {
			if ( ! $input instanceof \DOMElement ) {
				continue;
			}
			$name = (string) $input->getAttribute( 'name' );
			$name = trim( $name );
			if ( '' === $name ) {
				continue;
			}
			if ( isset( $seen_names[ $name ] ) ) {
				continue;
			}

			$group = $xpath->query( self::ROOT . '//input[translate(@type,"RADIO","radio")="radio"][@name=' . HtmlScanHelpers::escape_xpath_literal( $name ) . ']' );
			if ( ! $group || $group->length < 2 ) {
				$seen_names[ $name ] = true;
				continue;
			}

			$first = $group->item( 0 );
			if ( ! $first instanceof \DOMElement ) {
				$seen_names[ $name ] = true;
				continue;
			}

			if ( self::radio_group_has_legend( $xpath, $first ) ) {
				$seen_names[ $name ] = true;
				continue;
			}

			if ( count( $out ) >= $max ) {
				break;
			}

			$out[] = $issue(
				'form_radio_group_no_legend',
				'medium',
				array(
					'post_id'    => (int) $post_id,
					'context'    => 'content',
					'impact'     => 'conversion',
					'group_name' => substr( $name, 0, 80 ),
				)
			);
			$seen_names[ $name ] = true;
		}

		return $out;
	}

	/**
	 * @param \DOMXPath   $xpath XPath.
	 * @param \DOMElement $radio Any radio in the group.
	 * @return bool
	 */
	protected static function radio_group_has_legend( \DOMXPath $xpath, \DOMElement $radio ) {
		$fs = $xpath->query( 'ancestor::fieldset', $radio );
		if ( $fs && $fs->length > 0 ) {
			$fieldset = $fs->item( 0 );
			if ( $fieldset instanceof \DOMElement ) {
				foreach ( $fieldset->getElementsByTagName( 'legend' ) as $leg ) {
					if ( $leg instanceof \DOMElement && '' !== trim( $leg->textContent ?? '' ) ) {
						return true;
					}
				}
			}
		}
		$rg = $xpath->query( 'ancestor::*[translate(@role,"GROUP","group")="group"]', $radio );
		if ( $rg && $rg->length > 0 ) {
			$g = $rg->item( 0 );
			if ( $g instanceof \DOMElement ) {
				$al = trim( (string) $g->getAttribute( 'aria-label' ) );
				if ( '' !== $al ) {
					return true;
				}
				if ( '' !== trim( (string) $g->getAttribute( 'aria-labelledby' ) ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	protected static function collect_required_hints( \DOMXPath $xpath, $post_id, callable $issue ) {
		$out = array();
		$max = (int) apply_filters( 'scorefix_scan_max_required_hint_issues', 20, $post_id );
		$max = max( 1, min( 80, $max ) );

		$query = self::ROOT . '//input[( @required or translate(@aria-required,"TRUE","true")="true" )] | ' .
			self::ROOT . '//select[@required] | ' .
			self::ROOT . '//textarea[@required]';

		$nodes = $xpath->query( $query );
		if ( ! $nodes ) {
			return $out;
		}

		foreach ( $nodes as $el ) {
			if ( ! $el instanceof \DOMElement ) {
				continue;
			}
			if ( self::required_field_has_error_affordance( $xpath, $el ) ) {
				continue;
			}
			$tag = strtolower( $el->tagName );
			if ( 'input' === $tag ) {
				$type = strtolower( (string) $el->getAttribute( 'type' ) );
				if ( '' === $type ) {
					$type = 'text';
				}
				$skip_types = array( 'checkbox', 'radio', 'hidden', 'submit', 'button', 'file', 'image', 'reset', 'range', 'color' );
				if ( in_array( $type, $skip_types, true ) ) {
					continue;
				}
			}
			if ( count( $out ) >= $max ) {
				break;
			}
			$type = strtolower( (string) $el->getAttribute( 'type' ) );
			if ( '' === $type && 'input' === $tag ) {
				$type = 'text';
			}
			$input_type = 'input' === $tag ? $type : $tag;
			$out[]      = $issue(
				'form_required_no_error_hint',
				'low',
				array(
					'post_id'     => (int) $post_id,
					'context'     => 'content',
					'impact'      => 'conversion',
					'control_tag' => $tag,
					'input_type'  => $input_type,
				)
			);
		}

		return $out;
	}

	/**
	 * Heuristic: required control has some error / description wiring.
	 *
	 * @param \DOMXPath   $xpath XPath.
	 * @param \DOMElement $el    Control.
	 * @return bool True if hint wiring looks present.
	 */
	protected static function required_field_has_error_affordance( \DOMXPath $xpath, \DOMElement $el ) {
		$ad = trim( (string) $el->getAttribute( 'aria-describedby' ) );
		if ( '' !== $ad ) {
			return true;
		}
		$em = trim( (string) $el->getAttribute( 'aria-errormessage' ) );
		if ( '' !== $em ) {
			return true;
		}
		$next = $el->nextSibling;
		$steps = 0;
		while ( $next && $steps < 5 ) {
			if ( $next instanceof \DOMElement ) {
				$class = strtolower( (string) $next->getAttribute( 'class' ) );
				$role  = strtolower( (string) $next->getAttribute( 'role' ) );
				if ( 'alert' === $role || false !== strpos( $class, 'error' ) || false !== strpos( $class, 'invalid' ) ) {
					return true;
				}
			}
			$next = $next->nextSibling;
			++$steps;
		}
		return false;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	protected static function collect_autocomplete_gaps( \DOMXPath $xpath, $post_id, callable $issue ) {
		$out = array();
		$max = (int) apply_filters( 'scorefix_scan_max_autocomplete_issues', 25, $post_id );
		$max = max( 1, min( 80, $max ) );

		$inputs = $xpath->query( self::ROOT . '//input' );
		if ( ! $inputs ) {
			return $out;
		}

		foreach ( $inputs as $input ) {
			if ( ! $input instanceof \DOMElement ) {
				continue;
			}
			$ac = trim( (string) $input->getAttribute( 'autocomplete' ) );
			if ( '' !== $ac ) {
				continue;
			}
			$type = strtolower( (string) $input->getAttribute( 'type' ) );
			if ( '' === $type ) {
				$type = 'text';
			}
			$id   = strtolower( (string) $input->getAttribute( 'id' ) );
			$name = strtolower( (string) $input->getAttribute( 'name' ) );

			$needs = false;
			if ( 'email' === $type ) {
				$needs = true;
			} elseif ( 'tel' === $type ) {
				$needs = true;
			} elseif ( 'text' === $type || 'search' === $type ) {
				$hay = $id . ' ' . $name;
				if ( preg_match( '/email|e-mail|mail|phone|tel|mobile|fname|first_?name|lname|last_?name|billing|your-name|postcode|zip|address|city|state|country/i', $hay ) ) {
					$needs = true;
				}
			}

			if ( ! $needs ) {
				continue;
			}
			if ( count( $out ) >= $max ) {
				break;
			}
			$out[] = $issue(
				'form_autocomplete_missing',
				'low',
				array(
					'post_id'    => (int) $post_id,
					'context'    => 'content',
					'impact'     => 'conversion',
					'input_type' => $type,
					'field_hint' => substr( $id ? $id : $name, 0, 60 ),
				)
			);
		}

		return $out;
	}
}
