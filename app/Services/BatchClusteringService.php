<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\FoodItemRepository;

/**
 * BatchClusteringService
 *
 * Handles all batch patient clustering operations:
 *   — Deterministic 60-patient synthetic demo data generation
 *   — Patient-to-package assignment algorithm
 *   — Package summary statistics aggregation
 *   — CSV manifest generation for kitchen distribution
 *
 * Mirrors the BATCH PATIENT CLUSTERING MODULE in script.js.
 *
 * @package App\Services
 */
class BatchClusteringService
{
    // ─────────────────────────────────────────────────────────────────
    // MENU PACKAGE DEFINITIONS
    // Mirrors MENU_PACKAGES in script.js
    // ─────────────────────────────────────────────────────────────────

    /** @var array<int, array<string, mixed>> */
    private const MENU_PACKAGES = [
        1 => [
            'id'     => 1,
            'name'   => 'Paket Menu 1',
            'label'  => 'Diet Rendah Natrium & Lunak',
            'target' => 'Hipertensi / CKD / Post-Op / Tekstur Lunak',
            'dishes' => [
                'breakfast'       => 'D07', // Bubur Manado
                'snack_morning'   => 'D18', // Teh Herbal Jahe Lemon
                'lunch'           => 'D05', // Sayur Bening Bayam Jagung
                'snack_afternoon' => 'D11', // Bubur Sum-Sum
                'dinner'          => 'D03', // Sop Ayam Bening
            ],
        ],
        2 => [
            'id'     => 2,
            'name'   => 'Paket Menu 2',
            'label'  => 'Diet Rendah Gula & Karbo Kompleks',
            'target' => 'Diabetes Melitus Tipe 2',
            'dishes' => [
                'breakfast'       => 'D13', // Nasi Merah Tim
                'snack_morning'   => 'D17', // Perkedel Kukus
                'lunch'           => 'D15', // Ayam Panggang Rempah
                'snack_afternoon' => 'D18', // Teh Herbal Jahe Lemon
                'dinner'          => 'D03', // Sop Ayam Bening
            ],
        ],
        3 => [
            'id'     => 3,
            'name'   => 'Paket Menu 3',
            'label'  => 'Diet High Protein & Regular',
            'target' => 'Pemulihan Umum / Tanpa Restriksi Khusus',
            'dishes' => [
                'breakfast'       => 'D01', // Nasi Tim Ayam Bening
                'snack_morning'   => 'D09', // Tempe Bacem Rebus
                'lunch'           => 'D15', // Ayam Panggang Rempah
                'snack_afternoon' => 'D17', // Perkedel Kukus
                'dinner'          => 'D08', // Ikan Kukus Bumbu Kuning
            ],
        ],
    ];

    /** @var array<string, string> */
    private const ALLERGEN_LABELS = [
        'seafood' => 'Seafood/Udang', 'nuts' => 'Kacang',
        'egg'     => 'Telur',         'milk' => 'Susu', 'soy' => 'Kedelai',
    ];

    /** @var array<string, string> */
    private const TEXTURE_LABELS = [
        'normal' => 'Biasa', 'soft' => 'Lunak/Bubur',
        'puree'  => 'Saring', 'liquid' => 'Cair',
    ];

    /** @var array<string, string> */
    private const DIAG_LABELS = [
        'dm2'   => 'DM Tipe 2',
        'htn'   => 'Hipertensi',
        'ckd'   => 'CKD',
        'other' => 'Lainnya',
    ];

    public function __construct(
        private readonly FoodItemRepository $foodRepo,
    ) {}

    // ─────────────────────────────────────────────────────────────────
    // PUBLIC API
    // ─────────────────────────────────────────────────────────────────

