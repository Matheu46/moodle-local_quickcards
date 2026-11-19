<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_quickcards;

/**
 * SuperMemo-2 (SM-2) spaced repetition algorithm implementation.
 *
 * @package     local_quickcards
 * @copyright   2026 Matheus Mathias
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sm2 {
    /** @var int Rating: Incorrect / Failed. */
    public const RATING_AGAIN = 1;

    /** @var int Rating: Difficult / Hard. */
    public const RATING_HARD = 2;

    /** @var int Rating: Good / Standard success. */
    public const RATING_GOOD = 3;

    /** @var int Rating: Easy / Perfect recall. */
    public const RATING_EASY = 4;

    /** @var float Default initial ease factor. */
    public const DEFAULT_EASE_FACTOR = 2.50;

    /** @var float Minimum allowed ease factor to prevent ease hell. */
    public const MINIMUM_EASE_FACTOR = 1.30;

    /**
     * Compute next review state for a card based on SM-2.
     *
     * @param float $currentease Current ease factor (e.g. 2.50).
     * @param int $currentinterval Current interval in days.
     * @param int $repetitions Consecutive successful reviews count.
     * @param int $rating User evaluation (1: Again, 2: Hard, 3: Good, 4: Easy).
     * @param int|null $now Current timestamp (defaults to time()).
     * @return array Calculation results with keys: easefactor, intervaldays, repetitions, nextreview.
     */
    public static function calculate(
        float $currentease,
        int $currentinterval,
        int $repetitions,
        int $rating,
        ?int $now = null
    ): array {
        $now = $now ?? time();

        // Map 1-4 UI rating into 0-5 SM-2 quality scale:
        // 1 (Again) -> 1 (Complete blackout / incorrect).
        // 2 (Hard)  -> 3 (Correct response with serious difficulty).
        // 3 (Good)  -> 4 (Correct response after hesitation).
        // 4 (Easy)  -> 5 (Perfect response).
        $sm2quality = match ($rating) {
            self::RATING_AGAIN => 1,
            self::RATING_HARD => 3,
            self::RATING_GOOD => 4,
            self::RATING_EASY => 5,
            default => 3,
        };

        // Update Ease Factor: EF' = EF + (0.1 - (5 - q) * (0.08 + (5 - q) * 0.02)).
        $diff = 5 - $sm2quality;
        $newease = $currentease + (0.1 - ($diff * (0.08 + ($diff * 0.02))));
        if ($newease < self::MINIMUM_EASE_FACTOR) {
            $newease = self::MINIMUM_EASE_FACTOR;
        }

        // Determine new interval and repetitions.
        if ($rating === self::RATING_AGAIN) {
            $newreps = 0;
            $newinterval = 1;
        } else {
            if ($repetitions === 0) {
                $newinterval = 1;
            } else if ($repetitions === 1) {
                $newinterval = ($rating === self::RATING_HARD) ? 3 : 6;
            } else {
                $multiplier = $newease;
                if ($rating === self::RATING_HARD) {
                    $multiplier = 1.2;
                } else if ($rating === self::RATING_EASY) {
                    $multiplier = $newease * 1.3;
                }
                $newinterval = (int) max(1, round($currentinterval * $multiplier));
            }
            $newreps = $repetitions + 1;
        }

        $nextreview = $now + ($newinterval * DAYSECS);

        return [
            'easefactor' => round($newease, 2),
            'intervaldays' => $newinterval,
            'repetitions' => $newreps,
            'nextreview' => $nextreview,
        ];
    }
}
