<?php
/**
 * Build URL list for rendered HTML queue (defaults + published content).
 *
 * @package ScoreFix
 */

namespace ScoreFix\Scanner;

defined( 'ABSPATH' ) || exit;

/**
 * Class RenderUrlCollector
 */
class RenderUrlCollector {

	/**
	 * All candidate same-host URLs for background render scan.
	 *
	 * @return array<int, string>
	 */
	public static function collect() {
		$urls = array();

		foreach ( RenderUrlDefaults::get() as $u ) {
			$u = esc_url_raw( (string) $u );
			if ( '' !== $u ) {
				$urls[] = $u;
			}
		}

		$post_types = apply_filters( 'scorefix_render_url_post_types', array( 'post', 'page', 'product' ) );
		$post_types = array_values( array_filter( array_map( 'sanitize_key', (array) $post_types ), 'post_type_exists' ) );
		if ( empty( $post_types ) ) {
			$post_types = array( 'post', 'page' );
		}

		$max_ids = (int) apply_filters( 'scorefix_render_url_collector_max_posts', 500 );
		$max_ids = max( 50, min( 2000, $max_ids ) );

		$q = new \WP_Query(
			array(
				'post_type'              => $post_types,
				'post_status'            => 'publish',
				'posts_per_page'         => $max_ids,
				'orderby'                => 'modified',
				'order'                  => 'DESC',
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		foreach ( $q->posts as $pid ) {
			$pid = (int) $pid;
			if ( $pid <= 0 ) {
				continue;
			}
			$link = get_permalink( $pid );
			if ( is_string( $link ) && '' !== $link ) {
				$urls[] = esc_url_raw( $link );
			}
		}
		wp_reset_postdata();

		$urls = array_values( array_unique( array_filter( $urls ) ) );

		$max_total = (int) apply_filters(
			'scorefix_render_queue_max_urls',
			max( 20, min( 500, (int) RenderCaptureConfig::QUEUE_MAX_URLS ) )
		);

		return array_slice( $urls, 0, $max_total );
	}
}
