# Implementation Plan
## Jadwal Sekolah Mantap Terpusat

| Atribut | Nilai |
|---|---|
| Versi | 1.0 (Draft untuk review) |
| Tanggal | 22 November 2026 |
| Acuan | `docs/PRD.md` versi 1.3 (disetujui) |
| Status | **Menunggu review. Coding belum boleh dimulai** sampai dokumen ini disetujui dan keputusan teknis gerbang Fase 1 (Bagian 2) diputuskan |

**Konvensi penandaan**

| Tanda | Arti |
|---|---|
| **[PRD]** | Diambil langsung dari PRD v1.3. Tidak boleh diubah oleh dokumen ini |
| **[USULAN-TEKNIS]** | Usulan tim teknis. Berlaku setelah disetujui |
| **[TD-xx]** | Keputusan teknis yang belum diputuskan (Bagian 2) |
| **[A-0x]** | Asumsi bisnis dari PRD 27.3. Diimplementasikan agar mudah diubah, **bukan** keputusan final |
| **[R-0x]** | Rekomendasi PRD 27.4. Aturan awal MVP, tetap berstatus rekomendasi |

Kode role: **SA** Superadmin, **AP** Admin Pusat, **AC** Admin Cabang, **M** Mentor.

---

## Daftar Isi

1. Audit Kebutuhan Teknis & Dependensi Antarmodul
2. Keputusan Teknis yang Membutuhkan Persetujuan (termasuk PD-20, PD-27, PD-29, PD-30)
3. Rekomendasi Stack Teknologi
4. Rancangan Arsitektur Aplikasi
5. Rancangan Database
6. Matriks Permission Final
7. Daftar Modul & Pemetaan ke FR/AC
8. Tahapan Implementasi (Deliverable & Exit Criteria)
9. Strategi Pengujian
10. Risiko Teknis, Keamanan, Performa & Potensi Konflik Business Rules
11. Penanganan Asumsi A-01 s.d. A-04
12. Gerbang Review Sebelum Coding

---

## 1. Audit Kebutuhan Teknis & Dependensi Antarmodul

### 1.1 Karakteristik sistem dari PRD

| Karakteristik | Sumber PRD | Implikasi teknis |
|---|---|---|
| Aplikasi web internal dengan 4 role, tanpa akun publik | Bagian 6, 9, BR-ACC-04 | Autentikasi sesi biasa. Tidak ada registrasi mandiri atau OAuth publik |
| Pembatasan data berlapis: per role, per cabang (AC), per penugasan (M) | BR-ACC-01..05, Bagian 9 | Otorisasi di tiga lapis: role, query scope, dan policy per aksi (4.4). Wajib ditegakkan di server |
| Field-level visibility: mentor melihat jadwal mentor lain tanpa catatan laporan dan tautan | PD-03, R-01, AC-RPT-02 | Output (resource/DTO) dibedakan per konteks. Model mentah tidak boleh dikirim ke frontend |
| Riwayat wajib di banyak entitas: jadwal, laporan, narahubung, penugasan, rangkaian TL, kontak awal | BR-ACT-06, BR-RPT-03, BR-SCH-06/11, BR-MNT-03, BR-TL-09, BR-RPT-09 | Pola **append-only history table** per entitas, ditambah audit trail global |
| Status turunan: posisi tahap sekolah, status sertifikat, progres TL, rangkaian selesai | 15.2–15.5, BR-TL-06/12 | Satu service penghitung ulang, dijalankan dalam transaksi yang sama dengan perubahan sumber (NFR "Konsistensi data") |
| Invariant unik bersyarat | BR-AWL-03, BR-TL-07, BR-SCH-06, 16.5, BR-SCH-03/13 | Butuh partial unique index di DB, bukan hanya validasi aplikasi |
| Notifikasi event-driven dan terjadwal (H-1, H+1) | FR-NTF-02, BR-NTF-01 | Event bus internal, queue, dan scheduler harian |
| Audit trail append-only, hanya SA | FR-AUD-01..03, BR-AUD-01..03 | Tabel audit terpisah, tanpa endpoint ubah/hapus. Idealnya ada pembatasan hak DB [TD-02] |
| Kemiripan nama sekolah (bukan hanya identik) | AC-SCH-03 | Pencarian fuzzy/trigram di DB |
| Performa ≤ 3 detik untuk 5.000 sekolah / 50.000 kegiatan | NFR, AC-DSH-05, AC-SCH-06 | Indeks, agregasi di DB, paginasi server. Angka acuan dari [A-04] |
| Konkurensi edit | E9 | Optimistic locking (kolom `version`) |
| Responsif, mentor memakai ponsel | Bagian 20 | UI mobile-first untuk halaman mentor |
| Zona waktu WIB | NFR | Timezone aplikasi `Asia/Jakarta`; timestamp disimpan dengan zona waktu |
| Tidak ada impor data lama | Keputusan no. 32 | Tidak ada modul impor. Data awal diinput lewat UI (T-29) |

### 1.2 Kebutuhan teknis lintas modul (cross-cutting)

| ID | Komponen | Dipakai oleh | Harus siap sebelum |
|---|---|---|---|
| X-01 | Autentikasi & sesi | Semua | Fase 2 |
| X-02 | Kerangka otorisasi (role gate + scope + policy + resource per konteks) | Semua | Fase 2 |
| X-03 | Audit trail writer (observer/listener) | Semua aksi tulis | Fase 2 |
| X-04 | Optimistic locking | Sekolah, Kegiatan, Narahubung, Sertifikat | Fase 3 |
| X-05 | History writer (pola append-only) | Jadwal, Laporan, Narahubung, Penugasan, Rangkaian, Kontak Awal | Fase 3 |
| X-06 | Derived-state service (`ProgressRecalculator`) | Laporan, Jadwal, Sertifikat, Dashboard | Fase 5 |
| X-07 | Domain events + notification dispatcher | Jadwal, Laporan, Sertifikat, Narahubung, Penugasan | Fase 7 (event disiapkan sejak Fase 3) |
| X-08 | Scheduler + queue | Notifikasi H-1/H+1 | Fase 7 |

### 1.3 Graf dependensi antarmodul

```
                  ┌──────────────┐
                  │ X-01 Auth    │
                  └──────┬───────┘
                         ▼
                  ┌──────────────┐      ┌──────────────┐
                  │ X-02 Authz   │◄────►│ X-03 Audit   │
                  └──────┬───────┘      └──────────────┘
          ┌──────────────┼───────────────┐
          ▼              ▼               ▼
   ┌────────────┐  ┌────────────┐  ┌────────────┐
   │ M-MST      │  │ M-USR      │  │ (A-01..A-03│
   │ Data Master│  │ Pengguna   │  │  config)   │
   └─────┬──────┘  └─────┬──────┘  └────────────┘
         └───────┬───────┘
                 ▼
          ┌────────────┐
          │ M-SCH      │ Sekolah, kategori, narahubung, arsip, koreksi cabang
          └─────┬──────┘
                ▼
          ┌────────────┐
          │ M-MNT      │ Penugasan mentor penanggung jawab
          └─────┬──────┘
                ▼
          ┌────────────┐
          │ M-JDA      │ Jadwal Pelatihan Awal
          └─────┬──────┘
                ▼
          ┌────────────┐       ┌────────────┐
          │ M-JTL      │◄─────►│ M-RPT      │ Laporan & status (sumber status kegiatan)
          └─────┬──────┘       └─────┬──────┘
                └─────────┬──────────┘
                          ▼
                 ┌─────────────────┐
                 │ X-06 Derived    │ posisi tahap, rangkaian selesai
                 └────────┬────────┘
            ┌─────────────┼──────────────┐
            ▼             ▼              ▼
      ┌──────────┐  ┌──────────┐   ┌──────────┐
      │ M-CRT    │  │ M-NTF    │   │ M-DSH /  │
      │Sertifikat│  │Notifikasi│   │ M-RNK    │
      └──────────┘  └──────────┘   └──────────┘
                          ▲
                   M-AUD UI (bergantung X-03 saja)
```

**Ketergantungan kritis:**
1. **M-JTL ↔ M-RPT** saling bergantung. Penyelesaian rangkaian (FR-JTL-05/10) bergantung pada status yang ditulis M-RPT. Karena itu T-16a dijadwalkan setelah T-19 (sesuai PRD 28.1).
2. **M-CRT** bergantung pada X-06, karena status sertifikat diturunkan dari tahap yang selesai.
3. **M-NTF** memakai event dari hampir semua modul. Event sudah dipancarkan sejak modul sumber dibuat, sedangkan listener notifikasi dibangun di Fase 7.
4. **M-DSH** butuh definisi metrik final (BR-DSH-02..05, R-03) dan data dari M-RPT dan M-CRT.

