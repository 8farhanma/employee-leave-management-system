## Template Change Request #001 — [Tanggal]

**Permintaan:** Approval 2 level (Supervisor + HRD)

**Dampak Database:**
- Tabel baru: supervisor (atau tambah kolom supervisor_id di karyawan)
- Tabel cuti_karyawan: tambah kolom status_supervisor, approved_by_supervisor,
  approved_at_supervisor

**Dampak Backend:**
- CutiService: logic approval berubah total (~3 hari)
- 2 middleware baru: supervisor role
- Endpoint baru: supervisor approve/reject (~1 hari)
- Update semua test cases (~1 hari)

**Dampak Frontend:**
- Halaman baru untuk supervisor (~2 hari)
- Alur status berubah: pending → supervisor_approved → approved (~1 hari)

**Total Estimasi Tambahan: 8 hari kerja**
**Timeline Baru: mundur dari 30 hari ke 38 hari**

**Rekomendasi:**
Tunda ke versi 2.0 setelah go-live, supaya core system selesai
tepat waktu. Versi 1.0 pakai 1 level approval (admin) seperti
rencana awal.

**Keputusan Stakeholder:** [ ] Setuju tunda | [ ] Setuju tambah waktu