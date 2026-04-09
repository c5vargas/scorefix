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
| `scanner/` | `Scanner` — scan + scoring |
| `fixes/` | `FixEngine` — runtime HTML fixes |
| `frontend/` | `RenderHooks` — `the_content`, attachments |
| `admin/` | Dashboard + POST actions |
| `docs/SAAS.md` | SaaS roadmap & API sketch |
| `COPY.md` | Marketing copy |
| `readme.txt` | WordPress.org-style readme |

## Requirements

- WordPress 6.0+
- PHP 7.4+

## License

GPL v2 or later.
