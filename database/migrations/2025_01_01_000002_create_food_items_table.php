<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: create_food_items_table
 *
 * Static reference table for the 18 local Indonesian dishes.
 * Populated by DatabaseSeeder via FoodItemRepository data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('food_items', function (Blueprint $table) {
            $table->id();

            // External ID matching script.js (D01–D18)
            $table->string('dish_id', 10)->unique()->index();
            $table->string('name', 100);

            // JSON arrays
            $table->json('meal_types');       // ['breakfast', 'lunch', ...]
            $table->json('ingredients');
            $table->json('allergens');
            $table->json('suitable_textures');
            $table->json('dietary_tags');

            // Nutritional values (per base serving)
            $table->decimal('serving_grams', 7, 2);
            $table->decimal('calories_kcal', 7, 2);
            $table->decimal('protein_grams', 6, 2);
            $table->decimal('sodium_mg',     7, 2);
            $table->decimal('sugar_grams',   6, 2);
            $table->decimal('carbs_grams',   6, 2);
            $table->decimal('fat_grams',     6, 2);

            $table->string('source_type', 20)->default('local');
            $table->text('source_note')->nullable();

            // No updated_at — static reference data
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('food_items');
    }
};
