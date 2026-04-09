<?php
/**
 * Issues table card.
 *
 * @package ScoreFix
 *
 * @var array<int, mixed> $issues
 */

defined( 'ABSPATH' ) || exit;

use ScoreFix\Admin\DashboardPage;

?>
<div class="scorefix-card scorefix-card--issues">
	<h2><?php esc_html_e( 'Issues found', 'scorefix' ); ?></h2>
	<?php if ( empty( $issues ) ) : ?>
		<p class="scorefix-muted"><?php esc_html_e( 'No scan results yet, or no issues detected. Run a scan to populate this list.', 'scorefix' ); ?></p>
	<?php else : ?>
		<table class="widefat striped scorefix-table">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Issue', 'scorefix' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Why it matters', 'scorefix' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Context', 'scorefix' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				$scorefix_issues_shown = 0;
				foreach ( $issues as $scorefix_issue ) {
					if ( ! is_array( $scorefix_issue ) ) {
						continue;
					}
					if ( $scorefix_issues_shown >= 50 ) {
						break;
					}
					++$scorefix_issues_shown;
					list( $scorefix_issue_title, $scorefix_issue_desc ) = DashboardPage::describe_issue( $scorefix_issue );
					$scorefix_ctx_parts = array();
					if ( ! empty( $scorefix_issue['post_id'] ) ) {
						$scorefix_ctx_parts[] = sprintf(
							/* translators: %d: post or attachment ID */
							__( 'ID %d', 'scorefix' ),
							(int) $scorefix_issue['post_id']
						);
					}
					if ( ! empty( $scorefix_issue['context'] ) ) {
						$scorefix_ctx_parts[] = sanitize_text_field( (string) $scorefix_issue['context'] );
					}
					$scorefix_ctx = implode( ' · ', $scorefix_ctx_parts );
					?>
					<tr>
						<td><?php echo esc_html( $scorefix_issue_title ); ?></td>
						<td><?php echo esc_html( $scorefix_issue_desc ); ?></td>
						<td><?php echo esc_html( $scorefix_ctx ); ?></td>
					</tr>
				<?php } ?>
			</tbody>
		</table>
		<?php if ( count( $issues ) > 50 ) : ?>
			<p class="scorefix-muted"><?php esc_html_e( 'Showing the first 50 issues. Future versions will add filtering and exports.', 'scorefix' ); ?></p>
		<?php endif; ?>
	<?php endif; ?>
</div>
