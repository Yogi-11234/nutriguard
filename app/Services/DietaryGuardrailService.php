<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\FoodItemRepository;

/**
 * DietaryGuardrailService
 *
 * Core business logic migrated from script.js.
 * Handles:
 *   — Guardrail configuration lookup per diagnosis
 *   — Meal schedule selection with nutritional scoring
 *   — Daily total computation
 *   — Per-meal compliance status
 *   — Red flag / blacklist detection
 *   — Dietary review status determination
 *   — Cooking method recommendations
 *
 * All methods are pure / side-effect-free and take explicit inputs,
 * making them straightforward to unit-test.
 *
 * @package App\Services
 */
class DietaryGuardrailService
{
    // ─────────────────────────────────────────────────────────────────
    // GUARDRAIL CONFIGURATION
    // Mirrors GUARDRAIL_CONFIG in script.js
    // ─────────────────────────────────────────────────────────────────

    /** @var array<string, array<string, mixed>> */
    private const GUARDRAIL_CONFIG = [
        'dm2' => [
            'label'              => 'Diabetes Melitus Tipe 2',
            'sodiumMgMax'        => 2300,
            'sugarGMax'          => 25,
            'energyKcalTarget'   => 1700,
            'proteinGMin'        => 55,
            'notes'              => 'Batasi gula sederhana. Pilih karbohidrat kompleks & indeks glikemik rendah.',
        ],
        'htn' => [
            'label'              => 'Hipertensi',
            'sodiumMgMax'        => 1500,
            'sugarGMax'          => 50,
            'energyKcalTarget'   => 1800,
            'proteinGMin'        => 50,
            'notes'              => 'Restriksi sodium ketat (DASH diet). Batasi makanan olahan & asin.',
        ],
        'ckd' => [
            'label'              => 'Penyakit Ginjal Kronis',
            'sodiumMgMax'        => 1000,
            'sugarGMax'          => 40,
            'energyKcalTarget'   => 1900,
            'proteinGMin'        => 35,
            'notes'              => 'Restriksi sodium & protein sangat ketat. Hindari suplemen kalium tinggi.',
        ],
        'other' => [
            'label'              => 'Lainnya',
            'sodiumMgMax'        => 2400,
            'sugarGMax'          => 50,
            'energyKcalTarget'   => 2000,
            'proteinGMin'        => 50,
            'notes'              => 'Panduan gizi umum. Konsultasikan dengan dokter untuk panduan spesifik.',
        ],
    ];

    /** @var array<string, string[]> */
    private const COOKING_METHODS = [
        'normal' => ['Panggang', 'Rebus', 'Kukus', 'Tim', 'Tumis (minyak minimal)'],
        'soft'   => ['Kukus', 'Rebus hingga lunak', 'Tim', 'Pressure cook'],
        'puree'  => ['Blender halus setelah dikukus', 'Tim hingga sangat lunak', 'Saring'],
        'liquid' => ['Blender hingga cair', 'Saring halus', 'Rebus dan saring'],
    ];

    /** @var string[] Canonical meal slot order */
    private const MEAL_TIME_ORDER = [
        'breakfast', 'snack_morning', 'lunch', 'snack_afternoon', 'dinner',
    ];

    /** @var array<string, string> */
    private const ALLERGEN_LABELS = [
        'seafood' => 'Seafood/Udang',
        'nuts'    => 'Kacang',
        'egg'     => 'Telur',
        'milk'    => 'Susu',
        'soy'     => 'Kedelai',
    ];

    /** @var array<string, string> */
    private const TEXTURE_LABELS = [
        'normal' => 'Biasa',
        'soft'   => 'Lunak/Bubur',
        'puree'  => 'Saring',
        'liquid' => 'Cair',
    ];

    public function __construct(
        private readonly FoodItemRepository $foodRepo,
    ) {}

    // ─────────────────────────────────────────────────────────────────
    // PUBLIC API
    // ─────────────────────────────────────────────────────────────────

