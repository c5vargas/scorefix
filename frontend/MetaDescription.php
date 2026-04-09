<?php
/**
 * Optional meta description tag when none is set (SEO plugins take precedence).
 *
 * @package ScoreFix
 */

namespace ScoreFix\Frontend;

use ScoreFix\Core\Loader;

defined( 'ABSPATH' ) || exit;

/**
 * Class MetaDescription
 */
class MetaDescription {

	/**
	 * Register hooks.
	 *
	 * @param Loader $loader Loader.
	 * @return void
	 */
	public function register( Loader $loader ) {
		$loader->add_action( 'wp_head', $this, 'maybe_output', 4 );
	}

	/**
	 * Print meta description if enabled and a snippet is available.
	 *
	 * @return void
	 */
	public function maybe_output() {
		$is_json = function_exists( 'wp_is_json_request' ) && wp_is_json_request();
		if ( is_admin() || $is_json || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}
		if ( ! self::is_enabled() ) {
			return;
		}
		if ( apply_filters( 'scorefix_disable_meta_description', false ) ) {
			return;
		}

		$desc = $this->build_description();
		$desc = apply_filters( 'scorefix_meta_description_content', $desc );
		if ( ! is_string( $desc ) || '' === trim( $desc ) ) {
			return;
		}

		echo '<meta name="description" content="' . esc_attr( $desc ) . '" />' . "\n";
	}

	/**
	 * @return bool
	 */
	public static function is_enabled() {
		$settings = get_option( 'scorefix_settings', array() );
		if ( ! is_array( $settings ) ) {
			return true;
		}
		if ( ! array_key_exists( 'meta_description_enabled', $settings ) ) {
			return true;
		}
		return (bool) $settings['meta_description_enabled'];
	}

	/**
	 * @return string
	 */
	protected function build_description() {
		if ( is_front_page() && ! is_paged() ) {
			if ( is_page() ) {
				return $this->description_for_post( (int) get_queried_object_id() );
			}
			$tagline = get_bloginfo( 'description', false );
			return $this->sanitize_snippet( is_string( $tagline ) ? $tagline : '' );
		}

		if ( is_singular() ) {
			return $this->description_for_post( (int) get_queried_object_id() );
		}

		return '';
	}

	/**
	 * @param int $post_id Post ID.
	 * @return string
	 */
	protected function description_for_post( $post_id ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return '';
		}
		if ( $this->post_has_seo_meta_description( $post_id ) ) {
			return '';
		}

		$custom = apply_filters( 'scorefix_meta_description_for_post', null, $post_id );
		if ( is_string( $custom ) && trim( $custom ) !== '' ) {
			return $this->sanitize_snippet( $custom );
		}

		$excerpt = get_the_excerpt( $post_id );
		if ( is_string( $excerpt ) && trim( wp_strip_all_tags( $excerpt ) ) !== '' ) {
			return $this->sanitize_snippet( $excerpt );
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) {
			return '';
		}
		$text = wp_strip_all_tags( $post->post_content );

		return $this->sanitize_snippet( $text );
	}

	/**
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	protected function post_has_seo_meta_description( $post_id ) {
		$keys = apply_filters(
			'scorefix_seo_metadesc_meta_keys',
			array(
				'_yoast_wpseo_metadesc',
				'rank_math_description',
			)
		);
		if ( ! is_array( $keys ) ) {
			$keys = array();
		}
		foreach ( $keys as $key ) {
			$key = sanitize_key( (string) $key );
			if ( '' === $key ) {
				continue;
			}
			$v = get_post_meta( $post_id, $key, true );
			if ( is_string( $v ) && trim( $v ) !== '' ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param string $text Raw text.
	 * @return string
	 */
	protected function sanitize_snippet( $text ) {
		$text = wp_strip_all_tags( (string) $text );
		$text = preg_replace( '/\s+/u', ' ', $text );
		$text = trim( (string) $text );
		if ( '' === $text ) {
			return '';
		}
		$max = (int) apply_filters( 'scorefix_meta_description_max_length', 300 );
		if ( $max < 50 ) {
			$max = 300;
		}
		if ( function_exists( 'mb_strlen' ) && function_exists( 'mb_substr' ) ) {
			if ( mb_strlen( $text ) > $max ) {
				$text = mb_substr( $text, 0, $max - 1 ) . '…';
			}
		} elseif ( strlen( $text ) > $max ) {
			$text = substr( $text, 0, $max - 1 ) . '…';
		}

		return $text;
	}
}
