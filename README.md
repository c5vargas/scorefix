![WordPress Version](https://img.shields.io/badge/wordpress-%3E%3D%206.0-blue.svg)
![PHP Version](https://img.shields.io/badge/php-%3E%3D%207.4-8892bf.svg)
![License](https://img.shields.io/badge/license-GPL--2.0--or--later-green.svg)

# ScoreFix – Boost Lighthouse & Improve UX

WordPress plugin: scan for common Lighthouse accessibility issues, show a **ScoreFix Score (0–100)**, and optionally apply **non-destructive runtime fixes** (no fake overlays).

## Install

Copy this folder to `wp-content/plugins/scorefix/` and activate **ScoreFix** in wp-admin.

Then open **Settings → ScoreFix**.

## Structure

| Path | Role |
|------|------|
| `scorefix.php` | Bootstrap, constants, autoload |
| `core/` | `Plugin`, `Loader`, `Autoload` |
| `scanner/` | `Scanner`, rules (`PerformanceHeuristicRule`, …), `UrlHtmlCapture`, `RenderUrlCollector`, `RenderScanQueue`, `RenderCaptureConfig` — scan + scoring + background same-host HTML pass |
| `fixes/` | `FixEngine` — runtime HTML fixes |
| `frontend/` | `RenderHooks` — `the_content`, attachments |
| `admin/` | Dashboard + POST actions |
| `docs/SAAS.md` | SaaS roadmap & API sketch |
| `readme.txt` | WordPress.org-style readme |
| `languages/` | Translation files (WordPress.org loads from here) |

## Requirements

- WordPress 6.0+
- PHP 7.4+

## License

GPL v2 or later.

## Marketing copy (listing & UI)

Use consistently on WordPress.org, landing pages, and in-product microcopy.

**Plugin name:** ScoreFix – Boost Lighthouse & Improve UX

**Short description (≤150 chars):** Fix the issues hurting your Lighthouse score and conversions in one click. No coding required.

**Long description (summary):** Lighthouse Accessibility reflects real usability. ScoreFix finds issues that hurt Lighthouse and outcomes (missing ALT, unnamed links/buttons, unlabeled fields). Scan → **ScoreFix Score (0–100)** → plain-language list tied to readability, trust, and conversions. **Apply Fixes** changes real HTML output, not overlays. For owners, WooCommerce, and agencies without code.

**Differentiation:** Real fixes not overlays; Lighthouse + UX + conversions; scan → prioritize → fix.

**Honest limitations:** No legal WCAG guarantee or perfect Lighthouse; editorial ALT may need humans; some JS-heavy UIs need work outside the plugin.

## WordPress.org package

Do not ship hidden dotfiles (e.g. `.distignore`, `.gitkeep`) in the plugin ZIP; Plugin Check rejects them. Omit development-only markdown from the root if the checker flags it (`README.md` is often kept for GitHub only—use `readme.txt` for the directory).