---

## 2. Keputusan Teknis yang Membutuhkan Persetujuan

Setiap TD punya **gerbang**: komponen yang bergantung padanya tidak boleh diimplementasikan sebelum TD diputuskan. Empat PD teknis dari PRD (PD-20, PD-27, PD-29, PD-30) dipertahankan di sini dengan ID aslinya.

| ID | Topik | Opsi | Usulan | Gerbang (harus diputuskan sebelum) |
|---|---|---|---|---|
| **TD-01 (PD-30a)** | Stack aplikasi | (A) Laravel + PostgreSQL + Inertia; (B) NestJS + Next.js + PostgreSQL; (C) Django + HTMX | A, dengan syarat tim menguasai PHP/Laravel (Bagian 3) | Fase 1 (setup proyek) |
| **TD-02 (PD-30b)** | Hosting & infrastruktur | VPS/cloud, on-premise kampus, atau PaaS | VPS/cloud dengan PostgreSQL terkelola atau terpisah; environment staging dan production terpisah | Fase 1 (environment), go-live |
| **TD-03 (PD-30c)** | Tim pengembang & kepemilikan operasional | — | Perlu ditetapkan: siapa developer, siapa admin server, siapa pemilik backup | Fase 1 |
| **TD-04 (PD-27)** | Batas waktu sesi idle | 30 / 60 / 120 menit | 60 menit (usulan PRD) | Fase 1 (modul auth, AC-AUTH-05) |
| **TD-05 (PD-20)** | Retensi audit log dan notifikasi | — | Audit ≥ 5 tahun tanpa penghapusan otomatis di MVP; notifikasi 90 hari dengan pruning terjadwal | Audit: Fase 1 (desain tabel dan partisi). Notifikasi: Fase 7 (job pruning) |
| **TD-06 (PD-29)** | Backup, RPO/RTO, penanggung jawab | — | Backup harian otomatis terenkripsi, retensi ≥ 30 hari, RPO 24 jam, RTO 8 jam, uji restore tiap bulan | Fase 1 (environment), wajib terverifikasi sebelum go-live |
| TD-07 | Identitas login: email, username, atau keduanya | PRD 16.4 menyebut "email/username" | Email sebagai login (unik). Username tidak dipakai di MVP | Fase 1 (auth, skema `users`) |
| TD-08 | Pengelolaan role & permission oleh SA: matriks tetap di kode, atau dapat diedit saat runtime | PRD Bagian 9: "Kelola role & permission: SA" | **Matriks tetap di kode**; SA mengelola penetapan role ke akun. Alasan: banyak aturan bersifat mutlak ("hanya AP" untuk link sertifikat, status, sesi terakhir). Editor permission runtime berisiko melanggar keputusan bisnis. Lihat K-07 | Fase 1 (kerangka otorisasi) |
| TD-09 | Kebijakan password & lockout | — | Minimal 8 karakter; lockout 15 menit setelah 5 kali gagal; wajib ganti password saat login pertama | Fase 1 |
| TD-10 | Jam pengiriman pengingat H-1 dan H+1 | — | Satu job harian pukul 07.00 WIB | Fase 7 |
| TD-11 | Mekanisme pembaruan notifikasi | Polling vs WebSocket | Polling setiap 60 detik (memenuhi AC-NTF-01 tanpa infrastruktur tambahan) | Fase 7 |
| TD-12 | Varian frontend bila TD-01 = A | Vue 3 atau React | Mengikuti keahlian tim; keduanya didukung Inertia | Fase 1 |

**Catatan:** TD-01 sampai TD-04, TD-06 sampai TD-09, dan TD-12 adalah gerbang Fase 1. Tanpa keputusan ini, coding tidak dapat dimulai.

---

## 3. Rekomendasi Stack Teknologi **[USULAN-TEKNIS, TD-01]**

### 3.1 Rekomendasi utama (Opsi A)

| Lapisan | Pilihan | Alasan teknis terhadap PRD |
|---|---|---|
| Bahasa & framework backend | **PHP 8.3+ dengan Laravel** (versi major stabil terbaru saat kick-off; versi pasti dikunci di Fase 1) | Fitur bawaan menutup sebagian besar kebutuhan PRD: autentikasi sesi dan CSRF (Bagian 23), Policies/Gates (BR-ACC), Eloquent global scope (scoping cabang), Events & Listeners (notifikasi), Queue dan Scheduler (H-1/H+1), Notifications database channel (FR-NTF-01), migration. Satu aplikasi deployable, sehingga operasionalnya sederhana untuk tim kecil |
| Database | **PostgreSQL 16+** | (1) **Partial unique index** untuk invariant BR-AWL-03, BR-TL-07, narahubung utama, dan satu penugasan aktif. MySQL tidak mendukung ini secara langsung. (2) **pg_trgm** untuk deteksi kemiripan nama (AC-SCH-03). (3) **JSONB** untuk snapshot riwayat dan audit. (4) `timestamptz` untuk konsistensi WIB |
| Frontend | **Inertia.js + Vue 3 atau React [TD-12] + Tailwind CSS** | Pengalaman SPA tanpa membangun API publik terpisah. Auth sesi dan CSRF tetap dari Laravel. Komponen tabel, filter, dan kalender mudah dibuat responsif |
| Komponen UI | Pustaka komponen headless yang aksesibel (dipilih di Fase 1) | WCAG AA (Bagian 20 butir 14) |
| Kalender | Pustaka kalender open-source yang mendukung tampilan bulan/minggu (dipilih di Fase 4) | FR T-18 (P1) |
| Grafik | Pustaka chart ringan (dipilih di Fase 8) | FR-DSH-02/03, FR-RNK-03 |
| Queue | Driver **database** di MVP | Tanpa infrastruktur tambahan. Bisa beralih ke Redis bila volume naik [A-04] |
| Cache & session | Session driver **database** | Memungkinkan pencabutan sesi saat akun dinonaktifkan (Bagian 23 butir 9) |
| Testing | Pest/PHPUnit (feature & unit), Playwright (E2E inti), k6 (uji beban) | Acceptance criteria dipetakan langsung ke test (Bagian 9) |
| Kualitas kode | Laravel Pint, Larastan (static analysis), ESLint/Prettier | Konsistensi |
| CI | Pipeline Git (penyedia mengikuti TD-02/TD-03): lint, static analysis, test, build | Gerbang merge |

Paket pihak ketiga dibatasi seminimal mungkin. Audit trail, riwayat, dan otorisasi dibangun sendiri karena aturannya sangat spesifik PRD (misalnya "SA dilarang input link"). Paket generik cenderung memberi akses penuh ke SA secara default.

### 3.2 Alternatif

| Opsi | Kelebihan | Kekurangan | Kapan dipilih |
|---|---|---|---|
| B: NestJS + Next.js + Prisma + PostgreSQL | Satu bahasa (TypeScript) | Dua aplikasi; auth, CSRF, queue, scheduler, dan notifikasi harus dirakit sendiri; partial index lewat raw SQL | Tim jauh lebih kuat di TypeScript |
| C: Django + HTMX + PostgreSQL | Admin bawaan, ORM matang | UI interaktif (kalender, aksi cepat mentor) lebih repot; scheduler perlu Celery | Tim Python |

Apa pun opsinya, **PostgreSQL tetap direkomendasikan** karena kebutuhan partial unique index dan trigram.

---

## 4. Rancangan Arsitektur Aplikasi

### 4.1 Gaya arsitektur

**Modular monolith**: satu codebase dan satu deployment, dibagi per modul domain dengan batas yang jelas. Alasannya: skala MVP (asumsi A-04: ~5.000 sekolah), tim kecil, dan transaksi lintas modul yang harus atomik (misalnya laporan Selesai → posisi tahap → sertifikat → notifikasi).

### 4.2 Diagram komponen

