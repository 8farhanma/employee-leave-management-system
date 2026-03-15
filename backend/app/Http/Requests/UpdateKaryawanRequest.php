<?php

namespace App\Http\Requests;

use App\Constants\LeaveConstants;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Contracts\Validation\Validator as ValidatorInstance;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class UpdateKaryawanRequest extends FormRequest
{
    // Hanya admin yang bisa update data karyawan lain
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        // Ambil ID Karyawan dari route parameter
        $karyawanId = $this->route('karyawan');

        return [
            'nik' => [
                'sometimes', // hanya validasi jika field dikirim
                'string',
                'max:20',
                // NIK harus unik, kecuali untuk karyawan ini sendiri
                Rule::unique('karyawan', 'nik')->ignore($karyawanId),
            ],

            'nama' => [
                'sometimes',
                'string',
                'max:100',
            ],

            'departemen' => [
                // Jika role di-update ke 'karyawan', departemen wajib ada
                Rule::requiredIf(function () {
                    $roleYangDikirim  = $this->input('role');
                    $karyawan         = $this->route('karyawan'); // model binding
                    $roleAktif        = $roleYangDikirim ?? $karyawan->role;

                    return $roleAktif === 'karyawan';
                }),
                'nullable',
                Rule::in(LeaveConstants::DEPARTMENTS),
            ],

            'email' => [
                'sometimes',
                'email',
                Rule::unique('karyawan', 'email')->ignore($karyawanId),
            ],

            'password' => [
                'sometimes',
                'string',
                'min:8',
            ],

            'sisa_cuti' => [
                'sometimes',
                'integer',
                'min:0',
                'max:' . LeaveConstants::ANNUAL_QUOTA
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'nik.unique'    => 'NIK sudah digunakan oleh karyawan lain.',
            'email.unique'  => 'Email sudah digunakan oleh karyawan lain.',
            'email.email'   => 'Format email tidak valid.',
            'departemen.in' => 'Departemen harus salah satu dari: Sewing, Cutting, Finishing, QA.',
            'password.min'  => 'Password harus minimal 8 karakter.',
            'sisa_cuti.min' => 'Sisa cuti tidak boleh negatif.',
            'sisa_cuti.max' => 'Sisa cuti tidak boleh melebihi 12 hari.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
            'success' => false,
            'message' => 'Data karyawan tidak valid.',
            'errors' => $validator->errors(),
        ], 422));
    }

    public function withValidator(ValidatorInstance $validator): void
    {
        $validator->after(function (ValidatorInstance $v) {
            
            $karyawanId = $this->route('karyawan');

            // Admin tidak boleh ubah role dirinya sendiri
            if (
                $this->has('role') &&
                (int)$karyawanId === $this->user()->id
            ) {
                $v->errors()->add(
                    'role',
                    'Anda tidak dapat mengubah role akun Anda sendiri.'
                );
            }
        });
    }
}
