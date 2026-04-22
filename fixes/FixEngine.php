<?php
/**
 * Runtime fixes (non-destructive): output filters only.
 *
 * @package ScoreFix
 */

namespace ScoreFix\Fixes;

use ScoreFix\Support\ViewportMeta;

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
		$this->fix_selects_and_textareas( $dom, $root );
		$this->fix_iframe_title( $dom, $root );
		$this->fix_multiple_h1( $dom, $root );
		$this->fix_landmark_nav( $dom, $root );
		$this->fix_table_scope( $dom, $root );
$this->fix_video_text_alternative( $dom, $root );
		$this->fix_audio_text_alternative( $dom, $root );

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

		$this->fix_viewport_metas( $dom );

		$root = $dom->getElementsByTagName( 'body' )->item( 0 );
		if ( ! $root instanceof \DOMElement ) {
			$root = $dom->documentElement;
		}

		$this->fix_images( $dom, $root );
		$this->fix_links( $dom, $root );
		$this->fix_buttons( $dom, $root );
		$this->fix_inputs( $dom, $root );
		$this->fix_selects_and_textareas( $dom, $root );
		$this->fix_iframe_title( $dom, $root );
		$this->fix_multiple_h1( $dom, $root );
		$this->fix_landmark_nav( $dom, $root );
		$this->fix_table_scope( $dom, $root );