```
┌───────────────────────────── Browser (desktop & ponsel) ─────────────────────────────┐
│  Inertia pages (Vue/React) · Tailwind · polling notifikasi 60 dtk [TD-11]             │
└───────────────────────────────────────┬───────────────────────────────────────────────┘
                                        │ HTTPS, cookie sesi (HttpOnly, Secure, SameSite), CSRF
┌───────────────────────────────────────▼───────────────────────────────────────────────┐
│                               Aplikasi Laravel (monolith)                             │
│                                                                                       │
│  HTTP layer      : Routes → Middleware (auth, active-user, role gate, idle timeout)   │
│                    → Controllers (tipis) → Form Requests (validasi)                    │
│                                                                                       │
│  Authorization   : Policies per aksi · Query scopes per role · Resources per konteks  │
│                                                                                       │
│  Domain modules  : Master · Pengguna · Sekolah · Mentor · Jadwal · Laporan ·          │
│  (Actions/       :  Rangkaian TL · Sertifikat · Notifikasi · Dashboard · Klasemen ·   │
│   Services)      :  Audit                                                             │
│                                                                                       │
│  Cross-cutting   : AuditWriter · HistoryWriter · ProgressRecalculator ·               │
│                    OptimisticLock · DomainEvents                                      │
│                                                                                       │
│  Async           : Queue worker (listener notifikasi) · Scheduler (H-1, H+1, pruning) │
└───────────────┬──────────────────────────────────────────────────┬────────────────────┘
                │                                                  │
        ┌───────▼────────┐                                ┌────────▼────────┐
        │ PostgreSQL     │                                │ Backup storage  │
        │ (data, audit,  │──── backup harian terenkripsi ─►│ terpisah [TD-06]│
        │ queue, session)│                                └─────────────────┘
        └────────────────┘
```

### 4.3 Struktur folder **[USULAN-TEKNIS]**

```
app/
  Domain/
    Master/        Models, Actions, Policies
    Pengguna/
    Sekolah/       Sekolah, Kategori, Narahubung, UsulanNarahubung, Arsip, KoreksiCabang
    Mentor/        Penugasan, MentorPelaksana
    Jadwal/        Kegiatan, RiwayatJadwal (Awal & TL)
    Rangkaian/     SesiTerakhir, BukaKembali, RiwayatRangkaian
    Laporan/       LaporanRevisi, KontakAwal
    Sertifikat/
    Notifikasi/    Notifications, Listeners, ScheduledReminders
    Dashboard/     Query objects (KPI, perbandingan cabang)
    Klasemen/
    Audit/
  Support/
    Authorization/ RoleGate, ScopeResolver, AccessConfig (A-01..A-03)
    History/       HistoryWriter
    Audit/         AuditWriter
    Progress/      ProgressRecalculator
    Concurrency/   OptimisticLock
  Http/
    Controllers/<Modul>/
    Requests/<Modul>/
    Resources/<Modul>/   (resource berbeda per konteks visibilitas)
resources/js/Pages/<Modul>/
database/migrations · database/seeders (35 kab/kota, 6 kategori, jenis, metode)
tests/Feature/<Modul>/  (nama test memuat ID AC PRD)
```

### 4.4 Model otorisasi tiga lapis

| Lapis | Fungsi | Contoh |
|---|---|---|
| 1. Role gate (middleware) | Menolak role yang tidak berhak atas seluruh area | `/pengguna/*` dan `/audit/*` hanya SA (AC-USR-01, AC-AUD-02) |
| 2. Query scope | Membatasi baris yang dapat dibaca | AC: `sekolah.cabang_id = user.cabang_id`. M: semua sekolah bila `mentor_can_view_all_schools = true` **[A-01]**, selain itu hanya sekolah tugasnya |
| 3. Policy per aksi + aturan state | Mengizinkan aksi pada objek tertentu sesuai kondisi | Mentor menambah sesi hanya bila ia penanggung jawab **dan** sesi terakhir belum ditetapkan (BR-TL-10). AP mengubah status hanya dengan alasan (BR-ACT-04) |
| + Resource per konteks | Membatasi field yang dikirim | `KegiatanKoordinasiResource` untuk mentor non-tugas: tanpa catatan dan tautan (R-01, AC-RPT-02) |

**Aturan keras:** controller tidak boleh mengirim model Eloquent mentah ke Inertia props. Props Inertia dapat dibaca di page source, jadi semua data keluar lewat Resource (lihat risiko S-02).

### 4.5 Alur transaksi kunci

**(a) Mentor melaporkan status kegiatan**

```
Request → Policy(LaporanPolicy::update) → DB transaction {
    lock kegiatan (optimistic: version)
    insert laporan_revisi (versi n+1, status, catatan wajib)
    update kegiatan.status
    ProgressRecalculator::forSekolah(id)   // posisi tahap, tanggal mitra,
                                           // rangkaian selesai, baris sertifikat
    insert riwayat_rangkaian bila rangkaian berubah status
    AuditWriter::record(...)
    emit KegiatanStatusChanged, RangkaianSelesai?, SekolahMenjadiMitra?
} → after commit: listener queue → notifikasi
```

**(b) AP menetapkan sesi terakhir** → validasi sesi milik sekolah dan berstatus bukan Dibatalkan → pindahkan flag dengan konfirmasi (AC-JTL-06) → recalculator → audit dan riwayat rangkaian.

**(c) Job harian [TD-10]** → query kegiatan H-1 (Belum Dimulai) dan H+1 (belum Selesai/Dibatalkan) → kirim notifikasi dengan *dedup key* `(user, jenis, kegiatan, tanggal)` agar job yang terulang tidak mengirim dobel.

### 4.6 Derived state: `ProgressRecalculator`

Satu fungsi murni, diuji unit secara menyeluruh. Fungsi ini menghitung ulang untuk satu sekolah:

| Output | Aturan PRD |
|---|---|
| `posisi_tahap` | 15.3, termasuk pengecualian sesi Dibatalkan |
| `tanggal_mitra` | BR-SCH-02 |
| Status rangkaian TL | BR-TL-06/12: ada sesi terakhir, sesi terakhir Selesai, dan seluruh sesi yang tidak Dibatalkan Selesai |
| Baris `sertifikat` | Dibuat saat tahap selesai (FR-CRT-01). Tidak pernah dihapus saat dibuka kembali (E6) |
| Kebalikan (E8) | Pelatihan awal kembali dari Selesai: sekolah kembali Calon Mitra hanya bila belum ada sesi TL dan sertifikat Awal belum Tersedia; selain itu aksi ditolak di policy |

Hasilnya disimpan sebagai **cache kolom** di `sekolah` (untuk filter dan dashboard cepat). Sumber kebenaran tetap tabel `kegiatan`. Tersedia perintah `progress:rebuild` untuk menghitung ulang seluruh data bila diperlukan.

---

## 5. Rancangan Database

### 5.1 Prinsip

1. **Tidak ada hard delete** untuk data bisnis, kecuali kegiatan tanpa laporan (BR-ACT-09). Gunakan arsip atau nonaktif.
2. **Invariant penting dijaga di DB** (partial unique index, CHECK, FK `ON DELETE RESTRICT`), tidak hanya di aplikasi.
3. **Riwayat berupa tabel append-only**. Aplikasi tidak menyediakan update/delete untuk tabel riwayat dan audit.
4. Enum disimpan sebagai `text` + CHECK constraint, agar penambahan nilai cukup lewat migration ringan.
5. Semua tabel bisnis memiliki `created_at`, `updated_at`; tabel yang dapat diedit memiliki `version` (optimistic lock).
6. Timestamp memakai `timestamptz`. Tanggal dan jam kegiatan memakai `date` + `time` (WIB).

### 5.2 Daftar tabel

#### Master **[PRD 13.10, 16.10]**

| Tabel | Kolom utama | Constraint |
|---|---|---|
| `kabupaten_kota` | id, kode, nama | UNIQUE(kode). Seed 35 kab/kota Jawa Tengah |
| `cabang` | id, kode, nama, alamat, aktif | UNIQUE(kode). Tidak ada pemetaan ke kabupaten (BR-SCH-09) |
| `kategori_pelatihan` | id, nama, deskripsi, aktif | UNIQUE(nama). Seed 6 kategori |
| `jenis_kegiatan` | id, nama, aktif | Seed: Pelatihan, Pendampingan |
| `metode_kegiatan` | id, nama, butuh_lokasi, butuh_tautan, aktif | Seed: Online (tautan), Offline (lokasi) |

#### Pengguna **[PRD 16.4]**

| Tabel | Kolom utama | Constraint |
|---|---|---|
| `users` | id, nama, email [TD-07], telepon, role (`superadmin`/`admin_pusat`/`admin_cabang`/`mentor`), cabang_id NULL, password (hash), aktif, wajib_ganti_password, gagal_login, dikunci_sampai, last_login_at | UNIQUE(lower(email)). CHECK(role <> 'admin_cabang' OR cabang_id IS NOT NULL). `cabang_id` untuk mentor tidak dipakai untuk otorisasi **[A-03]** |
| `sessions` | standar driver database | Index user_id (pencabutan sesi) |

#### Sekolah **[PRD 16.1–16.3]**

