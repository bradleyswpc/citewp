<?php
/**
 * Lucide SVG icon helper.
 *
 * @package CiteWP\Aiso
 */

declare( strict_types=1 );

namespace CiteWP\Aiso\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Static helper that returns Lucide SVG icon strings.
 *
 * Usage:
 *   echo IconLibrary::icon( 'dashboard' );
 *   echo IconLibrary::icon( 'settings', 20, 'my-extra-class' );
 *
 * All paths sourced from lucide-icons/lucide (ISC licence).
 * Inner stroke-width attributes stripped; inherited from the wrapper svg.
 */
final class IconLibrary {

	/**
	 * Inner SVG path data keyed by PHP icon name.
	 *
	 * @var array<string, string>
	 */
	private static array $icons = [

		// chart-line (Lucide: chart-line)
		'dashboard'      => '<path d="M3 3v16a2 2 0 0 0 2 2h16" /><path d="m19 9-5 5-4-4-3 3" />',

		// list (Lucide: list)
		'crawler-logs'   => '<path d="M3 5h.01" /><path d="M3 12h.01" /><path d="M3 19h.01" /><path d="M8 5h13" /><path d="M8 12h13" /><path d="M8 19h13" />',

		// target (Lucide: target)
		'cite-score'     => '<circle cx="12" cy="12" r="10" /><circle cx="12" cy="12" r="6" /><circle cx="12" cy="12" r="2" />',

		// file-text (Lucide: file-text)
		'llms-txt'       => '<path d="M6 22a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h8a2.4 2.4 0 0 1 1.704.706l3.588 3.588A2.4 2.4 0 0 1 20 8v12a2 2 0 0 1-2 2z" /><path d="M14 2v5a1 1 0 0 0 1 1h5" /><path d="M10 9H8" /><path d="M16 13H8" /><path d="M16 17H8" />',

		// settings-2 (Lucide: settings-2)
		'settings'       => '<path d="M14 17H5" /><path d="M19 7h-9" /><circle cx="17" cy="17" r="3" /><circle cx="7" cy="7" r="3" />',

		// external-link (Lucide: external-link)
		'external-link'  => '<path d="M15 3h6v6" /><path d="M10 14 21 3" /><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6" />',

		// bot (Lucide: bot)
		'bot'            => '<path d="M12 8V4H8" /><rect width="16" height="12" x="4" y="8" rx="2" /><path d="M2 14h2" /><path d="M20 14h2" /><path d="M15 13v2" /><path d="M9 13v2" />',

		// triangle-alert (Lucide: triangle-alert)
		'alert-triangle' => '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3" /><path d="M12 9v4" /><path d="M12 17h.01" />',

		// sparkles (Lucide: sparkles)
		'sparkles'       => '<path d="M11.017 2.814a1 1 0 0 1 1.966 0l1.051 5.558a2 2 0 0 0 1.594 1.594l5.558 1.051a1 1 0 0 1 0 1.966l-5.558 1.051a2 2 0 0 0-1.594 1.594l-1.051 5.558a1 1 0 0 1-1.966 0l-1.051-5.558a2 2 0 0 0-1.594-1.594l-5.558-1.051a1 1 0 0 1 0-1.966l5.558-1.051a2 2 0 0 0 1.594-1.594z" /><path d="M20 2v4" /><path d="M22 4h-4" /><circle cx="4" cy="20" r="2" />',

		// lightbulb (Lucide: lightbulb)
		'lightbulb'      => '<path d="M15 14c.2-1 .7-1.7 1.5-2.5 1-.9 1.5-2.2 1.5-3.5A6 6 0 0 0 6 8c0 1 .2 2.2 1.5 3.5.7.7 1.3 1.5 1.5 2.5" /><path d="M9 18h6" /><path d="M10 22h4" />',

		// refresh-cw (Lucide: refresh-cw)
		'refresh-cw'     => '<path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8" /><path d="M21 3v5h-5" /><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16" /><path d="M8 16H3v5" />',

		// search (Lucide: search)
		'search'         => '<path d="m21 21-4.34-4.34" /><circle cx="11" cy="11" r="8" />',

		// link (Lucide: link)
		'link'           => '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71" /><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71" />',

		// eye (Lucide: eye)
		'eye'            => '<path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0" /><circle cx="12" cy="12" r="3" />',

		// circle-check (Lucide: circle-check — replaces retired check-circle)
		'check-circle'   => '<circle cx="12" cy="12" r="10" /><path d="m9 12 2 2 4-4" />',

		// calendar (Lucide: calendar)
		'calendar'       => '<path d="M8 2v4" /><path d="M16 2v4" /><rect width="18" height="18" x="3" y="4" rx="2" /><path d="M3 10h18" />',

		// gauge (Lucide: gauge)
		'gauge'          => '<path d="m12 14 4-4" /><path d="M3.34 19a10 10 0 1 1 17.32 0" />',

		// arrow-right (Lucide: arrow-right)
		'arrow-right'    => '<path d="M5 12h14" /><path d="m12 5 7 7-7 7" />',

		// circle-x (Lucide: circle-x)
		'x-circle'       => '<circle cx="12" cy="12" r="10" /><path d="m15 9-6 6" /><path d="m9 9 6 6" />',

		// chart-bar (Lucide: chart-bar — replaces retired bar-chart-2)
		'chart-bar'      => '<path d="M3 3v16a2 2 0 0 0 2 2h16" /><path d="M7 16h8" /><path d="M7 11h12" /><path d="M7 6h3" />',

		// info (Lucide: info)
		'info'           => '<circle cx="12" cy="12" r="10" /><path d="M12 16v-4" /><path d="M12 8h.01" />',

		// layout (Lucide: layout)
		'layout'         => '<path d="M19 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2z"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/>',

		// quote (Lucide: quote)
		'quote'          => '<path d="M3 21c3 0 7-1 7-8V5c0-1.25-.756-2.017-2-2H4c-1.25 0-2 .75-2 1.972V11c0 1.25.75 2 2 2 1 0 1 0 1 1v1c0 1-1 2-2 2s-1 .008-1 1.031V20c0 1 0 1 1 1z"/><path d="M15 21c3 0 7-1 7-8V5c0-1.25-.757-2.017-2-2h-4c-1.25 0-2 .75-2 1.972V11c0 1.25.75 2 2 2h.75c0 2.25.25 4-2.75 4v3c0 1 0 1 1 1z"/>',

		// shield (Lucide: shield)
		'shield'         => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
	];

	/**
	 * Return an escaped SVG icon string safe to echo directly.
	 *
	 * @param string $name  PHP icon name (see $icons array keys).
	 * @param int    $size  Width and height in pixels. Default 18.
	 * @param string $class Additional CSS class(es) appended after 'citewp-icon'.
	 *
	 * @return string Escaped SVG markup, or empty string for unknown names.
	 */
	public static function icon( string $name, int $size = 18, string $class = '' ): string {
		$inner = self::$icons[ $name ] ?? '';

		if ( '' === $inner ) {
			return '';
		}

		$size  = (int) $size;
		$class = esc_attr( $class );

		return sprintf(
			'<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="citewp-icon%s" aria-hidden="true">%s</svg>',
			$size,
			$size,
			$class ? ' ' . $class : '',
			$inner
		);
	}
}
