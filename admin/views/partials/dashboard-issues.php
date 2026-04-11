<?php
/**
 * Issues table card.
 *
 * @package ScoreFix
 *
 * @var array<int, mixed>               $issues
 * @var array<string, mixed>|null     $scorefix_issues_view From DashboardPage::build_issues_table_view().
 */

defined( 'ABSPATH' ) || exit;

use ScoreFix\Admin\DashboardPage;
use ScoreFix\Scanner\IssueGlossary;

if ( ! isset( $scorefix_issues_view ) || ! is_array( $scorefix_issues_view ) ) {
	$scorefix_issues_view = DashboardPage::build_issues_table_view( isset( $issues ) && is_array( $issues ) ? $issues : array() );
}

$scorefix_iv          = $scorefix_issues_view;
$scorefix_iv_items    = isset( $scorefix_iv['items'] ) && is_array( $scorefix_iv['items'] ) ? $scorefix_iv['items'] : array();
$scorefix_iv_filter   = isset( $scorefix_iv['filter'] ) ? (string) $scorefix_iv['filter'] : '';
$scorefix_iv_total    = isset( $scorefix_iv['total_all'] ) ? (int) $scorefix_iv['total_all'] : 0;
$scorefix_iv_filtered = isset( $scorefix_iv['total_filtered'] ) ? (int) $scorefix_iv['total_filtered'] : 0;
$scorefix_iv_from     = isset( $scorefix_iv['display_from'] ) ? (int) $scorefix_iv['display_from'] : 0;
$scorefix_iv_to       = isset( $scorefix_iv['display_to'] ) ? (int) $scorefix_iv['display_to'] : 0;
$scorefix_iv_err      = isset( $scorefix_iv['count_error'] ) ? (int) $scorefix_iv['count_error'] : 0;
$scorefix_iv_warn     = isset( $scorefix_iv['count_warning'] ) ? (int) $scorefix_iv['count_warning'] : 0;
$scorefix_iv_pages    = isset( $scorefix_iv['pagination_html'] ) ? (string) $scorefix_iv['pagination_html'] : '';

