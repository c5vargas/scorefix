<?php
/**
 * Static glossary: issue type to business copy and orientative WCAG/Lighthouse pointers.
 *
 * @package ScoreFix
 */

namespace ScoreFix\Scanner;

defined( 'ABSPATH' ) || exit;

/**
 * Class IssueGlossary
 */
class IssueGlossary {

	/**
	 * Legal / product disclaimer for all glossary panels.
	 *
	 * @return string
	 */
	public static function disclaimer() {
		return __( 'These references are orientative only. ScoreFix does not guarantee WCAG compliance, legal accessibility conformance, or a specific Lighthouse score.', 'scorefix' );
	}

	/**
	 * Glossary entry for an issue type, or generic fallback.
	 *
	 * @param string $type Issue type slug (e.g. image_no_alt).
	 * @return array{
	 *   title: string,
	 *   business: string,
	 *   references: array<int, string>,
	 *   disclaimer: string
	 * }
	 */
	public static function get_entry( $type ) {
		$type = sanitize_key( (string) $type );
		$defs  = self::definitions();
		if ( isset( $defs[ $type ] ) ) {
			$entry = $defs[ $type ];
			$entry['disclaimer'] = self::disclaimer();
			return apply_filters( 'scorefix_issue_glossary_entry', $entry, $type );
		}
		$fallback = self::generic_entry( $type );
		return apply_filters( 'scorefix_issue_glossary_entry', $fallback, $type );
	}

