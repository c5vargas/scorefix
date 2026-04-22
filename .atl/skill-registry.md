# Skill Registry

**Delegator use only.** Any agent that launches sub-agents reads this registry to resolve compact rules, then injects them directly into sub-agent prompts. Sub-agents do NOT read this registry or individual SKILL.md files.

See `_shared/skill-resolver.md` for the full resolution protocol.

## User Skills

| Trigger | Skill | Path |
|---------|-------|------|
| When creating a GitHub issue, reporting a bug, or requesting a feature | issue-creation | C:\Users\Usuario\.config\opencode\skills\issue-creation\SKILL.md |
| When creating a pull request, opening a PR, or preparing changes for review | branch-pr | C:\Users\Usuario\.config\opencode\skills\branch-pr\SKILL.md |
| When user asks to create a new skill, add agent instructions, or document patterns for AI | skill-creator | C:\Users\Usuario\.config\opencode\skills\skill-creator\SKILL.md |
| When writing Go tests, using teatest, or adding test coverage | go-testing | C:\Users\Usuario\.config\opencode\skills\go-testing\SKILL.md |
| When user says "judgment day", "review adversarial", "dual review", "juzgar" | judgment-day | C:\Users\Usuario\.config\opencode\skills\judgment-day\SKILL.md |
| Optimize web performance for faster loading and better user experience | performance | C:\laragon\www\developer\wp-content\plugins\scorefix\.agents\skills\performance\SKILL.md |
| Optimize for search engine visibility and ranking | seo | C:\laragon\www\developer\wp-content\plugins\scorefix\.agents\skills\seo\SKILL.md |
| Audit and improve web accessibility following WCAG 2.2 guidelines | accessibility | C:\laragon\www\developer\wp-content\plugins\scorefix\.agents\skills\accessibility\SKILL.md |
| Use when investigating or improving WordPress performance (backend-only agent) | wp-performance | C:\laragon\www\developer\wp-content\plugins\scorefix\.agents\skills\wp-performance\SKILL.md |
| Use when developing WordPress plugins | wp-plugin-development | C:\laragon\www\developer\wp-content\plugins\scorefix\.agents\skills\wp-plugin-development\SKILL.md |

## Compact Rules

Pre-digested rules per skill. Delegators copy matching blocks into sub-agent prompts as `## Project Standards (auto-resolved)`.

### issue-creation
- Blank issues are disabled — MUST use a template (bug report or feature request)
- Every issue gets `status:needs-review` automatically on creation
- A maintainer MUST add `status:approved` before any PR can be opened
- Questions go to Discussions, not issues
- Bug Report template auto-labels: `bug`, `status:needs-review`
- Feature Request template auto-labels: `enhancement`, `status:needs-review`
- No PR can be opened without `status:approved` label on the linked issue

### branch-pr
- Every PR MUST link an approved issue — no exceptions
- Every PR MUST have exactly one `type:*` label
- Automated checks must pass before merge is possible
- Branch naming: `^(feat|fix|chore|docs|style|refactor|perf|test|build|ci|revert)\/[a-z0-9._-]+$`
- Conventional commits: `^(build|chore|ci|docs|feat|fix|perf|refactor|revert|style|test)(\([a-z0-9\._-]+\))?!?: .+`
- No `Co-Authored-By` trailers in commits
- Run shellcheck on modified shell scripts before opening PR

### skill-creator
- Create a skill when: pattern used repeatedly, project-specific conventions differ, complex workflows need guidance, decision trees help
- Don't create a skill when: documentation exists, pattern is trivial, one-off task
- Skill structure: `skills/{skill-name}/SKILL.md`, optional `assets/` and `references/`
- Frontmatter required: `name`, `description` (includes trigger keywords), `license: Apache-2.0`, `metadata.author`, `metadata.version`
- `references/` should point to LOCAL files, not web URLs
- After creating, add to `AGENTS.md` with name, description, and path

### go-testing
- Table-driven tests for multiple cases with name/input/expected/wantErr struct
- Test state transitions directly via `m.Update(msg)` — no UI needed
- Use `teatest.NewTestModel(t, m)` for full TUI integration tests
- Golden file testing: compare output against saved files in `testdata/`
- Use `t.TempDir()` for file operations in tests
- Pass `--update` flag to regenerate golden files

### judgment-day
- Launch TWO sub-agents in parallel (delegate, async) — each works independently
- Neither agent knows about the other — no cross-contamination
- Orchestrator synthesizes verdict: Confirmed (both agree), Suspect A/B (one only), Contradiction (disagree)
- WARNING classification: ask "Can a normal user trigger this?" YES = real, NO = theoretical
- Only fix confirmed CRITICALs and real WARNINGs; theoretical warnings are INFO only
- After 2 fix iterations with issues remaining, ASK user whether to continue
- MUST NOT declare APPROVED until Round 1 clean OR Round 2 with 0 CRITICALs + 0 real WARNINGs
- MUST NOT commit/push after fixes until re-judgment completes

