<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class RejectLeaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Hanya admin yang boleh reject pengajuan cuti
        return $this->user()?->is_admin ?? false;
    }

    public function rules(): array
    {
        return [
            // Saat reject, Catatan wajib diisi - admin harus memberikan alasan penolakan
            'catatan_admin' => [
                'required',
                'string',
                'min:10',
                'max:300',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'catatan_admin.required'    => 'Alasan penolakan wajib diisi.',
            'catatan_admin.min'         => 'Alasan penolakan minimal 10 karakter.',
            'catatan_admin.max'         => 'Alasan penolakan maksimal 300 karakter.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'message' => 'Alasan penolakan wajib diisi dengan benar.',
                'errors' => $validator->errors(),
            ], 422)
        );
    }
}