    /**
     * Run a full single-patient dietary analysis.
     *
     * @param  array $patient  Keys: pid, name, age, diagnosis, texture, allergies, notes
     * @return array           Full analysis payload
     */
    public function analyze(array $patient): array
    {
        $diagnosis = $patient['diagnosis'];
        $texture   = $patient['texture'];
        $allergies = $patient['allergies'] ?? [];

        $guardrail      = $this->getGuardrail($diagnosis);
        $schedule       = $this->selectSchedule($diagnosis, $texture, $allergies);
        $totals         = $this->computeDailyTotals($schedule);
        $redFlags       = $this->buildRedFlags($patient, $guardrail);
        $reviewStatus   = $this->getDietaryReviewStatus($totals, $guardrail);
        $cookingMethods = $this->getCookingMethods($texture);

        // Attach per-meal compliance status to each schedule slot
        $scheduleWithCompliance = array_map(
            fn (array $slot) => array_merge($slot, [
                'compliance' => $this->computeComplianceStatus(
                    $slot['dish'],
                    $slot['portionGrams'],
                    $guardrail
                ),
            ]),
            $schedule
        );

        return [
            'patient'        => $patient,
            'guardrail'      => $guardrail,
            'schedule'       => $scheduleWithCompliance,
            'totals'         => $totals,
            'redFlags'       => $redFlags,
            'cookingMethods' => $cookingMethods,
            'reviewStatus'   => $reviewStatus,
        ];
    }

    /**
     * Return the full dish database (for /api/food-items endpoint).
     */
    public function getAllDishes(): array
    {
        return $this->foodRepo->all();
    }

    /**
     * Return all guardrail configs (useful for frontend reference).
     */
    public function getGuardrailConfigs(): array
    {
        return self::GUARDRAIL_CONFIG;
    }

    // ─────────────────────────────────────────────────────────────────
    // GUARDRAIL LOOKUP
    // ─────────────────────────────────────────────────────────────────

    /**
     * Retrieve the guardrail configuration for a given diagnosis key.
     *
     * @param  string $diagnosis  dm2 | htn | ckd | other
     * @return array
     * @throws \InvalidArgumentException
     */
    public function getGuardrail(string $diagnosis): array
    {
        if (! array_key_exists($diagnosis, self::GUARDRAIL_CONFIG)) {
            throw new \InvalidArgumentException("Unknown diagnosis key: {$diagnosis}");
        }

        return self::GUARDRAIL_CONFIG[$diagnosis];
    }

    // ─────────────────────────────────────────────────────────────────
    // MEAL SCHEDULE SELECTION
    // Mirrors selectSchedule() in script.js
    // ─────────────────────────────────────────────────────────────────

    /**
     * Select the best dish for each of the 5 meal time slots.
     *
     * Scoring rules (same as script.js):
     *   — CKD/HTN: bonus = (500 - sodiumMg)  → prefer lower sodium
     *   — DM2:     bonus = (30 - sugarGrams) * 2 → prefer lower sugar
     *   — All:     +proteinGrams              → prefer higher protein
     *
     * @param  string   $diagnosis
     * @param  string   $texture
     * @param  string[] $allergies
     * @return array    Array of schedule slots: { mealTime, dish, portionGrams }
     */
    public function selectSchedule(string $diagnosis, string $texture, array $allergies): array
    {
        $allDishes = $this->foodRepo->all();
        $schedule  = [];

        foreach (self::MEAL_TIME_ORDER as $mealTime) {
            // Filter: must support this meal time, texture, and exclude allergens
            $candidates = array_filter($allDishes, function (array $dish) use ($mealTime, $texture, $allergies): bool {
                if (! in_array($mealTime, $dish['mealTypes'], true)) {
                    return false;
                }
                if (! in_array($texture, $dish['suitableTextures'], true)) {
                    return false;
                }
                // Exclude if patient has any of this dish's allergens
                if (! empty($allergies)) {
                    foreach ($dish['allergens'] as $allergen) {
                        if (in_array($allergen, $allergies, true)) {
                            return false;
                        }
                    }
                }
                return true;
            });

            if (empty($candidates)) {
                continue; // No valid dish for this slot — skip gracefully
            }

            // Score each candidate
            $scored = array_map(function (array $dish) use ($diagnosis): array {
                $score = 0.0;

                if ($diagnosis === 'ckd' || $diagnosis === 'htn') {
                    $score += (500.0 - $dish['sodiumMg']);
                }
                if ($diagnosis === 'dm2') {
                    $score += (30.0 - $dish['sugarGrams']) * 2.0;
                }
                $score += $dish['proteinGrams'];

                return ['dish' => $dish, 'score' => $score];
            }, $candidates);

            // Sort descending by score
            usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

            $best = $scored[0];
            $schedule[] = [
                'mealTime'     => $mealTime,
                'dish'         => $best['dish'],
                'portionGrams' => $best['dish']['servingGrams'],
            ];
        }

        return $schedule;
    }