### performance
- Performance budgets: Total < 1.5MB, JS < 300KB, CSS < 100KB, Images (above-fold) < 500KB
- TTFB target < 800ms; enable Brotli/Gzip, HTTP/2+, edge caching
- LCP images: `fetchpriority="high"`, `loading="eager"`, `decoding="sync"`
- Below-fold images: `loading="lazy"`, `decoding="async"`
- Use `fetchpriority="high"` on hero/Largest Contentful Paint image
- Fonts: subset to Latin, use `font-display: swap`, preload critical fonts
- Preconnect to required origins; defer non-critical CSS/scripts
- Cache-Control: immutable for hashed assets, short/no-cache for HTML

### seo
- Title tags: 50-60 chars, primary keyword near beginning, unique per page
- Meta descriptions: 150-160 chars, include keyword, compelling CTA, unique per page
- Single `<h1>` per page with logical hierarchy (no skipping levels)
- Images: descriptive filenames, meaningful alt text, WebP/AVIF with fallbacks
- Canonical URLs on all pages to prevent duplicate content
- robots.txt: allow crawling, block admin/api/private areas only
- Structured data: JSON-LD for Organization, Article, Product, FAQ, Breadcrumbs
- Internal links: descriptive anchor text with keywords

### accessibility
- WCAG 2.2 conformance: target AA minimum
- Text alternatives: all images need alt text (empty `alt=""` for decorative)
- Color contrast: 4.5:1 normal text, 3:1 large text and UI components
- Focus visible: use `:focus-visible` for keyboard-only focus styles
- Target size: minimum 24x24 CSS pixels (recommended 44x44)
- Keyboard accessible: all functionality via keyboard (Tab, Enter, Space, Escape)
- No keyboard traps; use native `<dialog>` or focus trap pattern for modals
- `prefers-reduced-motion`: disable animations when set
- Single `<h1>` per page; logical heading hierarchy
- Form labels: every input needs programmatically associated label
- Error handling: `role="alert"`, `aria-live`, `aria-invalid="true"` on invalid fields
- Prefer native HTML elements over ARIA — `<button>` not `div[role="button"]`

### wp-performance
- Backend-only agent; prefer WP-CLI (doctor/profile) when available
- Measure first: capture baseline TTFB before making changes
- Run `wp doctor check` for fast diagnostics (autoload bloat, SAVEQUERIES, plugin counts)
- Profile with `wp profile stage` then `wp profile hook` to find slow hooks/callbacks
- Query Monitor: use REST headers (`x-qm-*`) with authenticated requests for headless inspection
- Fix by dominant bottleneck: DB queries, autoloaded options, object cache, HTTP calls, cron
- Verify: repeat same measurement, confirm delta and behavior unchanged
- No cache flush required for correctness (flush as last resort only)
- DO NOT: install plugins, enable SAVEQUERIES, flush caches during production traffic

### wp-plugin-development
- Single bootstrap (main plugin file with header); avoid heavy side effects at file load time
- Register hooks in dedicated loader class; keep admin-only code behind `is_admin()`
- Activation/deactivation hooks: register at top-level, not inside other hooks
- Flush rewrite rules only after registering CPTs/rules; uninstall via `uninstall.php`
- Settings API: `register_setting()`, `add_settings_section()`, `add_settings_field()`
- Security baseline: validate/sanitize input early, escape output late
- Use nonces for CSRF AND capability checks for authorization
- Use `$wpdb->prepare()` for SQL — never concatenate user input into queries
- No test suite — verify manually or run PHP syntax check before commits
- WP req: 6.0+ | PHP: 7.4+ | Namespace: `ScoreFix\`

## Project Conventions

| File | Path | Notes |
|------|------|-------|
| AGENTS.md (index) | C:\laragon\www\developer\wp-content\plugins\scorefix\AGENTS.md | Index — references skills below |
| wp-plugin-development skill | C:\laragon\www\developer\wp-content\plugins\scorefix\.agents\skills\wp-plugin-development\SKILL.md | Referenced by AGENTS.md |
| wp-performance skill | C:\laragon\www\developer\wp-content\plugins\scorefix\.agents\skills\wp-performance\SKILL.md | Referenced by AGENTS.md |

Read the convention files listed above for project-specific patterns and rules. All referenced paths have been extracted — no need to read index files to discover more.