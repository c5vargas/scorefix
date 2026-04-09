=== ScoreFix – Boost Lighthouse & Improve UX ===
Contributors: scorefix
Tags: lighthouse, accessibility, performance, UX, SEO, WooCommerce
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Fix the issues hurting your Lighthouse score and conversions in one click. No coding required.

== Description ==

**ScoreFix helps real visitors — not just your score.** It finds common accessibility and UX issues that drag down Google Lighthouse (especially Accessibility), then applies **real fixes** to your HTML output: meaningful ALT text, labels for controls, and names for links and buttons. No fake overlays, no “accessibility theater.”

= Why ScoreFix? =

* **Business-first language** — Issues are explained in terms of conversions, readability, and trust — not jargon.
* **Automatic Scan** — Surfaces images without ALT, unnamed links/buttons, unlabeled fields, and basic contrast risks.
* **ScoreFix Score (0–100)** — One number you can track after each scan.
* **Apply Fixes (optional)** — When you turn fixes on, ScoreFix improves the markup your site outputs. The MVP applies **non-destructive** runtime fixes (no bulk database rewrites), so you can ship safely.

= Who it is for =

* Non-technical WordPress owners
* WooCommerce stores
* Small agencies managing client sites

= What makes it different =

* **Not a generic “accessibility widget”** — We don’t paint over problems; we address missing names and labels in the actual output.
* **Not only a checklist** — Scan + prioritized issues + one-click enable for automatic fixes.
* **Lighthouse-aligned** — Focused on changes that commonly affect Accessibility audits and real users.

= Limitations (important) =

* ScoreFix does **not** guarantee legal WCAG compliance or a perfect Lighthouse score.
* **Semantic ALT** for SEO may still need your editorial judgment.
* Highly dynamic JavaScript-only interfaces may need manual work outside the plugin.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/scorefix/` or install the zip from the Plugins screen.
2. Activate **ScoreFix** through the **Plugins** menu.
3. Go to **Settings → ScoreFix**, run a scan, then choose whether to enable automatic fixes.

== Frequently Asked Questions ==

= Will this change my database content? =

The MVP applies fixes at **runtime** when “Apply Fixes” is on. It does not bulk-edit posts in the database.

= Does it work with WooCommerce? =

Yes — WooCommerce’s `product` post type is included in scans when that post type exists.

= Is this a visual overlay? =

No. ScoreFix does not rely on overlays to fake compliance.

== Screenshots ==

1. ScoreFix dashboard with score, issues, and actions.

== Changelog ==

= 1.0.0 =
* Initial release: scanner, ScoreFix Score, admin dashboard, runtime fix engine.
