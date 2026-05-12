# Session 26 — FAQ Detection v2, Schema Signal Grading, 3-State Message, v0.7.1

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rewrite FAQ detection to use DOMDocument (catches page-builder accordions), restore incremental schema scoring (6/3/0 instead of binary 6/0), update the Schema Suggestions panel to reflect real FAQ state in 3 messages, and bump the plugin to v0.7.1.

**Architecture:** `Generator::extract_faq_pairs()` is replaced with a DOMDocument-based implementation that detects 4 HTML patterns (headings, `<details>/<summary>`, WAI-ARIA `role="button"`, CSS-class fallback). A new private `get_faq_pairs()` method applies the X15 extensibility filter (`citewp_aiso/schema/faq_pairs`). A new public `count_faq_pairs()` method feeds a new `faq_count` field added to the REST schema response. The sidebar uses `faq_count` to render one of 3 messages. `Engine::check_schema()` becomes a 3-state graded signal (6 pts inline schema / 3 pts SEO plugin only / 0 pts nothing).

**Tech Stack:** PHP 8.0+, WordPress 6.5+, DOMDocument + DOMXPath, React via @wordpress/scripts, `@wordpress/components`, Sass (style.scss).

**Confirmed decisions (gates cleared before plan write):**
- Scoring values: 6 / 3 / 0 confirmed (A11 gate)
- Version bump: 0.7.0 → 0.7.1 confirmed
- X15 filter: `apply_filters('citewp_aiso/schema/faq_pairs', $pairs, $post)` confirmed

---

## File Map

| File | Change |
|---|---|
| `tests/fixtures/faq-detection/` | **Create** — 8 HTML fixture files for manual verification |
| `includes/Schema/Generator.php` | **Modify** — rewrite `extract_faq_pairs()`, add `get_faq_pairs()`, `count_faq_pairs()`, 4 private helpers, memoize `analysis_for()` |
| `includes/Rest/SchemaController.php` | **Modify** — add `faq_count` field to REST response |
| `src/sidebar/index.js` | **Modify** — 3-state FAQ message, `statusText` prop on `SchemaTypeRow` |
| `src/sidebar/style.scss` | **Modify** — add `.citewp-aiso-sidebar-schema-row__status-text` rule |
| `includes/Scoring/Engine.php` | **Modify** — `check_schema()` 3-state graded (6/3/0) |
| `includes/Admin/RecommendationMapper.php` | **Modify** — update `schema` copy to cover partial state |
| `ai-search-optimizer.php` | **Modify** — version header 0.7.0 → 0.7.1 |
| `readme.txt` | **Modify** — changelog entry for 0.7.1 |

**SignalResult.php: NO CHANGE NEEDED.** Pre-flight confirmed it already supports `int $score`, `int $max`, `string $status ('pass'|'partial'|'fail')`. A 3-pt partial state needs no class extension.

---

## Task 1: HTML Fixture Files

**Files:**
- Create: `tests/fixtures/faq-detection/01-heading-basic.html`
- Create: `tests/fixtures/faq-detection/02-details-summary.html`
- Create: `tests/fixtures/faq-detection/03-kadence-accordion.html`
- Create: `tests/fixtures/faq-detection/04-elementor-accordion.html`
- Create: `tests/fixtures/faq-detection/05-divi-accordion.html`
- Create: `tests/fixtures/faq-detection/06-beaver-accordion.html`
- Create: `tests/fixtures/faq-detection/07-bricks-accordion.html`
- Create: `tests/fixtures/faq-detection/08-spectra-faq.html`
- Create: `tests/fixtures/faq-detection/09-one-pair-only.html`
- Create: `tests/fixtures/faq-detection/10-no-faq-false-positive-guard.html`

These fixtures represent rendered HTML from each builder. They are loaded into test posts for manual verification at Step 9 of the session protocol.

- [ ] **Step 1: Create fixture directory and the 10 HTML files**

```
tests/fixtures/faq-detection/
```

**01-heading-basic.html** — standard h2/h3 + paragraph:
```html
<h2>What is AI citation optimization?</h2>
<p>AI citation optimization is the practice of structuring your content so AI assistants cite your page when answering relevant questions.</p>
<h2>How does the Cite Score work?</h2>
<p>The Cite Score is a 100-point metric across three categories: Structure, Citability, and Authority.</p>
<h3>Why does structured data help?</h3>
<p>Structured data provides machine-readable context that AI engines use to understand entities on your page.</p>
```

**02-details-summary.html** — HTML5 native accordion:
```html
<div class="faq-section">
  <details>
    <summary>What is CiteWP?</summary>
    <p>CiteWP is a WordPress plugin that helps your content get cited by AI search engines like ChatGPT, Claude, and Perplexity.</p>
  </details>
  <details>
    <summary>How does the scoring work?</summary>
    <p>CiteWP evaluates your content across 17 signals grouped into Structure, Citability, and Authority categories.</p>
  </details>
</div>
```

**03-kadence-accordion.html** — Kadence Blocks (uses `<details>/<summary>` natively):
```html
<div class="wp-block-kadence-accordion">
  <details class="wp-block-kadence-pane kt-accordion-pane-0 kt-accordion-pane">
    <summary class="kt-accordion-header-wrap kt-accordion-header-0">What is AI search optimization?</summary>
    <div class="kt-accordion-panel kt-accordion-panel-0">
      <div class="kt-accordion-panel-inner">
        <p>AI search optimization helps your content appear as a citation in AI-generated answers.</p>
      </div>
    </div>
  </details>
  <details class="wp-block-kadence-pane kt-accordion-pane-1 kt-accordion-pane">
    <summary class="kt-accordion-header-wrap kt-accordion-header-1">Why should I use CiteWP?</summary>
    <div class="kt-accordion-panel kt-accordion-panel-1">
      <div class="kt-accordion-panel-inner">
        <p>CiteWP is the only WordPress plugin that scores content specifically for AI citation readiness.</p>
      </div>
    </div>
  </details>
</div>
```

