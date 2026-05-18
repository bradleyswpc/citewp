=== CiteWP AI Search Optimizer ===
Contributors: bradleyswpc
Tags: ai, ai-seo, ai-search, llm, llms-txt
Requires at least: 6.5
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 0.6.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Optimize WordPress content for AI search. AI crawler tracking, llms.txt generation, and a transparent 100-point Cite Score.

== Description ==

AI Search Optimizer helps your WordPress content get cited by AI search engines.

Traditional SEO optimizes for ranking in search results. AI Search Optimizer focuses on a different goal: getting your content referenced inside AI-generated answers from ChatGPT, Claude, Perplexity, and other AI engines.

= What's included =

**Cite Score** — a transparent 100-point score showing how likely your content is to be cited by AI engines. Calculated across 17 signals in three categories: Structure, Citability, and Authority. The full scoring rubric is published — no black box.

**AI Crawler Tracking** — see which AI bots visit your site, when, and what they request. Filter by bot, export to CSV, and identify which content AI is paying attention to.

**llms.txt Generation** — automatically generate llms.txt and llms-full.txt files using your most important content. Integrates with Yoast SEO, Rank Math, SEOPress, and AIOSEO to respect your existing settings.

**Gutenberg Sidebar** — see your Cite Score and signal-by-signal breakdown directly in the WordPress editor while you write. No context switching.

**Post List Column** — sortable Cite Score column in All Posts and All Pages. Spot weak content at a glance.

**Dashboard Widget** — average Cite Score across your site, top crawled pages, AI bot trends.

= Why this matters =

AI engines now influence a meaningful share of search behavior. Your content can rank well on Google and still be invisible to AI assistants. AI Search Optimizer measures and improves your content's likelihood of being cited.

= About the Cite Score =

The Cite Score is the heart of this plugin. Unlike competitors with proprietary "AI scoring" formulas, our rubric is fully public. The 17 signals are documented at https://citewp.com/cite-score, with research citations for every weight.

= Built for the future =

This plugin is built and maintained by **CiteWP**. Visit citewp.com for documentation, blog posts on AI search optimization, and product updates.

== Installation ==

1. Upload the plugin to /wp-content/plugins/, or install via Plugins → Add New.
2. Activate.
3. AI crawler tracking and llms.txt generation start automatically.
4. Open any post or page to see the Cite Score in the editor sidebar.

== Frequently Asked Questions ==

= Does this replace my SEO plugin? =

No. AI Search Optimizer complements Yoast SEO, Rank Math, SEOPress, and AIOSEO. Keep your SEO plugin for traditional search; add this for AI search.

= Is the Cite Score formula public? =

Yes. The full 100-point rubric is published at https://citewp.com/cite-score with research citations for every signal. Unlike competitor "AI scores," there's no black box.

= Does this work with WooCommerce? =

Cite Score and llms.txt generation work with any post type, including WooCommerce products. Crawler tracking is site-wide.

= What's an llms.txt file? =

It's an emerging standard that helps AI engines understand your site's most important content. AI Search Optimizer generates it automatically, similar to how SEO plugins generate sitemap.xml.

== Screenshots ==

1. Cite Score in the Gutenberg sidebar — 100-point score with category breakdown (Structure, Citability, Authority) and per-signal drilldown. Recalculate on demand.
2. Sortable Cite Score column on the All Posts screen — color-coded red/orange/yellow/green so weak content stands out at a glance.
3. WordPress Dashboard widget — site-wide average Cite Score, top crawled pages, and AI bot visit trends.
4. Crawler Logs — AI bot visit log with bot type filter, date range filter, 24h/7d/30d summary stats, and CSV export.
5. llms.txt Settings — configure which content appears in your llms.txt and llms-full.txt files.

== Development ==

CiteWP AI Search Optimizer is open source under GPL v2 or later.

* Source code repository: https://github.com/bradleyswpc/citewp
* Source for this release: https://github.com/bradleyswpc/citewp/tree/v0.6.3
* Un-minified JavaScript source: src/sidebar/index.js
* Build tooling: webpack via @wordpress/scripts
* The compiled build/index.js in this plugin is generated from src/sidebar/index.js
* To build from source: `npm install && npm run build`
* Contributions welcome via pull requests at the repository above.

== Changelog ==

= 0.6.3 =
* Renamed plugin slug to citewp-ai-search-optimizer per WordPress.org plugin naming guidance (Round 3 review).
* Plugin display name shortened to "CiteWP AI Search Optimizer" — brand-first naming pattern.
* No functional changes from 0.6.2.

= 0.6.2 =
* Fix: Inline sanitize_text_field() on $_SERVER inputs in crawler detection (REMOTE_ADDR, REQUEST_URI, HTTP_REFERER).
* Fix: WHERE clause fragments in admin crawler logs query now built via $wpdb->prepare() instead of raw concatenation.
* Docs: Development section in readme expanded with release-tagged source links for easier review verification.

= 0.6.1 =
* Fix: Inline CSS converted to properly enqueued assets per WordPress.org guidelines
* Fix: Added Development section to readme with source code repository reference

= 0.6.0 =
* Initial WordPress.org release.
* Cite Score: transparent 100-point AI citation score with 17 signals across Structure, Citability, and Authority categories.
* AI Crawler Tracking: log and filter visits from GPTBot, ClaudeBot, PerplexityBot, and 38+ other AI bots.
* llms.txt and llms-full.txt generation with cornerstone content prioritization.
* Gutenberg sidebar with category drilldown.
* Sortable Cite Score column on Posts and Pages.
* Dashboard widget with site-wide score, top crawled pages, and bot activity trends.
* Integrations with Yoast SEO, Rank Math, SEOPress, and AIOSEO.