| Tabel | Kolom utama | Constraint & index |
|---|---|---|
| `sekolah` | id, nama, nama_normal, jenjang (`SMA`/`SMK`/`MA`), alamat, kabupaten_kota_id, cabang_id, posisi_tahap (cache), tanggal_mitra, tl_selesai_at (cache), diarsipkan_at, alasan_arsip, diarsipkan_oleh, created_by, version | **UNIQUE(nama_normal, jenjang, kabupaten_kota_id)** tanpa filter arsip (BR-SCH-03/13, AC-SCH-20). GIN trigram index pada `nama_normal` (AC-SCH-03). Index (cabang_id, posisi_tahap), (kabupaten_kota_id) |
| `sekolah_kategori` | sekolah_id, kategori_id, created_at | PK(sekolah_id, kategori_id) (BR-SCH-04). Index kategori_id (klasemen) |
| `sekolah_cabang_koreksi` | id, sekolah_id, cabang_lama, cabang_baru, alasan, oleh (SA), created_at | Append-only (BR-SCH-12). Juga dicatat di audit |
| `narahubung` | id, sekolah_id, nama, jabatan, nomor_wa, email, is_utama, aktif, version | **Partial UNIQUE(sekolah_id) WHERE is_utama AND aktif** (BR-SCH-06) |
| `narahubung_usulan` | id, sekolah_id, narahubung_id NULL, data_sebelum JSONB, data_usulan JSONB, diusulkan_oleh, diajukan_at, status (`diajukan`/`disetujui`/`ditolak`/`perlu_ditinjau`), diputuskan_oleh, diputuskan_at, alasan_penolakan | Index (sekolah_id, status). `perlu_ditinjau` dipakai untuk R-09 |
| `narahubung_riwayat` | id, narahubung_id, data_sebelum JSONB, data_sesudah JSONB, diubah_oleh, usulan_id NULL, created_at | Append-only |

**Normalisasi `nama_normal`** **[USULAN-TEKNIS]**: huruf kecil, spasi ganda dihapus, tanda baca dibuang, singkatan baku diseragamkan ("sma negeri" → "sman", dan seterusnya; daftar dikunci di Fase 3). Normalisasi dihitung di aplikasi, dengan unit test.

#### Mentor **[PRD 16.5]**

| Tabel | Kolom utama | Constraint |
|---|---|---|
| `penugasan_mentor` | id, sekolah_id, mentor_id, mulai, selesai NULL, ditugaskan_oleh, alasan_selesai | **Partial UNIQUE(sekolah_id) WHERE selesai IS NULL** (BR-MNT-05). Index (mentor_id) WHERE selesai IS NULL |

#### Jadwal & Rangkaian **[PRD 16.6, 16.7, 16.14]**

| Tabel | Kolom utama | Constraint & index |
|---|---|---|
| `kegiatan` | id, sekolah_id, tahap (`awal`/`tindak_lanjut`), urutan, nama_sesi, jenis_kegiatan_id, metode_id, tanggal, waktu_mulai, waktu_selesai, lokasi, tautan_pertemuan, mentor_pelaksana_id, pelaksana_ditunjuk_khusus (bool, untuk E3), keterangan, status (`belum_dimulai`/`sedang_berlangsung`/`selesai`/`dibatalkan`), is_sesi_terakhir, created_by, version | CHECK(waktu_selesai > waktu_mulai). **Partial UNIQUE(sekolah_id) WHERE tahap='awal' AND status<>'dibatalkan'** (BR-AWL-03, AC-JDA-02/08). **Partial UNIQUE(sekolah_id) WHERE is_sesi_terakhir** (BR-TL-07). UNIQUE(sekolah_id, tahap, urutan). CHECK(tahap='tindak_lanjut' OR is_sesi_terakhir = false). Index (tanggal, status), (mentor_pelaksana_id, tanggal), (sekolah_id, tahap) |
| `kegiatan_jadwal_riwayat` | id, kegiatan_id, versi, sebelum JSONB, sesudah JSONB, alasan, diubah_oleh, created_at | UNIQUE(kegiatan_id, versi). Append-only |
| `rangkaian_tl_riwayat` | id, sekolah_id, aksi (`tetapkan_sesi_terakhir`/`pindah_sesi_terakhir`/`selesai_otomatis`/`buka_kembali`/`sesi_terakhir_dibatalkan`), kegiatan_id NULL, alasan, oleh NULL (NULL = sistem), created_at | Append-only (FR-JTL-07, BR-TL-09) |

Lokasi pelatihan awal: metode dikunci Offline dan lokasi diisi "Kampus Pusat" (FR-JDA-03). Nilai teks lokasi kampus pusat disimpan sebagai **setting aplikasi** yang dikelola SA, bukan hard-code (detail alamat belum dikonfirmasi).

#### Laporan **[PRD 16.8, 16.13]**

| Tabel | Kolom utama | Constraint |
|---|---|---|
| `laporan_revisi` | id, kegiatan_id, versi, status, catatan, ditulis_oleh, peran_penulis (`mentor`/`admin_pusat`), alasan_perubahan_admin, created_at | UNIQUE(kegiatan_id, versi). CHECK(peran_penulis <> 'admin_pusat' OR alasan_perubahan_admin IS NOT NULL). CHECK(length(trim(catatan)) > 0). Append-only |
| `kontak_awal` | id, sekolah_id, mentor_id, versi, sudah_dihubungi, tanggal_kontak, catatan, created_at | UNIQUE(sekolah_id, versi). Append-only; versi terbaru = nilai aktif |

#### Sertifikat **[PRD 16.9]**

| Tabel | Kolom utama | Constraint |
|---|---|---|
| `sertifikat` | id, sekolah_id, tahap, kegiatan_acuan_id, link NULL, tanggal_terbit, diinput_oleh, diinput_at, version | UNIQUE(sekolah_id, tahap). CHECK(link IS NULL OR link ~* '^https?://'). Status diturunkan: baris belum ada = Belum Berlaku, link kosong = Menunggu Penerbitan, link ada = Tersedia |

#### Notifikasi, Audit, Setting

| Tabel | Kolom utama | Catatan |
|---|---|---|
| `notifications` | id (uuid), type, notifiable_id, data JSONB, read_at, created_at, dedup_key NULL | Partial UNIQUE(dedup_key) WHERE dedup_key IS NOT NULL (pengingat terjadwal). Pruning sesuai [TD-05] |
| `audit_log` | id (bigserial), occurred_at, user_id NULL, role_saat_aksi, aksi, entitas, entitas_id, sebelum JSONB, sesudah JSONB, ip, user_agent | Append-only. Index (entitas, entitas_id), (user_id, occurred_at), (occurred_at). Partisi bulanan bila volume besar [TD-05, A-04]. User DB aplikasi idealnya hanya punya hak INSERT/SELECT [TD-02] |
| `app_settings` | key, value JSONB, diubah_oleh, updated_at | Lokasi kampus pusat, flag asumsi A-01 (bila diputuskan bisa diubah tanpa deploy) |
| `jobs`, `failed_jobs`, `cache`, `job_batches` | standar | Queue driver database |

### 5.3 Diagram relasi

```
kabupaten_kota 1──* sekolah *──1 cabang 1──* users(admin_cabang)
                     │
   ┌─────────────────┼───────────────────┬──────────────────┬───────────────────┐
   │                 │                   │                  │                   │
   * sekolah_kategori*──1 kategori       * narahubung        * penugasan_mentor  * kontak_awal
                     │                   │   ├─* narahubung_riwayat  └──1 users(mentor)
                     │                   * narahubung_usulan
                     │
                     * kegiatan ──* kegiatan_jadwal_riwayat
                     │   ├──* laporan_revisi
                     │   ├──1 jenis_kegiatan, 1 metode_kegiatan
                     │   └──1 users(mentor_pelaksana)
                     │
                     * rangkaian_tl_riwayat
                     * sertifikat (≤2) ──1 kegiatan (acuan)
                     * sekolah_cabang_koreksi

users 1──* notifications · users 1──* audit_log · users 1──* sessions
```

### 5.4 Pemetaan ke entitas PRD

| Entitas PRD (Bagian 16) | Tabel |
|---|---|
| Sekolah, Sekolah_Kategori, Narahubung | `sekolah`, `sekolah_kategori`, `narahubung` |
| Usulan_Narahubung, Riwayat_Narahubung | `narahubung_usulan`, `narahubung_riwayat` |
| Pengguna | `users` |
| Penugasan_Mentor | `penugasan_mentor` |
| Kegiatan, Riwayat_Jadwal | `kegiatan`, `kegiatan_jadwal_riwayat` |
| Laporan_Revisi | `laporan_revisi` |
| Kontak_Awal | `kontak_awal` |
| Riwayat_Rangkaian_TL | `rangkaian_tl_riwayat` |
| Sertifikat | `sertifikat` |
| Master | `cabang`, `kabupaten_kota`, `kategori_pelatihan`, `jenis_kegiatan`, `metode_kegiatan` |
| Notifikasi, Audit_Log | `notifications`, `audit_log` |
| *(tambahan teknis)* | `sekolah_cabang_koreksi`, `app_settings`, `sessions`, tabel queue |

