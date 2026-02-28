<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreLeaveRequest extends FormRequest
{
    /**
     * Siapa yang boleh submit request ini?
     * Semua karyawan yang sudah login (admin pun boleh cuti)
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'jenis_cuti_id' => [
                'required',
                'integer',
                'exists:jenis_cuti,id', // ID harus ada di tabel jenis_cuti
            ],

            'tanggal_mulai' => [
                'required',
                'date',
                'date_format:Y-m-d', 
                'after_or_equal:today', // Tanggal mulai harus hari ini atau setelahnya
            ],

            'tanggal_selesai' => [
                'required',
                'date',
                'date_format:Y-m-d',
                // Aturan Bisnis: selesai >= mulai
                'after_or_equal:tanggal_mulai', 
            ],

            'keterangan' => [
                'nullable',
                'string',
                'max:500',
            ],
        ];
    }

    /**
     * Semua pesan error dalam bahasa Indonesia
     */
    public function messages(): array
    {
        return [
            'jenis_cuti_id.required'    => 'Jenis cuti wajib dipilih.',
            'jenis_cuti_id.exists'      => 'Jenis cuti yang dipilih tidak valid.',

            'tanggal_mulai.required'        => 'Tanggal mulai cuti wajib diisi.',
            'tanggal_mulai.date'            => 'Format tanggal mulai tidak valid.',
            'tanggal_mulai.date_format'     => 'Tanggal mulai harus dalam format YYYY-MM-DD.',
            'tanggal_mulai.after_or_equal'  => 'Tanggal mulai harus hari ini atau setelahnya.',

            'tanggal_selesai.required'          => 'Tanggal selesai wajib diisi.',
            'tanggal_selesai.date'              => 'Format tanggal selesai tidak valid.',
            'tanggal_selesai.date_format'       => 'Tanggal selesai harus dalam format YYYY-MM-DD.',
            'tanggal_selesai.after_or_equal'    => 'Tanggal selesai harus sama dengan atau setelah tanggal mulai.',

            'keterangan.string' => 'Keterangan harus berupa teks.',
            'keterangan.max'    => 'Keterangan tidak boleh lebih dari 500 karakter.',
        ];
    }

    /**
     * Nama field yang human-readable untuk error message default
     */
    public function attributes(): array
    {
        return [
            'jenis_cuti_id' => 'jenis cuti',
            'tanggal_mulai' => 'tanggal mulai',
            'tanggal_selesai' => 'tanggal selesai',
            'keterangan' => 'keterangan',
        ];
    }

    /**
     * Override failedValidation agar response selalu JSON dengan format yang konsisten
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Data pengajuan tidak valid.',
                'errors' => $validator->errors(),
            ], 422)
        );
    }
}