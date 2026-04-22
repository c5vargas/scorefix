# Skill Registry

**Delegator use only.** Any agent that launches sub-agents reads this registry to resolve compact rules, then injects them directly into sub-agent prompts. Sub-agents do NOT read this registry or individual SKILL.md files.

See `_shared/skill-resolver.md` for the full resolution protocol.

## Project Skills

| Trigger | Skill | Path |
|---------|-------|------|
| "improve accessibility", "a11y audit", "WCAG compliance", "screen reader support", "keyboard navigation", "make accessible" | accessibility | `.agents/skills/accessibility/SKILL.md` |
| "speed up my site", "optimize performance", "reduce load time", "fix slow loading", "improve page speed", "performance audit" (general web) | performance | `.agents/skills/performance/SKILL.md` |
| "improve SEO", "optimize for search", "fix meta tags", "add structured data", "sitemap optimization", "search engine optimization" | seo | `.agents/skills/seo/SKILL.md` |
| WordPress performance profiling, DB queries, autoloaded options, object caching, cron, HTTP API | wp-performance | `.agents/skills/wp-performance/SKILL.md` |
| WordPress plugin development: hooks, activation, admin UI, settings, security, packaging | wp-plugin-development | `.agents/skills/wp-plugin-development/SKILL.md` |

## User Skills (Global)

| Trigger | Skill | Path |
|---------|-------|------|
| User asks about libraries, frameworks, SDKs, APIs (React, Next.js, Prisma, Supabase, etc.) | context7-mcp | `C:\Users\Usuario\.agents\skills\context7-mcp\SKILL.md` |
| "caveman mode", "talk like caveman", "use caveman", "less tokens", "be brief" | caveman | `C:\Users\Usuario\.agents\skills\caveman\SKILL.md` |

## Compact Rules

### accessibility
- Prefer native HTML elements (button, a, input) over ARIA roles
- All images need alt text (decorative: `alt="" role="presentation"`)
- Color contrast: 4.5:1 normal text, 3:1 large text/UI (WCAG AA)
- Focus visible always: `:focus-visible { outline: 2px solid #005fcc; }`
- Target size minimum 24×24px
- Form labels: explicit `<label for="id">` or wrapper label
- Skip link for keyboard navigation
- Respect `prefers-reduced-motion`

### performance
- TL < 800ms target, compress (Brotli), HTTP/2+
- JS: defer non-critical, tree-shake imports
- Images: use WebP/AVIF, lazy load below-fold, eager load LCP
- Fonts: font-display: swap, preload critical
- Cache: hash-based immutable for static assets (1 year)
- Avoid layout thrashing: batch reads, then batch writes

### seo
- Unique title tag per page (50-60 chars)
- Meta description (150-160 chars), unique per page
- Single `<h1>` per page, logical heading hierarchy
- Descriptive anchor text (no "click here")
- robots.txt and XML sitemap required
- Canonical URLs to prevent duplicate content
- JSON-LD structured data for Organization, Article, Product, FAQ

### wp-performance
- Measure before and after with same URL/environment
- Prefer WP-CLI (wp doctor, wp profile) for profiling
- Autoload options bloat is common WP foot-gun
- Object cache: use groups/keys consistently
- Remote HTTP: add timeouts, avoid per-request calls
- Cron: de-duplicate events, move heavy tasks out of request path

### wp-plugin-development
- Single bootstrap file with plugin header
- Hooks: register at top-level, flush rewrite rules only when needed
- Settings API: register_setting() with sanitize_callback
- Security: validate input early, escape output late
- Nonces + capability checks required
- Use $wpdb->prepare() for SQL
- Uninstall: explicit via uninstall.php or register_uninstall_hook

### context7-mcp
- Use resolve-library-id first to get Context7 library ID
- Then query-docs with full user question
- Prefer official packages over community forks
- Version-specific library IDs when user mentions version

### caveman
- DROP articles (a/an/the), filler (just/really/basically), hedging
- Fragments OK, short synonyms (big not extensive)
- Active every response until "stop caveman"
- Default: full intensity
- Code/commits unchanged, only text terse

## Project Conventions

| File | Path | Notes |
|------|------|-------|
| AGENTS.md | `AGENTS.md` | Main project conventions |
| ScoreFix entry | `scorefix.php` | Plugin bootstrap |
| Scanner rules | `scanner/rules/` | Rule classes (HeadingsRule, LinksRule, etc.) |
| Fixes | `fixes/` | FixEngine for runtime HTML fixes |
| Admin | `admin/` | Dashboard and POST actions |
| Frontend | `frontend/` | the_content, attachment hooks |

Project uses PSR-4 autoloading under `ScoreFix\` namespace.
WordPress 6.0+, PHP 7.4+ required.