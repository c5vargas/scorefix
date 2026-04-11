<?php
/**
 * Banner while rendered-URL queue runs (sync scan done; async pass still running).
 *
 * @package ScoreFix
 *
 * @var array{running: bool, done: int, total: int, pct: int} $render_scan_state
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $render_scan_state['running'] ) ) {
	return;
}

$done  = isset( $render_scan_state['done'] ) ? (int) $render_scan_state['done'] : 0;
$total = isset( $render_scan_state['total'] ) ? (int) $render_scan_state['total'] : 0;
$pct   = isset( $render_scan_state['pct'] ) ? (int) $render_scan_state['pct'] : 0;

?>
<div
	class="scorefix-card scorefix-card--render-progress"
	role="status"
	aria-live="polite"
	data-scorefix-render-card="1"
>
	<div class="scorefix-render-progress__row">
		<span class="scorefix-render-progress__icon dashicons dashicons-update" aria-hidden="true"></span>
		<div class="scorefix-render-progress__text">
			<p class="scorefix-render-progress__title"><?php esc_html_e( 'Background scan in progress', 'scorefix' ); ?></p>
			<p class="scorefix-render-progress__desc">
				<?php esc_html_e( 'The first pass (published content and media) is complete. Rendered pages are still being analyzed — the score and issue list below are not final yet.', 'scorefix' ); ?>
			</p>
		</div>
	</div>
	<div class="scorefix-render-progress__meter" aria-label="<?php esc_attr_e( 'Rendered URL scan progress', 'scorefix' ); ?>">
		<div class="scorefix-render-progress__meter-track">
			<div
				class="scorefix-render-progress__meter-fill"
				data-scorefix-render-bar
				style="width: <?php echo esc_attr( (string) max( 0, min( 100, $pct ) ) ); ?>%;"
			></div>
		</div>
		<p class="scorefix-render-progress__counts">
			<span data-scorefix-render-count>
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: completed steps, 2: total steps */
						__( '%1$d of %2$d rendered URLs processed', 'scorefix' ),
						$done,
						max( 1, $total )
					)
				);
				?>
			</span>
			<span class="scorefix-render-progress__pct" data-scorefix-render-pct><?php echo esc_html( (string) $pct ); ?>%</span>
		</p>
	</div>
</div>