$this->fix_video_text_alternative( $dom, $root );
		$this->fix_audio_text_alternative( $dom, $root );

		$out = $dom->saveHTML();
		if ( ! is_string( $out ) || '' === $out ) {
			return $html;
		}

		return (string) preg_replace( '/^<\?xml[^>]+>\s*/', '', $out );
	}

	/**
	 * Normalize viewport meta tags (SEO / zoom).
	 *
	 * @param \DOMDocument $dom Document.
	 * @return void
	 */
	protected function fix_viewport_metas( \DOMDocument $dom ) {
		$metas = $dom->getElementsByTagName( 'meta' );
		if ( ! $metas ) {
			return;
		}
		for ( $i = 0; $i < $metas->length; $i++ ) {
			$m = $metas->item( $i );
			if ( ! $m instanceof \DOMElement ) {
				continue;
			}
			if ( 'viewport' !== strtolower( trim( (string) $m->getAttribute( 'name' ) ) ) ) {
				continue;
			}
			$c = trim( (string) $m->getAttribute( 'content' ) );
			if ( '' === $c ) {
				continue;
			}
			$n = ViewportMeta::normalize_content( $c );
			if ( $n !== $c ) {
				$m->setAttribute( 'content', $n );
				$this->bump_stat( 'viewport_meta' );
			}
		}
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
		$input_types = array( 'text', 'email', 'tel', 'url', 'search', 'number', 'password', 'checkbox', 'radio' );
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
			if ( $this->form_control_has_explicit_label( $input, $dom ) ) {
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
	 * Fix select and textarea without label (aria-label fallback).
	 *
	 * @param \DOMDocument $dom  Document.
	 * @param \DOMElement  $root Root.
	 * @return void
	 */
	protected function fix_selects_and_textareas( \DOMDocument $dom, \DOMElement $root ) {
		$xpath = new \DOMXPath( $dom );
		foreach ( array( 'select', 'textarea' ) as $tag ) {
			foreach ( $xpath->query( './/' . $tag, $root ) as $el ) {
				if ( ! $el instanceof \DOMElement ) {
					continue;
				}
				if ( $this->form_control_has_explicit_label( $el, $dom ) ) {
					continue;
				}
				$name = trim( (string) $el->getAttribute( 'name' ) );
				$ph   = 'textarea' === $tag ? trim( (string) $el->getAttribute( 'placeholder' ) ) : '';
				if ( $ph !== '' ) {
					$el->setAttribute( 'aria-label', sanitize_text_field( $ph ) );
				} elseif ( $name !== '' ) {
					$el->setAttribute( 'aria-label', sanitize_text_field( str_replace( array( '-', '_' ), ' ', $name ) ) );
				} else {
					$el->setAttribute(
						'aria-label',
						'select' === $tag ? __( 'Selection', 'scorefix' ) : __( 'Field', 'scorefix' )
					);
				}
				$this->bump_stat( 'input_aria' );
			}
		}
	}

	/**
	 * Fix iframe missing title attribute.
	 *
	 * @param \DOMDocument $dom  Document.
	 * @param \DOMElement  $root Root fragment.
	 * @return void
	 */
	protected function fix_iframe_title( \DOMDocument $dom, \DOMElement $root ) {
		$xpath = new \DOMXPath( $dom );
		$iframes = $xpath->query( './/iframe[not(@title)]', $root );
		if ( ! $iframes || 0 === $iframes->length ) {
			return;
		}

		foreach ( $iframes as $iframe ) {
			if ( ! $iframe instanceof \DOMElement ) {
				continue;
			}
			$src = trim( (string) $iframe->getAttribute( 'src' ) );
			if ( '' === $src ) {
				continue;
			}

			$path = wp_parse_url( $src, PHP_URL_PATH );
			if ( ! is_string( $path ) || '' === $path ) {
				continue;
			}

			$file = basename( $path );
			$file = preg_replace( '/\.[^.]+$/', '', $file );
			$file = trim( $file );

			if ( '' === $file ) {
				$file = 'Embedded content';
			}

			$title = sanitize_text_field( $file );
			if ( '' === $title ) {
				continue;
			}

			$iframe->setAttribute( 'title', $title );
			$this->bump_stat( 'iframe_title' );
		}
	}

	/**
	 * Fix multiple h1 headings - convert extra h1 to h2.
	 *
	 * @param \DOMDocument $dom  Document.
	 * @param \DOMElement  $root Root fragment.
	 * @return void
	 */
	protected function fix_multiple_h1( \DOMDocument $dom, \DOMElement $root ) {
		$xpath = new \DOMXPath( $dom );
		$h1s = $xpath->query( './/h1', $root );
		if ( ! $h1s || $h1s->length <= 1 ) {
			return;
		}

		// First h1 stays, convert the rest to h2.
		$first = true;
		foreach ( $h1s as $h1 ) {
			if ( ! $h1 instanceof \DOMElement ) {
				continue;
			}
			if ( $first ) {
				$first = false;
				continue;
			}

			$new_element = $dom->createElement( 'h2' );
			while ( $h1->firstChild ) {
				$new_element->appendChild( $h1->firstChild );
			}
			foreach ( $h1->attributes as $attr ) {
				if ( 'id' === $attr->name || 'class' === $attr->name ) {
					$new_element->setAttribute( $attr->name, $attr->value );
				}
			}
			$h1->parentNode->replaceChild( $new_element, $h1 );
			$this->bump_stat( 'heading_h1_to_h2' );
		}
	}

	/**
	 * Fix nav element without accessible name.
	 *
	 * @param \DOMDocument $dom  Document.
	 * @param \DOMElement  $root Root fragment.
	 * @return void
	 */
	protected function fix_landmark_nav( \DOMDocument $dom, \DOMElement $root ) {
		$xpath = new \DOMXPath( $dom );
		$navs = $xpath->query( './/nav[not(@aria-label) and not(@aria-labelledby)]', $root );
		if ( ! $navs || 0 === $navs->length ) {
			return;
		}

		foreach ( $navs as $nav ) {
			if ( ! $nav instanceof \DOMElement ) {
				continue;
			}
			$heading_text = self::heading_text_for_nav( $nav, $xpath );
			if ( null === $heading_text || '' === $heading_text ) {
				continue;
			}
			if ( mb_strlen( $heading_text ) > 100 ) {
				continue;
			}

			$nav->setAttribute( 'aria-label', $heading_text );
			$this->bump_stat( 'nav_aria_label' );
		}
	}

	/**
	 * Find closest preceding heading text for nav element.
	 *
	 * @param \DOMElement  $nav   Nav element.
	 * @param \DOMXPath   $xpath XPath instance.
	 * @return string|null
	 */
	protected static function heading_text_for_nav( \DOMElement $nav, \DOMXPath $xpath ) {
		$headings = $xpath->query( 'preceding::h1[1] | preceding::h2[1] | preceding::h3[1] | preceding::h4[1] | preceding::h5[1] | preceding::h6[1]', $nav );
		if ( ! $headings || 0 === $headings->length ) {
			return null;
		}

		$heading = $headings->item( 0 );
		if ( ! $heading instanceof \DOMElement ) {
			return null;
		}

		$text = trim( $heading->textContent ?? '' );
		if ( '' === $text ) {
			return null;
		}

		return sanitize_text_field( $text );
	}

	/**
	 * Fix th missing scope attribute in first table row.
	 *
	 * @param \DOMDocument $dom  Document.
	 * @param \DOMElement  $root Root fragment.
	 * @return void
	 */
	protected function fix_table_scope( \DOMDocument $dom, \DOMElement $root ) {
		$xpath = new \DOMXPath( $dom );
		$tables = $xpath->query( './/table', $root );
		if ( ! $tables || 0 === $tables->length ) {
			return;
		}

		foreach ( $tables as $table ) {
			if ( ! $table instanceof \DOMElement ) {
				continue;
			}
			$first_row = $table->getElementsByTagName( 'tr' )->item( 0 );
			if ( ! $first_row instanceof \DOMElement ) {
				continue;
			}

			$cells = $first_row->getElementsByTagName( '*' );
			if ( 0 === $cells->length ) {
				continue;
			}

			$modified = false;
			foreach ( $cells as $cell ) {
				if ( ! $cell instanceof \DOMElement ) {
					continue;
				}
				if ( 'th' === $cell->tagName && ! $cell->hasAttribute( 'scope' ) ) {
					$cell->setAttribute( 'scope', 'col' );
					$modified = true;
				} elseif ( 'td' === $cell->tagName && ! $cell->hasAttribute( 'scope' ) ) {
					// Convert first td without th to th.
					$new_th = $dom->createElement( 'th' );
					$new_th->setAttribute( 'scope', 'col' );
					while ( $cell->firstChild ) {
						$new_th->appendChild( $cell->firstChild );
					}
					foreach ( $cell->attributes as $attr ) {
						if ( 'id' === $attr->name || 'class' === $attr->name ) {
							$new_th->setAttribute( $attr->name, $attr->value );
						}
					}
					$cell->parentNode->replaceChild( $new_th, $cell );
					$modified = true;
				}
				if ( $modified ) {
					break; // Only process first row once.
				}
			}

			if ( $modified ) {
				$this->bump_stat( 'th_scope_col' );
			}
		}
	}

	/**
<<<<<<< HEAD
	 * Fix video elements without text alternative (track, aria-label, or figcaption).
	 *
	 * @param \DOMDocument $dom  Document.
	 * @param \DOMElement  $root Root fragment.
	 * @return void
	 */
	protected function fix_video_text_alternative( \DOMDocument $dom, \DOMElement $root ) {
		$xpath = new \DOMXPath( $dom );
		$videos = $xpath->query( './/video', $root );
		if ( ! $videos || 0 === $videos->length ) {
			return;
		}

		foreach ( $videos as $video ) {
			if ( ! $video instanceof \DOMElement ) {
				continue;
			}
			if ( $this->video_has_text_alternative( $video, $xpath ) ) {
				continue;
			}

			$text = $this->text_alternative_for_media( $video, $xpath );
			if ( null === $text ) {
				continue;
			}

			$video->setAttribute( 'aria-label', $text );
			$this->bump_stat( 'video_aria_label' );
		}
	}

	/**
	 * Check if video has a text alternative.
	 *
	 * @param \DOMElement $video Video element.
	 * @param \DOMXPath  $xpath XPath instance.
	 * @return bool
	 */
	protected function video_has_text_alternative( \DOMElement $video, \DOMXPath $xpath ) {
		$tracks = $video->getElementsByTagName( 'track' );
		foreach ( $tracks as $track ) {
			if ( ! $track instanceof \DOMElement ) {
				continue;
			}
			$kind = strtolower( (string) $track->getAttribute( 'kind' ) );
			if ( in_array( $kind, array( 'captions', 'subtitles', 'descriptions' ), true ) ) {
				return true;
			}
		}
		$al = trim( (string) $video->getAttribute( 'aria-label' ) );
		if ( mb_strlen( $al ) > 16 ) {
			return true;
		}
		$fig = $xpath->query( 'ancestor::figure[1]//figcaption', $video );
		if ( $fig && $fig->length > 0 ) {
			$fc = $fig->item( 0 );
			if ( $fc instanceof \DOMElement && mb_strlen( trim( $fc->textContent ?? '' ) ) > 12 ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Fix audio elements without text alternative.
	 *
	 * @param \DOMDocument $dom  Document.
	 * @param \DOMElement  $root Root fragment.
	 * @return void
	 */
	protected function fix_audio_text_alternative( \DOMDocument $dom, \DOMElement $root ) {
		$xpath = new \DOMXPath( $dom );
		$audios = $xpath->query( './/audio', $root );
		if ( ! $audios || 0 === $audios->length ) {
			return;
		}

		foreach ( $audios as $audio ) {
			if ( ! $audio instanceof \DOMElement ) {
				continue;
			}
			if ( $this->audio_has_text_alternative( $audio ) ) {
				continue;
			}

			$text = $this->text_alternative_for_media( $audio, $xpath );
			if ( null === $text ) {
				continue;
			}

			$audio->setAttribute( 'aria-label', $text );
			$this->bump_stat( 'audio_aria_label' );
		}
	}

	/**
	 * Check if audio has a text alternative.
	 *
	 * @param \DOMElement $audio Audio element.
	 * @return bool
	 */
	protected function audio_has_text_alternative( \DOMElement $audio ) {
		$tracks = $audio->getElementsByTagName( 'track' );
		foreach ( $tracks as $track ) {
			if ( ! $track instanceof \DOMElement ) {
				continue;
			}
			$kind = strtolower( (string) $track->getAttribute( 'kind' ) );
			if ( in_array( $kind, array( 'descriptions', 'captions' ), true ) ) {
				return true;
			}
		}
		$al = trim( (string) $audio->getAttribute( 'aria-label' ) );
		return mb_strlen( $al ) > 16;
	}

	/**
	 * Extract text alternative for media elements from figcaption or preceding heading.
	 *
	 * @param \DOMElement $video Video or audio element.
	 * @param \DOMXPath  $xpath XPath instance.
	 * @return string|null
	 */
	protected function text_alternative_for_media( \DOMElement $el, \DOMXPath $xpath ) {
		// 1. Figcaption from ancestor figure.
		$fig = $xpath->query( 'ancestor::figure[1]//figcaption', $el );
		if ( $fig && $fig->length > 0 ) {
			$fc = $fig->item( 0 );
			if ( $fc instanceof \DOMElement ) {
				$text = trim( $fc->textContent ?? '' );
				if ( mb_strlen( $text ) > 12 ) {
					return sanitize_text_field( $text );
				}
			}
		}
		// 2. Nearest preceding heading.
		$heading = $xpath->query(
			'preceding::h1[1] | preceding::h2[1] | preceding::h3[1] | preceding::h4[1]',
			$el
		);
		if ( $heading && $heading->length > 0 ) {
			$h = $heading->item( 0 );
			if ( $h instanceof \DOMElement ) {
				$text = trim( $h->textContent ?? '' );
				if ( mb_strlen( $text ) > 0 ) {
					return sanitize_text_field( $text );
				}
			}
		}
return null;
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
	 * Whether a form control already has an associated or computed accessible label.
	 *
	 * @param \DOMElement   $el  Input, select, or textarea.
	 * @param \DOMDocument $dom Document.
	 * @return bool
	 */
	protected function form_control_has_explicit_label( \DOMElement $el, \DOMDocument $dom ) {
		$id = trim( (string) $el->getAttribute( 'id' ) );
		if ( $id !== '' ) {
			$xpath = new \DOMXPath( $dom );
			$labels = $xpath->query( '//label[@for="' . self::xpath_escape( $id ) . '"]' );
			if ( $labels && $labels->length > 0 ) {
				return true;
			}
		}
		if ( trim( (string) $el->getAttribute( 'aria-label' ) ) !== '' ) {
			return true;
		}
		if ( trim( (string) $el->getAttribute( 'aria-labelledby' ) ) !== '' ) {
			return true;
		}
		$xpath = new \DOMXPath( $dom );
		$wrap = $xpath->query( 'ancestor::label', $el );
		return $wrap && $wrap->length > 0;
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
