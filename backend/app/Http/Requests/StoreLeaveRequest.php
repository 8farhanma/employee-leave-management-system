<?php

namespace App\Http\Requests;

use App\Constants\LeaveConstants;
use App\Models\CutiKaryawan;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Contracts\Validation\Validator as ValidatorInstance;
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

    public function withValidator(ValidatorInstance $validator): void
    {
        $validator->after(function (ValidatorInstance $v) {

            /**
             * Validasi overlap tanggal
             */

            // Hanya cek jika tanggal valid dulu
            if ($v->errors()->hasAny(['tanggal_mulai', 'tanggal_selesai'])) {
                return;
            }

            $overlap = CutiKaryawan::where('karyawan_id', $this->user()->id)
                ->whereIn('status', [
                    LeaveConstants::STATUS_PENDING,
                    LeaveConstants::STATUS_APPROVED,
                ])
                ->where(function ($q) {
                    // Cek overlap: ada pengajuan lain yang tanggalnya bertabrakan
                    $q->whereBetween('tanggal_mulai', [
                            $this->tanggal_mulai,
                            $this->tanggal_selesai,
                        ])
                        ->orWhereBetween('tanggal_selesai', [
                            $this->tanggal_mulai,
                            $this->tanggal_selesai,
                        ])
                        ->orWhere(function ($q2) {
                            // Kasus: request baru membungkus request lama
                            $q2->where('tanggal_mulai', '<=', $this->tanggal_mulai)
                            ->where('tanggal_selesai', '>=', $this->tanggal_selesai);
                        });
                })
                ->exists();
            
            if ($overlap) {
                $v->errors()->add(
                    'tanggal_mulai',
                    'Terdapat pengajuan cuti lain yang tanggalnya bertabrakan ' .
                    'dengan periode ini.'
                );
            }  
            
            /**
             * Batas maksimum hari per pengajuan
             */
            if ($v->errors()->hasAny(['tanggal_mulai', 'tanggal_selesai'])) {
                return;
            }

            $jumlahHari = Carbon::parse($this->tanggal_mulai)
                                ->diffInDays($this->tanggal_selesai) + 1;

            // Maksimum 12 hari per pengajuan (= 1 jatah penuh)
            if ($jumlahHari > LeaveConstants::ANNUAL_QUOTA) {
                $v->errors()->add(
                    'tanggal_selesai',
                    'Maksimal pengajuan cuti adalah ' .
                    LeaveConstants::ANNUAL_QUOTA . ' hari sekaligus.'
                );
            }        
        });        
    }
}