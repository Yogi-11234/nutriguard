<?php

declare(strict_types=1);

namespace App\Repositories;

/**
 * FoodItemRepository
 *
 * In-memory repository for the NutriGuard AI dish database.
 * Contains 18 local Indonesian dishes with full nutritional profiles.
 *
 * In a production environment, replace this with an Eloquent-backed
 * implementation backed by the `food_items` table (see FoodItem model).
 * This repository is injected via Laravel's service container and can be
 * swapped via repository binding in AppServiceProvider.
 *
 * Data mirrors DISH_DB in script.js exactly.
 *
 * @package App\Repositories
 */
class FoodItemRepository
{
    /**
     * Full dish database.
     * Cached in-memory after first call.
     *
     * @var array<int, array<string, mixed>>|null
     */
    private ?array $dishes = null;

    /**
     * Return all dishes.
     *
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        if ($this->dishes === null) {
            $this->dishes = $this->seed();
        }

        return $this->dishes;
    }

    /**
     * Find a single dish by its ID (e.g. 'D01').
     *
     * @param  string $id
     * @return array|null
     */
    public function findById(string $id): ?array
    {
        foreach ($this->all() as $dish) {
            if ($dish['id'] === $id) {
                return $dish;
            }
        }

        return null;
    }

    /**
     * Return dishes filtered by meal time slot.
     *
     * @param  string $mealTime  e.g. 'breakfast', 'lunch'
     * @return array
     */
    public function forMealTime(string $mealTime): array
    {
        return array_values(array_filter(
            $this->all(),
            fn (array $d) => in_array($mealTime, $d['mealTypes'], true)
        ));
    }

    // ─────────────────────────────────────────────────────────────────
    // SEED DATA
    // ─────────────────────────────────────────────────────────────────

