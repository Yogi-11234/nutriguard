<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\AnalyzePatientRequest;
use App\Http\Requests\BatchClusterRequest;
use App\Services\DietaryGuardrailService;
use App\Services\BatchClusteringService;
use App\Services\CsvPatientParserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * PatientDietaryController
 *
 * Entry point for all NutriGuard AI interactions:
 * — Serves the single-page Blade dashboard.
 * — Exposes REST API endpoints consumed by the frontend via Fetch API.
 *
 * @package App\Http\Controllers
 */
class PatientDietaryController extends Controller
{
    public function __construct(
        private readonly DietaryGuardrailService $guardrailService,
        private readonly BatchClusteringService  $batchService,
        private readonly CsvPatientParserService $csvParser,
    ) {}

    // ─────────────────────────────────────────────────────────────────
    // WEB ROUTES
    // ─────────────────────────────────────────────────────────────────

    /**
     * Render the main dashboard SPA shell.
     * All dynamic data is loaded client-side via Fetch API.
     */
    public function index(): View
    {
        return view('patients.index', [
            'appName'    => config('app.name', 'NutriGuard AI'),
            'appEnv'     => app()->environment(),
            'csrfToken'  => csrf_token(),
        ]);
    }

    /**
     * POST /download-manifest
     *
     * Accepts a JSON array of clustered patients and returns a
     * UTF-8 BOM CSV file as a browser download attachment.
     *
     * @param  Request $request
     * @return Response
     */
    public function downloadManifest(Request $request): Response
    {
        $validated = $request->validate([
            'clustered'   => 'required|array|min:1',
            'clustered.*.patient.pid'       => 'required|string',
            'clustered.*.patient.name'      => 'required|string',
            'clustered.*.patient.age'       => 'required|integer',
            'clustered.*.patient.diagnosis' => 'required|string',
            'clustered.*.packageId'         => 'required|integer|in:1,2,3',
            'clustered.*.fitStatus'         => 'required|string|in:full,adjust',
        ]);

        $csv = $this->batchService->generateCsvManifest($validated['clustered']);

        return response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="NutriGuard_KitchenManifest_' . now()->format('Y-m-d') . '.csv"',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    // API ROUTES
    // ─────────────────────────────────────────────────────────────────

    /**
     * POST /api/analyze-patient
     *
     * Runs guardrail analysis for a single patient and returns a full
     * JSON payload: guardrail config, 5-slot meal schedule, daily totals,
     * red flags, cooking methods, and dietary review status.
     *
     * @param  AnalyzePatientRequest $request
     * @return JsonResponse
     */
    public function analyzePatient(AnalyzePatientRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();

            // Normalise allergies: ensure it is always an array
            $allergies = $data['allergies'] ?? [];
            if (in_array('none', $allergies, true)) {
                $allergies = [];
            }

            // Generate a synthetic patient ID for demo purposes
            $pid = 'NG-' . now()->year . '-' . random_int(1000, 9999);

            $patient = [
                'pid'       => $pid,
                'name'      => $data['name'],
                'age'       => (int) $data['age'],
                'diagnosis' => $data['diagnosis'],
                'texture'   => $data['texture'],
                'allergies' => $allergies,
                'notes'     => $data['notes'] ?? '',
            ];

            // Run the core dietary analysis via the service layer
            $result = $this->guardrailService->analyze($patient);

            return response()->json([
                'success' => true,
                'data'    => $result,
            ]);
        } catch (\Throwable $e) {
            Log::error('analyzePatient error', ['message' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses analisis pasien: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * POST /api/batch-cluster
     *
     * Accepts either:
     *   (a) JSON body:  { "patients": [ {...}, ... ] }
     *   (b) Multipart:  csv_file = uploaded .csv
     *
     * Returns clustering result for up to 200 patients.
     *
     * @param  BatchClusterRequest $request
     * @return JsonResponse
     */
    public function batchCluster(BatchClusterRequest $request): JsonResponse
    {
        try {
            // Branch: CSV upload vs. JSON patients array
            if ($request->hasFile('csv_file')) {
                $patients = $this->csvParser->parse(
                    $request->file('csv_file')->get()
                );
            } else {
                $patients = $request->validated()['patients'];
            }

            if (empty($patients)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak ada data pasien yang valid.',
                ], 422);
            }

            $result = $this->batchService->cluster($patients);

            return response()->json([
                'success' => true,
                'data'    => $result,
            ]);
        } catch (\Throwable $e) {
            Log::error('batchCluster error', ['message' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses batch clustering: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * GET /api/demo-patients
     *
     * Returns the 60 deterministic synthetic demo patients.
     * Identical output to the original script.js generateDemoPatients().
     *
     * @return JsonResponse
     */
    public function demoPatients(): JsonResponse
    {
        $patients = $this->batchService->generateDemoPatients(60);

        return response()->json([
            'success' => true,
            'data'    => $patients,
        ]);
    }

    /**
     * GET /api/food-items
     *
     * Returns the full dish database.
     *
     * @return JsonResponse
     */
    public function foodItems(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->guardrailService->getAllDishes(),
        ]);
    }
}