**04-elementor-accordion.html** — Elementor Toggle widget (WAI-ARIA `role="button"` + `aria-controls`):
```html
<div class="elementor-accordion" role="tablist">
  <div class="elementor-accordion-item">
    <div id="elementor-tab-title-1841" class="elementor-tab-title elementor-active" data-tab="1" role="button" tabindex="0" aria-controls="elementor-tab-content-1841" aria-expanded="true">
      What is AI citation optimization?
    </div>
    <div id="elementor-tab-content-1841" class="elementor-tab-content elementor-clearfix elementor-active" data-tab="1" role="tabpanel" aria-labelledby="elementor-tab-title-1841">
      <p>AI citation optimization is the practice of structuring content so AI assistants cite your pages.</p>
    </div>
  </div>
  <div class="elementor-accordion-item">
    <div id="elementor-tab-title-1842" class="elementor-tab-title" data-tab="2" role="button" tabindex="0" aria-controls="elementor-tab-content-1842" aria-expanded="false">
      How does schema markup help?
    </div>
    <div id="elementor-tab-content-1842" class="elementor-tab-content elementor-clearfix" data-tab="2" role="tabpanel" aria-labelledby="elementor-tab-title-1842">
      <p>Schema markup provides structured data that AI engines parse to understand your content entities.</p>
    </div>
  </div>
</div>
```

**05-divi-accordion.html** — Divi Toggle module (h5 with `role="button"`):
```html
<div class="et_pb_module et_pb_accordion">
  <div class="et_pb_module et_pb_toggle et_pb_toggle_open">
    <h5 class="et_pb_toggle_title" role="button" aria-expanded="true">What is Cite Score?</h5>
    <div class="et_pb_toggle_content clearfix">
      <p>Cite Score is a 100-point metric that measures how ready your content is for AI search citation.</p>
    </div>
  </div>
  <div class="et_pb_module et_pb_toggle et_pb_toggle_close">
    <h5 class="et_pb_toggle_title" role="button" aria-expanded="false">Why does AI citation matter?</h5>
    <div class="et_pb_toggle_content clearfix">
      <p>AI systems increasingly answer questions by citing sources. Being cited means traffic without ranking.</p>
    </div>
  </div>
</div>
```

**06-beaver-accordion.html** — Beaver Builder Accordion module (`role="button"` + `aria-controls`):
```html
<div class="fl-module fl-module-accordion">
  <div class="fl-accordion">
    <div class="fl-accordion-item fl-accordion-item-active">
      <div class="fl-accordion-button" role="button" tabindex="0" aria-expanded="true" aria-controls="fl-accordion-content-1">
        <span class="fl-accordion-button-label">What makes CiteWP different?</span>
      </div>
      <div id="fl-accordion-content-1" class="fl-accordion-content" role="region">
        <div class="fl-accordion-content-inner">
          <p>CiteWP is the only plugin that publishes its full scoring rubric. Every signal is documented and research-backed.</p>
        </div>
      </div>
    </div>
    <div class="fl-accordion-item">
      <div class="fl-accordion-button" role="button" tabindex="0" aria-expanded="false" aria-controls="fl-accordion-content-2">
        <span class="fl-accordion-button-label">Does it work with my SEO plugin?</span>
      </div>
      <div id="fl-accordion-content-2" class="fl-accordion-content" role="region">
        <div class="fl-accordion-content-inner">
          <p>Yes. CiteWP complements Yoast, Rank Math, and AIOSEO — it adds AI citation scoring on top of traditional SEO signals.</p>
        </div>
      </div>
    </div>
  </div>
</div>
```

**07-bricks-accordion.html** — Bricks Builder Accordion element (`role="button"` on title wrapper):
```html
<div class="brxe-accordion">
  <div class="accordion-item">
    <div class="accordion-title-wrapper" role="button" tabindex="0" aria-expanded="true">
      <span class="accordion-title">What is structured data?</span>
      <span class="accordion-toggle-icon"></span>
    </div>
    <div class="accordion-content-wrapper">
      <div class="accordion-content">
        <p>Structured data is machine-readable markup (JSON-LD, Microdata) that tells AI engines what your page is about.</p>
      </div>
    </div>
  </div>
  <div class="accordion-item">
    <div class="accordion-title-wrapper" role="button" tabindex="0" aria-expanded="false">
      <span class="accordion-title">Why does schema markup matter for AI?</span>
      <span class="accordion-toggle-icon"></span>
    </div>
    <div class="accordion-content-wrapper">
      <div class="accordion-content">
        <p>Schema markup gives AI assistants unambiguous entity definitions, increasing citation likelihood.</p>
      </div>
    </div>
  </div>
</div>
```