---

## 6. Matriks Permission Final

Diturunkan dari PRD Bagian 9 dan Bagian 14 (v1.3). Implementasinya berupa **satu sumber konfigurasi** (`AccessConfig`) yang dibaca policy, sehingga test otorisasi bisa dibangkitkan otomatis dari tabel ini.

Legenda: ✔ semua data · **C** hanya cabangnya · **T** sekolah/kegiatan yang menjadi tanggung jawabnya (penanggung jawab atau pelaksana sesi) · **PJ** hanya mentor penanggung jawab · ✖ ditolak (403) · *(A-0x)* bergantung asumsi.

### 6.1 Sekolah & Mentor

| Kode aksi | Aksi | SA | AP | AC | M | Aturan tambahan | Rujukan |
|---|---|:-:|:-:|:-:|:-:|---|---|
| `sekolah.view` | Lihat daftar & detail sekolah | ✔ | ✔ | C | ✔ *(A-01)* | Mentor non-tugas: tanpa catatan laporan dan link sertifikat | BR-ACC-05, AC-SCH-04/17 |
| `sekolah.create` | Tambah sekolah | ✔ | ✔ (pilih cabang) | C (cabang otomatis) | ✖ | Cek duplikat termasuk arsip | FR-SCH-01/02/03, AC-SCH-14/20 |
| `sekolah.update` | Ubah data sekolah (selain cabang) | ✔ | ✔ | C | ✖ | Optimistic lock | FR-SCH-01 |
| `sekolah.correct_cabang` | Koreksi cabang salah input | ✔ | ✖ | ✖ | ✖ | Hanya bila sekolah tanpa kegiatan sama sekali (termasuk Dibatalkan); alasan wajib | BR-SCH-12, AC-SCH-18/19 |
| `sekolah.transfer` | Pindah cabang | ✖ | ✖ | ✖ | ✖ | Tidak ada endpoint | BR-SCH-10 |
| `sekolah.archive` | Arsipkan sekolah | ✔ | ✔ | C | ✖ | Alasan wajib; kegiatan belum selesai dibatalkan dengan alasan | BR-SCH-05, AC-SCH-15 |
| `sekolah.kategori` | Atur kategori | ✔ | ✔ | C | ✖ | — | FR-SCH-04 |
| `narahubung.manage` | Kelola narahubung langsung | ✔ | ✔ | C | ✖ | Satu utama; nama dan WA wajib | BR-SCH-06, AC-SCH-12/16 |
| `narahubung.propose` | Usulkan perubahan narahubung | ✖ | ✖ | ✖ | T | Tidak langsung berlaku | BR-SCH-11, AC-SCH-21 |
| `narahubung.decide` | Setujui/tolak usulan | ✖ | ✔ | C | ✖ | — | BR-SCH-11, AC-SCH-22/23 |
| `penugasan.assign` | Tugaskan/ganti mentor penanggung jawab | ✔ | ✔ *(A-02)* | ✖ | ✖ | Satu aktif per sekolah; mentor lintas cabang *(A-03)* | BR-MNT-01/05, FR-MNT-02 |
| `kegiatan.assign_pelaksana` | Tunjuk mentor pelaksana per sesi | ✔ | ✔ | ✖ | ✖ | — | BR-MNT-06, FR-MNT-05 |
| `kontak_awal.record` | Isi checklist kontak awal | ✖ | ✖ | ✖ | T | Tidak memblokir laporan *(R-02)* | BR-RPT-09, AC-RPT-11 |

### 6.2 Jadwal, Rangkaian & Laporan

| Kode aksi | Aksi | SA | AP | AC | M | Aturan tambahan | Rujukan |
|---|---|:-:|:-:|:-:|:-:|---|---|
| `kegiatan.view` | Lihat jadwal | ✔ | ✔ | C | ✔ info koordinasi *(R-01)*; detail untuk T | Field per konteks | PD-03, AC-RPT-02 |
| `awal.create` | Buat jadwal Pelatihan Awal | ✔ | ✔ | C | ✖ | Belum ada awal aktif/Selesai; sekolah punya PJ; tidak diarsipkan | FR-JDA-01/02, AC-JDA-01..08 |
| `tl.create` | Tambah sesi TL (sebelum sesi terakhir ditetapkan) | ✔ | ✔ | C | PJ | Pelatihan awal harus Selesai | BR-TL-05, BR-ACT-12, AC-JTL-01/10 |
| `tl.create_after_last` | Tambah sesi setelah sesi terakhir ditetapkan | ✖ | ✔ | ✖ | ✖ | AP lalu memperbarui sesi terakhir | BR-TL-10, AC-JTL-13 |
| `kegiatan.reschedule` | Ubah tanggal/waktu/lokasi/tautan | ✔ | ✔ | C | T | Alasan wajib; bukan untuk status Selesai/Dibatalkan; riwayat versi | BR-ACT-05..08/11, AC-JTL-08 |
| `kegiatan.delete` | Hapus kegiatan | ✔ | ✔ | C | ✖ | Hanya bila belum ada laporan | BR-ACT-09 |
| `kegiatan.report` | Ubah status + catatan (laporan mentor) | ✖ | ✖ | ✖ | T | Catatan wajib; Selesai tidak untuk tanggal masa depan | BR-ACT-04, BR-RPT-02/07, AC-RPT-01 |
| `kegiatan.admin_status` | Ubah status oleh admin | ✖ | ✔ | ✖ | ✖ | Alasan wajib; tercatat sebagai revisi; bukan verifikasi | BR-ACT-04, AC-RPT-09/10 |
| `laporan.view_detail` | Lihat laporan detail & revisi | ✔ | ✔ | C | T | Mentor lain ditolak | BR-RPT-08, AC-RPT-02 |
| `rangkaian.set_last` | Tetapkan/pindah sesi terakhir | ✖ | ✔ | ✖ | ✖ | Sesi tidak Dibatalkan | BR-TL-06, AC-JTL-06 |
| `rangkaian.reopen` | Buka kembali rangkaian | ✖ | ✔ | ✖ | ✖ | Alasan wajib | BR-TL-09, AC-JTL-11 |

### 6.3 Sertifikat, Insight, Administrasi

| Kode aksi | Aksi | SA | AP | AC | M | Rujukan |
|---|---|:-:|:-:|:-:|:-:|---|
| `sertifikat.view_status` | Lihat status sertifikat | ✔ | ✔ | C | ✔ *(A-01)* | 13.7 |
| `sertifikat.open_link` | Buka link | ✔ | ✔ | C | T | BR-CRT-07, AC-CRT-08 |
| `sertifikat.manage_link` | Input/ubah/ganti link | ✖ | ✔ | ✖ | ✖ | BR-CRT-03, AC-CRT-01 |
| `dashboard.view` | Dashboard | ✔ global | ✔ global | C | T *(rekomendasi PRD 22.3)* | BR-DSH-01, AC-DSH-01/02 |
| `dashboard.branch_compare` | Perbandingan cabang (agregat) | ✔ | ✔ | ✔ agregat, tanpa drill-down cabang lain | ✖ *(rekomendasi PRD)* | BR-DSH-05, AC-DSH-06/07 |
| `klasemen.view` | Klasemen kategori | ✔ | ✔ | ✔ | ✔ | BR-RNK-03, AC-RNK-04/07 |
| `users.manage` | Kelola semua akun (termasuk mentor) | ✔ | ✖ | ✖ | ✖ | BR-USR-01, AC-USR-01 |
| `roles.assign` | Tetapkan role ke akun | ✔ | ✖ | ✖ | ✖ | Bagian 9 [TD-08] |
| `master.manage` | Kelola data master & setting | ✔ | ✖ | ✖ | ✖ | BR-USR-02, AC-USR-02 |
| `audit.view` | Lihat audit trail | ✔ | ✖ | ✖ | ✖ | BR-AUD-01, AC-AUD-02 |
| `notifikasi.own` | Lihat & tandai notifikasi sendiri | ✔ | ✔ | ✔ | ✔ | FR-NTF-01/03 |

