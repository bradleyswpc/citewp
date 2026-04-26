<?php
/**
 * Result of a single scoring signal check.
 *
 * @package CiteWP
 */

declare( strict_types=1 );

namespace CiteWP\Scoring;

defined( 'ABSPATH' ) || exit;

final class SignalResult {

	public function __construct(
		public string $id,
		public string $category,         // 'structure' | 'citability' | 'authority'
		public string $label,
		public int    $score,            // points awarded
		public int    $max,              // max possible for this signal
		public string $status,           // 'pass' | 'partial' | 'fail'
		public string $message,          // user-facing explanation
		public string $recommendation = '' // optional how-to-fix text
	) {}

	/**
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return [
			'id'             => $this->id,
			'category'       => $this->category,
			'label'          => $this->label,
			'score'          => $this->score,
			'max'            => $this->max,
			'status'         => $this->status,
			'message'        => $this->message,
			'recommendation' => $this->recommendation,
		];
	}
}