    /**
     * Cluster an array of patient profiles into the 3 menu packages.
     *
     * @param  array $patients  Array of patient arrays
     * @return array  {
     *   total, packages: {1,2,3 => {count,fitFull,fitAdjust,dishNames}},
     *   clustered: [{patient, packageId, fitStatus, schedule}],
     *   stats: {totalFit, totalAdjust}
     * }
     */
    public function cluster(array $patients): array
    {
        $allDishes = $this->foodRepo->all();
        $clustered = [];

        $pkgCounts   = [1 => 0, 2 => 0, 3 => 0];
        $pkgFitFull  = [1 => 0, 2 => 0, 3 => 0];

        foreach ($patients as $patient) {
            // Normalise required fields with safe defaults
            $patient['texture']   ??= 'normal';
            $patient['allergies'] ??= [];

            [$packageId, $fitStatus] = $this->assignPackage($patient, $allDishes);

            $schedule = $this->buildPackageSchedule($packageId, $allDishes);

            $clustered[] = [
                'patient'   => $patient,
                'packageId' => $packageId,
                'fitStatus' => $fitStatus,
                'schedule'  => $schedule,
            ];

            $pkgCounts[$packageId]++;
            if ($fitStatus === 'full') {
                $pkgFitFull[$packageId]++;
            }
        }

        $total       = count($patients);
        $totalFit    = array_sum($pkgFitFull);
        $totalAdjust = $total - $totalFit;

        // Build per-package summary
        $packages = [];
        foreach ([1, 2, 3] as $pkgId) {
            $packages[$pkgId] = array_merge(self::MENU_PACKAGES[$pkgId], [
                'count'      => $pkgCounts[$pkgId],
                'fitFull'    => $pkgFitFull[$pkgId],
                'fitAdjust'  => $pkgCounts[$pkgId] - $pkgFitFull[$pkgId],
                'percentage' => $total > 0
                    ? round(($pkgCounts[$pkgId] / $total) * 100, 1)
                    : 0.0,
                'dishNames'  => $this->getPackageDishNames($pkgId, $allDishes),
            ]);
        }

        return [
            'total'     => $total,
            'packages'  => $packages,
            'clustered' => $clustered,
            'stats'     => [
                'totalFit'    => $totalFit,
                'totalAdjust' => $totalAdjust,
            ],
        ];
    }

    /**
     * Generate N deterministic synthetic demo patients.
     *
     * Replicates the seeded random logic from script.js generateDemoPatients()
     * using PHP's sin()-based seeding for cross-language reproducibility.
     *
     * @param  int $count  Default 60
     * @return array
     */
    public function generateDemoPatients(int $count = 60): array
    {
        $firstNames = [
            'Budi','Siti','Ahmad','Dewi','Rizki','Nur','Hendra','Rina','Agus','Wati',
            'Eko','Sri','Dian','Wahyu','Lina','Tono','Yuni','Bambang','Desi','Fajar',
            'Indah','Joko','Kartika','Lukman','Maya','Nanda','Oki','Putri','Qori','Rudi',
            'Sari','Taufik','Umar','Vina','Wawan','Xena','Yanto','Zahra','Andri','Bella',
            'Cahyo','Dita','Erwin','Fitri','Galih','Hani','Ivan','Julia','Kevin','Laila',
        ];
        $lastNames = [
            'Santoso','Rahayu','Hidayat','Permata','Pratama','Kusuma','Wijaya',
            'Saputra','Halim','Nuraini','Setiawan','Purnama','Siregar','Hakim',
            'Wahyudi','Sulistyo','Panjaitan','Budiman','Lestari','Firmansyah',
        ];
        $diagWeights    = array_merge(
            array_fill(0, 4, 'dm2'),
            array_fill(0, 6, 'htn'),
            array_fill(0, 3, 'ckd'),
            array_fill(0, 2, 'other')
        );
        $textureWeights = ['normal','normal','normal','normal','normal','soft','soft','soft','puree','liquid'];
        $allergyOptions = [[], ['seafood'], ['milk'], ['egg'], ['soy'], ['seafood','milk'], ['nuts']];

        $patients = [];
        for ($i = 0; $i < $count; $i++) {
            $fn       = $firstNames[$this->seededRand($i * 7, count($firstNames))];
            $ln       = $lastNames[$this->seededRand($i * 13, count($lastNames))];
            $age      = 35 + $this->seededRand($i * 3, 45);
            $diag     = $diagWeights[$this->seededRand($i * 5, count($diagWeights))];
            $texture  = $textureWeights[$this->seededRand($i * 11, count($textureWeights))];
            $allergies= $allergyOptions[$this->seededRand($i * 17, count($allergyOptions))];
            $wardChar = chr(65 + $this->seededRand($i * 19, 6)); // A–F
            $bed      = $this->seededRand($i * 23, 20) + 1;

            $patients[] = [
                'pid'       => sprintf('NG-2025-%d', 1000 + $i + 1),
                'name'      => "{$fn} {$ln}",
                'age'       => $age,
                'diagnosis' => $diag,
                'texture'   => $texture,
                'allergies' => $allergies,
                'notes'     => '',
                'ward'      => "Bangsal {$wardChar}",
                'bed'       => "Bangsal {$wardChar} / Bed {$bed}",
            ];
        }

        return $patients;
    }

