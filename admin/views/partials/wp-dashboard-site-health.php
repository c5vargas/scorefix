<?php
/**
 * Compact Site Health block for WP dashboard (Escritorio).
 *
 * @package ScoreFix
 *
 * @var int|null              $score
 * @var array<string, string> $perf_copy
 * @var string                $widget_sub
 * @var string                $scorefix_url
 */

defined( 'ABSPATH' ) || exit;

use ScoreFix\Admin\DashboardPage;

$cta_label = null === $score
	? __( 'Run first scan', 'scorefix' )
	: __( 'View ScoreFix', 'scorefix' );
?>
<div class="scorefix-wp-dash-health">
	<div class="scorefix-donut-wrap">
		<?php
		$scorefix_donut_pct   = null !== $score ? max( 0, min( 100, (int) $score ) ) : 0;
		$scorefix_donut_tone  = DashboardPage::donut_score_tone_slug( $score );
		$scorefix_donut_class = 'scorefix-donut scorefix-donut--wp-widget';
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
	<p class="scorefix-wp-dash-health__headline" role="heading" aria-level="2"><?php echo esc_html( $perf_copy['headline'] ); ?></p>
	<p class="scorefix-wp-dash-health__sub"><?php echo esc_html( $widget_sub ); ?></p>
	<a class="button scorefix-btn-scan scorefix-wp-dash-health__cta" href="<?php echo esc_url( $scorefix_url ); ?>"><?php echo esc_html( $cta_label ); ?></a>
</div>
