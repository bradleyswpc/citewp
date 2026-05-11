<?php
declare( strict_types=1 );

namespace CiteWP\Aiso\Admin;

defined( 'ABSPATH' ) || exit;

final class ScoreDial {

	public static function render( int $score, string $grade ): void {
		$score        = max( 0, min( 100, $score ) );
		$grade_labels = [
			'green'  => __( 'Excellent',         'ai-search-optimizer' ),
			'yellow' => __( 'Good',              'ai-search-optimizer' ),
			'orange' => __( 'Fair',              'ai-search-optimizer' ),
			'red'    => __( 'Needs Improvement', 'ai-search-optimizer' ),
			'empty'  => __( 'No data',           'ai-search-optimizer' ),
		];
		$grade_label = $grade_labels[ $grade ] ?? '';
		?>
		<div class="citewp-cite-score-gauge citewp-cite-score-gauge--<?php echo esc_attr( $grade ); ?>"
		     style="--score:<?php echo esc_attr( (string) $score ); ?>">
			<svg viewBox="0 0 240 140" role="img"
			     aria-label="<?php echo esc_attr( sprintf( /* translators: 1: score integer 0-100, 2: grade label e.g. "Excellent" */ __( 'Cite Score %1$d out of 100, %2$s', 'ai-search-optimizer' ), $score, $grade_label ) ); ?>">
				<defs>
					<linearGradient id="citewp-gauge-gradient" x1="30" y1="120" x2="210" y2="120" gradientUnits="userSpaceOnUse">
						<stop offset="0%"   stop-color="#ef4444" />
						<stop offset="33%"  stop-color="#f97316" />
						<stop offset="66%"  stop-color="#f7d84a" />
						<stop offset="100%" stop-color="#16a34a" />
					</linearGradient>
				</defs>
				<path class="gauge-bg" d="M 30 120 A 90 90 0 0 1 210 120" pathLength="100" />
				<path class="gauge-score" d="M 30 120 A 90 90 0 0 1 210 120" pathLength="100" />
				<text x="120" y="88" text-anchor="middle" class="gauge-number">
					<?php echo esc_html( $score > 0 ? (string) $score : '—' ); ?>
				</text>
				<text x="120" y="112" text-anchor="middle" class="gauge-total">/100</text>
				<text x="120" y="132" text-anchor="middle" class="gauge-label">
					<?php echo esc_html( $grade_label ); ?>
				</text>
			</svg>
		</div>
		<?php
	}
}
