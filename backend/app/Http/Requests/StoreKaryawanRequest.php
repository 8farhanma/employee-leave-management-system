<?php

namespace App\Http\Requests;

use App\Constants\LeaveConstants;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreKaryawanRequest extends FormRequest
{
    public function rules(): array
{
    return [
        'nik'        => 'required|string|max:20|unique:karyawan,nik',
        'nama'       => 'required|string|max:100',
        'email'      => 'required|email|unique:karyawan,email',
        'password'   => 'required|string|min:8',
        'role'       => ['required', Rule::in(LeaveConstants::ALL_ROLES)],

        // departemen WAJIB jika role = karyawan
        // departemen BOLEH NULL jika role = admin
        'departemen' => [
            Rule::requiredIf(fn() => $this->input('role') === 'karyawan'),
            'nullable',
            Rule::in(LeaveConstants::DEPARTMENTS),
        ],
    ];
}

public function messages(): array
{
    return [
        'departemen.required' => 'Departemen wajib diisi untuk karyawan produksi.',
        'departemen.in'       => 'Departemen harus salah satu dari: Sewing, Cutting, Finishing, QA.',
    ];
}
}
