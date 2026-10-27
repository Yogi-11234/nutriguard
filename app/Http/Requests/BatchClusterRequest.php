<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * BatchClusterRequest
 *
 * Validates the JSON payload for POST /api/batch-cluster.
 * Supports both JSON patients array and CSV file upload.
 */
class BatchClusterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // If CSV file is uploaded, skip JSON patient validation
        if ($this->hasFile('csv_file')) {
            return [
                'csv_file' => 'required|file|mimes:csv,txt|max:2048',
            ];
        }

        return [
            'patients'                  => 'required|array|min:1|max:200',
            'patients.*.name'           => 'required|string|max:80',
            'patients.*.age'            => 'sometimes|integer|min:1|max:120',
            'patients.*.diagnosis'      => 'required|string|in:dm2,htn,ckd,other',
            'patients.*.texture'        => 'sometimes|string|in:normal,soft,puree,liquid',
            'patients.*.allergies'      => 'sometimes|array',
            'patients.*.allergies.*'    => 'string|in:seafood,nuts,egg,milk,soy',
            'patients.*.ward'           => 'sometimes|nullable|string|max:50',
            'patients.*.bed'            => 'sometimes|nullable|string|max:50',
            'patients.*.notes'          => 'sometimes|nullable|string|max:300',
        ];
    }

    public function messages(): array
    {
        return [
            'patients.required'              => 'Array pasien tidak boleh kosong.',
            'patients.max'                   => 'Maksimum 200 pasien per request.',
            'patients.*.name.required'       => 'Nama pasien wajib diisi di setiap baris.',
            'patients.*.diagnosis.required'  => 'Diagnosis pasien wajib diisi di setiap baris.',
            'patients.*.diagnosis.in'        => 'Kode diagnosis tidak valid (dm2/htn/ckd/other).',
            'patients.*.texture.in'          => 'Kode tekstur tidak valid (normal/soft/puree/liquid).',
            'csv_file.mimes'                 => 'File harus berformat CSV.',
            'csv_file.max'                   => 'Ukuran file CSV maksimal 2MB.',
        ];
    }
}
