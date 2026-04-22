# ScoreFix – AGENTS.md

## Quick facts
- **Type:** WordPress plugin (Lighthouse accessibility scanner + runtime fixes)
- **Entry:** `scorefix.php` registers autoloader, hooks into `plugins_loaded`
- **Namespace:** `ScoreFix\` (e.g., `ScoreFix\Scanner\Scanner`)
- **WP req:** 6.0+ | **PHP:** 7.4+

## Development commands
```bash
# PHP syntax check (CI runs this)
find . -name "*.php" -not -path "./vendor/*" | xargs -n 1 -P 4 php -l
```

## Version sync (required before release)
1. Edit `VERSION` (e.g., `1.0.9`)
2. Run: `php bin/sync-version.php`
3. Commit, push, confirm CI passes
4. Tag: `git tag -a v1.0.9 -m "Release 1.0.9"` → `git push origin v1.0.9`

Tag deploys to WordPress.org via `deploy.yml`.

## Architecture
| Dir | Purpose |
|----|---------|
| `scanner/rules/` | 20+ rule classes: `HeadingsRule`, `LinksRule`, `ImagesRule`, etc. |
| `fixes/` | `FixEngine` — runtime HTML fixes |
| `admin/` | Dashboard + POST actions |
| `frontend/` | `the_content`, attachment hooks |

## Gotchas
- WordPress.org rejects hidden dotfiles (`.distignore`, `.gitkeep`) — use `.wordpress-org/` only
- `readme.txt` required for WP.org; `README.md` is GitHub-only
- No test suite — verify manually or add PHP syntax check before commits

## Supplemental resources
- `.agents/skills/wp-plugin-development/` — WordPress plugin development skill (hooks, security, Settings API, lifecycle)
- `.agents/skills/wp-performance/` — WordPress performance skill (caching, queries, optimization)