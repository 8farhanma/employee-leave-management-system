<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class ApproveLeaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Hanya admin - double check di sini selain middleware
        return $this->user()?->is_admin ?? false;
    }

    public function rules(): array
    {
        return [
            // Catatan opsional saat approve/reject, tapi jika diisi maksimal 300 karakter
            'catatan_admin' => [
                'nullable',
                'string',
                'max:300',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'catatan_admin.max' => 'Catatan tidak boleh lebih dari 300 karakter.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
            'message' => 'Data tidak valid.',
            'errors' => $validator->errors(),
        ], 422));
    }
}
