=== CiteWP AI Search Optimizer – Optimize Content for AI Engines ===
Contributors: bradleyswpc
Tags: ai, ai-seo, ai-search, llm, llms-txt
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 8.0
Stable tag: 0.7.8
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

1. Cite Score in the WordPress editor — a transparent 100-point AI citation readiness score with category breakdown (Structure, Citability, Authority) and signal-by-signal recommendations, shown live in the editor sidebar.
2. The CiteWP Dashboard — site-wide Cite Score health, AI bot visit totals, indexed-page coverage, top AI crawlers, and posts needing attention, all at a glance.
3. Cite Score in every editor — the universal meta box brings the per-post Cite Score, category breakdown, AI bot visits, and llms.txt controls to the Classic Editor and page builders, not just Gutenberg.
4. Crawler Logs — AI crawler activity across your site with total crawls, unique bots, pages crawled, a Bot Visits Over Time chart, top crawled pages, bot-type and date-range filters, and CSV export.
5. The Cite Score page — site-wide scoring overview with top crawler, optimization coverage, schema coverage, a score health gauge, category breakdown, and AI-powered recommendations.

== Changelog ==

= 0.7.8 =
* Schema detection — emitter-agnostic FAQPage and Article detection via rendered-page JSON-LD (template_redirect cache + sync self-request on Recalculate). Credits schema from Rank Math, Yoast, AIOSEO, and hand-rolled wp:html blocks equally.
* FAQPage signal — 0/8 false-negative fixed; json_decode_tolerant() handles hand-crafted JSON-LD with literal CR/LF in string values.
* Article/Schema signal — SEO plugin proxy (3/6 for "plugin installed") replaced with real rendered-page detection; full 6/6 credit for any valid emitter.
* Flag-don't-inject — CiteWP no longer offers to insert FAQPage schema when valid FAQPage already exists on the rendered page, preventing Rich Result clobbering.

= 0.7.7 =
* Cite Score page — 'Excluded from llms.txt' pill on per-post table rows for posts excluded from llms.txt generation.
* Cite Score Over Time chart — sparse-data state shown when the selected window has fewer than 3 recorded data points.
* Left rail — 'Request a Feature' link below the Pro card.

= 0.7.6 =
First public release on WordPress.org.

* Cite Score — transparent 100-point AI citation readiness score across 17 signals in three categories (Structure, Citability, Authority). Full rubric published at https://citewp.com/cite-score with research citations for every weight.
* AI Crawler Tracking — log and filter visits from GPTBot, ClaudeBot, PerplexityBot, and 40+ other AI bots across 19 vendors. Bot-type and date-range filters, summary stats, CSV export.
* llms.txt Generation — automatic llms.txt and llms-full.txt with cornerstone content prioritization. Integrates with Yoast SEO, Rank Math, SEOPress, and AIOSEO.
* Cite Score in every editor — Gutenberg sidebar with per-signal drilldown and on-demand recalculation; universal meta box brings the Cite Score and schema suggestions to Classic Editor, Elementor, Divi, Beaver Builder, and Bricks.
* Schema Suggestions — one-click Article and FAQPage JSON-LD generation with per-post schema type selection. FAQ detection recognizes accordions from Kadence, Elementor, Divi, Beaver Builder, Bricks, Spectra, and HTML5 details/summary.
* Cite Score dashboard — site-wide score, score-over-time trend, per-post score table, AI recommendations, and schema coverage.
* Crawler Logs page — Bot Visits Over Time and Top Crawled Pages, with date-range-aware KPIs.
* Per-post llms.txt control — exclude individual posts or pages from llms.txt; excluded content is also dropped from aggregate score metrics.
* Dashboard widget — average Cite Score, top crawled pages, and AI bot activity at a glance.
