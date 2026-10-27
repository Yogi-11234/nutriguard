<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * AnalyzePatientRequest
 *
 * Validates the JSON payload for POST /api/analyze-patient.
 */
class AnalyzePatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Open in demo environment; add auth gate in production.
    }

    public function rules(): array
    {
        return [
            'name'      => 'required|string|max:80',
            'age'       => 'required|integer|min:1|max:120',
            'diagnosis' => 'required|string|in:dm2,htn,ckd,other',
            'texture'   => 'required|string|in:normal,soft,puree,liquid',
            'allergies' => 'sometimes|array',
            'allergies.*' => 'string|in:seafood,nuts,egg,milk,soy,none',
            'notes'     => 'sometimes|nullable|string|max:300',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'      => 'Nama pasien wajib diisi.',
            'age.required'       => 'Usia pasien wajib diisi.',
            'age.min'            => 'Usia harus minimal 1 tahun.',
            'age.max'            => 'Usia tidak boleh melebihi 120 tahun.',
            'diagnosis.required' => 'Diagnosis utama wajib dipilih.',
            'diagnosis.in'       => 'Diagnosis tidak valid. Pilih: dm2, htn, ckd, atau other.',
            'texture.required'   => 'Tekstur makanan wajib dipilih.',
            'texture.in'         => 'Tekstur tidak valid. Pilih: normal, soft, puree, atau liquid.',
            'allergies.*.in'     => 'Kode alergi tidak valid.',
        ];
    }
}
