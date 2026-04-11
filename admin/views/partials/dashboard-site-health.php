<?php
/**
 * Site health card (score, donut, metrics).
 *
 * @package ScoreFix
 *
 * @var int|null              $score
 * @var array<string, string> $perf_copy
 * @var array<string, mixed>  $metrics
 * @var bool                  $show_metric_trend_hint Required by metric-cards partial.
 */

defined( 'ABSPATH' ) || exit;

use ScoreFix\Admin\DashboardPage;

?>
<div class="scorefix-card scorefix-card--performance">
	<p class="scorefix-performance__kicker"><?php esc_html_e( 'Site health', 'scorefix' ); ?></p>
	<div class="scorefix-performance__top">
		<div class="scorefix-performance__intro">
			<p class="scorefix-performance__headline" role="heading" aria-level="2"><?php echo esc_html( $perf_copy['headline'] ); ?></p>
			<p class="scorefix-performance__sub"><?php echo esc_html( $perf_copy['sub'] ); ?></p>
			<p class="scorefix-performance__note scorefix-muted">
				<?php esc_html_e( 'The overall score blends accessibility, SEO, and local performance signals from your content. Rendered-URL issues and heavy lab metrics are out of scope here—use PSI or your host for deep speed work.', 'scorefix' ); ?>
			</p>
		</div>
		<div class="scorefix-donut-wrap">
			<?php
			$scorefix_donut_pct   = null !== $score ? max( 0, min( 100, (int) $score ) ) : 0;
			$scorefix_donut_tone  = DashboardPage::donut_score_tone_slug( $score );
			$scorefix_donut_class = 'scorefix-donut';
			if ( null === $score ) {
				$scorefix_donut_class .= ' scorefix-donut--empty';
			} elseif ( null !== $scorefix_donut_tone ) {
				$scorefix_donut_class .= ' scorefix-donut--tone-' . $scorefix_donut_tone;
			}
			?>
			<div
				class="<?php echo esc_attr( $scorefix_donut_class ); ?>"
				style="<?php echo null !== $score ? '--scorefix-donut-pct: ' . (int) $scorefix_donut_pct . ';' : ''; ?>"
				role="img"
				aria-label="<?php echo esc_attr( null !== $score ? sprintf( /* translators: %d: score */ __( 'Overall score %d out of 100', 'scorefix' ), (int) $score ) : __( 'No score yet', 'scorefix' ) ); ?>"
			>
				<div class="scorefix-donut__hole"></div>
				<div class="scorefix-donut__content">
					<?php if ( null === $score ) : ?>
						<span class="scorefix-donut__value scorefix-donut__value--na">&mdash;</span>
					<?php else : ?>
						<span class="scorefix-donut__value" aria-hidden="true"><?php echo esc_html( (string) $score ); ?></span>
					<?php endif; ?>
					<span class="scorefix-donut__label"><?php esc_html_e( 'Overall score', 'scorefix' ); ?></span>
				</div>
			</div>
		</div>
	</div>

	<?php
	$scorefix_metric_layout = 'performance-row';
	include SCOREFIX_PLUGIN_DIR . 'admin/views/partials/metric-cards.php';
	?>
</div>
