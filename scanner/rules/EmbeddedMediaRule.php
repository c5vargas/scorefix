<?php
/**
 * video, audio, iframe accessibility heuristics.
 *
 * @package ScoreFix
 */

namespace ScoreFix\Scanner\Rules;

defined( 'ABSPATH' ) || exit;

/**
 * Class EmbeddedMediaRule
 */
class EmbeddedMediaRule {

	const ROOT = "//*[@id='scorefix-root']";

	/**
	 * @param \DOMXPath $xpath   Document.
	 * @param int       $post_id Post ID.
	 * @param callable  $issue   Issue factory.
	 * @return array<int, array<string, mixed>>
	 */
	public static function collect( \DOMXPath $xpath, $post_id, callable $issue ) {
		$out = array_merge(
			self::collect_videos( $xpath, (int) $post_id, $issue ),
			self::collect_audios( $xpath, (int) $post_id, $issue ),
			self::collect_iframes( $xpath, (int) $post_id, $issue )
		);
		return $out;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	protected static function collect_videos( \DOMXPath $xpath, $post_id, callable $issue ) {
		$out   = array();
		$max   = (int) apply_filters( 'scorefix_scan_max_video_issues', 15, $post_id );
		$max   = max( 1, min( 40, $max ) );
		$nodes = $xpath->query( self::ROOT . '//video' );
		if ( ! $nodes ) {
			return $out;
		}
		foreach ( $nodes as $video ) {
			if ( ! $video instanceof \DOMElement ) {
				continue;
			}
			if ( self::video_has_text_alternative( $xpath, $video ) ) {
				continue;
			}
			if ( count( $out ) >= $max ) {
				break;
			}
			$out[] = $issue(
				'video_no_text_track',
				'medium',
				array(
					'post_id'     => (int) $post_id,
					'context'     => 'content',
					'impact'      => 'readability',
					'element_key' => (string) spl_object_id( $video ),
				)
			);
		}
		return $out;
	}

	/**
	 * @param \DOMXPath   $xpath XPath.
	 * @param \DOMElement $video Video element.
	 * @return bool
	 */
	protected static function video_has_text_alternative( \DOMXPath $xpath, \DOMElement $video ) {
		$tracks = $video->getElementsByTagName( 'track' );
		foreach ( $tracks as $t ) {
			if ( ! $t instanceof \DOMElement ) {
				continue;
			}
			$kind = strtolower( (string) $t->getAttribute( 'kind' ) );
			if ( in_array( $kind, array( 'captions', 'subtitles', 'descriptions' ), true ) ) {
				return true;
			}
		}
		$al = trim( (string) $video->getAttribute( 'aria-label' ) );
		if ( strlen( $al ) > 16 ) {
			return true;
		}
		$fig = $xpath->query( 'ancestor::figure[1]//figcaption', $video );
		if ( $fig && $fig->length > 0 ) {
			$fc = $fig->item( 0 );
			if ( $fc instanceof \DOMElement && strlen( trim( $fc->textContent ?? '' ) ) > 12 ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	protected static function collect_audios( \DOMXPath $xpath, $post_id, callable $issue ) {
		$out   = array();
		$max   = (int) apply_filters( 'scorefix_scan_max_audio_issues', 10, $post_id );
		$max   = max( 1, min( 30, $max ) );
		$nodes = $xpath->query( self::ROOT . '//audio' );
		if ( ! $nodes ) {
			return $out;
		}
		foreach ( $nodes as $audio ) {
			if ( ! $audio instanceof \DOMElement ) {
				continue;
			}
			if ( self::audio_has_text_alternative( $audio ) ) {
				continue;
			}
			if ( count( $out ) >= $max ) {
				break;
			}
			$out[] = $issue(
				'audio_no_text_track',
				'low',
				array(
					'post_id'     => (int) $post_id,
					'context'     => 'content',
					'impact'      => 'readability',
					'element_key' => (string) spl_object_id( $audio ),
				)
			);
		}
		return $out;
	}

	/**
	 * @param \DOMElement $audio Audio element.
	 * @return bool
	 */
	protected static function audio_has_text_alternative( \DOMElement $audio ) {
		$tracks = $audio->getElementsByTagName( 'track' );
		foreach ( $tracks as $t ) {
			if ( ! $t instanceof \DOMElement ) {
				continue;
			}
			$kind = strtolower( (string) $t->getAttribute( 'kind' ) );
			if ( in_array( $kind, array( 'descriptions', 'captions' ), true ) ) {
				return true;
			}
		}
		$al = trim( (string) $audio->getAttribute( 'aria-label' ) );
		return strlen( $al ) > 16;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	protected static function collect_iframes( \DOMXPath $xpath, $post_id, callable $issue ) {
		$out   = array();
		$max   = (int) apply_filters( 'scorefix_scan_max_iframe_issues', 20, $post_id );
		$max   = max( 1, min( 50, $max ) );
		$nodes = $xpath->query( self::ROOT . '//iframe' );
		if ( ! $nodes ) {
			return $out;
		}
		foreach ( $nodes as $frame ) {
			if ( ! $frame instanceof \DOMElement ) {
				continue;
			}
			$title = trim( (string) $frame->getAttribute( 'title' ) );
			if ( '' !== $title ) {
				continue;
			}
			$aria = trim( (string) $frame->getAttribute( 'aria-label' ) );
			if ( '' !== $aria ) {
				continue;
			}
			if ( count( $out ) >= $max ) {
				break;
			}
			$src = substr( (string) $frame->getAttribute( 'src' ), 0, 120 );
			$out[] = $issue(
				'iframe_missing_title',
				'medium',
				array(
					'post_id'     => (int) $post_id,
					'context'     => 'content',
					'impact'      => 'readability',
					'src'         => $src,
					'element_key' => (string) spl_object_id( $frame ),
				)
			);
		}
		return $out;
	}
}