    /**
     * Generate a UTF-8 BOM CSV manifest for kitchen distribution.
     *
     * @param  array $clustered
     * @return string  Raw CSV content with BOM
     */
    public function generateCsvManifest(array $clustered): string
    {
        $pkgNames = [
            1 => 'Paket Menu 1 - Rendah Natrium',
            2 => 'Paket Menu 2 - Rendah Gula',
            3 => 'Paket Menu 3 - High Protein',
        ];

        $rows   = [];
        $rows[] = implode(',', [
            '"ID Pasien"', '"Nama"', '"Usia"', '"Diagnosis"',
            '"Alergi"', '"Tekstur"', '"Bangsal/Bed"',
            '"Paket Menu"', '"Status Kesesuaian"',
        ]);

        foreach ($clustered as $c) {
            $p         = $c['patient'];
            $allergens = empty($p['allergies'])
                ? 'Tidak ada'
                : implode('; ', array_map(
                    fn ($a) => self::ALLERGEN_LABELS[$a] ?? $a,
                    $p['allergies']
                ));
            $texture   = self::TEXTURE_LABELS[$p['texture'] ?? 'normal'] ?? $p['texture'];
            $diag      = self::DIAG_LABELS[$p['diagnosis']] ?? $p['diagnosis'];

            $rows[] = implode(',', array_map(
                fn ($v) => '"' . str_replace('"', '""', (string) $v) . '"',
                [
                    $p['pid'] ?? '',
                    $p['name'],
                    $p['age'] ?? '',
                    $diag,
                    $allergens,
                    $texture,
                    $p['bed'] ?? ($p['ward'] ?? ''),
                    $pkgNames[$c['packageId']] ?? '',
                    $c['fitStatus'] === 'full' ? '100% Fit' : 'Butuh Penyesuaian',
                ]
            ));
        }

        // UTF-8 BOM prefix so Excel opens correctly
        return "\xEF\xBB\xBF" . implode("\r\n", $rows);
    }

    // ─────────────────────────────────────────────────────────────────
    // PRIVATE HELPERS
    // ─────────────────────────────────────────────────────────────────

    /**
     * Assign a patient to a menu package with fitStatus.
     *
     * Priority order: Package 1 (HTN/CKD/soft) → Package 2 (DM2) → Package 3 (other)
     * fitStatus = 'adjust' when any package dish conflicts with allergies or texture.
     *
     * @param  array $patient
     * @param  array $allDishes
     * @return array  [ packageId (int), fitStatus (string) ]
     */
    private function assignPackage(array $patient, array $allDishes): array
    {
        $diagnosis = $patient['diagnosis'];
        $texture   = $patient['texture'];
        $allergies = $patient['allergies'] ?? [];

        // Determine which package this patient matches (priority order)
        $matchedPackageId = null;

        // Package 1: HTN / CKD / soft textures
        if (in_array($diagnosis, ['htn', 'ckd'], true)
            || in_array($texture, ['soft', 'puree', 'liquid'], true)) {
            $matchedPackageId = 1;
        }
        // Package 2: DM2 (only if not already matched to Pkg 1)
        elseif ($diagnosis === 'dm2') {
            $matchedPackageId = 2;
        }
        // Package 3: General / other
        else {
            $matchedPackageId = 3;
        }

        // Determine fit status based on allergy and texture compatibility
        $fitStatus = $this->evaluateFitStatus($matchedPackageId, $patient, $allDishes);

        return [$matchedPackageId, $fitStatus];
    }

