=== CiteWP AI Search Optimizer – Optimize Content for AI Engines ===
Contributors: bradleyswpc
Tags: seo, ai seo, llms-txt, chatgpt, rankmath
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 8.0
Stable tag: 0.7.10
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

SEO gets you ranked. CiteWP gets you cited. AI crawler tracking, llms.txt generation, and a 100-point citation readiness score — free.

== Description ==

AI Search Optimizer helps your WordPress content get cited by AI search engines.

Traditional SEO optimizes for ranking in search results. AI Search Optimizer focuses on a different goal: getting your content referenced inside AI-generated answers from ChatGPT, Claude, Perplexity, and other AI engines.

= What's included =

✅ **Cite Score** — a transparent 100-point score across 17 signals (Structure, Citability, Authority). Full rubric at citewp.com/cite-score — no black box.

✅ **AI Crawler Tracking** — see which AI bots visit your site, what they request, and which content gets attention. Filter by bot, export to CSV.

✅ **llms.txt Generation** — auto-generate llms.txt and llms-full.txt from your most important content. Respects Yoast, Rank Math, SEOPress, and AIOSEO settings.

✅ **Gutenberg Sidebar** — Cite Score and signal-by-signal breakdown live in the editor as you write. No context switching.

✅ **Post List Column** — sortable Cite Score column in All Posts and All Pages. Spot weak content at a glance.

✅ **Dashboard Widget** — site-wide Cite Score average, top crawled pages, and AI bot trends at a glance.

= Who this is for =

✅ **Bloggers and content publishers** — your audience is already asking AI for recommendations. Be the source they cite.

✅ **WordPress agencies and freelancers** — measure AI citation readiness across client sites alongside traditional SEO metrics.

✅ **DIY founders and small-business owners** — own your WordPress site, want to be found by AI search? Start here.

✅ **SaaS companies and documentation sites** — AI engines cite authoritative reference content. Measure whether yours qualifies.

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

= 0.7.10 =
* Improvement: AI Recommendations cards now group by signal and post type — Posts and Pages are shown as separate, targeted cards.
* Improvement: Cards ranked by recoverable Cite Score points so the highest-impact fixes surface first.
* Fix: "View N Posts/Pages" button now opens a curated list showing exactly those N affected items — not the full dashboard.
* Fix: Card count, button count, and filter-banner count now always agree (count-match contract restored).
* Fix: Recommendation filter correctly scopes to published content only, excluding llms.txt-opted-out posts.
* Improvement: Empty state shows a positive message when all content is fully optimized.
* Improvement: Statistics density scoring uses a raw-count floor for more consistent scoring across content lengths.
* Improvement: Named entity detection extended to CamelCase brand names (ChatGPT, WordPress, SEMrush, etc.).

= 0.7.9 =
* Schema detection now reads rendered page output, crediting FAQPage and Article schema from any emitter (Rank Math, Yoast, AIOSEO, hand-rolled JSON-LD) equally.
* Schema generation moved from post content insertion to head injection (wp_head), eliminating wpautop corruption and improving compatibility with caching plugins.
* FAQ and Article signals now score independently — Article signal credits validated Article schema only, not generic SEO-plugin presence.
* FAQ extractor fixes: CSS leak in text extraction, br-tag word-fusion, and whitespace handling.
* Tolerant JSON-LD parsing handles hand-crafted schema with embedded line breaks.
* Unicode handling fix — em-dashes and other escaped Unicode characters now render correctly in injected schema.
* Removed block-label as a schema-generation trigger to prevent overwriting existing user-authored schema.
* New REST endpoint POST /citewp/aiso/v1/schema/[id]/inject for Insert/Remove with conflict guard.
* AI Recommendations card on the Cite Score page now excludes llms.txt-opted-out posts from per-signal counts and card visibility, matching the rest of the dashboard's exclusion behavior.

= 0.7.8 =
* Schema detection — emitter-agnostic rendered-output detection via template_redirect full-page cache and sync self-request on Recalculate. Credits schema from Rank Math, Yoast, AIOSEO, and hand-rolled wp:html blocks equally.
* FAQ/Article signal independence — Article/Schema signal gates on Article-type validation, not presence of any schema type; FAQPage-only detections credit the FAQ signal only. Schema detection cache survives content edits and clears on post status transitions only (not every save).
* json_decode_tolerant — hand-crafted JSON-LD with literal CR/LF in string values (accepted by Google Rich Results, rejected by PHP's strict parser) now parsed correctly.
* Flag-don't-inject — CiteWP no longer offers to insert FAQPage schema when a valid FAQPage already exists on the rendered page, preventing Rich Result clobbering. Removes the Kadence block-label auto-generation trigger that caused duplicate FAQPage schema insertion.
* FAQ extraction fix — inline CSS from Kadence accordion blocks no longer leaks into acceptedAnswer.text; clean_text() strips style/script noise nodes before extracting text content.
* Head injection — Schema Suggestions Insert/Remove now stores generated schema in post meta and emits via wp_head, replacing block-editor insertion. Eliminates wpautop corruption of JSON-LD in post_content. Insert and Remove buttons backed by REST endpoint with detect-before-inject conflict guard.
* Schema storage — injected schema stored as serialized PHP array instead of JSON string, preventing wp_unslash from corrupting Unicode escapes (e.g. em-dash rendered as 'u2014').
* FAQ word-fusion fix — clean_text() replaces br elements with a space text node before reading textContent, preventing adjacent words from fusing at line breaks.

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