    // ─────────────────────────────────────────────────────────────────
    // DAILY TOTALS
    // Mirrors computeDailyTotals() in script.js
    // ─────────────────────────────────────────────────────────────────

    /**
     * Sum all nutritional values from the active meal schedule.
     *
     * Nutrients are scaled proportionally to portionGrams vs. servingGrams.
     *
     * @param  array $schedule
     * @return array  { calories, protein, sodium, sugar, carbs, fat }
     */
    public function computeDailyTotals(array $schedule): array
    {
        $totals = ['calories' => 0.0, 'protein' => 0.0, 'sodium' => 0.0,
                   'sugar'    => 0.0, 'carbs'   => 0.0, 'fat'    => 0.0];

        foreach ($schedule as $slot) {
            $dish  = $slot['dish'];
            $ratio = $slot['portionGrams'] / $dish['servingGrams'];

            $totals['calories'] += $dish['caloriesKcal']  * $ratio;
            $totals['protein']  += $dish['proteinGrams']  * $ratio;
            $totals['sodium']   += $dish['sodiumMg']      * $ratio;
            $totals['sugar']    += $dish['sugarGrams']    * $ratio;
            $totals['carbs']    += $dish['carbsGrams']    * $ratio;
            $totals['fat']      += $dish['fatGrams']      * $ratio;
        }

        // Round to 2 decimal places for clean API responses
        return array_map(fn (float $v) => round($v, 2), $totals);
    }

    // ─────────────────────────────────────────────────────────────────
    // PER-MEAL COMPLIANCE
    // Mirrors computeComplianceStatus() in script.js
    // ─────────────────────────────────────────────────────────────────

    /**
     * Determine per-meal compliance against guardrail thresholds.
     *
     * Thresholds are 1/5 of the daily limit (5 meals per day).
     *
     * @param  array $dish
     * @param  float $portionGrams
     * @param  array $guardrail
     * @return string  'ok' | 'warn' | 'bad'
     */
    public function computeComplianceStatus(array $dish, float $portionGrams, array $guardrail): string
    {
        $sodiumThreshold = $guardrail['sodiumMgMax'] / 5.0;
        $sugarThreshold  = $guardrail['sugarGMax']   / 5.0;

        $actualSodium = $this->scaleNutrient($dish['sodiumMg'],   $dish['servingGrams'], $portionGrams);
        $actualSugar  = $this->scaleNutrient($dish['sugarGrams'], $dish['servingGrams'], $portionGrams);

        if ($actualSodium > $sodiumThreshold || $actualSugar > $sugarThreshold * 1.5) {
            return 'bad';
        }
        if ($actualSodium > $sodiumThreshold * 0.75 || $actualSugar > $sugarThreshold) {
            return 'warn';
        }

        return 'ok';
    }

    // ─────────────────────────────────────────────────────────────────
    // RED FLAGS / BLACKLIST
    // Mirrors buildRedFlags() in script.js
    // ─────────────────────────────────────────────────────────────────

