# WordPress.org deploy and versioning

How to keep versions aligned in the codebase, ship to the plugin directory, and use this repo’s CI.

## Single source of version

The plugin’s **canonical** version string lives at the repo root:

| File | Contents |
|------|----------|
| `VERSION` | One line, e.g. `1.0.6` (no `v` prefix). |

That value must match:

- The `Version:` header and `SCOREFIX_VERSION` constant in `scorefix.php`.
- The `Stable tag:` line in `readme.txt` (WordPress.org).

**`bin/sync-version.php`** reads `VERSION` and updates `scorefix.php` and `readme.txt` so you do not maintain three places by hand.

### Running the script

From the plugin root (where `VERSION` and `scorefix.php` live):

```bash
php bin/sync-version.php
```

It validates `x.y.z` or `x.y.z-suffix` (e.g. `1.2.3-beta.1`). On failure it exits non-zero and prints to stderr.

**Note:** the **Changelog** block in `readme.txt` (`= 1.0.6 =`, etc.) is **not** updated by the script; add each release’s notes manually before shipping.

---

## GitHub secrets (deploy only)

The WordPress.org deploy workflow uses [10up/action-wordpress-plugin-deploy](https://github.com/10up/action-wordpress-plugin-deploy). Configure these **repository secrets**:

| Secret | Purpose |
|--------|---------|
| `SVN_USERNAME` | WordPress.org account with access to the `scorefix` plugin. |
| `SVN_PASSWORD` | Account password or recommended **application password** for SVN. |

Without them, the deploy job will fail when pushing to WordPress.org SVN.

---

## Workflows in `.github/workflows/`

### `main.yml` — PHP checks (push and pull request)

- Runs on **every push** and **every pull request**.
- Sets up PHP 8.1 and common extensions.
- Walks all `.php` files (except `vendor/`) and runs `php -l` for syntax.

Use this as a gate before merge or tagging: broken PHP fails the job.

### `deploy.yml` — WordPress.org release (tags only)

- Runs **only** when you push a **tag** matching `v*` (e.g. `v1.0.6`).
- Checks out the code at that **tagged commit** and deploys the plugin with slug `scorefix`.

It does not replace `main.yml`: the branch should already be green before you tag.

---

## Recommended release flow

1. **Bump `VERSION`** to the new number (e.g. `1.0.7`).
2. **Add** the `readme.txt` changelog section `= 1.0.7 =` with your notes.
3. Run **`php bin/sync-version.php`** and review the diff (`scorefix.php`, `readme.txt`).
4. **Commit** (including `VERSION` as needed) and **push** to `main` (or merge via PR). Confirm **WP Plugin Checks** (`main.yml`) passes.
5. **Create and push a Git tag** matching `VERSION`, with a `v` prefix:
   - If `VERSION` is `1.0.7`, the tag must be **`v1.0.7`**.
   - The tag must point at the commit that already contains the updated files.

   Example from a terminal:

   ```bash
   git tag -a v1.0.7 -m "Release 1.0.7"
   git push origin v1.0.7
   ```

   You can also create a **GitHub Release** in the UI using the same tag `v1.0.7`.

6. After the tag push, **Deploy to WordPress.org** (`deploy.yml`) runs. Check the *Actions* tab for a green job.

### Tag vs plugin version

WordPress.org reads the version from the plugin (`readme.txt` / main file header). The `v*` tag is Git/workflow convention only; it **must match** the same release as `VERSION` and `Stable tag`, so you do not ship a zip labeled differently from the tag you think you released.

---

## Quick reference

| Step | Action |
|------|--------|
| Version in repo | Edit `VERSION` + `readme.txt` changelog |
| Sync files | `php bin/sync-version.php` |
| PHP quality | Push/PR → `main.yml` |
| Publish to WordPress.org | Push tag `vX.Y.Z` → `deploy.yml` |

If deploy fails, check SVN secrets, plugin permissions on WordPress.org, and the job logs in GitHub Actions.
