# API Documentation — ELMS
**Versi:** 1.0.0
**Base URL:** `http://localhost/api`
**Format:** JSON
**Autentikasi:** Bearer Token (Laravel Sanctum)

---

## Konvensi Response

Semua response mengikuti struktur ini:

**Sukses:**
```json
{
  "success": true,
  "message": "Pesan sukses",
  "data": { ... }
}
```

**Gagal:**
```json
{
  "success": false,
  "message": "Pesan error",
  "errors": { "field": ["pesan validasi"] }
}
```

## HTTP Status Code

| Code | Arti                              |
|------|-----------------------------------|
| 200  | OK — Request berhasil             |
| 201  | Created — Data berhasil dibuat    |
| 401  | Unauthorized — Token tidak valid  |
| 403  | Forbidden — Akses ditolak         |
| 404  | Not Found — Data tidak ditemukan  |
| 405  | Method Not Allowed                |
| 422  | Unprocessable — Validasi gagal    |
| 500  | Internal Server Error             |

---

## Autentikasi

Token dikirim melalui header:
```
Authorization: Bearer {token}
```
Token berlaku **480 menit (8 jam)**. Setiap login baru akan
mencabut token lama (single active session).

---

## Endpoints

### 🔓 Public

#### GET /auth/ping
Health check — cek apakah API aktif.

**Response 200:**
```json
{
  "success": true,
  "message": "API ELMS aktif.",
  "version": "1.0.0",
  "time": "2026-03-04 09:00:00"
}
```

---

#### POST /auth/login
Login menggunakan email atau NIK.

**Body:**
| Field    | Tipe   | Wajib | Keterangan           |
|----------|--------|-------|----------------------|
| login    | string | ✅    | Email atau NIK       |
| password | string | ✅    | Password karyawan    |

**Response 200:**
```json
{
  "success": true,
  "message": "Login berhasil. Selamat datang, Siti Aminah!",
  "data": {
    "token": "3|kJ8xQm...",
    "token_type": "Bearer",
    "expires_in": "480 menit",
    "karyawan": {
      "id": 2,
      "nik": "SEW001",
      "nama": "Siti Aminah",
      "departemen": "Sewing",
      "label_departemen": "Dept. Sewing",
      "role": "karyawan",
      "email": "siti@company.id",
      "sisa_cuti": 12,
      "sisa_cuti_label": "12 hari tersisa"
    }
  }
}
```

**Response 422 — Password salah:**
```json
{
  "success": false,
  "message": "The given data was invalid.",
  "errors": {
    "login": ["NIK/Email atau password salah."]
  }
}
```

---

### 🔐 Protected (Semua Role)

> Semua endpoint berikut wajib menyertakan header:
> `Authorization: Bearer {token}`

#### POST /auth/logout
Cabut token yang sedang aktif.

**Response 200:**
```json
{
  "success": true,
  "message": "Sampai jumpa, Siti Aminah! Anda berhasil logout."
}
```

---

#### GET /me
Profil karyawan yang sedang login beserta statistik pengajuan cuti.

**Response 200:**
```json
{
  "success": true,
  "data": {
    "profil": {
      "id": 2,
      "nik": "SEW001",
      "nama": "Siti Aminah",
      "departemen": "Sewing",
      "label_departemen": "Dept. Sewing",
      "role": "karyawan",
      "email": "siti@company.id",
      "sisa_cuti": 9,
      "sisa_cuti_label": "9 hari tersisa"
    },
    "statistik": {
      "total_pengajuan": 3,
      "total_pending": 1,
      "total_approved": 2,
      "total_rejected": 0,
      "sisa_cuti": 9,
      "jatah_tahunan": 12,
      "cuti_terpakai": 3
    }
  }
}
```

---

#### GET /jenis-cuti
List semua jenis cuti. Bisa difilter dengan query parameter.

**Query Parameters:**
| Parameter    | Tipe    | Keterangan                        |
|--------------|---------|-----------------------------------|
| potong_jatah | boolean | `true` = memotong jatah tahunan   |

