<?php
/**
 * Block editor sidebar + media modal hints (Prioridad 3 roadmap).
 *
 * @package ScoreFix
 */

namespace ScoreFix\Admin;

use ScoreFix\Core\Loader;
use ScoreFix\Core\Plugin;
use ScoreFix\Scanner\IssueGlossary;
use ScoreFix\Scanner\Scanner;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Class EditorIntegration
 */
class EditorIntegration {

	/**
	 * Register hooks.
	 *
	 * @param Loader $loader Loader.
	 * @return void
	 */
	public function register( Loader $loader ) {
		$loader->add_action( 'rest_api_init', $this, 'register_rest_routes', 10, 0 );
		$loader->add_action( 'enqueue_block_editor_assets', $this, 'enqueue_block_editor_assets', 10, 0 );
		$loader->add_action( 'admin_enqueue_scripts', $this, 'enqueue_media_alt_notice_assets', 20, 1 );
		$loader->add_filter( 'attachment_fields_to_edit', $this, 'attachment_fields_alt_notice', 15, 2 );
		$loader->add_action( 'save_post', $this, 'maybe_refresh_last_scan_for_post', 30, 1 );
	}

	/**
	 * Re-merge stored-content issues for this post into the last scan snapshot (editor panel stays current).
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function maybe_refresh_last_scan_for_post( $post_id ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		( new Scanner() )->refresh_post_content_in_last_snapshot( $post_id );
	}

	/**
	 * REST: issues for current post/attachment from last scan.
	 *
	 * @return void
	 */
	public function register_rest_routes() {
		register_rest_route(
			'scorefix/v1',
			'/post/(?P<id>\\d+)/issues',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_post_issues' ),
				'permission_callback' => array( $this, 'rest_can_edit_post' ),
				'args'                => array(
					'id' => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param ) && (int) $param > 0;
						},
					),
				),
			)
		);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return bool|\WP_Error
	 */
	public function rest_can_edit_post( WP_REST_Request $request ) {
		$id = (int) $request['id'];
		if ( $id <= 0 ) {
			return false;
		}
		return current_user_can( 'edit_post', $id );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function rest_post_issues( WP_REST_Request $request ) {
		$post_id = (int) $request['id'];
		$scan    = Scanner::get_last_scan();
		$issues  = Scanner::get_issues_for_post_id( $post_id );
		$was_in  = Scanner::was_in_last_scan_scope( $post_id );
		if ( ! $was_in && ! empty( $issues ) ) {
			$was_in = true;
		}
		$rows    = array();
		foreach ( $issues as $issue ) {
			if ( ! is_array( $issue ) ) {
				continue;
			}
			$type = isset( $issue['type'] ) ? sanitize_key( (string) $issue['type'] ) : '';
			$g    = IssueGlossary::get_entry( $type );
			$rows[] = array_merge(
				$issue,
				array(
					'title'          => $g['title'],
					'severity_label' => DashboardPage::issue_severity_label( $issue ),
					'context_label'  => DashboardPage::issue_context_label( $issue ),
				)
			);
		}

		return new WP_REST_Response(
			array(
				'scanned_at'      => is_array( $scan ) && isset( $scan['scanned_at'] ) ? (string) $scan['scanned_at'] : '',
				'was_in_scan'     => $was_in,
				'site_score'      => is_array( $scan ) && isset( $scan['score'] ) ? (int) $scan['score'] : null,
				'fixes_enabled'   => Plugin::fixes_enabled(),
				'issues'          => $rows,
				'dashboard_url'   => admin_url( 'admin.php?page=scorefix' ),
			),
			200
		);
	}

	/**
	 * Gutenberg: document sidebar panel.
	 *
	 * @return void
	 */
	public function enqueue_block_editor_assets() {
		wp_enqueue_script(
			'scorefix-block-editor',
			SCOREFIX_PLUGIN_URL . 'assets/js/block-editor.js',
			array(
				'wp-plugins',
				'wp-edit-post',
				'wp-element',
				'wp-components',
				'wp-data',
				'wp-i18n',
				'wp-api-fetch',
			),
			SCOREFIX_VERSION,
			true
		);

		wp_enqueue_style(
			'scorefix-block-editor',
			SCOREFIX_PLUGIN_URL . 'assets/css/block-editor.css',
			array(),
			SCOREFIX_VERSION
		);

		wp_localize_script(
			'scorefix-block-editor',
			'scorefixEditor',
			array(
				'dashboardUrl'  => admin_url( 'admin.php?page=scorefix' ),
				'i18n'          => array(
					'panelTitle'       => __( 'ScoreFix', 'scorefix' ),
					'panelDescription' => __( 'Issues from the last site scan for this item.', 'scorefix' ),
					'loading'          => __( 'Loading…', 'scorefix' ),
					'errorLoad'        => __( 'Could not load ScoreFix data.', 'scorefix' ),
					'noScan'           => __( 'No scan data yet. Run a scan from the ScoreFix settings page.', 'scorefix' ),
					'notInSample'      => __( 'This item was not included in the last scan (only published content in the scan batch, or the media library pass). Run a new scan after publishing.', 'scorefix' ),
					'emptyIssues'      => __( 'No issues for this item in the last scan.', 'scorefix' ),
					'openDashboard'    => __( 'Open ScoreFix', 'scorefix' ),
					'severity'         => __( 'Severity', 'scorefix' ),
					'source'           => __( 'Source', 'scorefix' ),
				),
			)
		);

		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( 'scorefix-block-editor', 'scorefix' );
		}
	}

	/**
	 * Styles for attachment alt notice (modal + list).
	 *
	 * @param string $hook_suffix Hook.
	 * @return void
	 */
	public function enqueue_media_alt_notice_assets( $hook_suffix ) {
		$screens = array( 'post.php', 'post-new.php', 'upload.php', 'media.php' );
		if ( ! in_array( $hook_suffix, $screens, true ) ) {
			return;
		}

		wp_register_style(
			'scorefix-editor-aux',
			SCOREFIX_PLUGIN_URL . 'assets/css/block-editor.css',
			array(),
			SCOREFIX_VERSION
		);
		wp_enqueue_style( 'scorefix-editor-aux' );
	}

	/**
	 * Media modal: notice when image has no ALT meta (same condition as Scanner::scan_attachment_images).
	 *
	 * @param array<string, array<string, mixed>> $fields Fields.
	 * @param \WP_Post                              $post   Attachment.
	 * @return array<string, array<string, mixed>>
	 */
	public function attachment_fields_alt_notice( $fields, $post ) {
		if ( ! $post instanceof \WP_Post || 'attachment' !== $post->post_type ) {
			return $fields;
		}
		$mime = (string) $post->post_mime_type;
		if ( '' === $mime || strpos( $mime, 'image/' ) !== 0 ) {
			return $fields;
		}

		$alt = get_post_meta( $post->ID, '_wp_attachment_image_alt', true );
		$alt = is_string( $alt ) ? trim( $alt ) : '';
		if ( '' !== $alt ) {
			return $fields;
		}

		$settings = get_option( 'scorefix_settings', array() );
		$fixes_on = is_array( $settings ) && ! empty( $settings['fixes_enabled'] );

		if ( $fixes_on ) {
			$message = __( 'Alternative text is empty. ScoreFix can supply missing ALT on the front end while automatic fixes are enabled; storing ALT here is still best for SEO and the editor.', 'scorefix' );
		} else {
			$message = __( 'Alternative text is empty. Add a short description for screen reader users and SEO.', 'scorefix' );
		}

		$fields['scorefix_alt_notice'] = array(
			'label' => __( 'ScoreFix', 'scorefix' ),
			'input' => 'html',
			'html'  => '<p class="description scorefix-attachment-alt-notice">' . esc_html( $message ) . '</p>',
		);

		return $fields;
	}
}
