=== Cite Score — AI Search Optimization ===
Contributors: citewp
Tags: ai, seo, llms, gptbot, generative engine optimization
Requires at least: 6.5
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 0.5.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Generative Engine Optimization for WordPress. Detect AI crawlers, generate llms.txt, and score content for AI citability.

== Description ==

Cite Score is a Generative Engine Optimization (GEO) toolkit for WordPress. As AI search engines like ChatGPT, Claude, and Perplexity reshape how people find information, Cite Score helps your content get cited — not just ranked.

= Features =

**AI Crawler Detection**

* Detects 40+ AI bots by user agent (GPTBot, ClaudeBot, PerplexityBot, Google-Extended, Applebot-Extended, and more)
* Logs every AI visit with user agent, IP, URL, and timestamp
* Configurable log retention (7 days by default)
* Filter logs by bot type or date range
* Export logs to CSV
* Dashboard widget showing visit trends

**llms.txt Auto-Generator**

* Dynamically serves `/llms.txt` and `/llms-full.txt` per the llmstxt.org spec
* Tiered content selection: Pages → cornerstone content → recent quality posts → custom post types
* Integrates with Yoast SEO, Rank Math, and AIOSEO for cornerstone detection and meta descriptions
* 1-hour smart cache with automatic invalidation on publish/update

**GEO Score**

* 100-point content scoring system across 3 categories: Structure (35pts), Citability (40pts), Authority (25pts)
* 17 individual signals based on 2026 GEO research (statistics density, entity count, FAQ schema, E-E-A-T, and more)
* Visible in the Gutenberg sidebar with expandable per-signal recommendations
* Sortable GEO Score column on All Posts and All Pages screens
* Auto-recalculates on every save

= Why Cite Score? =

SEO gets you ranked. Cite Score gets you cited.

Traditional SEO optimizes for keyword matching. GEO optimizes for AI citation — the signals that cause ChatGPT, Perplexity, and Google AI Overviews to quote your content as a source.

= Privacy =

CiteWP logs AI crawler visits (user agent, IP address, URL) to your own WordPress database. No data is sent to external servers. All processing happens locally.

== Installation ==

1. Upload the `citewp` folder to `/wp-content/plugins/`
2. Activate through the **Plugins** menu in WordPress
3. Navigate to **Cite Score > Crawler Logs** to see AI bot activity
4. Open any post in the block editor and find **GEO Score** in the sidebar panels

== Frequently Asked Questions ==

= Does Cite Score send my content to external servers? =

No. All analysis and scoring happens locally on your WordPress installation. No content or personal data leaves your server.

= Which AI bots does Cite Score detect? =

40+ bots including GPTBot (OpenAI), ClaudeBot (Anthropic), PerplexityBot, Google-Extended, Applebot-Extended, Meta-ExternalAgent, Bytespider, and more.

= Does the GEO Score guarantee my content will be cited by AI? =

No tool can guarantee AI citations. The GEO Score reflects the structural and content signals that research shows correlate with AI citation frequency.

= Will CiteWP slow down my site? =

No. Crawler detection adds zero latency to human visitors — AI bots are identified by user agent only, with no content scanning. The GEO Score is calculated on save, not on every page load.

== Changelog ==

= 0.5.0 =
* Renamed plugin display name to "Cite Score — AI Search Optimization" (WP.org trademark compliance).
* Raised "Tested up to" to WordPress 6.9.
* CSV export: replaced direct filesystem calls with output streaming (WP.org Plugin Check compliance).
* Crawler detector: hardened sanitization on all `$_SERVER` inputs.
* Plugin Check compliance: phpcs annotations on all direct DB queries against custom tables.

= 0.4.0 =
* Added WordPress Dashboard widget (avg GEO score, bot visit trend, top crawled pages, lowest-scoring posts).
* Crawler Logs: summary stats banner (24h / 7d / 30d visit counts).
* Crawler Logs: bot type filter and date range filter.
* Crawler Logs: CSV export with active filters applied.
* Settings: minor UI polish.
* Security: completed full security audit; all output escaped, all queries prepared.
* Added LICENSE file (GPL v2).

= 0.3.0 =
* Added GEO Score: 100-point scoring across Structure (35), Citability (40), and Authority (25) categories.
* 17 individual signals based on 2026 GEO research (statistics density, entity count, FAQ schema, E-E-A-T, etc.).
* Gutenberg sidebar with expandable category breakdown and per-signal recommendations.
* Sortable GEO Score column on All Posts and All Pages screens.
* REST API at /citewp/v1/score/ for score retrieval and recalculation.
* Auto-recalculation on every save_post.

= 0.2.0 =
* Added llms.txt and llms-full.txt dynamic generation per llmstxt.org spec.
* Tiered content selection: pages, cornerstone content, recent quality posts.
* Yoast / Rank Math / AIOSEO integration for cornerstone detection and meta descriptions.
* Settings page with content selection controls and manual cache regeneration.

= 0.1.0 =
* Initial release: AI crawler detection and admin logs page.
