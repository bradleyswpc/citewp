<?php
/**
 * Standalone parser unit test for Detector::parse_jsonld_html().
 *
 * Tests FB71 (nested-subjectOf FAQPage detection) and the Fixture B regression
 * (top-level FAQPage must still work) plus a false-positive guard.
 *
 * Run from the plugin root:
 *   php tests/DetectorParserTest.php
 *
 * No PHPUnit required. Stubs the two WordPress functions used by the parser.
 * Only parse_jsonld_html() is exercised — no DB, no HTTP, no WP bootstrap.
 */

declare( strict_types=1 );

// ── Bootstrap stubs ──────────────────────────────────────────────────────────

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}
if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}

// apply_filters() is the only WP function called inside parse_jsonld_html().
// Stub it as a pass-through so the detected_types filter has no effect in tests.
if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( string $tag, $value, ...$args ) { // phpcs:ignore
		return $value;
	}
}

// load_plugin_textdomain() / add_action() are NOT called during construction
// or parse_jsonld_html() — no stubs needed.

require_once __DIR__ . '/../includes/Schema/Detector.php';

// ── Tiny assertion helper ────────────────────────────────────────────────────

$GLOBALS['_test_failures'] = 0;

function expect( bool $condition, string $label ): void {
	if ( $condition ) {
		echo "  PASS  {$label}\n";
	} else {
		echo "  FAIL  {$label}\n";
		++$GLOBALS['_test_failures'];
	}
}

// ── Shared detector instance ─────────────────────────────────────────────────

$detector = new \CiteWP\Aiso\Schema\Detector();
$fixture_dir = __DIR__ . '/fixtures/detector-parser';

// ── Fixture A: BlogPosting -> subjectOf[] -> FAQPage (Rank Math pattern) ─────

echo "\nFixture A — Rank Math nested subjectOf FAQPage (FB71):\n";
$html_a = file_get_contents( $fixture_dir . '/fixture-a-nested-subject-of.html' );
$a      = $detector->parse_jsonld_html( $html_a );

expect( $a['state'] === 'detected',    'state is detected' );
expect( $a['faq_valid'] === true,      'faq_valid is true (nested FAQPage found)' );
expect( $a['article_valid'] === true,  'article_valid is true (BlogPosting is Article-type)' );
expect( in_array( 'BlogPosting', $a['types'], true ), 'BlogPosting in types' );

// ── Fixture B: top-level FAQPage (Yoast-style regression) ────────────────────

echo "\nFixture B — top-level FAQPage regression (Yoast-style):\n";
$html_b = file_get_contents( $fixture_dir . '/fixture-b-toplevel-faq.html' );
$b      = $detector->parse_jsonld_html( $html_b );

expect( $b['state'] === 'detected',  'state is detected' );
expect( $b['faq_valid'] === true,    'faq_valid is true (top-level FAQPage still works)' );

// ── Fixture C: no FAQPage anywhere — false-positive guard ───────────────────

echo "\nFixture C — no FAQ present (false-positive guard):\n";
$html_c = file_get_contents( $fixture_dir . '/fixture-c-no-faq.html' );
$c      = $detector->parse_jsonld_html( $html_c );

expect( $c['state'] === 'detected',  'state is detected (Organization present)' );
expect( $c['faq_valid'] === false,   'faq_valid is false (no FAQPage anywhere)' );
expect( $c['article_valid'] === false, 'article_valid is false (no Article-type)' );

// ── Summary ──────────────────────────────────────────────────────────────────

$failures = $GLOBALS['_test_failures'];
echo "\n" . ( $failures === 0
	? "All tests passed.\n"
	: "{$failures} test(s) FAILED.\n"
);
exit( $failures === 0 ? 0 : 1 );
