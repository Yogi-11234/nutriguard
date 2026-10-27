<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * MealPlan
 *
 * Stores a saved 1-day meal plan for a patient.
 * Links a patient to their daily schedule and computed nutritional totals.
 *
 * @property int         $id
 * @property int         $patient_id
 * @property string      $diagnosis_snapshot  Diagnosis at time of plan creation
 * @property array       $schedule            JSON: [{mealTime, dishId, portionGrams, compliance}]
 * @property array       $totals              JSON: {calories, protein, sodium, sugar, carbs, fat}
 * @property array       $guardrail_snapshot  JSON: guardrail config at time of plan
 * @property string      $review_status       ok | warn | error
 * @property string|null $notes
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class MealPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'diagnosis_snapshot',
        'schedule',
        'totals',
        'guardrail_snapshot',
        'review_status',
        'notes',
    ];

    protected $casts = [
        'schedule'           => 'array',
        'totals'             => 'array',
        'guardrail_snapshot' => 'array',
    ];

    // ─────────────────────────────────────────────────────────────────
    // RELATIONSHIPS
    // ─────────────────────────────────────────────────────────────────

    /**
     * The patient this meal plan belongs to.
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    // ─────────────────────────────────────────────────────────────────
    // CONSTANTS
    // ─────────────────────────────────────────────────────────────────

    public const REVIEW_STATUSES = ['ok', 'warn', 'error'];
}
