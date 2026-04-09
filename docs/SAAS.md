# ScoreFix — SaaS foundation & roadmap

This document describes how the WordPress plugin can evolve into a hosted **ScoreFix** service without rewriting the product from scratch.

## What stays local (WordPress plugin)

- **Runtime fix engine** — Must stay close to the site for low latency and for filters that touch `the_content` and attachment attributes.
- **Admin UX** — Settings, branding, and “Apply fixes” toggles are natural to keep in WP.
- **Basic scanner (MVP)** — Heuristic scans over post content and the media library are cheap to run on-server for small sites.

## What moves to an external API (SaaS)

| Concern | Rationale |
|--------|-----------|
| **Heavy / cross-page analysis** | Crawling templates, cart/checkout flows, and multi-step funnels at scale needs workers and storage. |
| **Historical trends & benchmarks** | Aggregating scores across time and industry verticals is a central SaaS dataset. |
| **AI-assisted recommendations** | Suggesting better ALT copy, CTA wording, or form microcopy benefits from models and rate limits. |
| **Agency reporting** | PDF/white-label reports, client portals, and scheduled audits. |
| **Fraud & abuse controls** | API keys, quotas, and billing. |

## Suggested API surface (v1)

Base URL: `https://api.scorefix.example/v1`

| Method | Endpoint | Purpose |
|--------|----------|---------|
| `POST` | `/sites/{siteId}/scan` | Queue or run a remote scan job (URLs + optional HTML snapshots). |
| `GET` | `/sites/{siteId}/issues` | Paginated issues with severity, category, and suggested fix type. |
| `POST` | `/sites/{siteId}/fixes/preview` | Return a diff/preview of proposed fixes (no apply). |
| `POST` | `/sites/{siteId}/fixes/apply` | Optional: apply approved fixes via partner workflow (not always applicable to WP). |
| `GET` | `/sites/{siteId}/benchmarks` | Compare ScoreFix Score to anonymized cohorts. |

### Authentication

- **Site-scoped API keys** issued from the SaaS dashboard; stored in WP as `scorefix_license_key` (future) and sent as `Authorization: Bearer …`.

### Webhooks (optional)

- `scan.completed` — Push results to the plugin to refresh the local dashboard cache.

## Premium feature candidates

1. **Scheduled scans** + email/Slack alerts.
2. **Historical graphs** (score over time, regressions per deploy).
3. **Template-level insights** (archive vs single, checkout vs product).
4. **Conversion-focused suggestions** (forms, CTAs, trust signals) — paired with AI tier.
5. **Agency workspace** — multiple sites, roles, white-label exports.

## Implementation notes for the plugin bridge (future)

- Add `ScoreFix\Cloud\Client` with timeouts, retries, and capability checks (`manage_options` before sync).
- Cache remote scan results in `wp_options` or a custom table with TTL.
- Fall back gracefully when API is unavailable — local scanner remains usable.

## Roadmap (phased)

1. **Phase A — Plugin-only MVP** (current): local scan + runtime fixes + dashboard.
2. **Phase B — “Connect account”**: license check + optional upload of anonymized issue summaries.
3. **Phase C — Remote deep scan**: enqueue crawl from SaaS, display merged results in WP.
4. **Phase D — Teams & billing**: Stripe, seats, agency features.