    /**
     * Returns the static dish dataset (18 Indonesian dishes).
     *
     * Keys match the JavaScript DISH_DB properties 1:1:
     *   id, name, mealTypes, servingGrams, caloriesKcal,
     *   proteinGrams, sodiumMg, sugarGrams, carbsGrams, fatGrams,
     *   ingredients, allergens, suitableTextures, dietaryTags,
     *   sourceType, sourceNote
     *
     * @return array
     */
    private function seed(): array
    {
        return [
            [
                'id'              => 'D01',
                'name'            => 'Nasi Tim Ayam Bening',
                'mealTypes'       => ['breakfast', 'lunch', 'dinner'],
                'servingGrams'    => 200.0,
                'caloriesKcal'    => 215.0,
                'proteinGrams'    => 14.2,
                'sodiumMg'        => 180.0,
                'sugarGrams'      => 1.2,
                'carbsGrams'      => 32.5,
                'fatGrams'        => 3.8,
                'ingredients'     => ['beras', 'ayam kampung', 'bawang putih', 'jahe', 'garam rendah sodium'],
                'allergens'       => [],
                'suitableTextures'=> ['normal', 'soft'],
                'dietaryTags'     => ['rendah-lemak', 'tinggi-protein'],
                'sourceType'      => 'local',
                'sourceNote'      => 'Masakan tradisional Betawi, cocok untuk diet rendah lemak',
            ],
            [
                'id'              => 'D02',
                'name'            => 'Bubur Ayam Kuning',
                'mealTypes'       => ['breakfast', 'snack_morning'],
                'servingGrams'    => 250.0,
                'caloriesKcal'    => 185.0,
                'proteinGrams'    => 12.0,
                'sodiumMg'        => 210.0,
                'sugarGrams'      => 0.8,
                'carbsGrams'      => 26.5,
                'fatGrams'        => 3.5,
                'ingredients'     => ['beras', 'ayam', 'kunyit', 'jahe', 'garam', 'seledri'],
                'allergens'       => [],
                'suitableTextures'=> ['soft', 'puree'],
                'dietaryTags'     => ['rendah-lemak', 'mudah-dicerna'],
                'sourceType'      => 'local',
                'sourceNote'      => 'Cocok untuk pasien tekstur lunak/bubur',
            ],
            [
                'id'              => 'D03',
                'name'            => 'Sop Ayam Bening',
                'mealTypes'       => ['lunch', 'dinner'],
                'servingGrams'    => 300.0,
                'caloriesKcal'    => 145.0,
                'proteinGrams'    => 16.8,
                'sodiumMg'        => 160.0,
                'sugarGrams'      => 1.5,
                'carbsGrams'      => 10.2,
                'fatGrams'        => 3.0,
                'ingredients'     => ['ayam', 'wortel', 'kentang', 'buncis', 'bawang', 'garam'],
                'allergens'       => [],
                'suitableTextures'=> ['normal', 'soft', 'liquid'],
                'dietaryTags'     => ['rendah-kalori', 'tinggi-protein', 'rendah-sodium'],
                'sourceType'      => 'local',
                'sourceNote'      => 'Alternatif protein rendah sodium untuk semua kondisi',
            ],
            [
                'id'              => 'D04',
                'name'            => 'Pepes Tahu Kukus',
                'mealTypes'       => ['lunch', 'dinner'],
                'servingGrams'    => 150.0,
                'caloriesKcal'    => 135.0,
                'proteinGrams'    => 10.5,
                'sodiumMg'        => 145.0,
                'sugarGrams'      => 0.5,
                'carbsGrams'      => 8.2,
                'fatGrams'        => 6.5,
                'ingredients'     => ['tahu', 'daun kemangi', 'tomat', 'bawang', 'cabe merah (sedikit)', 'daun pisang'],
                'allergens'       => ['soy'],
                'suitableTextures'=> ['normal', 'soft'],
                'dietaryTags'     => ['vegetarian', 'rendah-sodium', 'protein-nabati'],
                'sourceType'      => 'local',
                'sourceNote'      => 'Mengandung kedelai — cek alergi pasien',
            ],
            [
                'id'              => 'D05',
                'name'            => 'Sayur Bening Bayam Jagung',
                'mealTypes'       => ['lunch', 'dinner'],
                'servingGrams'    => 200.0,
                'caloriesKcal'    => 75.0,
                'proteinGrams'    => 3.8,
                'sodiumMg'        => 90.0,
                'sugarGrams'      => 3.2,
                'carbsGrams'      => 13.5,
                'fatGrams'        => 0.8,
                'ingredients'     => ['bayam', 'jagung manis', 'bawang putih', 'garam sedikit'],
                'allergens'       => [],
                'suitableTextures'=> ['normal', 'soft', 'liquid'],
                'dietaryTags'     => ['rendah-kalori', 'rendah-sodium', 'tinggi-serat', 'vegetarian'],
                'sourceType'      => 'local',
                'sourceNote'      => 'Sayur bening rendah sodium, kaya serat',
            ],
            [
                'id'              => 'D06',
                'name'            => 'Tumis Labu Siam Rendah Garam',
                'mealTypes'       => ['lunch', 'dinner'],
                'servingGrams'    => 150.0,
                'caloriesKcal'    => 65.0,
                'proteinGrams'    => 1.5,
                'sodiumMg'        => 70.0,
                'sugarGrams'      => 2.0,
                'carbsGrams'      => 11.0,
                'fatGrams'        => 1.8,
                'ingredients'     => ['labu siam', 'bawang merah', 'bawang putih', 'minyak zaitun', 'garam sedikit'],
                'allergens'       => [],
                'suitableTextures'=> ['normal', 'soft'],
                'dietaryTags'     => ['rendah-kalori', 'rendah-sodium', 'vegetarian'],
                'sourceType'      => 'local',
                'sourceNote'      => 'Sangat rendah sodium, cocok untuk hipertensi & CKD',
            ],
            [
                'id'              => 'D07',
                'name'            => 'Bubur Manado (Tinutuan)',
                'mealTypes'       => ['breakfast', 'snack_morning'],
                'servingGrams'    => 300.0,
                'caloriesKcal'    => 165.0,
                'proteinGrams'    => 5.2,
                'sodiumMg'        => 130.0,
                'sugarGrams'      => 1.0,
                'carbsGrams'      => 30.0,
                'fatGrams'        => 2.5,
                'ingredients'     => ['beras', 'singkong', 'labu kuning', 'bayam', 'daun kemangi', 'garam'],
                'allergens'       => [],
                'suitableTextures'=> ['soft', 'puree'],
                'dietaryTags'     => ['tinggi-serat', 'rendah-lemak', 'rendah-sodium'],
                'sourceType'      => 'local',
                'sourceNote'      => 'Bubur khas Manado kaya sayuran, rendah kalori',
            ],
            [
                'id'              => 'D08',
                'name'            => 'Ikan Kukus Bumbu Kuning',
                'mealTypes'       => ['lunch', 'dinner'],
                'servingGrams'    => 180.0,
                'caloriesKcal'    => 195.0,
                'proteinGrams'    => 24.5,
                'sodiumMg'        => 200.0,
                'sugarGrams'      => 0.6,
                'carbsGrams'      => 4.5,
                'fatGrams'        => 7.0,
                'ingredients'     => ['ikan kakap', 'kunyit', 'jahe', 'bawang', 'jeruk nipis', 'daun salam'],
                'allergens'       => ['seafood'],
                'suitableTextures'=> ['normal', 'soft'],
                'dietaryTags'     => ['tinggi-protein', 'rendah-karbo', 'omega-3'],
                'sourceType'      => 'local',
                'sourceNote'      => 'Protein hewani tinggi — cek alergi seafood',
            ],
            [
                'id'              => 'D09',
                'name'            => 'Tempe Bacem Rebus',
                'mealTypes'       => ['breakfast', 'lunch', 'snack_morning'],
                'servingGrams'    => 100.0,
                'caloriesKcal'    => 155.0,
                'proteinGrams'    => 11.0,
                'sodiumMg'        => 155.0,
                'sugarGrams'      => 4.5,
                'carbsGrams'      => 14.0,
                'fatGrams'        => 6.0,
                'ingredients'     => ['tempe', 'gula merah (sedikit)', 'ketumbar', 'bawang putih', 'lengkuas'],
                'allergens'       => ['soy'],
                'suitableTextures'=> ['normal', 'soft'],
                'dietaryTags'     => ['protein-nabati', 'fermentasi'],
                'sourceType'      => 'local',
                'sourceNote'      => 'Mengandung kedelai — cek alergi. Gula rendah bacem rebus',
            ],
            [
                'id'              => 'D10',
                'name'            => 'Jus Alpukat Susu Rendah Lemak',
                'mealTypes'       => ['snack_morning', 'snack_afternoon'],
                'servingGrams'    => 200.0,
                'caloriesKcal'    => 145.0,
                'proteinGrams'    => 3.5,
                'sodiumMg'        => 55.0,
                'sugarGrams'      => 8.5,
                'carbsGrams'      => 12.0,
                'fatGrams'        => 9.0,
                'ingredients'     => ['alpukat', 'susu rendah lemak', 'sedikit madu'],
                'allergens'       => ['milk'],
                'suitableTextures'=> ['liquid', 'puree'],
                'dietaryTags'     => ['rendah-sodium', 'lemak-baik'],
                'sourceType'      => 'local',
                'sourceNote'      => 'Mengandung susu — cek alergi. Cocok untuk tekstur cair',
            ],
            [
                'id'              => 'D11',
                'name'            => 'Bubur Sum-Sum Santan Rendah',
                'mealTypes'       => ['snack_morning', 'snack_afternoon'],
                'servingGrams'    => 150.0,
                'caloriesKcal'    => 120.0,
                'proteinGrams'    => 1.8,
                'sodiumMg'        => 45.0,
                'sugarGrams'      => 6.0,
                'carbsGrams'      => 22.0,
                'fatGrams'        => 2.5,
                'ingredients'     => ['tepung beras', 'santan encer', 'gula aren (sedikit)', 'daun pandan'],
                'allergens'       => [],
                'suitableTextures'=> ['soft', 'puree', 'liquid'],
                'dietaryTags'     => ['rendah-sodium', 'rendah-protein', 'vegetarian'],
                'sourceType'      => 'local',
                'sourceNote'      => 'Cocok untuk tekstur lunak/saring, rendah sodium',
            ],
            [
                'id'              => 'D12',
                'name'            => 'Sup Tahu Telur Rebus',
                'mealTypes'       => ['lunch', 'dinner'],
                'servingGrams'    => 250.0,
                'caloriesKcal'    => 175.0,
                'proteinGrams'    => 13.5,
                'sodiumMg'        => 190.0,
                'sugarGrams'      => 1.0,
                'carbsGrams'      => 9.0,
                'fatGrams'        => 8.5,
                'ingredients'     => ['tahu', 'telur ayam rebus', 'wortel', 'buncis', 'bawang', 'garam'],
                'allergens'       => ['soy', 'egg'],
                'suitableTextures'=> ['normal', 'soft'],
                'dietaryTags'     => ['tinggi-protein', 'vegetarian'],
                'sourceType'      => 'local',
                'sourceNote'      => 'Mengandung kedelai & telur — cek alergi',
            ],
            [
                'id'              => 'D13',
                'name'            => 'Nasi Merah Tim',
                'mealTypes'       => ['lunch', 'dinner'],
                'servingGrams'    => 180.0,
                'caloriesKcal'    => 195.0,
                'proteinGrams'    => 4.2,
                'sodiumMg'        => 10.0,
                'sugarGrams'      => 0.5,
                'carbsGrams'      => 40.5,
                'fatGrams'        => 1.2,
                'ingredients'     => ['beras merah'],
                'allergens'       => [],
                'suitableTextures'=> ['normal', 'soft'],
                'dietaryTags'     => ['tinggi-serat', 'rendah-gi', 'rendah-sodium'],
                'sourceType'      => 'local',
                'sourceNote'      => 'Indeks glikemik rendah, pilihan karbohidrat DM Tipe 2',
            ],
            [
                'id'              => 'D14',
                'name'            => 'Puding Susu Maizena',
                'mealTypes'       => ['snack_afternoon'],
                'servingGrams'    => 120.0,
                'caloriesKcal'    => 110.0,
                'proteinGrams'    => 3.0,
                'sodiumMg'        => 50.0,
                'sugarGrams'      => 9.0,
                'carbsGrams'      => 18.0,
                'fatGrams'        => 2.5,
                'ingredients'     => ['susu rendah lemak', 'maizena', 'gula (sedikit)', 'vanila'],
                'allergens'       => ['milk'],
                'suitableTextures'=> ['soft', 'puree', 'liquid'],
                'dietaryTags'     => ['rendah-sodium', 'mudah-dicerna'],
                'sourceType'      => 'local',
                'sourceNote'      => 'Mengandung susu. Cocok untuk tekstur saring/cair',
            ],
            [
                'id'              => 'D15',
                'name'            => 'Ayam Panggang Rempah Tanpa Kulit',
                'mealTypes'       => ['lunch', 'dinner'],
                'servingGrams'    => 150.0,
                'caloriesKcal'    => 220.0,
                'proteinGrams'    => 28.0,
                'sodiumMg'        => 175.0,
                'sugarGrams'      => 1.0,
                'carbsGrams'      => 3.0,
                'fatGrams'        => 9.5,
                'ingredients'     => ['ayam tanpa kulit', 'kunyit', 'ketumbar', 'jahe', 'bawang putih', 'air jeruk nipis'],
                'allergens'       => [],
                'suitableTextures'=> ['normal'],
                'dietaryTags'     => ['tinggi-protein', 'rendah-karbo'],
                'sourceType'      => 'local',
                'sourceNote'      => 'Panggang rendah lemak, cocok untuk tekstur biasa',
            ],
            [
                'id'              => 'D16',
                'name'            => 'Kolak Pisang Rendah Gula',
                'mealTypes'       => ['snack_afternoon'],
                'servingGrams'    => 200.0,
                'caloriesKcal'    => 135.0,
                'proteinGrams'    => 1.2,
                'sodiumMg'        => 30.0,
                'sugarGrams'      => 12.0,
                'carbsGrams'      => 28.5,
                'fatGrams'        => 2.8,
                'ingredients'     => ['pisang kepok', 'santan encer', 'gula aren (minimal)', 'daun pandan', 'kayu manis'],
                'allergens'       => [],
                'suitableTextures'=> ['normal', 'soft', 'liquid'],
                'dietaryTags'     => ['rendah-sodium', 'vegetarian'],
                'sourceType'      => 'local',
                'sourceNote'      => 'Gula perlu diperhatikan untuk pasien DM',
            ],
            [
                'id'              => 'D17',
                'name'            => 'Perkedel Kentang Kukus',
                'mealTypes'       => ['snack_morning', 'snack_afternoon'],
                'servingGrams'    => 100.0,
                'caloriesKcal'    => 130.0,
                'proteinGrams'    => 4.5,
                'sodiumMg'        => 140.0,
                'sugarGrams'      => 0.8,
                'carbsGrams'      => 20.5,
                'fatGrams'        => 3.5,
                'ingredients'     => ['kentang', 'bawang putih', 'garam (sedikit)', 'seledri', 'merica'],
                'allergens'       => [],
                'suitableTextures'=> ['normal', 'soft'],
                'dietaryTags'     => ['vegetarian', 'rendah-lemak'],
                'sourceType'      => 'local',
                'sourceNote'      => 'Perkedel kukus, lebih rendah lemak dari yang digoreng',
            ],
            [
                'id'              => 'D18',
                'name'            => 'Teh Herbal Jahe Lemon Hangat',
                'mealTypes'       => ['snack_morning', 'snack_afternoon'],
                'servingGrams'    => 250.0,
                'caloriesKcal'    => 25.0,
                'proteinGrams'    => 0.1,
                'sodiumMg'        => 5.0,
                'sugarGrams'      => 5.5,
                'carbsGrams'      => 6.0,
                'fatGrams'        => 0.0,
                'ingredients'     => ['jahe', 'lemon', 'air', 'madu (sedikit)'],
                'allergens'       => [],
                'suitableTextures'=> ['liquid'],
                'dietaryTags'     => ['rendah-kalori', 'rendah-sodium', 'bebas-allergen'],
                'sourceType'      => 'local',
                'sourceNote'      => 'Minuman antiinflamasi, sangat rendah kalori & sodium',
            ],
        ];
    }
}