**08-spectra-faq.html** — Spectra (UAGB) FAQ Block (`role="button"` on question button):
```html
<div class="wp-block-uagb-faq uagb-faq uagb-block-faq uagb-faq--accordion uagb-faq--icon-row">
  <div class="uagb-faq__wrap uagb-faq-icon-support">
    <div class="uagb-faq-child__wrapper">
      <div class="uagb-faq-item" role="tab" aria-expanded="true">
        <div class="uagb-faq-questions-button uagb-faq-icon-row" role="button" tabindex="0">
          <span class="uagb-question">What is AI search optimization?</span>
        </div>
        <div class="uagb-faq-content" role="tabpanel">
          <p>AI search optimization is the discipline of structuring your content so AI assistants cite your pages in generated answers.</p>
        </div>
      </div>
      <div class="uagb-faq-item" role="tab" aria-expanded="false">
        <div class="uagb-faq-questions-button uagb-faq-icon-row" role="button" tabindex="0">
          <span class="uagb-question">How is it different from traditional SEO?</span>
        </div>
        <div class="uagb-faq-content" role="tabpanel">
          <p>Traditional SEO ranks pages in search results. AI search optimization ensures your page is cited as a source in AI-generated answers.</p>
        </div>
      </div>
    </div>
  </div>
</div>
```

**09-one-pair-only.html** — only 1 qualifying Q/A (should return 1 pair, no FAQPage schema):
```html
<h2>What is CiteWP?</h2>
<p>CiteWP is a WordPress plugin for AI citation optimization.</p>
<h2>Our Pricing Plans</h2>
<p>We offer Free, Pro, Business, and Agency tiers.</p>
<h2>About Our Team</h2>
<p>CiteWP is built by content marketing professionals.</p>
```

**10-no-faq-false-positive-guard.html** — topic headings, none qualify as questions:
```html
<h2>Introduction</h2>
<p>AI search is transforming how users find information online.</p>
<h2>The Problem with Traditional SEO</h2>
<p>Traditional SEO focuses on ranking pages in blue-link search results.</p>
<h2>Our Approach</h2>
<p>CiteWP takes a white-hat approach to AI citation optimization.</p>
<h2>Features Overview</h2>
<p>CiteWP includes crawler detection, Cite Score, and schema generation.</p>
```

- [ ] **Step 2: Commit fixtures**

```bash
git add tests/fixtures/faq-detection/
git commit -m "test: add FAQ detection HTML fixtures for 8 builder patterns + edge cases"
```

---

## Task 2: Rewrite `extract_faq_pairs()` in Generator.php

**Files:**
- Modify: `includes/Schema/Generator.php`

**Context:** `extract_faq_pairs()` is a `private` method at line 211. Only caller is `generate_faq_schema()` at line 109. `analysis_for()` is a private method at line 262 — add instance-level memoization cache so calling both `count_faq_pairs()` and `generate_faq_schema()` in one request doesn't double-parse content.

### New method inventory after this task:

| Method | Visibility | Purpose |
|---|---|---|
| `generate_article_schema(\WP_Post)` | public | unchanged |
| `generate_faq_schema(\WP_Post)` | public | updated to call `get_faq_pairs()` |
| `count_faq_pairs(\WP_Post)` | public | **NEW** — returns raw pair count for REST response |
| `detect_existing_types(\WP_Post)` | public | unchanged |
| `get_faq_pairs(\WP_Post)` | private | **NEW** — calls extract + applies X15 filter |
| `extract_faq_pairs(ContentAnalysis)` | private | **REWRITTEN** — DOMDocument 4-pattern detection |
| `first_p_after(\DOMNode)` | private | **NEW** — finds first `<p>` after a heading sibling |
| `details_body(\DOMElement, \DOMElement)` | private | **NEW** — extracts `<details>` body minus `<summary>` |
| `aria_answer(\DOMXPath, \DOMElement)` | private | **NEW** — resolves aria-controls or next sibling |
| `has_ancestor_tag(\DOMNode, string)` | private | **NEW** — walks parent chain looking for tag name |
| `collect_root_types(array, array&)` | private | unchanged |
| `analysis_for(\WP_Post)` | private | **UPDATED** — memoized with `$analysis_cache` |

- [ ] **Step 1: Add `$analysis_cache` property and update `analysis_for()`**

In `Generator.php`, add the cache property just before the first `public function`:

```php
/** @var array<int, ContentAnalysis> Memoization cache — one entry per post ID per request. */
private array $analysis_cache = [];
```

Replace the existing `analysis_for()` method (currently at line 262) with:

```php
private function analysis_for( \WP_Post $post ): ContentAnalysis {
	if ( ! isset( $this->analysis_cache[ $post->ID ] ) ) {
		$this->analysis_cache[ $post->ID ] = new ContentAnalysis( $post );
	}
	return $this->analysis_cache[ $post->ID ];
}
```

- [ ] **Step 2: Add `get_faq_pairs()` private method (X15 filter applier)**

Insert after the `generate_faq_schema()` closing brace (after line ~134):

```php
/**
 * Returns FAQ pairs after applying the X15 extensibility filter.
 *
 * Filter: citewp_aiso/schema/faq_pairs
 * Allows third-party code (e.g. FB29 schema type expansion) to add, remove,
 * or modify detected FAQ pairs before schema generation and pair counting.
 *
 * @param \WP_Post $post
 * @return array<int, array{question: string, answer: string}>
 */
private function get_faq_pairs( \WP_Post $post ): array {
	$pairs = $this->extract_faq_pairs( $this->analysis_for( $post ) );
	/** @var array<int, array{question: string, answer: string}> $pairs */
	return (array) apply_filters( 'citewp_aiso/schema/faq_pairs', $pairs, $post );
}
```

- [ ] **Step 3: Add `count_faq_pairs()` public method**

Insert immediately after `get_faq_pairs()`:

```php
/**
 * Returns the number of FAQ pairs detected in the post content.
 * Used by SchemaController to populate the faq_count field in the REST response,
 * which drives the 3-state message in the Schema Suggestions panel.
 *
 * @param \WP_Post $post
 * @return int
 */
public function count_faq_pairs( \WP_Post $post ): int {
	return count( $this->get_faq_pairs( $post ) );
}
```

