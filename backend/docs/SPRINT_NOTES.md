# Notes — Minggu 1 

## ✅ Yang Berjalan Baik
- [🗸] Setup Docker 4 container tanpa isu besar
- [🗸] 5 endpoint aktif dan tested (login,logout, me, jenis_cuti, register)
- [🗸] Postman collection siap pakai dengan auto-save token
- [🗸] API Resource mencegah data sensitif bocor (password tidak pernah muncul)
- [🗸] RefreshDatabase di feature test menjaga isolasi test

## ⚠️ Temuan Perbaikan
- 🔴 [🗸] N+1            -> isPotongJatah() lazy load
- 🔴 [🗸] Validasi       -> Tidak ada cek overlap tanggal
- 🔴 [🗸] Validasi       -> Maks hari per pengajuan
- 🔴 [🗸] Security       -> Tidak ada rate limit di login
- 🔴 [🗸] Naming         -> CutiService: campur Indo/Inggris
- 🟡 [🗸] N+1            -> scopeDepartemen whereHas 
- 🟡 [🗸] Validasi       -> Email DNS lookup bisa timeout 
- 🟡 [🗸] Validasi       -> Admin tidak bisa ubah role sendiri
- 🟡 [🗸] Technical Debt -> Tidak ada audit log
- 🟡 [🗸] Technical Debt -> Soft delete cuti_karyawan
- 🟡 [🗸] Technical Debt -> Carbon locale belum di-set

- 🟡 [🗸] Technical Debt -> Jenis cuti belum di di-cache
        // Jangan lupa clear cache jika data master diupdate:
        Cache::forget('jenis_cuti_all');

- 🟡 [🗸] Naming         -> Controller folder structure

- 🟡 [🗸] Config         -> SANCTUM_EXPIRATION hardcode
        // .env development:
        SANCTUM_EXPIRATION=480
        // .env production (lebih ketat — 8 jam):
        SANCTUM_EXPIRATION=480
        // .env staging (lebih pendek untuk testing):
        SANCTUM_EXPIRATION=60

- 🟢 [🗸] Response       -> Struktur konsisten pakai Trait
- 🟢 [🗸] Security       -> Password pakai Cast 'hashed'
- 🟢 [🗸] Security       -> Single session (hapus token lama)
- 🟢 [🗸] Validasi       -> FormRequest semua pakai override
- 🟢 [🗸] DB             -> FK, index, CHECK constraint aktif 


## 🔮 Persiapan Minggu 2
- [🗸] CutiService skeleton lengkap dengan docblock
- [🗸] CutiService di-bind sebagai singleton di AppServiceProvider 
- [🗸] CutiController & CutiAdminController skeleton sudah ada
- [🗸] 57 unit test CutiService sudah pass

## 📋 Perubahan Requirement
- Tidak ada perubahan requirement
- Semua aturan bisnis BR-01 s/d BR-10 dikonfirmasi masih berlaku



# Checklist Konfirmasi Requirement

ATURAN BISNIS — konfirmasi masih berlaku:
[🗸] BR-01: Jatah tahunan = 12 hari per tahun kalender → masih 12?
[🗸] BR-02: Hanya Cuti Tahunan yang potong jatah → masih sama?
[🗸] BR-03: Sisa cuti = 0 → auto reject Cuti Tahunan → masih berlaku?
[🗸] BR-04: tanggal_selesai >= tanggal_mulai → masih berlaku?
[🗸] BR-05: Hitung hari inklusif → masih inklusif?
[🗸] BR-06: Potong jatah hanya saat APPROVE, bukan saat submit → masih?
[🗸] BR-07: Jika approved lalu direject, jatah dikembalikan → masih?
[🗸] BR-08: Karyawan hanya lihat cuti sendiri → masih?
[🗸] BR-09: Admin lihat semua departemen → masih?
[🗸] BR-10: Cancel hanya boleh saat pending → masih?

FITUR — konfirmasi scope tidak berubah:
[🗸] Tidak ada fitur notifikasi email?
[🗸] Tidak ada fitur upload dokumen (surat dokter, dll)?
[🗸] Tidak ada fitur cuti massal / cuti bersama?
[🗸] Tidak ada integrasi payroll / absensi?
[🗸] Tidak ada rekap laporan PDF / export Excel?
[🗸] Role tetap hanya 2: admin dan karyawan?

TEKNIS — konfirmasi tidak ada perubahan stack:
[🗸] PHP 8.2 + Laravel 11 masih dipakai?
[🗸] MySQL 8 masih dipakai?
[🗸] React 18 masih dipakai?
[🗸] Docker masih dipakai untuk deployment?