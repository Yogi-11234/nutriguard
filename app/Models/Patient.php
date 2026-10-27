<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Patient
 *
 * Represents a hospital patient's clinical dietary profile.
 *
 * @property int         $id
 * @property string      $pid             Synthetic patient ID (e.g. NG-2025-1042)
 * @property string      $name
 * @property int         $age
 * @property string      $diagnosis       dm2 | htn | ckd | other
 * @property string      $texture         normal | soft | puree | liquid
 * @property array       $allergies       JSON array of allergen keys
 * @property string|null $ward
 * @property string|null $bed
 * @property string|null $notes
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Patient extends Model
{
    use HasFactory;

    protected $fillable = [
        'pid',
        'name',
        'age',
        'diagnosis',
        'texture',
        'allergies',
        'ward',
        'bed',
        'notes',
    ];

    protected $casts = [
        'age'       => 'integer',
        'allergies' => 'array', // JSON column → PHP array auto-cast
    ];

    // ─────────────────────────────────────────────────────────────────
    // RELATIONSHIPS
    // ─────────────────────────────────────────────────────────────────

    /**
     * A patient may have many saved meal plans over time.
     */
    public function mealPlans(): HasMany
    {
        return $this->hasMany(MealPlan::class);
    }

    // ─────────────────────────────────────────────────────────────────
    // CONSTANTS
    // ─────────────────────────────────────────────────────────────────

    public const DIAGNOSES = ['dm2', 'htn', 'ckd', 'other'];
    public const TEXTURES  = ['normal', 'soft', 'puree', 'liquid'];
    public const ALLERGENS = ['seafood', 'nuts', 'egg', 'milk', 'soy'];

    // ─────────────────────────────────────────────────────────────────
    // ACCESSORS
    // ─────────────────────────────────────────────────────────────────

    /**
     * Human-readable diagnosis label.
     */
    public function getDiagnosisLabelAttribute(): string
    {
        return match ($this->diagnosis) {
            'dm2'   => 'Diabetes Melitus Tipe 2',
            'htn'   => 'Hipertensi',
            'ckd'   => 'Penyakit Ginjal Kronis',
            default => 'Lainnya',
        };
    }

    /**
     * Human-readable texture label.
     */
    public function getTextureLabelAttribute(): string
    {
        return match ($this->texture) {
            'normal' => 'Biasa',
            'soft'   => 'Lunak/Bubur',
            'puree'  => 'Saring',
            'liquid' => 'Cair',
            default  => $this->texture,
        };
    }
}