- [ ] **Step 4: Update `generate_faq_schema()` to use `get_faq_pairs()`**

Replace the first two lines inside `generate_faq_schema()`:

Old (line 110):
```php
$pairs = $this->extract_faq_pairs( $this->analysis_for( $post ) );
```

New:
```php
$pairs = $this->get_faq_pairs( $post );
```

- [ ] **Step 5: Replace `extract_faq_pairs()` with DOMDocument implementation**

Replace the entire `extract_faq_pairs()` method (lines 211–260) with:

```php
/**
 * Extracts FAQ pairs from rendered post HTML using DOMDocument.
 *
 * Detects 4 patterns:
 *   1. <h2>/<h3>/<h4> followed by first <p> sibling (existing behaviour preserved)
 *   2. <details>/<summary> — HTML5 native + Kadence Blocks
 *   3. Elements with role="button" or aria-expanded — WAI-ARIA accordion pattern
 *      used by Elementor, Divi, Beaver Builder, Bricks, Spectra
 *   4. CSS-class containers (class contains "accordion"/"faq"/"toggle"/"collapse")
 *      — fallback for builders without ARIA roles
 *
 * Note: rendered_html = apply_filters('the_content', post_content). Detects content
 * stored in post_content (Gutenberg/block builders). Elementor/Divi Classic content
 * stored in post_meta may not appear here depending on the_content filter context.
 *
 * @param ContentAnalysis $analysis
 * @return array<int, array{question: string, answer: string}>
 */
private function extract_faq_pairs( ContentAnalysis $analysis ): array {
	$html = trim( $analysis->rendered_html );
	if ( $html === '' ) {
		return [];
	}

	$prev_errors = libxml_use_internal_errors( true );
	$dom         = new \DOMDocument( '1.0', 'UTF-8' );
	$dom->loadHTML(
		'<?xml encoding="utf-8" ?>' . $html,
		LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
	);
	libxml_clear_errors();
	libxml_use_internal_errors( $prev_errors );

	$xpath = new \DOMXPath( $dom );
	$pairs = [];
	$seen  = [];  // dedup by question text
	$q_re  = '/^(how|what|why|when|where|can|should|is|does|do|will)\b/i';

	// ── Pattern 1: h2 / h3 / h4 + first <p> sibling ────────────────────────
	$headings = $xpath->query( '//h2|//h3|//h4' );
	if ( $headings ) {
		foreach ( $headings as $heading ) {
			$q = trim( wp_strip_all_tags( $heading->textContent ) );
			if ( $q === '' || isset( $seen[ $q ] ) ) {
				continue;
			}
			if ( ! preg_match( $q_re, $q ) && ! str_ends_with( $q, '?' ) ) {
				continue;
			}
			$a = $this->first_p_after( $heading );
			if ( $a === '' ) {
				continue;
			}
			$seen[ $q ] = true;
			$pairs[]    = [ 'question' => $q, 'answer' => $a ];
		}
	}

	// ── Pattern 2: <details> / <summary> ────────────────────────────────────
	$details_list = $xpath->query( '//details' );
	if ( $details_list ) {
		foreach ( $details_list as $details ) {
			/** @var \DOMElement $details */
			$summary_list = $xpath->query( 'summary', $details );
			$summary      = $summary_list ? $summary_list->item( 0 ) : null;
			if ( ! $summary instanceof \DOMElement ) {
				continue;
			}
			$q = trim( wp_strip_all_tags( $summary->textContent ) );
			if ( $q === '' || isset( $seen[ $q ] ) ) {
				continue;
			}
			if ( ! preg_match( $q_re, $q ) && ! str_ends_with( $q, '?' ) ) {
				continue;
			}
			$a = $this->details_body( $details, $summary );
			if ( $a === '' ) {
				continue;
			}
			$seen[ $q ] = true;
			$pairs[]    = [ 'question' => $q, 'answer' => $a ];
		}
	}

	// ── Pattern 3: WAI-ARIA accordion buttons ────────────────────────────────
	$aria_nodes = $xpath->query( '//*[@role="button" or @aria-expanded]' );
	if ( $aria_nodes ) {
		foreach ( $aria_nodes as $btn ) {
			/** @var \DOMElement $btn */
			// Skip nodes inside a <details> element — already handled by Pattern 2.
			if ( $this->has_ancestor_tag( $btn, 'details' ) ) {
				continue;
			}
			$q = trim( wp_strip_all_tags( $btn->textContent ) );
			if ( $q === '' || isset( $seen[ $q ] ) ) {
				continue;
			}
			if ( ! preg_match( $q_re, $q ) && ! str_ends_with( $q, '?' ) ) {
				continue;
			}
			$a = $this->aria_answer( $xpath, $btn );
			if ( $a === '' ) {
				continue;
			}
			$seen[ $q ] = true;
			$pairs[]    = [ 'question' => $q, 'answer' => $a ];
		}
	}

	// ── Pattern 4: CSS-class accordion containers ────────────────────────────
	$css_query = '//*[contains(@class,"accordion") or contains(@class,"faq")'
		. ' or contains(@class,"toggle") or contains(@class,"collapse")]'
		. '[not(@role) and not(@aria-expanded)]';
	$css_nodes = $xpath->query( $css_query );
	if ( $css_nodes ) {
		foreach ( $css_nodes as $container ) {
			/** @var \DOMElement $container */
			$q_node = $xpath->query(
				'.//*[contains(@class,"question") or contains(@class,"title")'
				. ' or contains(@class,"header") or contains(@class,"heading")]',
				$container
			);
			$a_node = $xpath->query(
				'.//*[contains(@class,"answer") or contains(@class,"body")'
				. ' or contains(@class,"content") or contains(@class,"panel")]',
				$container
			);
			$q_el = $q_node ? $q_node->item( 0 ) : null;
			$a_el = $a_node ? $a_node->item( 0 ) : null;
			if ( ! $q_el || ! $a_el ) {
				continue;
			}
			$q = trim( wp_strip_all_tags( $q_el->textContent ) );
			$a = trim( wp_strip_all_tags( $a_el->textContent ) );
			if ( $q === '' || $a === '' || isset( $seen[ $q ] ) ) {
				continue;
			}
			if ( ! preg_match( $q_re, $q ) && ! str_ends_with( $q, '?' ) ) {
				continue;
			}
			$seen[ $q ] = true;
			$pairs[]    = [ 'question' => $q, 'answer' => $a ];
		}
	}

	return $pairs;
}
```

