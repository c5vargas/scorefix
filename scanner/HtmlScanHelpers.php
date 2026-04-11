<?php
/**
 * Shared DOM helpers for HTML scanning rules.
 *
 * @package ScoreFix
 */

namespace ScoreFix\Scanner;

defined( 'ABSPATH' ) || exit;

/**
 * Class HtmlScanHelpers
 */
class HtmlScanHelpers {

	/**
	 * Whether interactive element has accessible name.
	 *
	 * @param \DOMElement $el Element.
	 * @return bool
	 */
	public static function element_has_accessible_name( \DOMElement $el ) {
		$label = trim( $el->textContent ?? '' );
		if ( $label !== '' ) {
			return true;
		}
		$aria = trim( (string) $el->getAttribute( 'aria-label' ) );
		if ( $aria !== '' ) {
			return true;
		}
		$labelledby = trim( (string) $el->getAttribute( 'aria-labelledby' ) );
		if ( $labelledby !== '' ) {
			return true;
		}
		$title = trim( (string) $el->getAttribute( 'title' ) );
		if ( $title !== '' ) {
			return true;
		}
		$imgs = $el->getElementsByTagName( 'img' );
		if ( $imgs->length > 0 ) {
			$img = $imgs->item( 0 );
			if ( $img instanceof \DOMElement ) {
				$alt = trim( (string) $img->getAttribute( 'alt' ) );
				if ( $alt !== '' ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Whether input, select, or textarea is associated with a label (explicit or wrapping).
	 *
	 * @param \DOMElement   $el  Element.
	 * @param \DOMDocument $dom Document.
	 * @return bool
	 */
	public static function form_control_has_label( \DOMElement $el, \DOMDocument $dom ) {
		$id = trim( (string) $el->getAttribute( 'id' ) );
		if ( $id !== '' ) {
			$xpath = new \DOMXPath( $dom );
			$labels = $xpath->query( '//label[@for="' . self::escape_xpath_literal( $id ) . '"]' );
			if ( $labels && $labels->length > 0 ) {
				return true;
			}
		}
		$aria = trim( (string) $el->getAttribute( 'aria-label' ) );
		if ( $aria !== '' ) {
			return true;
		}
		$alb = trim( (string) $el->getAttribute( 'aria-labelledby' ) );
		if ( $alb !== '' ) {
			return true;
		}
		$xpath = new \DOMXPath( $dom );
		$wrap = $xpath->query( 'ancestor::label', $el );
		if ( $wrap && $wrap->length > 0 ) {
			return true;
		}
		$placeholder = trim( (string) $el->getAttribute( 'placeholder' ) );
		if ( $placeholder !== '' ) {
			return apply_filters( 'scorefix_count_placeholder_as_label', false, $el );
		}
		return false;
	}

	/**
	 * Escape string for XPath literal.
	 *
	 * @param string $s String.
	 * @return string
	 */
	public static function escape_xpath_literal( $s ) {
		if ( false === strpos( $s, "'" ) ) {
			return "'" . $s . "'";
		}
		if ( false === strpos( $s, '"' ) ) {
			return '"' . $s . '"';
		}
		return "concat('" . str_replace( "'", "', \"'\", '", $s ) . "')";
	}
}