**Catatan konsistensi:** SA memiliki "akses penuh" (keputusan no. 20), **kecuali** aksi yang PRD tetapkan secara spesifik untuk role lain: `sertifikat.manage_link`, `kegiatan.admin_status`, `kegiatan.report`, `rangkaian.*`, `tl.create_after_last`, `narahubung.decide`, `narahubung.propose`, `kontak_awal.record`. Pengecualian ini disengaja sesuai PRD (PD-05, PD-10). Test otorisasi wajib memverifikasi SA **ditolak** pada aksi-aksi ini.

---

## 7. Daftar Modul & Pemetaan ke FR/AC

Urutan mengikuti dependensi (Bagian 1.3) dan prioritas MVP PRD 28.1. Hanya FR berprioritas P0/P1 yang masuk MVP. FR P2 (FR-SCH-09, FR-RNK-04, FR-NTF-04, FR-EXP-01) **tidak dikerjakan**.

| # | Modul | Lingkup | FR | AC | Item PRD 28.1 | Prioritas |
|---|---|---|---|---|---|:-:|
| 1 | **M-AUTH** Autentikasi & Otorisasi | Login, logout, sesi idle, ganti/reset password, lockout, RBAC + scope + policy | FR-AUTH-01..05 | AC-AUTH-01..05 | T-07, T-08 | P0 |
| 2 | **M-AUD (inti)** Audit writer | Pencatatan semua aksi tulis, login, gagal login | FR-AUD-01, FR-AUD-03 | AC-AUD-01, AC-AUD-03 | T-09 | P0 |
| 3 | **M-MST** Data master & setting | CRUD master oleh SA, nonaktif bila dipakai, seed | FR-MST-01/02 | AC-USR-02 | T-10 | P0 |
| 4 | **M-USR** Pengguna | CRUD akun oleh SA, nonaktif + cabut sesi | FR-USR-01/02, FR-MNT-01 | AC-USR-01/03, AC-AUTH-03 | T-11 | P0 |
| 5 | **M-SCH** Sekolah | CRUD, anti-duplikasi, kategori, cabang terkunci, koreksi SA, arsip, daftar + filter, detail | FR-SCH-01..04, 06..08, 10, 11 | AC-SCH-01..11, 13..15, 17..20 | T-12, T-13 | P0 |
| 6 | **M-NRH** Narahubung | Kelola langsung, riwayat, usulan mentor + persetujuan | FR-SCH-05, FR-SCH-12 | AC-SCH-12, 16, 21..23 | T-12a | P0 |
| 7 | **M-MNT** Penugasan mentor | Penanggung jawab, mentor pelaksana per sesi, riwayat, halaman "Sekolah Saya" | FR-MNT-02..05 | AC-RPT-08 | T-14 | P0 |
| 8 | **M-JDA** Jadwal Pelatihan Awal | Buat, reschedule + riwayat, kunci offline kampus pusat, jadwal ulang setelah batal | FR-JDA-01..04 | AC-JDA-01..08 | T-15, T-17 | P0 |
| 9 | **M-JTL** Jadwal Tindak Lanjut | Sesi fleksibel, urutan, tambah sesi oleh mentor PJ, indikator juklak, reschedule | FR-JTL-01..04, 06, 09 | AC-JTL-01..05, 08..10, 12, 13, 16 | T-16, T-17 | P0 (FR-JTL-04: P1) |
| 10 | **M-RPT** Laporan & progres | Status + catatan, revisi, ubah status AP, kontak awal, Perlu Pembaruan, halaman Progres Mentor, halaman mentor | FR-RPT-01..08, FR-MNT-04/06 | AC-RPT-01..12 | T-19, T-21, T-22 | P0 (FR-MNT-06: P1) |
| 11 | **M-RGK** Penyelesaian rangkaian | Sesi terakhir, aturan sesi Dibatalkan, selesai otomatis, buka kembali, daftar perhatian AP | FR-JTL-05, 07, 08, 10 | AC-JTL-06, 07, 11, 14, 15 | T-16a, T-20 | P0 (FR-JTL-08: P1) |
| 12 | **M-CRT** Sertifikat | Baris per tahap, status turunan, input link AP, akses buka link, daftar Menunggu | FR-CRT-01..06 | AC-CRT-01..08 | T-23, T-24 | P0 |
| 13 | **M-NTF** Notifikasi | Pemicu event, H-1/H+1, lonceng, tandai dibaca, polling, pruning | FR-NTF-01..03 | AC-NTF-01..08 | T-25 | P0 |
| 14 | **M-RNK** Klasemen | Sekolah mitra unik per kategori, dense ranking, grafik | FR-RNK-01..03 | AC-RNK-01..07 | T-26 | P0 (FR-RNK-03: P1) |
| 15 | **M-DSH** Dashboard | KPI per role, grafik, perbandingan cabang, kegiatan mendatang | FR-DSH-01..06 | AC-DSH-01..07 | T-27 | P0 (FR-DSH-05: P1) |
| 16 | **M-AUD (UI)** Halaman audit | Filter dan diff, khusus SA | FR-AUD-02 | AC-AUD-02 | T-28 | P0 |
| 17 | **M-CAL** Kalender | Tampilan bulan/minggu, warna per tahap | — (UI 20.10) | — | T-18 | P1 |

---

## 8. Tahapan Implementasi

Setiap fase memiliki **gerbang masuk** (prasyarat), **deliverable**, dan **exit criteria** yang dapat diuji. Fase berikutnya dimulai setelah exit criteria fase sebelumnya terpenuhi, kecuali fase yang tidak saling bergantung. Estimasi durasi sengaja tidak dicantumkan karena ukuran tim belum diketahui [TD-03]; kompleksitas relatif ditandai S/M/L.

### Fase 0 — Persiapan & Keputusan Teknis (T-01..T-04) · M

| Komponen | Isi |
|---|---|
| Gerbang masuk | PRD v1.3 disetujui ✔ (T-01 Selesai) |
| Deliverable | (1) Keputusan TD-01..TD-04, TD-06..TD-09, TD-12 tercatat di dokumen ini; (2) wireframe dan desain hi-fi halaman inti: daftar sekolah, detail sekolah, form jadwal, Kegiatan Saya (mobile), Progres Mentor, dashboard; (3) data awal terkumpul: daftar cabang, sekolah, dan mentor beserta email untuk akun; (4) penetapan nilai setting "lokasi kampus pusat" |
| Exit criteria | Semua TD bergerbang Fase 1 berstatus "Diputuskan"; desain disetujui pemilik bisnis; dokumen ini berstatus "Disetujui" |

### Fase 1 — Fondasi (T-05..T-09) · L

| Komponen | Isi |
|---|---|
| Gerbang masuk | Fase 0 selesai; TD-01, 02, 03, 04, 06, 07, 08, 09, 12 diputuskan; TD-05 diputuskan untuk bagian audit |
| Deliverable | Repo dan struktur modul (4.3); CI (lint, static analysis, test); environment dev/staging dengan backup harian aktif [TD-06]; migration seluruh skema Bagian 5; autentikasi (login email [TD-07], lockout [TD-09], idle timeout [TD-04], wajib ganti password); kerangka otorisasi tiga lapis + `AccessConfig` (Bagian 6) + flag asumsi (Bagian 11); `AuditWriter`, `HistoryWriter`, `OptimisticLock`; layout aplikasi dan navigasi per role (PRD 11) |
| Exit criteria | AC-AUTH-01..05 lulus; AC-AUD-01 dan AC-AUD-03 lulus untuk aksi login/logout/gagal login; seluruh partial unique index terbukti menolak data invalid (test DB); **test otorisasi terbangkit otomatis dari matriks Bagian 6** berjalan (deny-by-default untuk aksi yang belum diimplementasikan); restore backup staging berhasil diuji satu kali |

### Fase 2 — Master & Pengguna (T-10, T-11) · S

| Komponen | Isi |
|---|---|
| Gerbang masuk | Fase 1 selesai |
| Deliverable | Seeder (35 kab/kota, 6 kategori, Pelatihan/Pendampingan, Online/Offline); CRUD master oleh SA dengan nonaktif-bukan-hapus; CRUD akun oleh SA, nonaktif + pencabutan sesi; reset password oleh SA |
| Exit criteria | AC-USR-01..03, AC-AUTH-03 lulus; FR-MST-02 diverifikasi (master yang dipakai tidak bisa dihapus); semua perubahan tercatat di audit |

### Fase 3 — Sekolah, Narahubung & Mentor (T-12, T-12a, T-13, T-14) · L

