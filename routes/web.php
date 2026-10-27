<?php

/**
 * NutriGuard AI — Web Routes
 *
 * Handles all HTML page routes. API endpoints are in routes/api.php.
 */

use App\Http\Controllers\PatientDietaryController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Dashboard Entry Point
|--------------------------------------------------------------------------
| Single-page dashboard entry. The full SPA-like interaction is handled
| client-side via Fetch API calls to /api/* endpoints.
*/
Route::get('/', [PatientDietaryController::class, 'index'])
    ->name('dashboard');

/*
|--------------------------------------------------------------------------
| CSV Download Helper
|--------------------------------------------------------------------------
| Browser-triggered download for Kitchen Distribution Manifest.
| Accepts JSON body of clustered patients, returns CSV attachment.
*/
Route::post('/download-manifest', [PatientDietaryController::class, 'downloadManifest'])
    ->name('manifest.download');