- [ ] **Step 6: Add the 4 private helper methods**

Insert all four helpers after `extract_faq_pairs()`, before `collect_root_types()`:

```php
/**
 * Returns the text of the first <p> after $node, stopping at the next heading.
 * Used by Pattern 1 (heading + paragraph).
 */
private function first_p_after( \DOMNode $node ): string {
	$sib = $node->nextSibling;
	while ( $sib ) {
		if ( $sib instanceof \DOMElement ) {
			if ( $sib->nodeName === 'p' ) {
				return trim( wp_strip_all_tags( $sib->textContent ) );
			}
			// Stop at any heading — we've left this question's section.
			if ( preg_match( '/^h[1-6]$/i', $sib->nodeName ) ) {
				break;
			}
			// Peek inside wrapper divs for a leading <p>.
			if ( in_array( $sib->nodeName, [ 'div', 'section' ], true ) ) {
				foreach ( $sib->childNodes as $child ) {
					if ( $child instanceof \DOMElement && $child->nodeName === 'p' ) {
						return trim( wp_strip_all_tags( $child->textContent ) );
					}
				}
			}
		}
		$sib = $sib->nextSibling;
	}
	return '';
}

/**
 * Returns the text body of a <details> element, excluding the <summary> text.
 * Used by Pattern 2 (details/summary).
 */
private function details_body( \DOMElement $details, \DOMElement $summary ): string {
	$parts = [];
	foreach ( $details->childNodes as $child ) {
		if ( $child->isSameNode( $summary ) ) {
			continue;
		}
		$text = trim( wp_strip_all_tags( $child->textContent ?? '' ) );
		if ( $text !== '' ) {
			$parts[] = $text;
		}
	}
	return implode( ' ', $parts );
}

/**
 * Returns the answer text for a WAI-ARIA accordion button element.
 * Resolution order:
 *   1. aria-controls attribute → find element by ID in the document.
 *   2. First element sibling after $btn.
 *   3. First element sibling of $btn's parent container.
 * Used by Pattern 3 (role="button" / aria-expanded).
 */
private function aria_answer( \DOMXPath $xpath, \DOMElement $btn ): string {
	$controls = $btn->getAttribute( 'aria-controls' );
	if ( $controls !== '' ) {
		$target = $xpath->query( '//*[@id="' . esc_attr( $controls ) . '"]' )->item( 0 );
		if ( $target ) {
			return trim( wp_strip_all_tags( $target->textContent ) );
		}
	}
	// Next element sibling of the button itself.
	$sib = $btn->nextSibling;
	while ( $sib ) {
		if ( $sib instanceof \DOMElement ) {
			return trim( wp_strip_all_tags( $sib->textContent ) );
		}
		$sib = $sib->nextSibling;
	}
	// Next element sibling of the button's parent container.
	$parent = $btn->parentNode;
	if ( $parent instanceof \DOMElement ) {
		$sib = $parent->nextSibling;
		while ( $sib ) {
			if ( $sib instanceof \DOMElement ) {
				return trim( wp_strip_all_tags( $sib->textContent ) );
			}
			$sib = $sib->nextSibling;
		}
	}
	return '';
}

/**
 * Returns true if $node has an ancestor element with the given tag name.
 * Used to prevent Pattern 3 from double-counting nodes inside <details>.
 */
private function has_ancestor_tag( \DOMNode $node, string $tag ): bool {
	$parent = $node->parentNode;
	while ( $parent instanceof \DOMElement ) {
		if ( strtolower( $parent->nodeName ) === strtolower( $tag ) ) {
			return true;
		}
		$parent = $parent->parentNode;
	}
	return false;
}
```

- [ ] **Step 7: Verify Generator.php parses cleanly (no PHP syntax error)**

Run from the plugin root in LocalWP terminal:

```bash
php -l includes/Schema/Generator.php
```

Expected output:
```
No syntax errors detected in includes/Schema/Generator.php
```

- [ ] **Step 8: Commit**

```bash
git add includes/Schema/Generator.php
git commit -m "feat: rewrite FAQ detection to DOMDocument — detects h2/details/WAI-ARIA/CSS-class accordion patterns (X15 filter registered)"
```

---

## Task 3: Add `faq_count` to Schema REST Response

**Files:**
- Modify: `includes/Rest/SchemaController.php`

**Context:** `get_schema()` currently calls `generate_article_schema()`, `generate_faq_schema()`, and `detect_existing_types()`. Add a call to the new `count_faq_pairs()` and include the result as `faq_count` in the response. Because `analysis_for()` is now memoized inside Generator, calling both `generate_faq_schema()` and `count_faq_pairs()` on the same post does not double-parse content.