| Komponen | Isi |
|---|---|
| Gerbang masuk | Fase 2 selesai; data master tersedia |
| Deliverable | CRUD sekolah dengan normalisasi nama dan cek duplikat (termasuk arsip) + peringatan kemiripan trigram; cabang terkunci + koreksi SA; arsip dengan alasan; daftar sekolah (pencarian, filter AND, paginasi server, kolom 13.2); detail sekolah (tab Ringkasan dan Riwayat); narahubung + riwayat + alur usulan/persetujuan; penugasan mentor PJ + riwayat; "Sekolah Saya" untuk mentor; domain events disiapkan (listener notifikasi belum) |
| Exit criteria | AC-SCH-01..23 lulus (kecuali bagian yang bergantung pada kegiatan: AC-SCH-09/10/19 diuji ulang di Fase 4/6); AC-SCH-06 ≤ 2 detik pada dataset 5.000 sekolah [A-04] |

### Fase 4 — Penjadwalan (T-15, T-16, T-17, T-18) · L

| Komponen | Isi |
|---|---|
| Gerbang masuk | Fase 3 selesai |
| Deliverable | Jadwal Pelatihan Awal (metode dan lokasi terkunci, jadwal ulang sebagai kegiatan baru); sesi TL (urutan otomatis, nama dapat diubah, validasi lokasi/tautan per metode, indikator juklak 4+1 tanpa validasi); tambah sesi oleh mentor PJ; penunjukan mentor pelaksana per sesi; reschedule + riwayat versi + alasan; optimistic lock; kalender (P1) |
| Exit criteria | AC-JDA-01..08, AC-JTL-01..05, 08, 09, 10, 12, 16 lulus; AC-SCH-09 dan AC-SCH-19 lulus; E9 (edit bersamaan) teruji |

### Fase 5 — Laporan, Progres & Penyelesaian Rangkaian (T-19, T-20, T-21, T-22, T-16a) · L

| Komponen | Isi |
|---|---|
| Gerbang masuk | Fase 4 selesai |
| Deliverable | Laporan mentor (status + catatan wajib, revisi, tolak Selesai bertanggal masa depan); ubah status oleh AP dengan alasan; `ProgressRecalculator` + `progress:rebuild`; sesi terakhir (AP), aturan sesi Dibatalkan, selesai otomatis, buka kembali, riwayat rangkaian; checklist kontak awal; label Perlu Pembaruan (H+1); halaman Progres Mentor (matriks sekolah × sesi); halaman mentor mobile (Beranda, Kegiatan Saya dengan aksi ≤ 3 klik, Jadwal Koordinasi); daftar perhatian AP (FR-JTL-08) |
| Exit criteria | AC-RPT-01..12, AC-JTL-06, 07, 11, 13, 14, 15, AC-JDA-07 lulus; unit test `ProgressRecalculator` mencakup seluruh baris tabel 15.3 dan edge case E6, E8, E15, E20, E22; uji manual di ponsel 360px untuk alur lapor |

### Fase 6 — Sertifikat (T-23, T-24) · M

| Komponen | Isi |
|---|---|
| Gerbang masuk | Fase 5 selesai (sertifikat bergantung pada tahap selesai) |
| Deliverable | Baris sertifikat otomatis saat tahap selesai; status turunan; input/ubah link oleh AP dengan validasi URL dan audit nilai lama/baru; aturan akses buka link; halaman Sertifikat (Menunggu/Tersedia); kartu sertifikat di detail sekolah |
| Exit criteria | AC-CRT-01..08, AC-SCH-10 lulus; SA terbukti ditolak saat input link |

### Fase 7 — Notifikasi (T-25) · M

| Komponen | Isi |
|---|---|
| Gerbang masuk | Fase 6 selesai; TD-05 (retensi notifikasi), TD-10, TD-11 diputuskan |
| Deliverable | Listener untuk seluruh pemicu FR-NTF-02 dan pemicu tambahan R-07; penerima sesuai BR-NTF-01 (mentor terkait, AP, AC cabang) tanpa pelaku (BR-NTF-02); job harian H-1/H+1 dengan dedup key; lonceng + daftar + tandai dibaca; polling; job pruning |
| Exit criteria | AC-NTF-01..08, AC-SCH-21 (bagian notifikasi), AC-RPT-07 (bagian notifikasi) lulus; job harian idempoten (dijalankan dua kali tanpa notifikasi ganda) |

### Fase 8 — Klasemen & Dashboard (T-26, T-27) · M

| Komponen | Isi |
|---|---|
| Gerbang masuk | Fase 6 selesai (klasemen boleh mulai setelah Fase 3) |
| Deliverable | Klasemen (sekolah mitra unik, dense ranking, kategori 0 tetap tampil, catatan kaki); KPI 22.1 per role; grafik 22.2; tabel perbandingan cabang (metrik BR-DSH-05, R-03) dengan pembatasan drill-down untuk AC; kegiatan mendatang dan perlu pembaruan |
| Exit criteria | AC-RNK-01..07, AC-DSH-01..07, AC-CRT-07 lulus; **seluruh rumus terbukti mengecualikan kegiatan Dibatalkan** (aturan konsistensi PRD 27.4) lewat test dengan data campuran; dashboard ≤ 3 detik p95 pada 5.000 sekolah / 50.000 kegiatan [A-04] |

### Fase 9 — Audit UI (T-28) · S

| Komponen | Isi |
|---|---|
| Gerbang masuk | Fase 1 (dapat dikerjakan paralel sejak Fase 3) |
| Deliverable | Halaman audit SA: filter pengguna/entitas/aksi/tanggal, tampilan diff sebelum/sesudah, termasuk pengguna nonaktif |
| Exit criteria | AC-AUD-02 lulus; tidak ada route ubah/hapus audit |

### Fase 10 — Hardening & Rilis (T-29..T-32) · M

| Komponen | Isi |
|---|---|
| Gerbang masuk | Fase 1–9 selesai; TD-02 dan TD-06 terverifikasi di production |
| Deliverable | Uji keamanan (akses lintas cabang, IDOR, XSS pada catatan, CSRF, brute force login); uji beban k6; environment production + backup + monitoring; input data awal manual oleh admin (T-29, tanpa impor); UAT dengan AP, AC, dan Mentor memakai checklist seluruh AC P0; pelatihan pengguna; go-live |
| Exit criteria | Seluruh AC P0 lulus UAT; tidak ada temuan keamanan berat/tinggi yang terbuka; restore backup production teruji; dokumen operasional (deploy, backup/restore, reset password) tersedia |

### Ringkasan urutan

```
F0 ─► F1 ─► F2 ─► F3 ─► F4 ─► F5 ─► F6 ─► F7 ─► F10
                    │                  └──► F8 ──┘
                    └──► F9 (paralel) ───────────┘
```

---

## 9. Strategi Pengujian

| Jenis | Cakupan | Aturan |
|---|---|---|
| **Feature test per AC** | Setiap AC PRD menjadi minimal satu test; nama test memuat ID, misalnya `AC_JTL_14_rangkaian_selesai_dengan_sesi_batal` | AC tanpa test = belum selesai |
| **Authorization matrix test** | Dibangkitkan dari `AccessConfig` (Bagian 6): setiap kombinasi aksi × role × scope (data sendiri/cabang lain/tugas/bukan tugas) diuji lewat HTTP langsung | Wajib menguji kasus *deny*, termasuk 403 lewat API langsung (AC-AUTH-04) |
| **Unit test domain** | `ProgressRecalculator`, normalisasi nama, aturan sesi terakhir, rumus metrik | Data campuran wajib berisi kegiatan Dibatalkan |
| **DB constraint test** | Setiap partial unique index dan CHECK | Insert invalid harus gagal di level DB |
| **E2E (Playwright)** | Alur inti: AP daftar sekolah → tugaskan mentor → jadwal awal → mentor lapor Selesai → sekolah mitra → sesi TL → AP tetapkan sesi terakhir → selesai → AP input link | Dijalankan di CI pada branch utama |
| **Uji beban (k6)** | Dataset sintetis 5.000 sekolah / 50.000 kegiatan [A-04] | Target NFR p95 |
| **Uji keamanan** | Checklist OWASP dasar + skenario IDOR lintas cabang/mentor | Fase 10 |
| **UAT** | Checklist seluruh AC P0, dijalankan oleh AP, AC, dan Mentor | Gerbang go-live |

---

## 10. Risiko & Potensi Konflik

### 10.1 Risiko teknis