**Response 200:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "nama": "Cuti Tahunan",
      "potong_jatah": true,
      "keterangan": "Memotong jatah 12 hari per tahun."
    },
    {
      "id": 2,
      "nama": "Izin",
      "potong_jatah": false,
      "keterangan": "Tidak memotong jatah cuti tahunan."
    },
    {
      "id": 3,
      "nama": "Sakit",
      "potong_jatah": false,
      "keterangan": "Cuti sakit, tidak memotong jatah."
    }
  ],
  "meta": {
    "total": 3,
    "potong_jatah": 1,
    "tidak_potong": 2
  }
}
```

---

#### GET /jenis-cuti/{id}
Detail satu jenis cuti.

**Response 404:**
```json
{
  "success": false,
  "message": "JenisCuti tidak ditemukan."
}
```

---

### 🛡️ Admin Only

> Endpoint berikut hanya bisa diakses dengan token role **admin**.
> Akses dengan token karyawan biasa → **403 Forbidden**.

#### POST /admin/karyawan/register
Mendaftarkan karyawan baru ke sistem.

**Body:**
| Field                 | Tipe   | Wajib | Keterangan                            |
|-----------------------|--------|-------|---------------------------------------|
| nik                   | string | ✅    | Unik, huruf kapital & angka, maks 20  |
| nama                  | string | ✅    | 3–100 karakter                        |
| departemen            | string | ✅    | Sewing / Cutting / Finishing / QA     |
| email                 | string | ✅    | Format email valid, unik              |
| password              | string | ✅    | Min 8 karakter, huruf besar+kecil+angka |
| password_confirmation | string | ✅    | Harus sama dengan password            |
| role                  | string | ❌    | karyawan (default) / admin            |
| sisa_cuti             | int    | ❌    | 0–12 (default: 12)                    |

**Response 201:**
```json
{
  "success": true,
  "message": "Karyawan Karyawan Baru berhasil didaftarkan.",
  "data": {
    "id": 8,
    "nik": "FIN099",
    "nama": "Karyawan Baru",
    "departemen": "Finishing",
    "label_departemen": "Dept. Finishing",
    "role": "karyawan",
    "email": "baru@company.id",
    "sisa_cuti": 12,
    "sisa_cuti_label": "12 hari tersisa"
  }
}
```

---

## Data Referensi

### Departemen Valid
| Value      | Label              |
|------------|--------------------|
| Sewing     | Dept. Sewing       |
| Cutting    | Dept. Cutting      |
| Finishing  | Dept. Finishing    |
| QA         | Quality Assurance  |

### Jenis Cuti (Seeded)
| ID | Nama          | Potong Jatah |
|----|---------------|--------------|
| 1  | Cuti Tahunan  | ✅ Ya         |
| 2  | Izin          | ❌ Tidak      |
| 3  | Sakit         | ❌ Tidak      |

### Status Pengajuan Cuti
| Value    | Label                     |
|----------|---------------------------|
| pending  | ⏳ Menunggu Persetujuan   |
| approved | ✅ Disetujui              |
| rejected | ❌ Ditolak                |

---

## Akun Test (Development Only)

| NIK    | Email                  | Password     | Role     | Dept.     | Sisa Cuti |
|--------|------------------------|--------------|----------|-----------|-----------|
| ADM001 | admin@company.id       | admin123     | admin    | null      | 12        |
| SEW001 | siti@company.id        | karyawan123  | karyawan | Sewing    | 12        |
| SEW002 | dewi@company.id        | karyawan123  | karyawan | Sewing    | 8         |
| CUT001 | ahmad@company.id       | karyawan123  | karyawan | Cutting   | 12        |
| CUT002 | rudi@company.id        | karyawan123  | karyawan | Cutting   | 0 ⚠️      |
| FIN001 | rina@company.id        | karyawan123  | karyawan | Finishing | 5         |
| QA001  | hendra@company.id      | karyawan123  | karyawan | QA        | 12        |

> ⚠️ **Rudi (CUT002)** — sisa cuti = 0, cocok untuk test edge case penolakan Cuti Tahunan.

---

*Dokumentasi ini akan diperbarui seiring penambahan endpoint.*
*Endpoint pengajuan cuti dan dashboard admin akan ditambahkan.*