    /**
     * Scan all dishes and report allergy conflicts, texture mismatches,
     * and high-sodium warnings (CKD).
     *
     * @param  array $patient
     * @param  array $guardrail
     * @return array  Array of { type, dish, reason }
     */
    public function buildRedFlags(array $patient, array $guardrail): array
    {
        $allDishes = $this->foodRepo->all();
        $flags     = [];
        $seen      = [];

        $allergies = $patient['allergies'] ?? [];
        $texture   = $patient['texture'];
        $diagnosis = $patient['diagnosis'];

        foreach ($allDishes as $dish) {
            // ── Allergy conflict ──────────────────────────────────
            $conflictAllergens = array_intersect($dish['allergens'], $allergies);
            if (! empty($conflictAllergens)) {
                $labels = array_map(
                    fn ($a) => self::ALLERGEN_LABELS[$a] ?? $a,
                    $conflictAllergens
                );
                $key = $dish['name'] . 'allergy';
                if (! isset($seen[$key])) {
                    $seen[$key] = true;
                    $flags[] = [
                        'type'   => 'allergy',
                        'dish'   => $dish['name'],
                        'reason' => 'Alergi: ' . implode(', ', $labels),
                    ];
                }
            }

            // ── Texture mismatch ──────────────────────────────────
            if (! in_array($texture, $dish['suitableTextures'], true)) {
                $available = implode(', ', array_map(
                    fn ($t) => self::TEXTURE_LABELS[$t] ?? $t,
                    $dish['suitableTextures']
                ));
                $textureLabel = self::TEXTURE_LABELS[$texture] ?? $texture;
                $key = $dish['name'] . 'texture';
                if (! isset($seen[$key])) {
                    $seen[$key] = true;
                    $flags[] = [
                        'type'   => 'texture',
                        'dish'   => $dish['name'],
                        'reason' => "Tekstur {$textureLabel} tidak didukung (tersedia: {$available})",
                    ];
                }
            }

            // ── High sodium — CKD only ────────────────────────────
            if ($diagnosis === 'ckd' && $dish['sodiumMg'] > 200) {
                $key = $dish['name'] . 'sodium';
                if (! isset($seen[$key])) {
                    $seen[$key] = true;
                    $flags[] = [
                        'type'   => 'sodium',
                        'dish'   => $dish['name'],
                        'reason' => "Sodium {$dish['sodiumMg']}mg per porsi — melebihi batas aman CKD",
                    ];
                }
            }
        }

        return $flags;
    }

    // ─────────────────────────────────────────────────────────────────
    // DIETARY REVIEW STATUS
    // Mirrors getDietaryReviewStatus() in script.js
    // ─────────────────────────────────────────────────────────────────

    /**
     * Determine the overall dietary review status for the dashboard KPI.
     *
     * Tolerance for energy: ±20% of target.
     *
     * @param  array $totals
     * @param  array $guardrail
     * @return array  { key, label }
     */
    public function getDietaryReviewStatus(array $totals, array $guardrail): array
    {
        $sodiumOk = $totals['sodium'] <= $guardrail['sodiumMgMax'];
        $sugarOk  = $totals['sugar']  <= $guardrail['sugarGMax'];
        $calOk    = abs($totals['calories'] - $guardrail['energyKcalTarget'])
                        <= $guardrail['energyKcalTarget'] * 0.20;

        if ($sodiumOk && $sugarOk && $calOk) {
            return ['key' => 'ok',    'label' => 'Within Configured Limits'];
        }
        if (! $sodiumOk || ! $sugarOk) {
            return ['key' => 'error', 'label' => 'Restriction Conflict'];
        }

        return ['key' => 'warn', 'label' => 'Needs Review'];
    }

    // ─────────────────────────────────────────────────────────────────
    // COOKING METHODS
    // ─────────────────────────────────────────────────────────────────

    /**
     * Return safe cooking methods for a given texture.
     *
     * @param  string $texture
     * @return string[]
     */
    public function getCookingMethods(string $texture): array
    {
        return self::COOKING_METHODS[$texture] ?? self::COOKING_METHODS['normal'];
    }

    // ─────────────────────────────────────────────────────────────────
    // UTILITY
    // ─────────────────────────────────────────────────────────────────

    /**
     * Scale a nutrient value proportionally to the actual portion size.
     *
     * @param  float $baseValue    Nutrient amount at base serving size
     * @param  float $baseGrams    Base serving size in grams
     * @param  float $portionGrams Actual portion size in grams
     * @return float
     */
    private function scaleNutrient(float $baseValue, float $baseGrams, float $portionGrams): float
    {
        if ($baseGrams <= 0) {
            return 0.0;
        }

        return round($baseValue * ($portionGrams / $baseGrams), 2);
    }
}
