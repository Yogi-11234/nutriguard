<?php

/**
 * NutriGuard AI — API Routes
 *
 * All endpoints return JSON. Consumed by the frontend via Fetch API.
 * No authentication required for this prototype/demo environment.
 */

use App\Http\Controllers\PatientDietaryController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Single-Patient Analysis
|--------------------------------------------------------------------------
| POST /api/analyze-patient
|
| Request body (JSON):
| {
|   "name":      "Bp. Ahmad",
|   "age":       58,
|   "diagnosis": "dm2",          // dm2 | htn | ckd | other
|   "texture":   "soft",         // normal | soft | puree | liquid
|   "allergies": ["seafood"],    // array of allergy keys
|   "notes":     "GDP 185 mg/dL"
| }
|
| Response (JSON):
| {
|   "patient":     { ...profile },
|   "guardrail":   { sodiumMgMax, sugarGMax, energyKcalTarget, ... },
|   "schedule":    [ { mealTime, dish, portionGrams, compliance } ],
|   "totals":      { calories, protein, sodium, sugar, carbs, fat },
|   "redFlags":    [ { type, dish, reason } ],
|   "cookingMethods": [...],
|   "reviewStatus":   { key, label }
| }
*/
Route::post('/analyze-patient', [PatientDietaryController::class, 'analyzePatient']);

/*
|--------------------------------------------------------------------------
| Batch Patient Clustering
|--------------------------------------------------------------------------
| POST /api/batch-cluster
|
| Request body (JSON):
| {
|   "patients": [
|     { "pid": "NG-2025-1001", "name": "...", "age": 55,
|       "diagnosis": "htn", "texture": "soft", "allergies": [],
|       "ward": "Bangsal A", "bed": "Bangsal A / Bed 3", "notes": "" },
|     ...
|   ]
| }
|
| — OR —
|
| POST /api/batch-cluster  with Content-Type: multipart/form-data
| Field: "csv_file" = uploaded .csv file
|
| Response (JSON):
| {
|   "total":     60,
|   "packages":  { "1": { count, fitFull, fitAdjust, ... }, ... },
|   "clustered": [ { patient, packageId, fitStatus, schedule } ],
|   "stats":     { totalFit, totalAdjust }
| }
*/
Route::post('/batch-cluster', [PatientDietaryController::class, 'batchCluster']);

/*
|--------------------------------------------------------------------------
| Demo Data Generator
|--------------------------------------------------------------------------
| GET /api/demo-patients
|
| Returns the 60 synthetic demo patients array (same deterministic
| seeded generation as the original script.js generateDemoPatients()).
*/
Route::get('/demo-patients', [PatientDietaryController::class, 'demoPatients']);

/*
|--------------------------------------------------------------------------
| Food Item Catalogue
|--------------------------------------------------------------------------
| GET /api/food-items
|
| Returns the full dish database with all nutritional properties.
| Used by frontend for the "swap dish" modal.
*/
Route::get('/food-items', [PatientDietaryController::class, 'foodItems']);
