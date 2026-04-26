=== CiteWP ===
Contributors: citewp
Tags: ai, llm, geo, llms.txt, gptbot, claudebot, perplexity, generative engine optimization
Requires at least: 6.5
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 0.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Generative Engine Optimization for WordPress. Detect AI crawlers, generate llms.txt, and score content for AI citability.

== Description ==

CiteWP is a Generative Engine Optimization (GEO) toolkit for WordPress. As AI search engines like ChatGPT, Claude, and Perplexity reshape how people find information, CiteWP helps your content get cited.

= Free Features =

* AI crawler detection for 40+ bots (GPTBot, ClaudeBot, PerplexityBot, Google-Extended, Applebot-Extended, and more)
* Detailed crawler activity logs with user agent, IP, and URL
* 7-day log history

= Coming Soon =

* llms.txt auto-generator
* Per-post GEO Score in the editor
* Citation tracking across major AI engines (Pro)
* Competitor monitoring (Business)

== Installation ==

1. Upload the `citewp` folder to `/wp-content/plugins/`
2. Activate through the 'Plugins' menu in WordPress
3. Navigate to **CiteWP > Crawler Logs** to start seeing AI bot activity

== Changelog ==

= 0.2.0 =
* Added llms.txt and llms-full.txt dynamic generation per llmstxt.org spec.
* Tiered content selection: pages, cornerstone content, recent quality posts.
* Yoast / Rank Math / AIOSEO integration for cornerstone detection and meta descriptions.
* Settings page with content selection controls and manual cache regeneration.

= 0.1.0 =
* Initial scaffold: AI crawler detection + admin logs page.