- [ ] **Step 1: Update `get_schema()` in SchemaController.php**

Replace the body of `get_schema()`:

```php
public function get_schema( \WP_REST_Request $request ): \WP_REST_Response {
	$post_id = (int) $request['post_id'];
	$post    = get_post( $post_id );

	if ( ! $post instanceof \WP_Post ) {
		return new \WP_REST_Response( [ 'error' => 'post_not_found' ], 404 );
	}

	$article   = $this->generator->generate_article_schema( $post );
	$faqpage   = $this->generator->generate_faq_schema( $post );
	$detected  = $this->generator->detect_existing_types( $post );
	$faq_count = $this->generator->count_faq_pairs( $post );

	return new \WP_REST_Response(
		[
			'article'   => $article,
			'faqpage'   => $faqpage ?: null,
			'detected'  => array_values( $detected ),
			'faq_count' => $faq_count,
		],
		200
	);
}
```

- [ ] **Step 2: Lint check**

```bash
php -l includes/Rest/SchemaController.php
```

Expected:
```
No syntax errors detected in includes/Rest/SchemaController.php
```

- [ ] **Step 3: Commit**

```bash
git add includes/Rest/SchemaController.php
git commit -m "feat: add faq_count to schema REST response for 3-state panel message"
```

---

## Task 4: 3-State FAQ Message in Schema Suggestions Panel

**Files:**
- Modify: `src/sidebar/index.js`
- Modify: `src/sidebar/style.scss`

**Context:** The Schema Suggestions panel renders `SCHEMA_TYPES` using `SchemaTypeRow`. The faqpage row currently has a static `emptyMessage`. After this task:
- When `schema.faqpage === null && schema.faq_count === 0`: "No FAQ content detected on this page."
- When `schema.faqpage === null && schema.faq_count === 1`: "Only 1 question/answer pair detected. FAQPage schema requires at least 2 pairs."
- When `schema.faqpage !== null` (≥ 2 pairs): Insert button active + `statusText` "FAQ detected: N question/answer pairs."

The `SchemaTypeRow` component gains an optional `statusText` prop that renders as a small helper line when `generated` is true (inside the `<div className="citewp-aiso-sidebar-schema-row">`).

- [ ] **Step 1: Update `SCHEMA_TYPES` — set faqpage `emptyMessage` to `null`**

Replace the faqpage entry in `SCHEMA_TYPES`:

```js
{
	key: 'faqpage',
	label: 'FAQPage',
	variants: [ 'FAQPage' ],
	emptyMessage: null,  // computed dynamically in SchemaSuggestions render from schema.faq_count
},
```

- [ ] **Step 2: Update `SchemaTypeRow` to accept and render `statusText` prop**

Replace the entire `SchemaTypeRow` function (lines 378–415):

```js
function SchemaTypeRow( { label, detected, generated, inserted, inserting, onInsert, emptyMessage, statusText } ) {
	if ( ! generated && ! detected ) {
		return emptyMessage ? (
			<div className="citewp-aiso-sidebar-schema-row__empty">
				{ emptyMessage }
			</div>
		) : null;
	}

	let action;
	if ( detected || inserted ) {
		const statusLabel = ( inserted && ! detected ) ? '✓ Added' : 'Already detected';
		action = (
			<span className="citewp-aiso-sidebar-schema-row__pill">
				{ statusLabel }
			</span>
		);
	} else {
		action = (
			<Button
				variant="secondary"
				size="small"
				onClick={ onInsert }
				isBusy={ inserting }
				disabled={ inserting }
			>
				Insert
			</Button>
		);
	}

	return (
		<div className="citewp-aiso-sidebar-schema-row">
			<div className="citewp-aiso-sidebar-schema-row__label-group">
				<span className="citewp-aiso-sidebar-schema-row__label">{ label }</span>
				{ statusText && (
					<span className="citewp-aiso-sidebar-schema-row__status-text">{ statusText }</span>
				) }
			</div>
			{ action }
		</div>
	);
}
```

- [ ] **Step 3: Update the `SCHEMA_TYPES.map()` call in `SchemaSuggestions` to compute dynamic props**

Replace the `{ SCHEMA_TYPES.map( ( type ) => { ... } ) }` block (starting around line 354):

```jsx
{ SCHEMA_TYPES.map( ( type ) => {
	const faqCount = schema.faq_count ?? 0;

	// Dynamic empty message for FAQPage: 0-pair vs 1-pair state.
	const emptyMsg = ( type.key === 'faqpage' && ! schema[ type.key ] )
		? ( faqCount === 0
			? 'No FAQ content detected on this page.'
			: 'Only 1 question/answer pair detected. FAQPage schema requires at least 2 pairs.' )
		: type.emptyMessage;

	// Status text shown alongside Insert button when FAQPage is generated.
	const statusText = ( type.key === 'faqpage' && !! schema[ type.key ] )
		? `FAQ detected: ${ faqCount } question/answer ${ faqCount === 1 ? 'pair' : 'pairs' }.`
		: null;

	return (
		<SchemaTypeRow
			key={ type.key }
			label={ type.label }
			detected={ detected.some( ( d ) => type.variants.includes( d ) ) }
			generated={ !! schema[ type.key ] }
			inserted={ !! inserted[ type.key ] }
			inserting={ !! inserting[ type.key ] }
			onInsert={ () => insertSchemaBlock( type.key ) }
			emptyMessage={ emptyMsg }
			statusText={ statusText }
		/>
	);
} ) }
```

