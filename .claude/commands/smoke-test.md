---
description: Run the 10-step pre-submission smoke test (the same one that passed 10/10 at end of Session 7.5).
---

# Smoke Test - 10 Steps

This is the same test that passed 10/10 at the end of Session 7.5 after the multi-product architecture refactor. Run it before any release, before WP.org submission, and any time a non-trivial change touches plugin bootstrap, DB schema, REST routes, hooks, asset enqueue, or uninstall.

**Working directory for all commands:**
```
C:\Users\KingpinBWP\Local Sites\citewp-dev\app\public\wp-content\plugins\ai-search-optimizer
```

**Site URL:** http://citewp-dev.local

**Critical (X6):** Do NOT use the live source folder for the uninstall test in Step 10. Use one of:
- `git archive --format=zip --output=../test.zip HEAD` then upload the zip via WP Admin
- Move the live folder out of `plugins/` temporarily before the test
- Use a second LocalWP site dedicated to destructive testing

WP core's `delete_plugins()` is a recursive delete that combines badly with Windows file locks on `node_modules/` and reliably corrupts `.git/`.

---

## 1. Plugin activates with new display name

- Plugins page shows: "AI Search Optimizer - Optimize Content for AI Engines"
- Activation succeeds with no fatal errors
- Check `debug.log` - no new errors

## 2. DB table created on activation

Expected table: `wp_citewp_aiso_crawler_logs`

Verify via Adminer (LocalWP) or:

```sql
SHOW TABLES LIKE 'wp_citewp_aiso_%';
```

Should return `wp_citewp_aiso_crawler_logs`.

## 3. Three options created with citewp_aiso_* prefix

```sql
SELECT option_name FROM wp_options WHERE option_name LIKE 'citewp_aiso_%';
```

Expected:
- `citewp_aiso_settings`
- `citewp_aiso_llms_settings`
- `citewp_aiso_db_version`

## 4. Gutenberg sidebar - score displays + recalculate works

- Open any post in the editor
- Open the sidebar via kebab menu -> Plugins -> AI Search Optimizer
- Score renders with sub-categories (Structure / Citability / Authority)
- Click "Recalculate" - score refreshes
- Save the post - score auto-recalculates

## 5. llms.txt + llms-full.txt serve correctly

```
http://citewp-dev.local/llms.txt
http://citewp-dev.local/llms-full.txt
```

Both return valid content. Cache-Control header set. No 404.

## 6. Crawler detection logs to new table

```powershell
# PowerShell open at any directory
curl.exe -A "GPTBot/1.0" http://citewp-dev.local/
```

Then check the Crawler Logs admin page or:

```sql
SELECT * FROM wp_citewp_aiso_crawler_logs ORDER BY id DESC LIMIT 5;
```

The `GPTBot/1.0` request should be there with vendor mapping.

## 7. Dashboard widget renders

- WP Admin home (`wp-admin/index.php`)
- Widget shows: avg score, bot visit trend (7d vs prior 7d), top 5 crawled URIs, lowest 5 scored posts

## 8. Crawler Logs admin page - filters + CSV

- Open: WP Admin -> CiteWP -> Crawler Logs
- 24h / 7d / 30d stats banner renders
- Bot type filter dropdown narrows results
- Date range filter narrows results
- "Export CSV" downloads a valid file with current filters applied

## 9. REST endpoint registered

```
http://citewp-dev.local/wp-json/citewp/aiso/v1/score/1
```

In a fresh browser tab (no nonce), should return a `citewp_aiso_forbidden` error code. That's the correct behavior - confirms the route is registered AND the error code rename landed.

From the editor (with nonce), `apiFetch` to the same path returns the cached score.

## 10. uninstall.php cleanup - DESTRUCTIVE TEST, USE A COPY

Per X6: do NOT run this against the live source folder.

Setup:
```
PowerShell open at:
  C:\Users\KingpinBWP\Local Sites\citewp-dev\app\public\wp-content\plugins\ai-search-optimizer

git archive --format=zip --output=../ai-search-optimizer-uninstall-test.zip HEAD
```

Then in WP Admin: Plugins -> Add New -> Upload Plugin -> upload the zip -> activate -> deactivate -> delete.

After delete, verify cleanup:

```sql
SELECT option_name FROM wp_options WHERE option_name LIKE 'citewp_aiso_%';
SELECT meta_key FROM wp_postmeta WHERE meta_key LIKE '\_citewp\_aiso\_%' LIMIT 1;
SHOW TABLES LIKE 'wp_citewp_aiso_%';
```

All three should return zero rows. Cron events `citewp_aiso_*` should also be gone.

---

## Pass / Fail summary

Report each step as PASS or FAIL with a one-line reason. If anything fails, do not skip to the next step - fix or document before continuing.
