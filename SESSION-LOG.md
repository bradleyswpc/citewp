# CiteWP — Session Log

> Per-session record of what shipped, what broke, what carried over, and what's next.
> Follow `Desktop\CiteWP\Brain\11-SESSION-PROTOCOL.md` for entry format.
> Most recent session at the top.

---

## Session 3 — GEO Score (Engine + REST + Sidebar + Post Column) ✅

**Date:** 2026-04-26

**Deliverable:** Per-post GEO Score visible in Gutenberg sidebar with sub-category drilldown, and as a sortable colored column on the post list.

**Shipped:**
- `includes/Scoring/SignalResult.php` — per-signal data structure
- `includes/Scoring/ScoreResult.php` — full result data structure
- `includes/Scoring/ContentAnalysis.php` — parses post content into queryable structures
- `includes/Scoring/Engine.php` — runs 17 signals, produces 100-point result ⚠️ NO-TOUCH
- `includes/Scoring/Repository.php` — post meta persistence + `save_post` hook
- `includes/Rest/ScoreController.php` — `/wp-json/citewp/v1/score/{id}` GET + `/recalculate` POST
- `includes/Admin/PostListColumn.php` — sortable colored column on All Posts/Pages
- `includes/Admin/EditorAssets.php` — enqueues built sidebar JS in editor
- `src/sidebar/index.js` — React sidebar (total + 3 categories + signal drilldown + recalculate button)
- `package.json` — npm + `@wordpress/scripts` build pipeline

**Modified:**
- `citewp.php` — version bumped to 0.3.0
- `includes/Plugin.php` — wired Scoring + Rest + PostListColumn + EditorAssets into `boot()`
- `readme.txt` — 0.3.0 changelog entry
- `.gitignore` — added `build/` and `node_modules/`

**Decisions made (logged in `Brain/08-DECISION-LOG.md`):**
- A11: Scoring engine in PHP, not JS (single source of truth, REST-served to React)
- A12: On-save scoring, not real-time per keystroke
- P9: 100-point system, 3 categories (Structure 35 / Citability 40 / Authority 25), 17 signals
- P10: Both Gutenberg sidebar AND post list column
- P12: Default sidebar discoverability via kebab menu (no auto-pin)
- S6: Public scoring rubric as differentiator
- X1: Two CLAUDE.md files (strategy + code)
- X2: SESSION-LOG.md inside plugin folder

**New canonical doc:** `Brain/12-SCORING-RUBRIC.md` — full rubric specification.

**Verified:**
- "Hello world!" default post scored 16/100 red — math behaving correctly.
- Sidebar registers in editor (kebab menu → Panels → CiteWP GEO Score; star to pin).
- Category expansion shows per-signal pass/partial/fail with messages and recommendations.
- Recalculate button refreshes score.
- Save → auto-recalculates.
- Post list shows colored sortable score column.
- `npm run build` succeeded after installing `@wordpress/icons`.
- 23 PHP files lint clean.

**Carryover into Session 4:**
- None.

**Next session focus:** Session 4 — Dashboard widget (avg GEO score, top crawled pages, bot visit trend, link to logs).

---

## Session 2 — llms.txt Auto-Generator ✅

**Date:** 2026-04-26

**Deliverable:** `/llms.txt` and `/llms-full.txt` served dynamically by WordPress with content selection logic.

**Shipped:**
- `includes/Llms/ContentSelector.php` — tiered selection (Pages → cornerstone → recent quality posts → opted-in CPTs)
- `includes/Llms/Generator.php` — builds llms.txt content per llmstxt.org spec
- `includes/Llms/Cache.php` — 1-hour transient with smart invalidation hooks
- `includes/Llms/Router.php` — registers rewrite rules and serves dynamic content
- `includes/Settings/Page.php` — admin UI for llms.txt configuration

**Modified:**
- `citewp.php` — version bumped to 0.2.0
- `includes/Plugin.php` — wired Llms + Settings modules into `boot()`
- `readme.txt` — 0.2.0 changelog entry

**Decisions made (logged in `Brain/08-DECISION-LOG.md`):**
- A10: llms.txt via rewrite rule, not physical file in webroot
- P11: Tiered content selection with smart defaults

**Verified:**
- `http://citewp-dev.local/llms.txt` serves valid output.
- `http://citewp-dev.local/llms-full.txt` serves expanded content version.
- SEO plugin integration confirmed (Yoast/Rank Math/AIOSEO meta detection).
- Cache busts on post publish/update.
- Manual "Regenerate" button works.

**Carryover into Session 3:**
- None.

---

## Session 1 — Plugin Scaffold + Crawler Detection ✅

**Date:** 2026-04-26

**Deliverable:** Plugin activates cleanly. AI bot visits to the site are detected and logged. Admin page displays the log.

**Shipped:**
- `citewp.php` — main plugin file with PSR-4 autoloader
- `includes/Plugin.php` — singleton orchestrator
- `includes/Database/Schema.php` — `citewp_crawler_logs` custom table with proper indexes
- `includes/Crawler/BotRegistry.php` — 41 AI bot signatures (GPTBot, ClaudeBot, PerplexityBot, Google-Extended, Applebot-Extended, etc.)
- `includes/Crawler/Detector.php` — UA matching + DB logging on `init` action
- `includes/Admin/Menu.php` — top-level CiteWP admin menu
- `includes/Admin/LogsPage.php` + `LogsTable.php` — paginated/sortable logs
- `uninstall.php` — drops table + options on plugin delete
- `readme.txt` — WP.org listing draft
- `.gitignore`

**Decisions made (logged in `Brain/08-DECISION-LOG.md`):**
- A6: PSR-4 namespaced autoloader (NOT flat-file convention from original Build Plan)
- A7: Singleton pattern for `Plugin` orchestrator
- A8: GMT timestamps in DB, local time at render
- A9: No proxy IP detection yet — REMOTE_ADDR only
- A13: LocalWP over wp-env for dev environment
- P8: Project name "CiteWP", slug `citewp`
- S5: citewp.com primary, citewp.ai redirect (transferring to Cloudflare)

**Environment setup completed:**
- LocalWP site created (`citewp-dev`, PHP 8.2.30, MySQL 8.0, Nginx)
- GitHub repo created (`bradleyswpc/citewp`, private)
- Git authenticated, pushed initial commits
- Node.js + npm verified installed

**Verified:**
- Plugin activates without errors.
- `wp_citewp_crawler_logs` table created.
- `curl.exe -A "GPTBot/1.0" http://citewp-dev.local/` produces a row in admin logs page.
- 3 of 3 test bots logged successfully (GPTBot, ClaudeBot, PerplexityBot).

**Carryover into Session 2:**
- None.

---

*Add new sessions ABOVE this line. Newest first.*
