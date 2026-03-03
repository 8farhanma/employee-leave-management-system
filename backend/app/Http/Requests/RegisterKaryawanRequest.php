<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterKaryawanRequest extends FormRequest
{
    /**
     * Hanya admin yang bisa mendaftarkan karyawan baru
     */
    public function authorize(): bool
    {
        return $this->user('sanctum')?->is_admin ?? false;
    }

    public function rules(): array
    {
        return [
            'nik' => [
                'required',
                'string',
                'max:20',
                'unique:karyawan,nik',   // nik harus unik
                'regex:/^[A-Z0-9]+$/',      // hanya huruf kapital & angka
            ],

            'nama' => [
                'required',
                'string',
                'min:3',
                'max:100',
            ],

            'departemen' => [
                'required',
                Rule::in(['Sewing', 'Cutting', 'Finishing', 'QA']),
            ],

            'email' => [
                'required',
                'email:rfc',            // validasi email + DNS
                'unique:karyawan,email',
                'max:100',    
            ],

            'password' => [
                'required',
                'confirmed',                // wajib ada pasword_confirmation
                Password::min(8)
                    ->mixedCase()           // harus ada huruf besar & kecil
                    ->numbers()             // harus ada angka
            ],

            'role' => [
                'sometimes',                // role opsional, default 'karyawan'
                Rule::in(['karyawan', 'admin']),
            ],

            'sisa_cuti' => [
                'sometimes',
                'integer',                
                'min:0',
                'max:12',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'nik.required' => 'NIK wajib diisi.',
            'nik.unique' => 'NIK sudah terdaftar.',
            'nik.regex' => 'NIK hanya boleh berisi huruf kapital dan angka.',
            'nik.max' => 'NIK maksimal 20 karakter.',

            'nama.required' => 'Nama wajib diisi.',
            'nama.min' => 'Nama minimal 3 karakter.',
            'nama.max' => 'Nama maksimal 100 karakter.',

            'departemen.required' => 'Departemen wajib dipilih.',
            'departemen.in' => 'Departemen harus salah satu dari: Sewing, Cutting, Finishing, QA.',

            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email sudah terdaftar.',

            'password.required' => 'Password wajib diisi.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
            'password.min' => 'Password minimal 8 karakter.',
            'password.mixedCase' => 'Password harus mengandung huruf besar dan kecil.',
            'password.numbers' => 'Password harus mengandung minimal satu angka.',

            'role.in' => 'Role harus karyawan atau admin.',
            'sisa_cuti.min' => 'Sisa cuti tidak boleh negatif.',
            'sisa_cuti.max' => 'Sisa cuti maksimal 12 hari.',
        ];
    }

    public function attributes(): array
    {
        return [
            'nik' => 'NIK',
            'nama' => 'nama karyawan',
            'departemen' => 'departemen',
            'email' => 'email',
            'password' => 'password',
            'role' => 'role',
            'sisa_cuti' => 'sisa cuti',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Data resgistrasi tidak valid.',
            'errors' => $validator->errors(),
        ], 422));
    }
}
