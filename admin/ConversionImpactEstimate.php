<?php
/**
 * Heuristic “conversion uplift” band from local score/issue deltas — not analytics.
 *
 * @package ScoreFix
 */

namespace ScoreFix\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Class ConversionImpactEstimate
 */
class ConversionImpactEstimate {

	/**
	 * Human-readable band + disclaimer for the dashboard (no external APIs).
	 *
	 * @param array<string, mixed>|null $scan Last scan snapshot or null.
	 * @return array{band: string, disclaimer: string}|array{}
	 */
	public static function for_last_scan( $scan ) {
		if ( ! is_array( $scan ) || ! isset( $scan['comparison'] ) || ! is_array( $scan['comparison'] ) ) {
			return array();
		}

		$comp = $scan['comparison'];
		if ( empty( $comp['has_prior'] ) ) {
			return array(
				'band'        => __( 'Run a second scan after changes to estimate a trend.', 'scorefix' ),
				'disclaimer'  => __( 'Illustrative range only - not a prediction. Real conversion impact depends on your traffic, audience, and measurement setup.', 'scorefix' ),
			);
		}

		$delta = null;
		if ( ! empty( $comp['score_delta_available'] ) && isset( $comp['score_delta'] ) ) {
			$delta = (int) $comp['score_delta'];
		}

		$resolved = isset( $comp['resolved_count'] ) ? (int) $comp['resolved_count'] : 0;

		$band = self::band_from_signals( $delta, $resolved );

		return array(
			'band'       => $band,
			'disclaimer' => __( 'Illustrative range only - not a prediction. Real conversion impact depends on your traffic, audience, and measurement setup.', 'scorefix' ),
		);
	}

	/**
	 * @param int|null $score_delta Score change vs prior scan.
	 * @param int      $resolved    Resolved issue rows.
	 * @return string
	 */
	protected static function band_from_signals( $score_delta, $resolved ) {
		$resolved = max( 0, $resolved );

		if ( null === $score_delta && $resolved < 1 ) {
			return __( 'Keep iterating: small UX wins often compound over time.', 'scorefix' );
		}

		if ( null !== $score_delta && $score_delta <= -3 ) {
			return __( 'Score dipped vs the last scan - review new issues and republish carefully.', 'scorefix' );
		}

		if ( null !== $score_delta && $score_delta >= 8 ) {
			return __( 'Typical illustrative band for comparable UX fixes: roughly mid single-digit % lift in key funnel metrics (varies widely by site).', 'scorefix' );
		}

		if ( null !== $score_delta && $score_delta >= 3 ) {
			return __( 'Typical illustrative band: low single-digit % lift in key funnel metrics is plausible when accessibility and clarity improve together.', 'scorefix' );
		}

		if ( $resolved >= 5 || ( null !== $score_delta && $score_delta >= 1 ) ) {
			return __( 'Typical illustrative band: fractional to low single-digit % lift in key funnel metrics - use A/B testing to validate.', 'scorefix' );
		}

		return __( 'Typical illustrative band: modest lift - prioritize remaining high-severity issues for clearer gains.', 'scorefix' );
	}
}
