<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * FoodItem
 *
 * Represents a dish in the NutriGuard AI food database.
 * In production, this Eloquent model backs the `food_items` table
 * populated by a DatabaseSeeder from FoodItemRepository data.
 *
 * @property int    $id
 * @property string $dish_id         e.g. 'D01'
 * @property string $name
 * @property array  $meal_types      JSON: ['breakfast', 'lunch', ...]
 * @property float  $serving_grams
 * @property float  $calories_kcal
 * @property float  $protein_grams
 * @property float  $sodium_mg
 * @property float  $sugar_grams
 * @property float  $carbs_grams
 * @property float  $fat_grams
 * @property array  $ingredients     JSON array
 * @property array  $allergens       JSON array
 * @property array  $suitable_textures JSON array
 * @property array  $dietary_tags    JSON array
 * @property string $source_type
 * @property string $source_note
 */
class FoodItem extends Model
{
    use HasFactory;

    protected $table    = 'food_items';
    public    $timestamps = false; // Static reference data, no timestamps needed

    protected $fillable = [
        'dish_id', 'name', 'meal_types', 'serving_grams',
        'calories_kcal', 'protein_grams', 'sodium_mg', 'sugar_grams',
        'carbs_grams', 'fat_grams', 'ingredients', 'allergens',
        'suitable_textures', 'dietary_tags', 'source_type', 'source_note',
    ];

    protected $casts = [
        'meal_types'        => 'array',
        'ingredients'       => 'array',
        'allergens'         => 'array',
        'suitable_textures' => 'array',
        'dietary_tags'      => 'array',
        'serving_grams'     => 'float',
        'calories_kcal'     => 'float',
        'protein_grams'     => 'float',
        'sodium_mg'         => 'float',
        'sugar_grams'       => 'float',
        'carbs_grams'       => 'float',
        'fat_grams'         => 'float',
    ];

    /**
     * Convert an Eloquent FoodItem to the array shape
     * expected by DietaryGuardrailService.
     */
    public function toServiceArray(): array
    {
        return [
            'id'               => $this->dish_id,
            'name'             => $this->name,
            'mealTypes'        => $this->meal_types,
            'servingGrams'     => $this->serving_grams,
            'caloriesKcal'     => $this->calories_kcal,
            'proteinGrams'     => $this->protein_grams,
            'sodiumMg'         => $this->sodium_mg,
            'sugarGrams'       => $this->sugar_grams,
            'carbsGrams'       => $this->carbs_grams,
            'fatGrams'         => $this->fat_grams,
            'ingredients'      => $this->ingredients,
            'allergens'        => $this->allergens,
            'suitableTextures' => $this->suitable_textures,
            'dietaryTags'      => $this->dietary_tags,
            'sourceType'       => $this->source_type,
            'sourceNote'       => $this->source_note,
        ];
    }
}