    /**
     * Check whether a package's dishes are all compatible with the patient.
     *
     * @param  int   $packageId
     * @param  array $patient
     * @param  array $allDishes  Indexed by dish id
     * @return string 'full' | 'adjust'
     */
    private function evaluateFitStatus(int $packageId, array $patient, array $allDishes): string
    {
        $pkg       = self::MENU_PACKAGES[$packageId];
        $allergies = $patient['allergies'] ?? [];
        $texture   = $patient['texture'];
        $dishMap   = array_column($allDishes, null, 'id');

        foreach ($pkg['dishes'] as $dishId) {
            $dish = $dishMap[$dishId] ?? null;
            if ($dish === null) {
                continue;
            }
            // Allergy conflict?
            if (! empty($allergies)) {
                foreach ($dish['allergens'] as $allergen) {
                    if (in_array($allergen, $allergies, true)) {
                        return 'adjust';
                    }
                }
            }
            // Texture mismatch?
            if (! in_array($texture, $dish['suitableTextures'], true)) {
                return 'adjust';
            }
        }

        return 'full';
    }

    /**
     * Build the schedule array for a given menu package.
     *
     * @param  int   $packageId
     * @param  array $allDishes
     * @return array  Array of { mealTime, dish, portionGrams }
     */
    private function buildPackageSchedule(int $packageId, array $allDishes): array
    {
        $pkg     = self::MENU_PACKAGES[$packageId];
        $dishMap = array_column($allDishes, null, 'id');
        $slots   = [];

        foreach ($pkg['dishes'] as $mealTime => $dishId) {
            $dish = $dishMap[$dishId] ?? null;
            if ($dish !== null) {
                $slots[] = [
                    'mealTime'     => $mealTime,
                    'dish'         => $dish,
                    'portionGrams' => $dish['servingGrams'],
                ];
            }
        }

        return $slots;
    }

    /**
     * Get display names for all dishes in a package (for summary cards).
     *
     * @param  int   $packageId
     * @param  array $allDishes
     * @return string[]
     */
    private function getPackageDishNames(int $packageId, array $allDishes): array
    {
        $mealLabels = [
            'breakfast'       => 'Sarapan',
            'snack_morning'   => 'Snack Pagi',
            'lunch'           => 'Makan Siang',
            'snack_afternoon' => 'Snack Sore',
            'dinner'          => 'Makan Malam',
        ];

        $pkg     = self::MENU_PACKAGES[$packageId];
        $dishMap = array_column($allDishes, null, 'id');
        $names   = [];

        foreach ($pkg['dishes'] as $mealTime => $dishId) {
            $dish  = $dishMap[$dishId] ?? null;
            $label = $mealLabels[$mealTime] ?? $mealTime;
            $names[] = $label . ': ' . ($dish['name'] ?? $dishId);
        }

        return $names;
    }

    /**
     * Seeded pseudo-random integer.
     *
     * Replicates the JavaScript seededRand() function:
     *   Math.floor((Math.abs(Math.sin(seed + 1)) * 10000 % 1) * max)
     *
     * Using PHP's sin() ensures identical output to the frontend.
     *
     * @param  int $seed
     * @param  int $max   Exclusive upper bound
     * @return int
     */
    private function seededRand(int $seed, int $max): int
    {
        $x = sin($seed + 1) * 10000;
        $frac = $x - floor($x);

        return (int) floor($frac * $max);
    }
}
