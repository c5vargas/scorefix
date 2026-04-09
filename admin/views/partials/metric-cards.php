<?php
/**
 * Dashboard metric cards (errors, warnings, resolved vs prior scan).
 *
 * @package ScoreFix
 *
 * @var array<string, array<string, mixed>> $metrics
 * @var bool                                 $show_metric_trend_hint
 * @var string                               $scorefix_metric_layout Optional. 'stack' or 'performance-row'.
 */

defined( 'ABSPATH' ) || exit;

use ScoreFix\Admin\DashboardMetrics;

$scorefix_metric_layout_local = isset( $scorefix_metric_layout ) ? (string) $scorefix_metric_layout : 'stack';

$scorefix_metric_cards = array(
	'active_errors' => array(
		'theme' => 'error',
		'icon'  => 'dashicons-dismiss',
		'label' => __( 'Active errors', 'scorefix' ),
		'sub'   => __( 'Issues found', 'scorefix' ),
	),
	'warnings'      => array(
		'theme' => 'warning',
		'icon'  => 'dashicons-warning',
		'label' => __( 'Warnings', 'scorefix' ),
		'sub'   => __( 'Review recommended', 'scorefix' ),
	),
	'resolved'      => array(
		'theme' => 'success',
		'icon'  => 'dashicons-yes-alt',
		'label' => __( 'Resolved', 'scorefix' ),
		'sub'   => __( 'Since last scan', 'scorefix' ),
	),
);

if ( 'performance-row' === $scorefix_metric_layout_local ) :
	?>
	<div class="scorefix-metrics scorefix-metrics--row" role="list">
		<?php foreach ( $scorefix_metric_cards as $scorefix_metric_key => $scorefix_metric_card ) : ?>
			<?php
			$scorefix_metric_m     = isset( $metrics[ $scorefix_metric_key ] ) && is_array( $metrics[ $scorefix_metric_key ] ) ? $metrics[ $scorefix_metric_key ] : array();
			$scorefix_metric_value = array_key_exists( 'value', $scorefix_metric_m ) ? $scorefix_metric_m['value'] : null;
			?>
			<div class="scorefix-metric-chip scorefix-metric-chip--<?php echo esc_attr( $scorefix_metric_card['theme'] ); ?>" role="listitem">
				<div class="scorefix-metric-chip__icon" aria-hidden="true">
					<span class="dashicons <?php echo esc_attr( $scorefix_metric_card['icon'] ); ?>"></span>
				</div>
				<div class="scorefix-metric-chip__body">
					<div class="scorefix-metric-chip__line1">
						<span class="scorefix-metric-chip__num"><?php echo null === $scorefix_metric_value ? esc_html( '—' ) : esc_html( (string) (int) $scorefix_metric_value ); ?></span>
						<span class="scorefix-metric-chip__name"><?php echo esc_html( $scorefix_metric_card['label'] ); ?></span>
					</div>
					<?php if ( ! empty( $scorefix_metric_m['show_trend'] ) ) : ?>
						<div class="<?php echo esc_attr( DashboardMetrics::trend_row_classes( $scorefix_metric_m ) ); ?> scorefix-metric-chip__trend">
							<?php if ( ! empty( $scorefix_metric_m['trend_special'] ) && 'new' === $scorefix_metric_m['trend_special'] ) : ?>
								<span class="scorefix-metric__trend-icon dashicons dashicons-chart-bar" aria-hidden="true"></span>
								<span class="scorefix-metric__trend-text"><?php esc_html_e( 'New since last scan', 'scorefix' ); ?></span>
							<?php else : ?>
								<span class="scorefix-metric__trend-icon dashicons <?php echo esc_attr( DashboardMetrics::trend_icon_class( $scorefix_metric_m ) ); ?>" aria-hidden="true"></span>
								<span class="scorefix-metric__trend-text">
									<?php
									echo esc_html( DashboardMetrics::format_signed_pct( (float) $scorefix_metric_m['trend_pct'] ) );
									echo ' ';
									esc_html_e( 'since last scan', 'scorefix' );
									?>
								</span>
							<?php endif; ?>
						</div>
					<?php else : ?>
						<p class="scorefix-metric-chip__line2 scorefix-muted"><?php echo esc_html( $scorefix_metric_card['sub'] ); ?></p>
					<?php endif; ?>
				</div>
			</div>
		<?php endforeach; ?>
	</div>
	<?php if ( ! empty( $show_metric_trend_hint ) ) : ?>
		<p class="scorefix-metrics-hint scorefix-muted"><?php esc_html_e( 'Run another scan to see percentage trends compared to this baseline.', 'scorefix' ); ?></p>
	<?php endif; ?>
	<?php
	return;
endif;
?>
<div class="scorefix-metrics" role="list">
	<?php foreach ( $scorefix_metric_cards as $scorefix_metric_key => $scorefix_metric_card ) : ?>
		<?php
		$scorefix_metric_m     = isset( $metrics[ $scorefix_metric_key ] ) && is_array( $metrics[ $scorefix_metric_key ] ) ? $metrics[ $scorefix_metric_key ] : array();
		$scorefix_metric_value = array_key_exists( 'value', $scorefix_metric_m ) ? $scorefix_metric_m['value'] : null;
		?>
		<div class="scorefix-metric scorefix-metric--<?php echo esc_attr( $scorefix_metric_card['theme'] ); ?>" role="listitem">
			<div class="scorefix-metric__body">
				<span class="scorefix-metric__label"><?php echo esc_html( $scorefix_metric_card['label'] ); ?></span>
				<span class="scorefix-metric__value" aria-live="polite">
					<?php echo null === $scorefix_metric_value ? esc_html( '—' ) : esc_html( (string) (int) $scorefix_metric_value ); ?>
				</span>
				<?php if ( ! empty( $scorefix_metric_m['show_trend'] ) ) : ?>
					<div class="<?php echo esc_attr( DashboardMetrics::trend_row_classes( $scorefix_metric_m ) ); ?>">
						<?php if ( ! empty( $scorefix_metric_m['trend_special'] ) && 'new' === $scorefix_metric_m['trend_special'] ) : ?>
							<span class="scorefix-metric__trend-icon dashicons dashicons-chart-bar" aria-hidden="true"></span>
							<span class="scorefix-metric__trend-text">
								<?php esc_html_e( 'New since last scan', 'scorefix' ); ?>
							</span>
						<?php else : ?>
							<span class="scorefix-metric__trend-icon dashicons <?php echo esc_attr( DashboardMetrics::trend_icon_class( $scorefix_metric_m ) ); ?>" aria-hidden="true"></span>
							<span class="scorefix-metric__trend-text">
								<?php
								echo esc_html( DashboardMetrics::format_signed_pct( (float) $scorefix_metric_m['trend_pct'] ) );
								echo ' ';
								esc_html_e( 'since last scan', 'scorefix' );
								?>
							</span>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</div>
			<div class="scorefix-metric__icon-wrap" aria-hidden="true">
				<span class="scorefix-metric__icon dashicons <?php echo esc_attr( $scorefix_metric_card['icon'] ); ?>"></span>
			</div>
		</div>
	<?php endforeach; ?>
</div>
<?php if ( ! empty( $show_metric_trend_hint ) ) : ?>
	<p class="scorefix-metrics-hint scorefix-muted">
		<?php esc_html_e( 'Run another scan to see percentage trends compared to this baseline.', 'scorefix' ); ?>
	</p>
<?php endif; ?>
