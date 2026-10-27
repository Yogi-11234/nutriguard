<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: create_meal_plans_table
 *
 * Stores the result of a single-patient dietary analysis.
 * Each row represents a complete 1-day meal plan snapshot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meal_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();

            // Snapshot of diagnosis at analysis time (may differ from patient.diagnosis
            // if updated later)
            $table->enum('diagnosis_snapshot', ['dm2', 'htn', 'ckd', 'other']);

            // Full 5-slot meal schedule:
            // [{ mealTime, dishId, dishName, portionGrams, compliance }, ...]
            $table->json('schedule');

            // Aggregated daily nutritional totals
            // { calories, protein, sodium, sugar, carbs, fat }
            $table->json('totals');

            // Complete guardrail config used for this analysis
            // { sodiumMgMax, sugarGMax, energyKcalTarget, proteinGMin, ... }
            $table->json('guardrail_snapshot');

            // Overall dietary review status
            $table->enum('review_status', ['ok', 'warn', 'error'])->default('warn');

            $table->text('notes')->nullable();

            $table->timestamps();

            // Index for fetching a patient's latest plan
            $table->index(['patient_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meal_plans');
    }
};