- [ ] **Step 4: Add `.citewp-aiso-sidebar-schema-row__label-group` and `__status-text` to style.scss**

Append to `src/sidebar/style.scss` (after the last existing `.citewp-aiso-sidebar-schema-row` block):

```scss
.citewp-aiso-sidebar-schema-row__label-group {
	display: flex;
	flex-direction: column;
	gap: 2px;
	min-width: 0;
}

.citewp-aiso-sidebar-schema-row__status-text {
	font-size: 11px;
	color: #757575; // WP admin muted text; no custom token needed — matches $color-text-muted intent
	line-height: 1.4;
}
```

- [ ] **Step 5: Build and check for JS errors**

```bash
npm run build
```

Expected output (last line):
```
webpack compiled successfully
```

No new errors or warnings (3 pre-existing `chartLine` icon warnings are known-good).

- [ ] **Step 6: Commit**

```bash
git add src/sidebar/index.js src/sidebar/style.scss build/
git commit -m "feat: 3-state FAQ message in Schema Suggestions panel (0-pair / 1-pair / N-pairs)"
```

---

## Task 5: Grade Schema Signal in Engine.php (6 / 3 / 0)

**Files:**
- Modify: `includes/Scoring/Engine.php`
- Modify: `includes/Admin/RecommendationMapper.php`

**Context:** `check_schema()` is at line 555. Currently binary: 6 pts if `$inline_count >= 1 || $has_seo_plugin`, else 0. The bug: Rank Math / Yoast presence gives full 6 pts even with no schema in post content — so inserting Article JSON-LD has zero observable score effect. The fix restores the feedback loop.

**A11 gate confirmed:** 6 pts inline / 3 pts SEO plugin / 0 pts neither.

**`SignalResult` supports this natively** — `int $score`, `int $max`, `string $status ('pass'|'partial'|'fail')` already exist. No class change needed.

- [ ] **Step 1: Replace `check_schema()` in Engine.php**

Find and replace the entire `check_schema()` method (line 555 to its closing `}`):

```php
private function check_schema( ContentAnalysis $a ): SignalResult {
	// SEO plugin presence implies potential schema output but cannot be verified
	// without a render-time scan (deferred to FB42 — render-time schema detection).
	$has_seo_plugin = defined( 'WPSEO_VERSION' )
		|| defined( 'RANK_MATH_VERSION' )
		|| defined( 'AIOSEO_VERSION' )
		|| defined( 'AIOSEO_VERSION_LITE' );

	$inline_count = count( array_unique( $a->schema_types ) );

	if ( $inline_count >= 1 ) {
		return new SignalResult(
			'schema', 'authority', 'Schema markup',
			6, 6, 'pass',
			sprintf( 'Schema types detected: %s.', implode( ', ', array_unique( $a->schema_types ) ) )
		);
	}

	if ( $has_seo_plugin ) {
		return new SignalResult(
			'schema', 'authority', 'Schema markup',
			3, 6, 'partial',
			'Active SEO plugin detected. Verify it is configured to output schema for this post type.',
			'You have an SEO plugin installed but no schema was found in your post content. Use the Schema Suggestions panel to insert JSON-LD directly, or verify your SEO plugin outputs schema for this post type.'
		);
	}

	return new SignalResult(
		'schema', 'authority', 'Schema markup',
		0, 6, 'fail',
		'No schema markup detected.',
		'Install Yoast, Rank Math, or AIOSEO — or use the Schema Suggestions panel to insert JSON-LD directly.'
	);
}
```

- [ ] **Step 2: Update `schema` copy in RecommendationMapper.php**

The admin recommendations panel uses `RecommendationMapper::get('schema')` for signals not at full credit. Update the `schema` entry to cover the partial state (SEO plugin present but unconfigured) as well as the fail state:

Find the `'schema'` entry in the MAP constant and replace its `'copy'` value:

Old:
```php
'schema'             => [
	'label'    => 'Schema markup',
	'category' => 'authority',
	'copy'     => 'Add Article or HowTo schema to your post. Structured data helps AI understand and cite your content.',
],
```

New:
```php
'schema'             => [
	'label'    => 'Schema markup',
	'category' => 'authority',
	'copy'     => 'Add JSON-LD schema to your post content via the Schema Suggestions panel, or verify your SEO plugin is configured to output schema for this post type. Structured data helps AI engines understand and cite your content.',
],
```

- [ ] **Step 3: Lint both files**

```bash
php -l includes/Scoring/Engine.php && php -l includes/Admin/RecommendationMapper.php
```

Expected:
```
No syntax errors detected in includes/Scoring/Engine.php
No syntax errors detected in includes/Admin/RecommendationMapper.php
```

- [ ] **Step 4: Commit**

```bash
git add includes/Scoring/Engine.php includes/Admin/RecommendationMapper.php
git commit -m "fix: grade schema signal 6/3/0 — SEO plugin alone earns partial credit, inline schema earns full (Bug B)"
```

---

## Task 6: Version Bump 0.7.0 → 0.7.1 + Final Build

**Files:**
- Modify: `ai-search-optimizer.php`
- Modify: `readme.txt`

- [ ] **Step 1: Update plugin header in `ai-search-optimizer.php`**

Find the `Version:` line in the plugin header and the `CITEWP_AISO_VERSION` constant:

```php
 * Version:           0.7.0
```
→
```php
 * Version:           0.7.1
```

And:
```php
define( 'CITEWP_AISO_VERSION', '0.7.0' );
```
→
```php
define( 'CITEWP_AISO_VERSION', '0.7.1' );
```

