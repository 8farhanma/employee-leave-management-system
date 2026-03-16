<?php

namespace App\Http\Requests;

use App\Constants\LeaveConstants;
use App\Models\CutiKaryawan;
use App\Models\JenisCuti;
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
                'after_or_equal:today' . now('Asia/Jakarta')->format('Y-m-d'), // Tanggal mulai harus hari ini atau setelahnya
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
                'min:3',
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

    /**
     * Validasi tambahan setelah semua rule dasar lolos.
     * Di sini dilakukan pengecekan logika bisnis yang butuh query DB.
     */
    public function withValidator(ValidatorInstance $validator): void
    {
        $validator->after(function (ValidatorInstance $v) {

            // Hanya cek jika tanggal valid terlebih dahulu
            if ($v->errors()->hasAny(['tanggal_mulai', 'tanggal_selesai'])) {
                return;
            }

            $mulai   = $this->tanggal_mulai;
            $selesai = $this->tanggal_selesai;

            // ────────────────────────────────────────────────────────────────
            // Validasi 1 : Batas maksimum hari per pengajuan
            // ──────────────────────────────────────────────────────────────── 
            $jumlahHari = Carbon::parse($mulai)
                                ->diffInDays($selesai) + 1;

            // Maksimum 12 hari per pengajuan (= 1 jatah penuh)
            if ($jumlahHari > LeaveConstants::ANNUAL_QUOTA) {
                $v->errors()->add(
                    'tanggal_selesai',
                    'Maksimal pengajuan cuti adalah ' .
                    LeaveConstants::ANNUAL_QUOTA . ' hari sekaligus.'
                );
                // Stop di sini - tidak perlu cek overlap jika durasi sudah invalid
                return;
            }
            
            // ────────────────────────────────────────────────────────────────
            // Validasi 2 : Cek sisa cuti mencukupi
            // ────────────────────────────────────────────────────────────────
            if (!$v->errors()->has('jenis_cuti_id')) {
                $jenisCuti = JenisCuti::find($this->jenis_cuti_id);

                if ($jenisCuti?->potong_jatah) {
                    $karyawan = $this->user();

                    if ($karyawan->sisa_cuti < $jumlahHari) {
                        $v->errors()->add(
                            'jumlah_hari',
                            "Sisa cuti Anda tidak mencukupi. " . 
                            "Dibutuhkan: {$jumlahHari} hari, " .
                            "tersisa: {$karyawan->sisa_cuti} hari."
                        );
                    }
                }
            }

            // ────────────────────────────────────────────────────────────────
            // Validasi 3 : Overlap tanggal
            // ────────────────────────────────────────────────────────────────
            $overlap = CutiKaryawan::where('karyawan_id', $this->user()->id)
                ->whereIn('status', [
                    LeaveConstants::STATUS_PENDING,
                    LeaveConstants::STATUS_APPROVED,
                ])
                ->where('tanggal_mulai', '<=', $selesai)    // lama mulai sebelum/saat baru selesai
                ->where('tanggal_selesai', '>=', $mulai)    // lama selesai setelah/saat baru mulai
                ->exists();
            
            if ($overlap) {
                $v->errors()->add(
                    'tanggal_mulai',
                    'Terdapat pengajuan cuti lain yang tanggalnya bertabrakan ' .
                    'dengan periode ini. Periksa daftar pengajuan Anda.'
                );
            }       
        });        
    }
}