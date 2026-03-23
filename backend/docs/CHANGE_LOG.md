## Change Request #001 — [17 Maret 2026]

**Permintaan:** Aturan keterangan & dokumen per jenis cuti
**Status:** Diterima & dikerjakan Hari 9

┌─────────────────┬──────────────┬──────────────┐
│ Jenis Cuti      │ Keterangan   │ Dokumen      │
├─────────────────┼──────────────┼──────────────┤
│ Cuti Tahunan    │ WAJIB        │ Opsional     │
│ Izin            │ WAJIB        │ Opsional     │
│ Sakit           │ Opsional     │ WAJIB        │
└─────────────────┴──────────────┴──────────────┘

**Dampak Database:**
[+] Tabel cuti_karyawan: kolom baru dokumen_path (VARCHAR, nullable)
[ ] Tabel jenis_cuti: tidak perlu berubah (logic di aplikasi)

**Dampak Backend:**
[+] Migration: add dokumen_path to cuti_karyawan
[+] Storage: disk config untuk simpan file upload
[+] StoreLeaveRequest: validasi kondisional per jenis cuti
[+] CutiService::submit(): handle file upload + simpan path
[+] CutiKaryawanResource: expose dokumen_url
[~] CutiController::store(): terima file dari request

**Dampak Frontend:**
[ ] ...

**Total Estimasi Tambahan:** ~2 jam
**Timeline Baru:** Masuk ke hari 9, tidak perlu geser timeline

**Rekomendasi:**
...

**Keputusan Stakeholder:** [ ] Setuju tunda | [🗸] Setuju tambah waktu