- [ ] **Step 2: Add changelog entry to `readme.txt`**

Find the `== Changelog ==` section and add a new entry at the top (above the existing `= 0.7.0 =` entry):

```
= 0.7.1 =
* Improvement: FAQ detection now recognises accordions built with Kadence, Elementor, Divi, Beaver Builder, Bricks, Spectra, and HTML5 details/summary — in addition to standard headings.
* Improvement: Schema Suggestions panel shows accurate FAQ pair count (0, 1, or N pairs found) instead of a generic "need ≥ 2" message.
* Fix: Schema signal now awards incremental points — 6 pts for inline JSON-LD, 3 pts when an SEO plugin is active, 0 pts with no schema detected. Previously an active SEO plugin always awarded full points regardless of whether schema was actually configured.
```

- [ ] **Step 3: Final build**

```bash
npm run build
```

Expected: `webpack compiled successfully` with no new errors.

- [ ] **Step 4: Check debug.log**

In LocalWP terminal, check the WordPress debug log:

```bash
type "C:\Users\KingpinBWP\Local Sites\citewp-dev\app\public\wp-content\debug.log"
```

Expected: File does not exist, OR no new entries since last session.

- [ ] **Step 5: Commit**

```bash
git add ai-search-optimizer.php readme.txt build/
git commit -m "chore: bump version to 0.7.1"
```

---

## Extensibility Hooks Registered (X15 compliance)

Per the backlog scan, this plan adds:

| Filter | Location | Purpose |
|---|---|---|
| `citewp_aiso/schema/faq_pairs` | `Generator::get_faq_pairs()` | Allows third-party code to add/remove/modify FAQ pairs before schema generation and pair counting. FB29 (schema type expansion to HowTo/LocalBusiness) uses this hook to inject additional question/answer sources. |

---

## Post-Task Verification Checklist (Step 9 of session protocol)

Run these after all 6 tasks are committed.

**Fixture verification — 8 builder patterns:**
Create a test post in WP admin. For each fixture file in `tests/fixtures/faq-detection/`, paste the HTML into a Custom HTML block, save, then open the Schema Suggestions panel in Gutenberg Document Settings.

| Fixture | Expected `faq_count` | Expected panel state |
|---|---|---|
| 01-heading-basic.html | 3 | ≥ 2 pairs — Insert active, "FAQ detected: 3 pairs" |
| 02-details-summary.html | 2 | ≥ 2 pairs — Insert active, "FAQ detected: 2 pairs" |
| 03-kadence-accordion.html | 2 | ≥ 2 pairs — Insert active |
| 04-elementor-accordion.html | 2 | ≥ 2 pairs — Insert active |
| 05-divi-accordion.html | 2 | ≥ 2 pairs — Insert active |
| 06-beaver-accordion.html | 2 | ≥ 2 pairs — Insert active |
| 07-bricks-accordion.html | 2 | ≥ 2 pairs — Insert active |
| 08-spectra-faq.html | 2 | ≥ 2 pairs — Insert active |
| 09-one-pair-only.html | 1 | 1 pair — "Only 1 question/answer pair detected..." |
| 10-no-faq-false-positive-guard.html | 0 | 0 pairs — "No FAQ content detected on this page." |

**Bug B verification:**
1. Open a post that has Rank Math active but no inline schema.
2. Before fix: schema signal was Pass (6/6). After fix: schema signal should be Partial (3/6).
3. Insert Article JSON-LD via Schema Suggestions panel → recalculate → schema signal should jump to Pass (6/6).
4. Confirm the score moved by ~3 points (observable in the Gutenberg sidebar score display).

**Live verification on citewp.com (Step 10):**
- Package updated plugin via `.\package.ps1`, upload to citewp.com, activate.
- Visit `/ai-search-optimizer/` (page with Kadence FAQ accordion). Schema Suggestions panel should show "FAQ detected: N pairs."
- On a page where Rank Math is active and no inline JSON-LD exists: recalculate → schema signal = 3/6 partial.

---

## Self-Review Against Spec

**Spec coverage check:**

| Requirement | Task |
|---|---|
| Primary: DOMDocument FAQ detection | Task 2 |
| Detect `<details>/<summary>` | Task 2 Pattern 2 |
| Detect WAI-ARIA `role="button"` / `aria-expanded` | Task 2 Pattern 3 |
| CSS-class fallback | Task 2 Pattern 4 |
| Preserve heading detection | Task 2 Pattern 1 |
| X15 filter `citewp_aiso/schema/faq_pairs` | Task 2 `get_faq_pairs()` |
| `count_faq_pairs()` public method | Task 2 |
| `faq_count` in REST response | Task 3 |
| Secondary: 3-state message (0 / 1 / N pairs) | Task 4 |
| Tertiary: Bug B schema grading 6/3/0 | Task 5 |
| RecommendationMapper partial copy | Task 5 |
| Quaternary: lazy migration (no script needed) | Decision A confirmed — no task required |
| Version bump 0.7.1 | Task 6 |
| Fixture files for manual verification | Task 1 |
| `analysis_for()` memoization (avoid double-parse) | Task 2 |

**Placeholder scan:** No TBDs, no "implement later" phrases. All code blocks complete.

**Type consistency:** `get_faq_pairs()` returns `array<int, array{question: string, answer: string}>`. `count_faq_pairs()` returns `int`. `generate_faq_schema()` calls `get_faq_pairs()` and guards on `count($pairs) < 2`. `SchemaController` calls `count_faq_pairs()` — type matches.

**No gaps found.**
