<?php

declare(strict_types=1);

namespace App\Services;

/**
 * CsvPatientParserService
 *
 * Parses an uploaded CSV file of patient records into a normalized
 * PHP array suitable for the BatchClusteringService.
 *
 * Supports:
 *   — Auto-detection of delimiter (comma or semicolon)
 *   — Flexible header name aliases (Indonesian & English)
 *   — Fuzzy diagnosis and texture normalization
 *   — Multi-allergen parsing from a single cell
 *
 * Mirrors parsePatientCSV() in script.js.
 *
 * @package App\Services
 */
class CsvPatientParserService
{
    /** @var array<string, string[]> Column name aliases */
    private const COL_MAP = [
        'pid'       => ['pid', 'id', 'patient_id', 'id_pasien', 'no_pasien'],
        'name'      => ['name', 'nama', 'nama_pasien', 'patient_name'],
        'age'       => ['age', 'usia', 'umur'],
        'diagnosis' => ['diagnosis', 'diagnosa', 'diagnosis_utama'],
        'texture'   => ['texture', 'tekstur', 'tekstur_makanan'],
        'allergies' => ['allergies', 'alergi', 'allergen'],
        'ward'      => ['ward', 'bangsal', 'ruangan', 'room'],
        'bed'       => ['bed', 'tempat_tidur', 'kamar'],
        'notes'     => ['notes', 'catatan', 'keterangan'],
    ];

    /** @var array<string, string> */
    private const DIAG_MAP = [
        'diabetes melitus tipe 2' => 'dm2', 'dm2' => 'dm2',
        'dm' => 'dm2', 'diabetes' => 'dm2', 'dm tipe 2' => 'dm2',
        'hipertensi' => 'htn', 'htn' => 'htn', 'hypertension' => 'htn',
        'penyakit ginjal kronis' => 'ckd', 'ckd' => 'ckd',
        'gagal ginjal' => 'ckd', 'chronic kidney disease' => 'ckd',
        'lainnya' => 'other', 'other' => 'other',
        'umum' => 'other', 'general' => 'other',
    ];

    /** @var array<string, string> */
    private const TEXTURE_MAP = [
        'biasa' => 'normal', 'normal' => 'normal', 'regular' => 'normal',
        'lunak' => 'soft', 'bubur' => 'soft', 'soft' => 'soft', 'lunak/bubur' => 'soft',
        'saring' => 'puree', 'puree' => 'puree', 'blender' => 'puree',
        'cair' => 'liquid', 'liquid' => 'liquid',
    ];

    /**
     * Allergen keyword aliases.
     * @var array<string, string[]>
     */
    private const ALLERGEN_ALIASES = [
        'seafood' => ['seafood', 'udang', 'ikan'],
        'nuts'    => ['kacang', 'nuts'],
        'egg'     => ['telur', 'egg'],
        'milk'    => ['susu', 'milk', 'dairy'],
        'soy'     => ['kedelai', 'soy', 'tempe'],
    ];

    // ─────────────────────────────────────────────────────────────────
    // PUBLIC API
    // ─────────────────────────────────────────────────────────────────

    /**
     * Parse raw CSV text into an array of normalized patient arrays.
     *
     * @param  string $rawCsv
     * @return array
     * @throws \InvalidArgumentException on malformed CSV
     */
    public function parse(string $rawCsv): array
    {
        $lines = preg_split('/\r?\n/', trim($rawCsv));

        if (count($lines) < 2) {
            throw new \InvalidArgumentException(
                'File CSV harus memiliki header dan minimal 1 baris data.'
            );
        }

        // Auto-detect delimiter
        $delimiter = str_contains($lines[0], ';') ? ';' : ',';

        // Parse and normalise headers
        $headers = array_map(
            fn (string $h) => strtolower(trim(str_replace(['"', "'"], '', $h))),
            str_getcsv($lines[0], $delimiter)
        );

        // Build column index map
        $colIndex = $this->buildColumnIndex($headers);

        // Validate required columns
        if ($colIndex['name'] === -1) {
            throw new \InvalidArgumentException('Kolom "name" atau "nama" tidak ditemukan dalam CSV.');
        }
        if ($colIndex['diagnosis'] === -1) {
            throw new \InvalidArgumentException('Kolom "diagnosis" atau "diagnosa" tidak ditemukan dalam CSV.');
        }

        $patients = [];
        $rowNum   = 0;

        for ($i = 1; $i < count($lines); $i++) {
            $line = trim($lines[$i]);
            if ($line === '') {
                continue;
            }

            $cells = str_getcsv($line, $delimiter);
            $get   = fn (string $key): string => isset($colIndex[$key], $cells[$colIndex[$key]])
                ? trim($cells[$colIndex[$key]])
                : '';

            $rawDiag    = strtolower($get('diagnosis'));
            $rawTexture = strtolower($get('texture'));
            $diagnosis  = self::DIAG_MAP[$rawDiag] ?? 'other';
            $texture    = self::TEXTURE_MAP[$rawTexture] ?? 'normal';
            $age        = (int) ($get('age') ?: 45);
            $ward       = $get('ward') ?: 'Bangsal Umum';
            $bed        = $get('bed') ?: "{$ward} / Bed {$i}";

            $patients[] = [
                'pid'       => $get('pid') ?: sprintf('CSV-%03d', ++$rowNum),
                'name'      => $get('name') ?: "Pasien {$i}",
                'age'       => $age,
                'diagnosis' => $diagnosis,
                'texture'   => $texture,
                'allergies' => $this->parseAllergies($get('allergies')),
                'ward'      => $ward,
                'bed'       => $bed,
                'notes'     => $get('notes'),
            ];
        }

        if (empty($patients)) {
            throw new \InvalidArgumentException('Tidak ada data pasien yang valid dalam file CSV.');
        }

        return $patients;
    }

    // ─────────────────────────────────────────────────────────────────
    // PRIVATE HELPERS
    // ─────────────────────────────────────────────────────────────────

    /**
     * Map each logical field name to its column index in the header row.
     *
     * @param  string[] $headers
     * @return array<string, int>  -1 if not found
     */
    private function buildColumnIndex(array $headers): array
    {
        $index = [];

        foreach (self::COL_MAP as $field => $aliases) {
            $index[$field] = -1;
            foreach ($aliases as $alias) {
                $pos = array_search($alias, $headers, true);
                if ($pos !== false) {
                    $index[$field] = (int) $pos;
                    break;
                }
            }
        }

        return $index;
    }

    /**
     * Parse a raw allergen cell string into an array of canonical allergen keys.
     *
     * Examples:
     *   "udang, susu"     → ['seafood', 'milk']
     *   "tidak ada"       → []
     *   ""                → []
     *
     * @param  string $raw
     * @return string[]
     */
    private function parseAllergies(string $raw): array
    {
        $normalized = strtolower(trim($raw));

        if ($normalized === '' || $normalized === 'tidak ada' || $normalized === '-') {
            return [];
        }

        $found = [];
        foreach (self::ALLERGEN_ALIASES as $key => $aliases) {
            foreach ($aliases as $alias) {
                if (str_contains($normalized, $alias)) {
                    $found[] = $key;
                    break; // Don't double-add same allergen key
                }
            }
        }

        return $found;
    }
}