| ID | Risiko | Dampak | Mitigasi |
|---|---|---|---|
| T-R1 | Status turunan tidak sinkron dengan data sumber (posisi tahap, sertifikat) | Tinggi | Satu `ProgressRecalculator` dalam transaksi yang sama; perintah `progress:rebuild`; test invariant |
| T-R2 | Logika penyelesaian rangkaian kompleks (sesi terakhir, sesi batal, buka kembali, pindah tanda) | Tinggi | State dirumuskan sebagai fungsi murni dengan test tabel; semua transisi dicatat di `rangkaian_tl_riwayat` |
| T-R3 | Race condition: dua aktor mengubah kegiatan yang sama (misalnya mentor reschedule saat AP mengubah status) | Sedang | Optimistic lock (`version`) + pesan E9; partial unique index sebagai pengaman terakhir |
| T-R4 | Job harian dobel atau terlewat | Sedang | Dedup key; job idempoten; monitoring `failed_jobs` |
| T-R5 | Stack tidak sesuai keahlian tim [TD-01, TD-03] | Tinggi | Keputusan TD-01 berdasarkan keahlian nyata tim |
| T-R6 | Volume tak diketahui [A-04] | Sedang | Indeks dan agregasi di DB sejak awal; queue/cache bisa ditingkatkan tanpa ubah domain |

### 10.2 Risiko keamanan

| ID | Risiko | Mitigasi |
|---|---|---|
| S-01 | IDOR lintas cabang/mentor (akses lewat ID di URL) | Query scope + policy pada setiap route; test matriks otorisasi |
| S-02 | **Kebocoran data lewat Inertia props** (props terlihat di page source) | Wajib memakai Resource per konteks; code review checklist; test yang memeriksa props untuk role mentor non-tugas tidak memuat `catatan`/`tautan_pertemuan`/`link` |
| S-03 | XSS lewat catatan mentor dan alasan | Escape default framework; larangan render HTML mentah; CSP header |
| S-04 | Brute force login | Rate limit + lockout [TD-09]; gagal login diaudit |
| S-05 | Sesi tetap aktif setelah akun dinonaktifkan | Session driver database; hapus sesi user saat nonaktif; middleware cek `aktif` di setiap request |
| S-06 | Link sertifikat publik di penyedia eksternal | Di luar kendali sistem (PRD R6). Link hanya ditampilkan ke yang berhak; `rel="noopener noreferrer"` |
| S-07 | Audit diubah lewat akses DB langsung | Pemisahan hak user DB (INSERT/SELECT pada `audit_log`) [TD-02]; backup terenkripsi |
| S-08 | Data pribadi narahubung terbaca mentor seluruh Jateng *(A-01)* | Diterima sesuai asumsi PRD; semua akses lewat login; flag A-01 memungkinkan pembatasan cepat |

### 10.3 Risiko performa

| ID | Risiko | Mitigasi |
|---|---|---|
| P-01 | Daftar sekolah dengan banyak kolom turunan (mentor, narahubung utama, progres, sertifikat) memicu N+1 query | Eager loading; kolom cache di `sekolah`; subquery agregat; uji query count di test |
| P-02 | Dashboard dan perbandingan cabang melakukan agregasi berat | Agregasi SQL dengan indeks; cache hasil 60 detik bila perlu (data dashboard boleh tertunda satu kali muat ulang, AC-DSH-04) |
| P-03 | Pencarian kemiripan nama | Index trigram GIN |
| P-04 | Pertumbuhan `audit_log` | Index tepat; partisi bulanan bila perlu [TD-05] |

### 10.4 Potensi konflik business rules (perlu perhatian saat implementasi)

Temuan berikut **tidak mengubah keputusan PRD**. Masing-masing disertai cara penanganan teknis yang konsisten dengan PRD. Butir bertanda ⚠ membutuhkan konfirmasi bisnis karena PRD tidak mengaturnya.

| ID | Temuan | Penanganan |
|---|---|---|
| K-01 | SA "akses penuh" (no. 20) vs aksi yang dikhususkan untuk AP atau mentor | Diikuti persis sesuai Bagian 6 (pengecualian eksplisit) dan diuji |
| K-02 | AP boleh mengubah status (PD-05) vs BR-AWL-04 (pelatihan awal Selesai terkunci setelah sertifikat Tersedia) | BR-AWL-04 berlaku juga untuk AP. AP harus mengosongkan link sertifikat lebih dulu bila koreksi benar-benar diperlukan |
| K-03 | Buka kembali rangkaian setelah sertifikat TL Tersedia: link tetap disimpan (E6), padahal rangkaian belum selesai | Status sertifikat tetap "Tersedia", ditambah badge "Rangkaian dibuka kembali" di detail sekolah. Tidak ada link yang dihapus |
| K-04 | Penggantian mentor penanggung jawab vs mentor pelaksana khusus (E3) | Kolom `pelaksana_ditunjuk_khusus`. Sesi mendatang tanpa penunjukan khusus berpindah ke PJ baru |
| K-05 | Sekolah diarsipkan saat masih punya penugasan mentor aktif | Penugasan diakhiri otomatis (`alasan_selesai = arsip`) dan tetap tersimpan sebagai riwayat. Mengikuti BR-SCH-05 (riwayat tidak dihapus) |
| K-06 ⚠ | **Sekolah yang diarsipkan ingin bergabung kembali.** BR-SCH-13 melarang daftar ulang dan PRD tidak menyediakan fitur batal-arsip | Tidak diimplementasikan di MVP. **Perlu keputusan bisnis**: apakah perlu fitur "aktifkan kembali" (oleh siapa, dengan alasan)? |
| K-07 | "SA mengelola role & permission" vs aturan mutlak "hanya AP" | Diusulkan matriks tetap di kode [TD-08] |
| K-08 | Mentor pelaksana yang bukan PJ boleh menulis laporan sesinya (R-05), tetapi tidak boleh menambah sesi (BR-ACT-12: hanya PJ) | Dibedakan di policy: `report`/`reschedule` = T, `tl.create` = PJ |
| K-09 ⚠ | **Pengarsipan sekolah oleh AC.** Kegiatan yang belum selesai harus dibatalkan "dengan catatan alasan", padahal status kegiatan hanya boleh diubah mentor atau AP (PD-05) | Usulan teknis: pembatalan otomatis oleh sistem sebagai bagian dari aksi arsip, memakai alasan arsip dan tercatat sebagai revisi bertanda "sistem (arsip)". **Perlu konfirmasi** bahwa pembatalan otomatis ini tidak melanggar PD-05 |
| K-10 | Menghapus kegiatan tanpa laporan (BR-ACT-09) vs "riwayat tidak boleh dihapus" | Hapus hanya untuk kegiatan yang belum pernah dilaporkan **dan** tidak pernah di-reschedule; selain itu gunakan Dibatalkan. Usulan ini lebih ketat daripada PRD, tidak melonggarkan |

---

## 11. Penanganan Asumsi A-01 s.d. A-04

Asumsi diimplementasikan sebagai **titik konfigurasi terisolasi**. Perubahan asumsi cukup mengubah satu tempat, tanpa refactor domain.

| Asumsi | Titik implementasi | Bila asumsi berubah |
|---|---|---|
| **A-01** Mentor melihat seluruh sekolah Jateng | Flag `access.mentor_can_view_all_schools = true` di `AccessConfig`, dibaca oleh query scope mentor | Ubah flag menjadi `false`: scope mentor otomatis hanya sekolah T. Test matriks dijalankan untuk kedua nilai flag |
| **A-02** Penugasan mentor oleh AP | Daftar role untuk aksi `penugasan.assign` di `AccessConfig` | Ubah daftar role. Tidak ada logika lain yang bergantung |
| **A-03** Mentor lintas cabang | Tidak ada constraint mentor–cabang di DB; `users.cabang_id` untuk mentor tidak dipakai otorisasi | Bila dibatasi: tambah validasi `penugasan.assign` (cabang mentor = cabang sekolah) tanpa migrasi data |
| **A-04** Volume belum ditentukan | Target NFR diuji dengan dataset sintetis 5.000 sekolah / 50.000 kegiatan | Ulangi uji beban dengan angka nyata; skala queue/cache/DB sesuai hasil |

Asumsi tidak dicatat sebagai keputusan di kode, komentar, maupun dokumentasi. Konfigurasi asumsi diberi komentar yang merujuk "PRD 27.3 A-0x (asumsi)".

---

## 12. Gerbang Review Sebelum Coding

Coding dimulai hanya bila seluruh butir berikut terpenuhi:

- [ ] Dokumen ini disetujui pemilik bisnis dan tim teknis
- [ ] TD-01, TD-02, TD-03, TD-04, TD-06, TD-07, TD-08, TD-09, TD-12 diputuskan (gerbang Fase 1)
- [ ] TD-05 diputuskan, minimal untuk retensi audit (desain tabel audit di Fase 1)
- [ ] Desain UI halaman inti disetujui (Fase 0)
- [ ] K-06 dan K-09 dikonfirmasi, atau diterima untuk ditangani setelah MVP

TD-10 dan TD-11 boleh diputuskan menjelang Fase 7.
