<?php
/**
 * Score history card: latest vs previous scan, heuristic impact, log (sidebar).
 *
 * @package ScoreFix
 *
 * @var array<int, array{score: int, at: string, trigger: string}> $score_history
 * @var int|null                                                   $score
 * @var array<string, mixed>                                       $comparison
 * @var array<string, mixed>                                       $impact_estimate
 */

defined( 'ABSPATH' ) || exit;

use ScoreFix\Admin\DashboardPage;

$scorefix_score_history = ( isset( $score_history ) && is_array( $score_history ) ) ? $score_history : array();
$scorefix_comparison = ( isset( $comparison ) && is_array( $comparison ) ) ? $comparison : array();
$scorefix_impact_estimate = ( isset( $impact_estimate ) && is_array( $impact_estimate ) ) ? $impact_estimate : array();

$scorefix_has_delta = ! empty( $scorefix_comparison['score_delta_available'] ) && isset( $scorefix_comparison['score_delta'] );
$scorefix_delta_val = $scorefix_has_delta ? (int) $scorefix_comparison['score_delta'] : null;

$scorefix_show_delta_block = null !== $score && $scorefix_has_delta;
$scorefix_show_impact_solo = ! empty( $scorefix_impact_estimate['band'] ) && ! $scorefix_show_delta_block;
$scorefix_show_insight     = $scorefix_show_delta_block || $scorefix_show_impact_solo;

?>
<div class="scorefix-card scorefix-card--automation scorefix-card--timeline-compact">
	<p class="scorefix-timeline-compact__title" role="heading" aria-level="2"><?php esc_html_e( 'Score history', 'scorefix' ); ?></p>
	<p class="scorefix-timeline-compact__lead scorefix-muted">
		<?php esc_html_e( 'Latest comparison, illustrative impact band, and a log of recent snapshots.', 'scorefix' ); ?>
	</p>

	<?php if ( $scorefix_show_insight ) : ?>
		<div class="scorefix-timeline-compact__insight" role="region" aria-label="<?php esc_attr_e( 'Latest score comparison', 'scorefix' ); ?>">
			<?php if ( $scorefix_show_delta_block ) : ?>
				<div class="scorefix-timeline-compact__delta-head">
					<span class="scorefix-timeline-compact__delta-label"><?php esc_html_e( 'Vs last scan', 'scorefix' ); ?></span>
					<span class="scorefix-timeline-compact__delta-value <?php echo $scorefix_delta_val >= 0 ? 'is-up' : 'is-down'; ?>">
						<?php
						echo esc_html(
							sprintf(
								/* translators: %d: signed score delta */
								__( '%+d pts', 'scorefix' ),
								$scorefix_delta_val
							)
						);
						?>
					</span>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $scorefix_impact_estimate['band'] ) ) : ?>
				<div class="scorefix-timeline-compact__impact">
					<p class="scorefix-timeline-compact__impact-band"><?php echo esc_html( (string) $scorefix_impact_estimate['band'] ); ?></p>
					<?php if ( ! empty( $scorefix_impact_estimate['disclaimer'] ) ) : ?>
						<p class="scorefix-timeline-compact__impact-disclaimer scorefix-muted"><?php echo esc_html( (string) $scorefix_impact_estimate['disclaimer'] ); ?></p>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php if ( count( $scorefix_score_history ) < 1 ) : ?>
		<p class="scorefix-timeline-compact__empty scorefix-muted"><?php esc_html_e( 'No log entries yet — run a scan to record snapshots here.', 'scorefix' ); ?></p>
	<?php else : ?>
		<?php if ( $scorefix_show_insight ) : ?>
			<p class="scorefix-timeline-compact__log-label"><?php esc_html_e( 'Log', 'scorefix' ); ?></p>
		<?php endif; ?>
		<ol class="scorefix-timeline-compact__list" aria-label="<?php esc_attr_e( 'Score snapshots', 'scorefix' ); ?>">
			<?php foreach ( $scorefix_score_history as $scorefix_hist_row ) : ?>
				<?php
				$scorefix_hist_score = isset( $scorefix_hist_row['score'] ) ? (int) $scorefix_hist_row['score'] : 0;
				$scorefix_hist_at    = isset( $scorefix_hist_row['at'] ) ? (string) $scorefix_hist_row['at'] : '';
				$scorefix_hist_tr    = isset( $scorefix_hist_row['trigger'] ) ? (string) $scorefix_hist_row['trigger'] : '';
				$scorefix_hist_ts    = $scorefix_hist_at ? strtotime( $scorefix_hist_at ) : false;
				$scorefix_hist_date  = $scorefix_hist_ts
					? wp_date( get_option( 'date_format' ), $scorefix_hist_ts )
					: '';
				$scorefix_hist_time  = $scorefix_hist_ts
					? wp_date( get_option( 'time_format' ), $scorefix_hist_ts )
					: '';
				$scorefix_hist_tone  = DashboardPage::donut_score_tone_slug( $scorefix_hist_score );
				$scorefix_pill_class = 'scorefix-timeline-compact__pill';
				if ( null !== $scorefix_hist_tone ) {
					$scorefix_pill_class .= ' scorefix-timeline-compact__pill--tone-' . $scorefix_hist_tone;
				}
				?>
				<li class="scorefix-timeline-compact__item">
					<span class="<?php echo esc_attr( $scorefix_pill_class ); ?>"><?php echo esc_html( (string) $scorefix_hist_score ); ?></span>
					<div class="scorefix-timeline-compact__body">
						<span class="scorefix-timeline-compact__datetime">
							<?php echo esc_html( trim( $scorefix_hist_date . ( $scorefix_hist_time ? ' · ' . $scorefix_hist_time : '' ) ) ); ?>
						</span>
						<?php if ( '' !== $scorefix_hist_tr ) : ?>
							<span class="scorefix-timeline-compact__trigger scorefix-muted"><?php echo esc_html( DashboardPage::score_history_trigger_label( $scorefix_hist_tr ) ); ?></span>
						<?php endif; ?>
					</div>
				</li>
			<?php endforeach; ?>
		</ol>
	<?php endif; ?>
</div>
