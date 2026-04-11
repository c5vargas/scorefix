<?php
/**
 * SEO actions card (fallback meta description).
 *
 * @package ScoreFix
 *
 * @var array<string, mixed> $scorefix_settings Plugin settings array.
 */

defined( 'ABSPATH' ) || exit;

use ScoreFix\Admin\ActionsController;

if ( ! is_array( $scorefix_settings ) ) {
	$scorefix_settings = array();
}

$scorefix_meta_description_on = ! array_key_exists( 'meta_description_enabled', $scorefix_settings ) || ! empty( $scorefix_settings['meta_description_enabled'] );
?>
<div class="scorefix-card scorefix-card--automation scorefix-card--seo">
	<p class="scorefix-automation__title scorefix-seo__title" role="heading" aria-level="2"><?php esc_html_e( 'SEO', 'scorefix' ); ?></p>
	<p class="scorefix-seo__lead scorefix-muted">
		<?php esc_html_e( 'Optional outputs that support search snippets without replacing a full SEO plugin.', 'scorefix' ); ?>
	</p>
	<p class="scorefix-seo__bridge scorefix-muted">
		<?php esc_html_e( 'Speed and UX support SEO indirectly (for example Core Web Vitals and how people experience the page). ScoreFix adds light HTML performance checks—many scripts, image hints—next to SEO audits; it is not a PageSpeed Insights or CrUX substitute.', 'scorefix' ); ?>
	</p>

	<form method="post" class="scorefix-seo-form">
		<?php wp_nonce_field( ActionsController::ACTION_SAVE_META_DESC ); ?>
		<input type="hidden" name="scorefix_action" value="<?php echo esc_attr( ActionsController::ACTION_SAVE_META_DESC ); ?>" />

		<div class="scorefix-automation__panel scorefix-seo__panel">
			<div class="scorefix-automation__panel-text">
				<strong class="scorefix-automation__panel-label"><?php esc_html_e( 'Fallback meta description', 'scorefix' ); ?></strong>
				<span class="scorefix-automation__panel-hint"><?php esc_html_e( 'Outputs a meta description on singular pages and the front page when none is set (Yoast / Rank Math custom descriptions are respected).', 'scorefix' ); ?></span>
			</div>
			<div class="scorefix-automation__toggle-slot">
				<label class="scorefix-toggle scorefix-toggle--form">
					<input
						type="checkbox"
						name="scorefix_meta_description_enabled"
						value="1"
						class="scorefix-toggle__input"
						aria-label="<?php esc_attr_e( 'Enable fallback meta description', 'scorefix' ); ?>"
						<?php checked( $scorefix_meta_description_on ); ?>
					/>
					<span class="scorefix-toggle__track" aria-hidden="true"><span class="scorefix-toggle__thumb"></span></span>
				</label>
			</div>
		</div>

		<div class="scorefix-seo__submit">
			<button type="submit" class="button button-primary scorefix-seo__save"><?php esc_html_e( 'Save SEO settings', 'scorefix' ); ?></button>
		</div>
	</form>
</div>