	/**
	 * @return array<string, array{title: string, business: string, references: array<int, string>}>
	 */
	protected static function definitions() {
		$d = array();

		$d['image_no_alt'] = array(
			'title'    => __( 'Image missing alternative text (ALT)', 'scorefix' ),
			'business' => __( 'Screen readers and search engines use ALT to understand images. Missing descriptions hurt discoverability and trust.', 'scorefix' ),
			'references' => array(
				__( 'Lighthouse (orientative): Accessibility — Image elements have [alt] attributes', 'scorefix' ),
				__( 'WCAG 2.2 (orientative): 1.1.1 Non-text Content', 'scorefix' ),
			),
		);

		$d['link_no_text'] = array(
			'title'    => __( 'Link without an accessible name', 'scorefix' ),
			'business' => __( 'People using keyboards or assistive tech need a clear name for every link. Empty or icon-only links are easy to miss and reduce clicks.', 'scorefix' ),
			'references' => array(
				__( 'Lighthouse (orientative): link-name', 'scorefix' ),
				__( 'WCAG 2.2 (orientative): 2.4.4 Link Purpose (In Context); 4.1.2 Name, Role, Value', 'scorefix' ),
			),
		);

		$d['button_no_text'] = array(
			'title'    => __( 'Button without an accessible name', 'scorefix' ),
			'business' => __( 'Buttons must say what they do. Unnamed buttons confuse shoppers and increase form abandonment.', 'scorefix' ),
			'references' => array(
				__( 'Lighthouse (orientative): button-name', 'scorefix' ),
				__( 'WCAG 2.2 (orientative): 4.1.2 Name, Role, Value', 'scorefix' ),
			),
		);

		$d['input_no_label'] = array(
			'title'    => __( 'Form control without a label', 'scorefix' ),
			'business' => __( 'Labels (or equivalent aria-* associations) tell users what to enter. Missing labels are a common checkout and lead-gen failure point.', 'scorefix' ),
			'references' => array(
				__( 'Lighthouse (orientative): label', 'scorefix' ),
				__( 'WCAG 2.2 (orientative): 1.3.1 Info and Relationships; 3.3.2 Labels or Instructions', 'scorefix' ),
			),
		);

		$d['contrast_risk'] = array(
			'title'    => __( 'Possible low contrast (inline styles)', 'scorefix' ),
			'business' => __( 'When text and background colors are too similar, content is hard to read on many screens. Fixing contrast improves readability and confidence.', 'scorefix' ),
			'references' => array(
				__( 'Lighthouse (orientative): color-contrast', 'scorefix' ),
				__( 'WCAG 2.2 (orientative): 1.4.3 Contrast (Minimum)', 'scorefix' ),
			),
		);

		// Reserved for upcoming scanner rules (Phase 2); glossary ready from Phase 1.
		$d['heading_multiple_h1'] = array(
			'title'    => __( 'Multiple level-one headings', 'scorefix' ),
			'business' => __( 'A predictable heading outline helps people skim content. More than one main heading can confuse navigation and SEO structure.', 'scorefix' ),
			'references' => array(
				__( 'Lighthouse (orientative): heading-order / semantic structure', 'scorefix' ),
				__( 'WCAG 2.2 (orientative): 1.3.1 Info and Relationships; 2.4.6 Headings and Labels', 'scorefix' ),
			),
		);

		$d['heading_level_skip'] = array(
			'title'    => __( 'Skipped heading level', 'scorefix' ),
			'business' => __( 'Skipping levels (for example from h2 to h4) breaks the outline screen-reader users rely on.', 'scorefix' ),
			'references' => array(
				__( 'Lighthouse (orientative): heading-order', 'scorefix' ),
				__( 'WCAG 2.2 (orientative): 1.3.1 Info and Relationships', 'scorefix' ),
			),
		);

		$d['landmark_no_main'] = array(
			'title'    => __( 'No main landmark', 'scorefix' ),
			'business' => __( 'A main region helps assistive tech jump to primary content. Full pages often use <main> or role="main" once.', 'scorefix' ),
			'references' => array(
				__( 'WCAG 2.2 (orientative): 1.3.1 Info and Relationships; 2.4.1 Bypass Blocks', 'scorefix' ),
			),
		);

		$d['landmark_multiple_main'] = array(
			'title'    => __( 'Multiple main landmarks', 'scorefix' ),
			'business' => __( 'There should usually be a single main region per page so users know where primary content starts and ends.', 'scorefix' ),
			'references' => array(
				__( 'WCAG 2.2 (orientative): 1.3.1 Info and Relationships', 'scorefix' ),
			),
		);

		$d['landmark_nav_unnamed'] = array(
			'title'    => __( 'Navigation landmark without a distinguishable name', 'scorefix' ),
			'business' => __( 'When there are several navigation regions, each needs a unique accessible name so users can tell them apart.', 'scorefix' ),
			'references' => array(
				__( 'WCAG 2.2 (orientative): 1.3.1 Info and Relationships; 2.4.1 Bypass Blocks', 'scorefix' ),
			),
		);

		$d['link_generic_text'] = array(
			'title'    => __( 'Generic link text', 'scorefix' ),
			'business' => __( 'Phrases like “click here” or “read more” do not describe the destination. Descriptive links improve clarity and conversions.', 'scorefix' ),
			'references' => array(
				__( 'Lighthouse (orientative): link-text', 'scorefix' ),
				__( 'WCAG 2.2 (orientative): 2.4.4 Link Purpose (In Context)', 'scorefix' ),
			),
		);

		$d['form_radio_group_no_legend'] = array(
			'title'    => __( 'Radio group without fieldset/legend', 'scorefix' ),
			'business' => __( 'Grouped radios should share a visible or programmatic group label so the question is clear.', 'scorefix' ),
			'references' => array(
				__( 'WCAG 2.2 (orientative): 1.3.1 Info and Relationships; 3.3.2 Labels or Instructions', 'scorefix' ),
			),
		);

		$d['form_required_no_error_hint'] = array(
			'title'    => __( 'Required field without clear error guidance', 'scorefix' ),
			'business' => __( 'Required fields should communicate errors in an accessible way. Heuristic checks are limited and may not catch all patterns.', 'scorefix' ),
			'references' => array(
				__( 'WCAG 2.2 (orientative): 3.3.1 Error Identification; 3.3.3 Error Suggestion', 'scorefix' ),
			),
		);

		$d['form_autocomplete_missing'] = array(
			'title'    => __( 'Missing autocomplete on common fields', 'scorefix' ),
			'business' => __( 'Appropriate autocomplete values help users fill forms faster and more accurately, especially on mobile.', 'scorefix' ),
			'references' => array(
				__( 'WCAG 2.2 (orientative): 1.3.5 Identify Input Purpose', 'scorefix' ),
			),
		);

		$d['video_no_text_track'] = array(
			'title'    => __( 'Video without captions or text alternative', 'scorefix' ),
			'business' => __( 'Captions and transcripts make video usable in noisy environments and for people who cannot hear audio.', 'scorefix' ),
			'references' => array(
				__( 'WCAG 2.2 (orientative): 1.2.2 Captions (Prerecorded); 1.1.1 Non-text Content', 'scorefix' ),
			),
		);

		$d['audio_no_text_track'] = array(
			'title'    => __( 'Audio without text alternative', 'scorefix' ),
			'business' => __( 'Audio-only content should have an equivalent text version or description where appropriate.', 'scorefix' ),
			'references' => array(
				__( 'WCAG 2.2 (orientative): 1.2.1 Audio-only and Video-only (Prerecorded)', 'scorefix' ),
			),
		);

		$d['iframe_missing_title'] = array(
			'title'    => __( 'iframe without a title', 'scorefix' ),
			'business' => __( 'A descriptive title helps users understand embedded content (maps, players, widgets) before they enter the frame.', 'scorefix' ),
			'references' => array(
				__( 'Lighthouse (orientative): frame-title', 'scorefix' ),
				__( 'WCAG 2.2 (orientative): 4.1.2 Name, Role, Value', 'scorefix' ),
			),
		);

		$d['table_missing_th'] = array(
			'title'    => __( 'Data table without header cells', 'scorefix' ),
			'business' => __( 'Header cells associate columns or rows with their meaning for screen readers.', 'scorefix' ),
			'references' => array(
				__( 'WCAG 2.2 (orientative): 1.3.1 Info and Relationships', 'scorefix' ),
			),
		);

		$d['table_missing_caption'] = array(
			'title'    => __( 'Data table without a caption', 'scorefix' ),
			'business' => __( 'A caption summarizes the table purpose. It is optional for very simple tables but helps comprehension on complex data.', 'scorefix' ),
			'references' => array(
				__( 'WCAG 2.2 (orientative): 1.3.1 Info and Relationships', 'scorefix' ),
			),
		);

		$d['perf_img_missing_dimensions'] = array(
			'title'    => __( 'Image without width and height', 'scorefix' ),
			'business' => __( 'Explicit dimensions help the browser reserve space so the page does not jump while images load (better perceived speed and Core Web Vitals layout stability). This check only looks at the HTML we scanned — not network weight.', 'scorefix' ),
			'references' => array(
				__( 'Lighthouse (orientative): Performance — Properly size images / CLS-related audits', 'scorefix' ),
				__( 'web.dev: Optimize Cumulative Layout Shift', 'scorefix' ),
			),
		);

		$d['perf_img_missing_lazy'] = array(
			'title'    => __( 'Image may benefit from lazy loading', 'scorefix' ),
			'business' => __( 'Deferring off-screen images can speed up first paint. ScoreFix uses a simple rule (for example: not the first image, and usually after the first main content block). Your theme or page builder may already optimize this on the live site.', 'scorefix' ),
			'references' => array(
				__( 'Lighthouse (orientative): Performance — Offscreen images', 'scorefix' ),
				__( 'MDN: loading attribute', 'scorefix' ),
			),
		);

		$d['perf_many_external_scripts'] = array(
			'title'    => __( 'Many external scripts in this HTML', 'scorefix' ),
			'business' => __( 'A large number of separate script files often means more network work and slower interactivity. This is a rough count from the HTML snapshot only.', 'scorefix' ),
			'references' => array(
				__( 'Lighthouse (orientative): Performance — Bootup time / JavaScript execution', 'scorefix' ),
				__( 'web.dev: Reduce JavaScript payloads', 'scorefix' ),
			),
		);

		$d['seo_thin_content'] = array(
			'title'    => __( 'Body text looks short for this post or page', 'scorefix' ),
			'business' => __( 'Very short stored content may offer little context for readers and for search engines. This is a simple word count on the HTML we scan (not the live rendered page). Expand with useful detail where it fits your strategy.', 'scorefix' ),
			'references' => array(
				__( 'Google Search Central: Creating helpful, reliable, people-first content', 'scorefix' ),
			),
		);

		$d['seo_few_internal_links'] = array(
			'title'    => __( 'Long article links out but not to your own site', 'scorefix' ),
			'business' => __( 'Internal links help visitors and crawlers discover related pages on your site. This rule only flags when the body is fairly long, there are multiple external links, and none point to your domain in the stored HTML.', 'scorefix' ),
			'references' => array(
				__( 'Google Search Central: Linking internally', 'scorefix' ),
			),
		);

		$d['seo_head_title_missing'] = array(
			'title'    => __( 'Missing or empty document title (HTML title element)', 'scorefix' ),
			'business' => __( 'The HTML snapshot from your public URL has no usable title. Titles help people and search engines understand the page. If a SEO plugin injects the title only at runtime in a way this capture cannot see, you can ignore this or adjust the theme.', 'scorefix' ),
			'references' => array(
				__( 'Lighthouse (orientative): SEO — document-title', 'scorefix' ),
				__( 'Google Search Central: Influencing title links', 'scorefix' ),
			),
		);

		$d['seo_head_title_length'] = array(
			'title'    => __( 'Document title length looks unusual', 'scorefix' ),
			'business' => __( 'Very short or very long titles can look odd in search results. This check uses simple character limits on the HTML we captured; tune thresholds with the scorefix_head_seo_title_min_chars / scorefix_head_seo_title_max_chars filters if needed.', 'scorefix' ),
			'references' => array(
				__( 'Lighthouse (orientative): SEO — document-title', 'scorefix' ),
			),
		);

		$d['seo_head_meta_description_missing'] = array(
			'title'    => __( 'No meta description in captured HTML', 'scorefix' ),
			'business' => __( 'A concise meta description can improve how your snippet looks in results. This checks for meta name="description" or property="og:description" in the HTML we fetched. Plugins that output description only via JavaScript may not appear here.', 'scorefix' ),
			'references' => array(
				__( 'Lighthouse (orientative): SEO — meta-description', 'scorefix' ),
			),
		);

		$d['seo_head_canonical_missing'] = array(
			'title'    => __( 'No canonical link in captured HTML', 'scorefix' ),
			'business' => __( 'A canonical URL tells search engines which URL is preferred when duplicates exist. Some setups omit it on purpose; disable this check with the scorefix_head_seo_require_canonical filter if your stack handles canonicals elsewhere.', 'scorefix' ),
			'references' => array(
				__( 'Lighthouse (orientative): SEO — canonical', 'scorefix' ),
				__( 'Google Search Central: Consolidate duplicate URLs', 'scorefix' ),
			),
		);

		$d['seo_head_viewport_missing'] = array(
			'title'    => __( 'No viewport meta tag in captured HTML', 'scorefix' ),
			'business' => __( 'Without a viewport meta tag, mobile browsers may render the page at desktop width. This is a common technical SEO and usability issue on the HTML we captured.', 'scorefix' ),
			'references' => array(
				__( 'Lighthouse (orientative): SEO — viewport', 'scorefix' ),
			),
		);

		$d['seo_head_robots_noindex'] = array(
			'title'    => __( 'Robots meta includes noindex (informational)', 'scorefix' ),
			'business' => __( 'The captured HTML has a robots meta tag that includes noindex, which usually discourages indexing. This issue is off by default; enable it with the scorefix_head_seo_report_noindex_in_html filter when you want to audit it.', 'scorefix' ),
			'references' => array(
				__( 'Google Search Central: Robots meta tag', 'scorefix' ),
			),
		);

		$d['seo_jsonld_invalid_json'] = array(
			'title'    => __( 'Invalid or empty JSON-LD block', 'scorefix' ),
			'business' => __( 'A script with type application/ld+json did not contain valid JSON. Search engines may ignore that block, which can break rich results tied to it. Fix the syntax in the theme, plugin, or template that outputs the block.', 'scorefix' ),
			'references' => array(
				__( 'Google Search Central: Introduction to structured data', 'scorefix' ),
				__( 'Schema.org: JSON-LD', 'scorefix' ),
			),
		);

		$d['seo_jsonld_missing_expected_type'] = array(
			'title'    => __( 'Expected schema.org type not found in JSON-LD', 'scorefix' ),
			'business' => __( 'Based on this URL (home, inner page, or WooCommerce product), a common structured data type was not detected in the captured HTML. On the home URL, Organization or WebSite (and similar site-level types) count as identity. This is a soft check: plugins may inject schema only after this snapshot runs. Tune expectations with scorefix_jsonld_expect_* and scorefix_jsonld_expected_types.', 'scorefix' ),
			'references' => array(
				__( 'Google Search Central: Understand how structured data works', 'scorefix' ),
			),
		);

		return $d;
	}

	/**
	 * Fallback when type is unknown.
	 *
	 * @param string $type Raw type slug.
	 * @return array{title: string, business: string, references: array<int, string>, disclaimer: string}
	 */
	protected static function generic_entry( $type ) {
		$label = '' !== $type ? $type : __( 'unknown', 'scorefix' );
		return array(
			'title'    => __( 'Accessibility improvement', 'scorefix' ),
			'business' => sprintf(
				/* translators: %s: technical issue type key */
				__( 'This finding type (%s) is summarized in your issues list. Review the suggested fix in the editor or theme.', 'scorefix' ),
				$label
			),
			'references' => array(
				__( 'W3C: Web Content Accessibility Guidelines (WCAG) Overview', 'scorefix' ),
			),
			'disclaimer' => self::disclaimer(),
		);
	}
}