?>
<div class="scorefix-card scorefix-card--issues">
	<h2><?php esc_html_e( 'Issues found', 'scorefix' ); ?></h2>
	<?php if ( $scorefix_iv_total <= 0 ) : ?>
		<p class="scorefix-muted"><?php esc_html_e( 'No scan results yet, or no issues detected. Run a scan to populate this list.', 'scorefix' ); ?></p>
	<?php else : ?>
		<nav class="scorefix-issues-filters" aria-label="<?php esc_attr_e( 'Filter issues by severity', 'scorefix' ); ?>">
			<a class="scorefix-issues-filter<?php echo '' === $scorefix_iv_filter ? ' is-active' : ''; ?>" href="<?php echo esc_url( DashboardPage::issues_filter_tab_url( '' ) ); ?>">
				<?php
				printf(
					/* translators: %d: issue count */
					esc_html__( 'All (%d)', 'scorefix' ),
					$scorefix_iv_total
				);
				?>
			</a>
			<a class="scorefix-issues-filter scorefix-issues-filter--error<?php echo 'error' === $scorefix_iv_filter ? ' is-active' : ''; ?>" href="<?php echo esc_url( DashboardPage::issues_filter_tab_url( 'error' ) ); ?>">
				<span class="dashicons dashicons-dismiss" aria-hidden="true"></span>
				<?php
				printf(
					/* translators: %d: error count */
					esc_html__( 'Errors (%d)', 'scorefix' ),
					$scorefix_iv_err
				);
				?>
			</a>
			<a class="scorefix-issues-filter scorefix-issues-filter--warning<?php echo 'warning' === $scorefix_iv_filter ? ' is-active' : ''; ?>" href="<?php echo esc_url( DashboardPage::issues_filter_tab_url( 'warning' ) ); ?>">
				<span class="dashicons dashicons-warning" aria-hidden="true"></span>
				<?php
				printf(
					/* translators: %d: warning count */
					esc_html__( 'Warnings (%d)', 'scorefix' ),
					$scorefix_iv_warn
				);
				?>
			</a>
		</nav>

		<?php if ( $scorefix_iv_filtered <= 0 ) : ?>
			<p class="scorefix-muted scorefix-issues-empty-filter">
				<?php esc_html_e( 'No issues match this filter. Try viewing all issues or pick another tab.', 'scorefix' ); ?>
			</p>
		<?php else : ?>
			<p class="scorefix-issues-displaying scorefix-muted">
				<?php
				printf(
					/* translators: 1: first row number, 2: last row number, 3: total in current filter */
					esc_html__( 'Showing %1$d–%2$d of %3$d issues', 'scorefix' ),
					$scorefix_iv_from,
					$scorefix_iv_to,
					$scorefix_iv_filtered
				);
				?>
			</p>

			<div class="scorefix-table-wrap scorefix-table-wrap--issues">
				<table class="widefat scorefix-table scorefix-table--issues">
					<thead>
						<tr>
							<th scope="col" class="column-issue"><?php esc_html_e( 'Issue', 'scorefix' ); ?></th>
							<th scope="col" class="column-why"><?php esc_html_e( 'Why it matters', 'scorefix' ); ?></th>
							<th scope="col" class="column-where"><?php esc_html_e( 'Where', 'scorefix' ); ?></th>
							<th scope="col" class="column-actions"><?php esc_html_e( 'Actions', 'scorefix' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						$scorefix_issue_row_index = 0;
						foreach ( $scorefix_iv_items as $scorefix_issue ) {
							if ( ! is_array( $scorefix_issue ) ) {
								continue;
							}
							list( $scorefix_issue_title, $scorefix_issue_desc ) = DashboardPage::describe_issue( $scorefix_issue );
							$scorefix_issue_type = isset( $scorefix_issue['type'] ) ? (string) $scorefix_issue['type'] : '';
							$scorefix_glossary   = IssueGlossary::get_entry( $scorefix_issue_type );
							$scorefix_tone       = DashboardPage::issue_severity_tone( $scorefix_issue );
							$scorefix_sev_label  = DashboardPage::issue_severity_label( $scorefix_issue );
							$scorefix_post_id    = isset( $scorefix_issue['post_id'] ) ? (int) $scorefix_issue['post_id'] : 0;
							$scorefix_post_title = DashboardPage::issue_where_primary_label( $scorefix_issue );
							$scorefix_show_id      = DashboardPage::issue_where_show_post_id( $scorefix_issue );
							$scorefix_family_disp  = DashboardPage::issue_family_display( $scorefix_issue );
							$scorefix_ctx_label    = DashboardPage::issue_context_label( $scorefix_issue );
							$scorefix_preview   = DashboardPage::issue_preview_fields( $scorefix_issue );
							$scorefix_actions   = DashboardPage::issue_row_actions( $scorefix_issue );
							$scorefix_has_preview = ! empty( $scorefix_preview );
							$scorefix_has_glossary = is_array( $scorefix_glossary ) && ( ! empty( $scorefix_glossary['business'] ) || ! empty( $scorefix_glossary['references'] ) );
							$scorefix_show_detail_toggle = $scorefix_has_preview || $scorefix_has_glossary;
							$scorefix_detail_id = wp_unique_id( 'sf-issue-detail-' );
							$scorefix_use_alt   = ( 0 !== ( $scorefix_issue_row_index % 2 ) );
							$scorefix_row_alt   = $scorefix_use_alt ? ' scorefix-issue-main--alt' : '';
							$scorefix_detail_alt = $scorefix_use_alt ? ' scorefix-issue-detail-row--alt' : '';
							++$scorefix_issue_row_index;
							?>
							<tr class="scorefix-issue-main scorefix-issue-row scorefix-issue-row--<?php echo esc_attr( $scorefix_tone ); ?><?php echo esc_attr( $scorefix_row_alt ); ?>">
								<td class="column-issue">
									<div class="scorefix-issue-heading">
										<strong class="scorefix-issue-title"><?php echo esc_html( $scorefix_issue_title ); ?></strong>
										<div class="scorefix-issue-badges" role="group" aria-label="<?php esc_attr_e( 'Severity and category', 'scorefix' ); ?>">
											<span class="scorefix-issue-badge scorefix-issue-badge--<?php echo esc_attr( $scorefix_tone ); ?>">
												<?php if ( 'error' === $scorefix_tone ) : ?>
													<span class="dashicons dashicons-dismiss" aria-hidden="true"></span>
												<?php else : ?>
													<span class="dashicons dashicons-warning" aria-hidden="true"></span>
												<?php endif; ?>
												<span class="scorefix-issue-badge__text"><?php echo esc_html( $scorefix_sev_label ); ?></span>
											</span>
											<span class="scorefix-issue-badge scorefix-issue-badge--family scorefix-issue-badge--family-<?php echo esc_attr( sanitize_key( $scorefix_family_disp['slug'] ) ); ?>">
												<span class="scorefix-issue-badge__text"><?php echo esc_html( $scorefix_family_disp['label'] ); ?></span>
											</span>
										</div>
									</div>
								</td>
								<td class="column-why"><?php echo esc_html( $scorefix_issue_desc ); ?></td>
								<td class="column-where">
									<span class="scorefix-issue-loc-title"><?php echo esc_html( $scorefix_post_title ); ?></span>
									<?php if ( $scorefix_show_id ) : ?>
										<br /><span class="scorefix-muted scorefix-issue-loc-id">
										<?php
										printf(
											/* translators: %d: post ID */
											esc_html__( 'ID %d', 'scorefix' ),
											$scorefix_post_id
										);
										?>
										</span>
									<?php endif; ?>
									<br /><span class="scorefix-muted scorefix-issue-loc-ctx"><?php echo esc_html( $scorefix_ctx_label ); ?></span>
								</td>
								<td class="column-actions">
									<?php
									$scorefix_has_actions = ! empty( $scorefix_actions );
									if ( ! $scorefix_has_actions && ! $scorefix_show_detail_toggle ) :
										?>
										<span class="scorefix-muted">—</span>
									<?php else : ?>
										<ul class="scorefix-issue-actions">
											<?php if ( $scorefix_has_actions ) : ?>
												<?php foreach ( $scorefix_actions as $scorefix_action ) : ?>
													<?php
													if ( ! is_array( $scorefix_action ) || empty( $scorefix_action['url'] ) || empty( $scorefix_action['label'] ) ) {
														continue;
													}
													$scorefix_aurl    = (string) $scorefix_action['url'];
													$scorefix_alabel  = (string) $scorefix_action['label'];
													$scorefix_atitle  = isset( $scorefix_action['title'] ) && '' !== (string) $scorefix_action['title']
														? (string) $scorefix_action['title']
														: $scorefix_alabel;
													$scorefix_aicon   = isset( $scorefix_action['icon'] ) ? (string) $scorefix_action['icon'] : '';
													$scorefix_icon_ok = ( '' !== $scorefix_aicon && preg_match( '/^dashicons-[a-z0-9-]+$/', $scorefix_aicon ) );
													$scorefix_attrs   = isset( $scorefix_action['attrs'] ) && is_array( $scorefix_action['attrs'] ) ? $scorefix_action['attrs'] : array();
													$scorefix_attr_html = '';
													foreach ( $scorefix_attrs as $scorefix_an => $scorefix_av ) {
														$scorefix_attr_html .= sprintf( ' %s="%s"', esc_attr( (string) $scorefix_an ), esc_attr( (string) $scorefix_av ) );
													}
													$scorefix_action_class = 'scorefix-issue-action' . ( $scorefix_icon_ok ? '' : ' scorefix-issue-action--text' );
													?>
													<li>
														<a class="<?php echo esc_attr( $scorefix_action_class ); ?>" href="<?php echo esc_url( $scorefix_aurl ); ?>" title="<?php echo esc_attr( $scorefix_atitle ); ?>" aria-label="<?php echo esc_attr( $scorefix_atitle ); ?>"<?php echo $scorefix_attr_html; ?>>
															<?php if ( $scorefix_icon_ok ) : ?>
																<span class="dashicons <?php echo esc_attr( $scorefix_aicon ); ?>" aria-hidden="true"></span>
																<span class="screen-reader-text"><?php echo esc_html( $scorefix_alabel ); ?></span>
															<?php else : ?>
																<span class="scorefix-issue-action__text"><?php echo esc_html( $scorefix_alabel ); ?></span>
															<?php endif; ?>
														</a>
													</li>
												<?php endforeach; ?>
											<?php endif; ?>
											<?php if ( $scorefix_show_detail_toggle ) : ?>
												<li class="scorefix-issue-actions__details">
													<input type="checkbox" id="<?php echo esc_attr( $scorefix_detail_id ); ?>" class="scorefix-issue-detail-toggle" />
													<label class="scorefix-issue-action scorefix-issue-action--details" for="<?php echo esc_attr( $scorefix_detail_id ); ?>" title="<?php echo esc_attr( __( 'Show or hide details and orientative references for this issue', 'scorefix' ) ); ?>">
														<span class="scorefix-issue-detail-trigger__show" aria-hidden="true">
															<span class="dashicons dashicons-info"></span>
														</span>
														<span class="scorefix-issue-detail-trigger__hide" aria-hidden="true">
															<span class="dashicons dashicons-arrow-up-alt2"></span>
														</span>
														<span class="screen-reader-text"><?php esc_html_e( 'Toggle details', 'scorefix' ); ?></span>
													</label>
												</li>
											<?php endif; ?>
										</ul>
									<?php endif; ?>
								</td>
							</tr>
							<?php if ( $scorefix_show_detail_toggle ) : ?>
								<tr class="scorefix-issue-detail-row<?php echo esc_attr( $scorefix_detail_alt ); ?>">
									<td class="scorefix-issue-detail-panel" colspan="4">
										<div class="scorefix-issue-detail-panel__inner">
											<?php if ( $scorefix_has_preview ) : ?>
												<p class="scorefix-issue-detail-panel__title"><?php esc_html_e( 'Technical details', 'scorefix' ); ?></p>
												<dl class="scorefix-issue-preview__dl scorefix-issue-preview__dl--panel">
													<?php foreach ( $scorefix_preview as $scorefix_pf ) : ?>
														<dt><?php echo esc_html( $scorefix_pf['label'] ); ?></dt>
														<dd><code class="scorefix-issue-code"><?php echo esc_html( $scorefix_pf['value'] ); ?></code></dd>
													<?php endforeach; ?>
												</dl>
											<?php endif; ?>
											<?php if ( $scorefix_has_glossary ) : ?>
												<div class="scorefix-issue-glossary scorefix-issue-glossary--in-panel<?php echo $scorefix_has_preview ? ' scorefix-issue-glossary--after-tech' : ''; ?>">
													<p class="scorefix-issue-glossary__heading"><?php esc_html_e( 'What this means', 'scorefix' ); ?></p>
													<?php if ( ! empty( $scorefix_glossary['business'] ) ) : ?>
														<p class="scorefix-issue-glossary__business"><?php echo esc_html( (string) $scorefix_glossary['business'] ); ?></p>
													<?php endif; ?>
													<?php if ( ! empty( $scorefix_glossary['references'] ) && is_array( $scorefix_glossary['references'] ) ) : ?>
														<p class="scorefix-issue-glossary__ref-title"><?php esc_html_e( 'Orientative references', 'scorefix' ); ?></p>
														<ul class="scorefix-issue-glossary__refs">
															<?php foreach ( $scorefix_glossary['references'] as $scorefix_ref_line ) : ?>
																<li><?php echo esc_html( (string) $scorefix_ref_line ); ?></li>
															<?php endforeach; ?>
														</ul>
													<?php endif; ?>
													<?php if ( ! empty( $scorefix_glossary['disclaimer'] ) ) : ?>
														<p class="scorefix-issue-glossary__disclaimer scorefix-muted"><?php echo esc_html( (string) $scorefix_glossary['disclaimer'] ); ?></p>
													<?php endif; ?>
												</div>
											<?php endif; ?>
											<p class="scorefix-issue-preview__hint scorefix-muted"><?php esc_html_e( 'Use Edit to open the post or media item and fix the content in the editor.', 'scorefix' ); ?></p>
										</div>
									</td>
								</tr>
							<?php endif; ?>
						<?php } ?>
					</tbody>
				</table>
			</div>

			<?php if ( '' !== $scorefix_iv_pages ) : ?>
				<div class="scorefix-issues-pagination tablenav">
					<div class="tablenav-pages"><?php echo wp_kses_post( $scorefix_iv_pages ); ?></div>
				</div>
			<?php endif; ?>
		<?php endif; ?>
	<?php endif; ?>
</div>
