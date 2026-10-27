<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: create_patients_table
 *
 * Stores hospital patient dietary profiles for NutriGuard AI.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->id();

            // Synthetic patient identifier (e.g. NG-2025-1042)
            $table->string('pid', 30)->unique()->index();

            $table->string('name', 80);
            $table->unsignedSmallInteger('age');

            // Clinical dietary profile
            $table->enum('diagnosis', ['dm2', 'htn', 'ckd', 'other'])->index();
            $table->enum('texture', ['normal', 'soft', 'puree', 'liquid'])->default('normal');

            // Stored as JSON array: ['seafood', 'nuts', ...]
            $table->json('allergies')->nullable();

            // Ward/bed location for kitchen manifest
            $table->string('ward', 50)->nullable();
            $table->string('bed',  50)->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
