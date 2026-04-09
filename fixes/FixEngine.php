<?php
/**
 * Runtime fixes (non-destructive): output filters only.
 *
 * @package ScoreFix
 */

namespace ScoreFix\Fixes;

defined( 'ABSPATH' ) || exit;

/**
 * Class FixEngine
 */
class FixEngine {

	/**
	 * Process post content HTML and apply accessibility fixes.
	 *
	 * @param string $content HTML content.
	 * @return string
	 */
	public function process_content( $content ) {
		if ( '' === trim( (string) $content ) ) {
			return $content;
		}

		if ( preg_match( '/<(?:!doctype|html|body)\b/i', (string) $content ) ) {
			return $this->process_document_html( $content );
		}

		libxml_use_internal_errors( true );
		$dom = new \DOMDocument();
		$wrapped = '<div id="scorefix-root">' . $content . '</div>';
		$dom->loadHTML( '<?xml encoding="utf-8"?>' . $wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();

		$root = $dom->getElementById( 'scorefix-root' );
		if ( ! $root ) {
			return $content;
		}

		$this->fix_images( $dom, $root );
		$this->fix_links( $dom, $root );
		$this->fix_buttons( $dom, $root );
		$this->fix_inputs( $dom, $root );

		$out = '';
		foreach ( $root->childNodes as $child ) {
			$out .= $dom->saveHTML( $child );
		}

		return $out;
	}

	/**
	 * Process full HTML document and keep global structure.
	 *
	 * @param string $html Full document.
	 * @return string
	 */
	protected function process_document_html( $html ) {
		libxml_use_internal_errors( true );
		$dom = new \DOMDocument();
		$loaded = $dom->loadHTML( '<?xml encoding="utf-8"?>' . $html );
		libxml_clear_errors();

		if ( ! $loaded || ! $dom->documentElement ) {
			return $html;
		}

		$root = $dom->getElementsByTagName( 'body' )->item( 0 );
		if ( ! $root instanceof \DOMElement ) {
			$root = $dom->documentElement;
		}

		$this->fix_images( $dom, $root );
		$this->fix_links( $dom, $root );
		$this->fix_buttons( $dom, $root );
		$this->fix_inputs( $dom, $root );

		$out = $dom->saveHTML();
		if ( ! is_string( $out ) || '' === $out ) {
			return $html;
		}

		return (string) preg_replace( '/^<\?xml[^>]+>\s*/', '', $out );
	}

	/**
	 * Fix img alt attributes.
	 *
	 * @param \DOMDocument $dom  Document.
	 * @param \DOMElement  $root Root fragment.
	 * @return void
	 */
	protected function fix_images( \DOMDocument $dom, \DOMElement $root ) {
		$xpath = new \DOMXPath( $dom );
		foreach ( $xpath->query( './/img', $root ) as $img ) {
			if ( ! $img instanceof \DOMElement ) {
				continue;
			}
			$role = strtolower( (string) $img->getAttribute( 'role' ) );
			if ( 'presentation' === $role || 'none' === $role ) {
				continue;
			}
			$alt = $img->hasAttribute( 'alt' ) ? trim( $img->getAttribute( 'alt' ) ) : '';
			if ( '' !== $alt ) {
				continue;
			}

			$attachment_id = $this->guess_attachment_id_from_img( $img );
			$fallback      = $attachment_id
				? self::fallback_alt_for_attachment( $attachment_id )
				: self::fallback_alt_from_src( (string) $img->getAttribute( 'src' ) );

			$img->setAttribute( 'alt', $fallback );
			$this->bump_stat( 'img_alt' );
		}
	}

	/**
	 * Guess attachment ID from class wp-image-{id}.
	 *
	 * @param \DOMElement $img Image.
	 * @return int
	 */
	protected function guess_attachment_id_from_img( \DOMElement $img ) {
		$class = (string) $img->getAttribute( 'class' );
		if ( preg_match( '/\bwp-image-(\d+)\b/', $class, $m ) ) {
			return (int) $m[1];
		}
		return 0;
	}

	/**
	 * Fallback alt text for attachment.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return string
	 */
	public static function fallback_alt_for_attachment( $attachment_id ) {
		$alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
		if ( is_string( $alt ) && trim( $alt ) !== '' ) {
			return trim( $alt );
		}
		$post = get_post( $attachment_id );
		if ( $post && $post->post_title ) {
			return sanitize_text_field( $post->post_title );
		}
		return __( 'Image', 'scorefix' );
	}

	/**
	 * Fallback from image URL filename.
	 *
	 * @param string $src URL.
	 * @return string
	 */
	public static function fallback_alt_from_src( $src ) {
		$path = wp_parse_url( $src, PHP_URL_PATH );
		if ( ! is_string( $path ) || '' === $path ) {
			return __( 'Image', 'scorefix' );
		}
		$file = basename( $path );
		$file = preg_replace( '/\.[^.]+$/', '', $file );
		$file = str_replace( array( '-', '_' ), ' ', $file );
		$file = trim( $file );
		if ( '' === $file ) {
			return __( 'Image', 'scorefix' );
		}
		return sanitize_text_field( $file );
	}

	/**
	 * Fix links without accessible name.
	 *
	 * @param \DOMDocument $dom  Document.
	 * @param \DOMElement  $root Root.
	 * @return void
	 */
	protected function fix_links( \DOMDocument $dom, \DOMElement $root ) {
		$xpath = new \DOMXPath( $dom );
		foreach ( $xpath->query( './/a[@href]', $root ) as $a ) {
			if ( ! $a instanceof \DOMElement ) {
				continue;
			}
			if ( $this->element_has_accessible_name( $a ) ) {
				continue;
			}
			$href = (string) $a->getAttribute( 'href' );
			$label = self::label_from_url( $href );
			$a->setAttribute( 'aria-label', $label );
			$this->bump_stat( 'link_aria' );
		}
	}

	/**
	 * Fix buttons without accessible name.
	 *
	 * @param \DOMDocument $dom  Document.
	 * @param \DOMElement  $root Root.
	 * @return void
	 */
	protected function fix_buttons( \DOMDocument $dom, \DOMElement $root ) {
		$xpath = new \DOMXPath( $dom );
		foreach ( $xpath->query( './/button', $root ) as $btn ) {
			if ( ! $btn instanceof \DOMElement ) {
				continue;
			}
			if ( $this->element_has_accessible_name( $btn ) ) {
				continue;
			}
			$btn->setAttribute( 'aria-label', __( 'Button', 'scorefix' ) );
			$this->bump_stat( 'button_aria' );
		}
	}

	/**
	 * Fix inputs without label (aria-label fallback).
	 *
	 * @param \DOMDocument $dom  Document.
	 * @param \DOMElement  $root Root.
	 * @return void
	 */
	protected function fix_inputs( \DOMDocument $dom, \DOMElement $root ) {
		$xpath = new \DOMXPath( $dom );
		$input_types = array( 'text', 'email', 'tel', 'url', 'search', 'number', 'password' );
		foreach ( $xpath->query( './/input', $root ) as $input ) {
			if ( ! $input instanceof \DOMElement ) {
				continue;
			}
			$type = strtolower( (string) $input->getAttribute( 'type' ) );
			if ( '' === $type ) {
				$type = 'text';
			}
			if ( ! in_array( $type, $input_types, true ) ) {
				continue;
			}
			if ( $this->input_has_explicit_label( $input, $dom ) ) {
				continue;
			}
			$name = trim( (string) $input->getAttribute( 'name' ) );
			$ph   = trim( (string) $input->getAttribute( 'placeholder' ) );
			if ( $ph !== '' ) {
				$input->setAttribute( 'aria-label', sanitize_text_field( $ph ) );
			} elseif ( $name !== '' ) {
				$input->setAttribute( 'aria-label', sanitize_text_field( str_replace( array( '-', '_' ), ' ', $name ) ) );
			} else {
				$input->setAttribute( 'aria-label', __( 'Field', 'scorefix' ) );
			}
			$this->bump_stat( 'input_aria' );
		}
	}

	/**
	 * Human-ish label from URL.
	 *
	 * @param string $href URL.
	 * @return string
	 */
	protected static function label_from_url( $href ) {
		$path = wp_parse_url( $href, PHP_URL_PATH );
		$host = wp_parse_url( $href, PHP_URL_HOST );
		if ( is_string( $path ) && $path !== '' && $path !== '/' ) {
			$seg = trim( basename( $path ), '/' );
			if ( $seg !== '' ) {
				$seg = str_replace( array( '-', '_' ), ' ', $seg );
				/* translators: %s: page or path segment */
				return sanitize_text_field( sprintf( __( 'Link: %s', 'scorefix' ), $seg ) );
			}
		}
		if ( is_string( $host ) && $host !== '' ) {
			/* translators: %s: hostname */
			return sanitize_text_field( sprintf( __( 'Link to %s', 'scorefix' ), $host ) );
		}
		return __( 'Link', 'scorefix' );
	}

	/**
	 * @param \DOMElement $el Element.
	 * @return bool
	 */
	protected function element_has_accessible_name( \DOMElement $el ) {
		$label = trim( $el->textContent ?? '' );
		if ( $label !== '' ) {
			return true;
		}
		if ( trim( (string) $el->getAttribute( 'aria-label' ) ) !== '' ) {
			return true;
		}
		if ( trim( (string) $el->getAttribute( 'aria-labelledby' ) ) !== '' ) {
			return true;
		}
		if ( trim( (string) $el->getAttribute( 'title' ) ) !== '' ) {
			return true;
		}
		$imgs = $el->getElementsByTagName( 'img' );
		if ( $imgs->length > 0 ) {
			$img = $imgs->item( 0 );
			if ( $img instanceof \DOMElement && trim( (string) $img->getAttribute( 'alt' ) ) !== '' ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param \DOMElement   $input Input.
	 * @param \DOMDocument $dom   Document.
	 * @return bool
	 */
	protected function input_has_explicit_label( \DOMElement $input, \DOMDocument $dom ) {
		$id = trim( (string) $input->getAttribute( 'id' ) );
		if ( $id !== '' ) {
			$xpath = new \DOMXPath( $dom );
			$labels = $xpath->query( '//label[@for="' . self::xpath_escape( $id ) . '"]' );
			if ( $labels && $labels->length > 0 ) {
				return true;
			}
		}
		if ( trim( (string) $input->getAttribute( 'aria-label' ) ) !== '' ) {
			return true;
		}
		if ( trim( (string) $input->getAttribute( 'aria-labelledby' ) ) !== '' ) {
			return true;
		}
		return false;
	}

	/**
	 * Escape for simple XPath attribute match.
	 *
	 * @param string $id Id.
	 * @return string
	 */
	protected static function xpath_escape( $id ) {
		if ( false === strpos( $id, '"' ) ) {
			return '"' . $id . '"';
		}
		if ( false === strpos( $id, "'" ) ) {
			return "'" . $id . "'";
		}
		return '"' . str_replace( '"', '\"', $id ) . '"';
	}

	/**
	 * Increment runtime fix stats (lightweight).
	 *
	 * @param string $key Stat key.
	 * @return void
	 */
	protected function bump_stat( $key ) {
		$stats = get_option( 'scorefix_fix_stats', array() );
		if ( ! is_array( $stats ) ) {
			$stats = array();
		}
		$today = gmdate( 'Y-m-d' );
		if ( ! isset( $stats[ $today ] ) || ! is_array( $stats[ $today ] ) ) {
			$stats[ $today ] = array();
		}
		if ( ! isset( $stats[ $today ][ $key ] ) ) {
			$stats[ $today ][ $key ] = 0;
		}
		$stats[ $today ][ $key ]++;
		// Keep last 14 days only.
		$stats = array_slice( $stats, -14, null, true );
		update_option( 'scorefix_fix_stats', $stats, false );
	}

	/**
	 * Filter callback for wp_get_attachment_image_attributes.
	 *
	 * @param array<string, string> $attr       Attributes.
	 * @param \WP_Post               $attachment Attachment.
	 * @return array<string, string>
	 */
	public function filter_attachment_image_attributes( $attr, $attachment ) {
		if ( empty( $attr['alt'] ) ) {
			$attr['alt'] = self::fallback_alt_for_attachment( (int) $attachment->ID );
			$this->bump_stat( 'img_alt_attr' );
		}
		return $attr;
	}
}
