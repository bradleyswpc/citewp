<?php
/**
 * Result of a GEO score analysis on a single post.
 *
 * @package CiteWP
 */

declare( strict_types=1 );

namespace CiteWP\Scoring;

defined( 'ABSPATH' ) || exit;

final class ScoreResult {

	/** @var SignalResult[] */
	public array $signals = [];

	public int $structure_score   = 0;
	public int $structure_max     = 35;
	public int $citability_score  = 0;
	public int $citability_max    = 40;
	public int $authority_score   = 0;
	public int $authority_max     = 25;

	/** Total 0-100 */
	public int $total = 0;

	public string $grade = 'red'; // red | orange | yellow | green

	/**
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return [
			'total'      => $this->total,
			'grade'      => $this->grade,
			'categories' => [
				'structure'  => [ 'score' => $this->structure_score,  'max' => $this->structure_max,  'label' => 'Structure'  ],
				'citability' => [ 'score' => $this->citability_score, 'max' => $this->citability_max, 'label' => 'Citability' ],
				'authority'  => [ 'score' => $this->authority_score,  'max' => $this->authority_max,  'label' => 'Authority'  ],
			],
			'signals'    => array_map( static fn( SignalResult $s ) => $s->to_array(), $this->signals ),
		];
	}

	public function compute_total(): void {
		$this->total = $this->structure_score + $this->citability_score + $this->authority_score;
		$this->grade = match ( true ) {
			$this->total >= 80 => 'green',
			$this->total >= 60 => 'yellow',
			$this->total >= 40 => 'orange',
			default            => 'red',
		};
	}
}
