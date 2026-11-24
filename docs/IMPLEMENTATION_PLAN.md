# Implementation Plan
## Jadwal Sekolah Mantap Terpusat

| Atribut | Nilai |
|---|---|
| Versi | **1.4** (Draft untuk review — belum Approved) |
| Tanggal | 22 November 2026 |
| Acuan | `docs/PRD.md` versi 1.3 (disetujui) |
| Status | **Menunggu review. Belum disetujui. Coding belum boleh dimulai** sampai dokumen ini disetujui dan keputusan teknis gerbang Fase 1 (Bagian 2) diputuskan |
| Versi sebelumnya | `docs/archive/IMPLEMENTATION_PLAN_v1.3.md`, `v1.2`, `v1.1`, `v1.0` (folder `docs/archive/`) |

### Riwayat Perubahan

| Versi | Tanggal | Perubahan |
|---|---|---|
| 1.0 | 22 Nov 2026 | Draft awal: stack usulan Laravel + Inertia + Vue/React + PostgreSQL |
| 1.1 | 22 Nov 2026 | **Revisi Filament-first** sesuai keputusan stack pemilik produk: Laravel + Filament + Livewire + Blade + Tailwind + PostgreSQL + queue database + Pest + Playwright. Diubah: Bagian 2 (TD-01 diputuskan, TD-12 diganti struktur panel, TD-13..TD-16 baru), Bagian 3 (stack + audit kompatibilitas versi), Bagian 4 (arsitektur dua panel, penempatan business logic, otorisasi di Filament), Bagian 6 (pemetaan permission ke permukaan UI; isi matriks **tidak berubah**), Bagian 7 (jenis implementasi Filament per modul), Bagian 8 (deliverable fase), Bagian 9 (alat uji), Bagian 10 (risiko khusus Filament/Livewire), Bagian 12 (gerbang). Tidak berubah: Bagian 1 (kecuali penyesuaian istilah), Bagian 5 (skema DB), Bagian 11 (asumsi), seluruh keputusan bisnis PRD |
| 1.2 | 22 Nov 2026 | **Koreksi pra-approval** (arah Filament-first dipertahankan): TD-13 dievaluasi ulang (`saade/filament-fullcalendar` ^5 vs ^4, Bagian 3.4, plugin baru dipasang setelah spike, kalender read-only); TD-04 diperjelas (Bagian 4.10, test server-side IT-01..07); keamanan Livewire diperjelas (Bagian 4.11, `#[Locked]` bukan authorization, test LS-01..10); TD-08 dipisah menjadi kelola akun, penetapan role, dan definisi permission; PostgreSQL distandarkan ke **satu versi (17)**; K-06 dan K-09 dikembalikan menjadi keputusan bisnis tertunda dengan usulan penundaan dari MVP; spike Fase 0 dirinci (SP-01..SP-07). Tidak berubah: keputusan bisnis PRD, struktur proses, matriks permission |
| 1.3 | 22 Nov 2026 | **Klarifikasi kecil pra-approval** (tanpa perubahan arsitektur): klasifikasi polling vs interaksi pengguna di endpoint Livewire yang sama dirinci + test IT-08..IT-13 (4.10); K-09 diselaraskan dengan matriks permission yang berlaku (mentor T dan AP sudah berwenang membatalkan; tidak ada izin baru) + perilaku penolakan Arsipkan dirinci; SP-07 dibatasi ke fitur FullCalendar berlisensi MIT; pemeriksaan ketersediaan PostgreSQL 17 ditambahkan ke TD-02 |
| 1.4 | 22 Nov 2026 | **Pencatatan keputusan pemilik produk + pemeriksaan konsistensi PRD.** Diputuskan: TD-01 (versi), TD-04, TD-08, TD-12, TD-14; K-06 dan K-09 ditunda dari MVP. TD-02/03/05/06/09 ditandai menunggu keputusan operasional konkret. Ditambahkan: katalog domain action (4.12), gerbang cakupan AC di CI (9), laporan konsistensi (Bagian 13). Penyelarasan dengan PRD tanpa perubahan bisnis: daftar jadwal lintas sekolah (PRD 11.1) dan kalender di kedua panel (PRD 11.1/11.2) masuk modul; klarifikasi TD-15 bahwa pencarian tabel tetap aktif. K-05 dan K-10 dikembalikan menjadi usulan yang menunggu konfirmasi. Status dokumen tetap **belum Approved** |

**Konvensi penandaan**

| Tanda | Arti |
|---|---|
| **[PRD]** | Diambil langsung dari PRD v1.3. Tidak boleh diubah oleh dokumen ini |
| **[DIPUTUSKAN]** | Keputusan teknis yang sudah ditetapkan pemilik produk |
| **[USULAN-TEKNIS]** | Usulan tim teknis. Berlaku setelah disetujui |
| **[TD-xx]** | Keputusan teknis yang belum diputuskan (Bagian 2) |
| **[A-0x]** | Asumsi bisnis dari PRD 27.3. Diimplementasikan agar mudah diubah, **bukan** keputusan final |
| **[R-0x]** | Rekomendasi PRD 27.4. Aturan awal MVP, tetap berstatus rekomendasi |

Kode role: **SA** Superadmin, **AP** Admin Pusat, **AC** Admin Cabang, **M** Mentor.

---

## Daftar Isi

1. Audit Kebutuhan Teknis & Dependensi Antarmodul
2. Keputusan Teknis (termasuk PD-20, PD-27, PD-29, PD-30)
3. Stack Teknologi & Audit Kompatibilitas
4. Rancangan Arsitektur Aplikasi
5. Rancangan Database
6. Matriks Permission Final & Pemetaan ke Permukaan UI
7. Daftar Modul & Pemetaan ke FR/AC
8. Tahapan Implementasi (Deliverable & Exit Criteria)
9. Strategi Pengujian
10. Risiko Teknis, Keamanan, Performa & Potensi Konflik Business Rules
11. Penanganan Asumsi A-01 s.d. A-04
12. Gerbang Review Sebelum Coding
13. Laporan Konsistensi PRD ↔ Implementation Plan

---

## 1. Audit Kebutuhan Teknis & Dependensi Antarmodul

### 1.1 Karakteristik sistem dari PRD

| Karakteristik | Sumber PRD | Implikasi teknis |
|---|---|---|
| Aplikasi web internal dengan 4 role, tanpa akun publik | Bagian 6, 9, BR-ACC-04 | Autentikasi sesi biasa. Tidak ada registrasi mandiri atau OAuth publik |
| Pembatasan data berlapis: per role, per cabang (AC), per penugasan (M) | BR-ACC-01..05, Bagian 9 | Otorisasi di tiga lapis: role/panel, query scope, dan policy per aksi (4.5). Wajib ditegakkan di server |
| Field-level visibility: mentor melihat jadwal mentor lain tanpa catatan laporan dan tautan | PD-03, R-01, AC-RPT-02 | Kolom dan data yang dirender dibedakan per konteks. Data sensitif tidak boleh masuk ke state Livewire yang dikirim ke browser (S-02) |
| Riwayat wajib di banyak entitas: jadwal, laporan, narahubung, penugasan, rangkaian TL, kontak awal | BR-ACT-06, BR-RPT-03, BR-SCH-06/11, BR-MNT-03, BR-TL-09, BR-RPT-09 | Pola **append-only history table** per entitas, ditambah audit trail global |
| Status turunan: posisi tahap sekolah, status sertifikat, progres TL, rangkaian selesai | 15.2–15.5, BR-TL-06/12 | Satu service penghitung ulang, dijalankan dalam transaksi yang sama dengan perubahan sumber |
| Invariant unik bersyarat | BR-AWL-03, BR-TL-07, BR-SCH-06, 16.5, BR-SCH-03/13 | Partial unique index di DB, bukan hanya validasi aplikasi |
| Notifikasi event-driven dan terjadwal (H-1, H+1) | FR-NTF-02, BR-NTF-01 | Events, queue, dan scheduler harian |
| Audit trail append-only, hanya SA | FR-AUD-01..03, BR-AUD-01..03 | Tabel audit terpisah, tanpa aksi ubah/hapus. Idealnya ada pembatasan hak DB [TD-02] |
| Kemiripan nama sekolah | AC-SCH-03 | Pencarian trigram di DB |
| Performa ≤ 3 detik untuk 5.000 sekolah / 50.000 kegiatan | NFR, AC-DSH-05, AC-SCH-06 | Indeks, agregasi di DB, paginasi server. Angka acuan dari [A-04] |
| Konkurensi edit | E9 | Optimistic locking (kolom `version`) |
| Responsif, mentor memakai ponsel | Bagian 20 | Panel mentor dengan halaman kustom mobile-first |
| Zona waktu WIB | NFR | Timezone aplikasi `Asia/Jakarta`; timestamp disimpan dengan zona waktu |
| Tidak ada impor data lama | Keputusan no. 32 | Tidak ada modul impor. Data awal diinput lewat UI (T-29) |

### 1.2 Kebutuhan teknis lintas modul (cross-cutting)

| ID | Komponen | Dipakai oleh | Harus siap sebelum |
|---|---|---|---|
| X-01 | Autentikasi & sesi (login panel, lockout, idle timeout, wajib ganti password) | Semua | Fase 2 |
| X-02 | Kerangka otorisasi (akses panel + query scope + Policy/Gate + aturan state di domain action) | Semua | Fase 2 |
| X-03 | Audit trail writer (domain action + model observer sebagai jaring pengaman) | Semua aksi tulis | Fase 2 |
| X-04 | Optimistic locking | Sekolah, Kegiatan, Narahubung, Sertifikat | Fase 3 |
| X-05 | History writer (append-only) | Jadwal, Laporan, Narahubung, Penugasan, Rangkaian, Kontak Awal | Fase 3 |
| X-06 | Derived-state service (`ProgressRecalculator`) | Laporan, Jadwal, Sertifikat, Dashboard | Fase 5 |
| X-07 | Domain events + notification dispatcher | Jadwal, Laporan, Sertifikat, Narahubung, Penugasan | Fase 7 (event disiapkan sejak Fase 3) |
| X-08 | Scheduler + queue database | Notifikasi H-1/H+1 | Fase 7 |

### 1.3 Graf dependensi antarmodul

*(Tidak berubah dari v1.0.)*

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
          │ M-JTL      │◄─────►│ M-RPT      │ Laporan & status
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
1. **M-JTL ↔ M-RPT** saling bergantung. Penyelesaian rangkaian (FR-JTL-05/10) bergantung pada status yang ditulis M-RPT, sehingga T-16a dijadwalkan setelah T-19.
2. **M-CRT** bergantung pada X-06.
3. **M-NTF** memakai event dari hampir semua modul.
4. **M-DSH** butuh definisi metrik final (BR-DSH-02..05, R-03) dan data M-RPT serta M-CRT.

---

## 2. Keputusan Teknis

Setiap TD punya **gerbang**: komponen yang bergantung padanya tidak boleh diimplementasikan sebelum TD diputuskan. Empat PD teknis PRD (PD-20, PD-27, PD-29, PD-30) dipertahankan dengan ID aslinya.

### 2.1 Daftar TD (status per v1.4)

| ID | Topik | Status | Usulan / Keputusan | Gerbang |
|---|---|---|---|---|
| **TD-01 (PD-30a)** | Stack aplikasi | **[DIPUTUSKAN v1.4]** stack dan versi | Stack: Laravel, Filament, Livewire, Blade, Tailwind CSS, PostgreSQL, queue database, Pest, Playwright. Usulan versi: lihat 3.2, termasuk **PostgreSQL 17 sebagai satu-satunya target** di development, staging, dan production | Fase 1 (versi dikunci di awal Fase 1) |
| **TD-02 (PD-30b)** | Hosting & infrastruktur | **Menunggu keputusan operasional konkret** | VPS/cloud; PostgreSQL terkelola atau terpisah; staging dan production terpisah; PHP-FPM + Nginx; worker queue dan cron scheduler sebagai proses terkelola (supervisor/systemd). **Syarat pemilihan hosting:** checklist ketersediaan PostgreSQL 17 (Bagian 3.2a) wajib lulus | Fase 1 (environment), go-live |
| **TD-03 (PD-30c)** | Tim pengembang & kepemilikan operasional | **Menunggu keputusan operasional konkret** | Tetapkan developer, admin server, dan pemilik backup | Fase 1 |
| **TD-04 (PD-27)** | Batas waktu sesi idle | **[DIPUTUSKAN v1.4]** 60 menit + seluruh test server-side (IT-01..IT-13) | 60 menit. Penentu idle adalah stempel aktivitas di server yang tidak diperbarui oleh request polling; sesi diakhiri di server, termasuk saat request polling datang setelah batas. Mekanisme dan test server-side: **Bagian 4.10** | Fase 1 |
| **TD-05 (PD-20)** | Retensi audit log dan notifikasi | **Menunggu keputusan operasional konkret** | Audit ≥ 5 tahun tanpa penghapusan otomatis di MVP; notifikasi 90 hari dengan pruning terjadwal | Audit: Fase 1. Notifikasi: Fase 7 |
| **TD-06 (PD-29)** | Backup, RPO/RTO, penanggung jawab | **Menunggu keputusan operasional konkret** | Backup harian otomatis terenkripsi, retensi ≥ 30 hari, RPO 24 jam, RTO 8 jam, uji restore bulanan | Fase 1; wajib terverifikasi sebelum go-live |
| TD-07 | Identitas login | Terbuka | Email (unik). Login memakai halaman login bawaan Filament | Fase 1 |
| TD-08 | Pengelolaan akun, role, dan permission | **[DIPUTUSKAN v1.4]** permission statis di kode; kelola akun & penetapan role oleh SA | Dibedakan tiga hal: (a) **kelola akun** (buat, ubah, nonaktifkan) = SA lewat UI; (b) **penetapan role ke akun** = SA lewat UI, satu role per akun; (c) **definisi permission per role** = ditetapkan **di kode** (`AccessConfig` + Policies), diubah hanya lewat perubahan kode yang ditinjau, diuji, dan dideploy. **Tidak ada permission editor dinamis** dan tidak memakai plugin seperti Filament Shield tanpa persetujuan terpisah. Lihat K-07 | Fase 1 |
| TD-09 | Kebijakan password & lockout | **Menunggu keputusan operasional konkret** | Minimal 8 karakter; lockout 15 menit setelah 5 kali gagal (throttle login bawaan Filament diperluas); wajib ganti password saat login pertama | Fase 1 |
| TD-10 | Jam pengiriman pengingat H-1 dan H+1 | Terbuka | Satu job harian pukul 07.00 WIB | Fase 7 |
| TD-11 | Mekanisme pembaruan notifikasi | Terbuka | **Database notifications bawaan Filament dengan polling 60 detik** (memenuhi AC-NTF-01 tanpa server WebSocket). Alternatif Laravel Reverb tidak direkomendasikan untuk MVP karena butuh proses dan infrastruktur tambahan | Fase 7 |
| ~~TD-12~~ | ~~Varian frontend Vue/React~~ | **Dihapus** | Tidak relevan, karena Inertia/Vue/React tidak dipakai | — |
| **TD-12 (baru)** | Struktur panel Filament | **[DIPUTUSKAN v1.4]** dua panel | **Dua panel** dengan satu backend, satu tabel `users`, dan satu guard: `admin` (SA, AP, AC) dan `mentor` (M). Lihat 4.2 | Fase 1 |
| TD-13 (baru) | Pustaka kalender | Terbuka — implementasi menunggu kelulusan SP-07 | **Dievaluasi ulang di v1.2 (Bagian 3.4).** Kandidat utama `saade/filament-fullcalendar` **^5.0** (v5.0.0, FullCalendar 7), cadangan **^4.0** (FullCalendar 6), cadangan terakhir tampilan mingguan kustom. **Tidak dipasang sebelum spike SP-07 lulus.** Kalender read-only; perubahan jadwal hanya lewat domain action | Fase 4 (T-18) |
| TD-14 (baru) | Multi-tenancy Filament untuk cabang | **[DIPUTUSKAN v1.4]** tanpa multi-tenancy Filament | **Tidak memakai fitur tenancy Filament.** Tenancy Filament dirancang untuk pengguna yang berpindah antar-tenant lewat URL, sedangkan di sini AP dan SA melihat lintas cabang, AC terkunci di satu cabang, dan mentor bekerja lintas cabang [A-03]. Pembatasan cabang memakai query scope domain + Policy (4.5) | Fase 1 |
| TD-15 (baru) | Fitur bawaan Filament yang tidak ada di PRD | Terbuka | **Dinonaktifkan di MVP:** global search, bulk delete, ekspor/impor bawaan, registrasi, reset password via email. Alasan: di luar lingkup PRD (ekspor = P2, impor ditiadakan, reset password oleh SA) dan berisiko membocorkan data lintas cabang. Yang **diaktifkan:** halaman login, halaman profil (ganti password), database notifications. **Klarifikasi v1.4:** pencarian dan filter di tabel Resource (FR-SCH-06/07) tetap aktif; yang dinonaktifkan hanya *global search* lintas resource | Fase 1 |
| TD-16 (baru) | Alat uji beban untuk NFR performa | Terbuka | k6 sebagai **alat uji terpisah** (bukan dependensi aplikasi), dijalankan di staging pada Fase 10. Alternatif: pengukuran waktu muat lewat Playwright + benchmark query di Pest, bila k6 tidak disetujui | Fase 10 |

### 2.2 Ringkasan perubahan TD dari v1.0

| TD | v1.0 | v1.1 |
|---|---|---|
| TD-01 | Usulan Laravel + Inertia + PostgreSQL | Stack diputuskan (Filament-first); hanya penguncian versi yang terbuka |
| TD-04 | 60 menit | Tetap 60 menit, ditambah catatan interaksi dengan polling Livewire |
| TD-08 | Matriks tetap di kode | Tetap, ditambah penegasan tanpa plugin editor permission |
| TD-09 | Lockout 15 menit | Tetap; implementasinya memperluas throttle login Filament |
| TD-11 | Polling 60 detik | Tetap; memakai database notifications bawaan Filament |
| TD-12 | Vue atau React | Dihapus; ID dipakai ulang untuk struktur panel Filament |
| TD-13..16 | — | Baru: kalender, tanpa tenancy, fitur bawaan yang dinonaktifkan, alat uji beban |

### 2.3 Ringkasan perubahan TD v1.2

| TD | v1.1 | v1.2 |
|---|---|---|
| TD-01 | PostgreSQL 16 atau 17 | **PostgreSQL 17** sebagai satu standar di semua environment |
| TD-04 | Catatan risiko polling | Mekanisme server-side lengkap + test IT-01..IT-13 (4.10) |
| TD-08 | Matriks tetap di kode | Dipisah: kelola akun / penetapan role (SA, UI) vs definisi permission (kode). Status: arah pemilik produk |
| TD-13 | ^4 sebagai usulan | ^5 kandidat utama, ^4 cadangan, keputusan setelah SP-07; kalender read-only |

---

## 3. Stack Teknologi & Audit Kompatibilitas

### 3.1 Stack final **[DIPUTUSKAN — TD-01]**

| Lapisan | Pilihan | Peran |
|---|---|---|
| Backend | **Laravel** | Satu-satunya sumber business logic: domain actions, Policies/Gates, Events/Listeners, Notifications, Queue, Scheduler, migration |
| Panel administrasi | **Filament** | Resources untuk CRUD, Custom Pages, Widgets, Actions (modal form), Infolists, database notifications |
| Interaktivitas | **Livewire** (dependensi Filament) | Komponen kustom: matriks progres, halaman kerja mentor, kalender |
| Frontend | **Blade + Tailwind CSS** | Tampilan halaman kustom; tema kustom Filament |
| Database | **PostgreSQL** | Partial unique index, `pg_trgm`, JSONB, `timestamptz` |
| Queue | **Driver database** (MVP) | Listener notifikasi dan pekerjaan terjadwal |
| Automated testing | **Pest** (+ helper testing Livewire/Filament) | Feature, unit, arsitektur, dan authorization matrix test |
| E2E testing | **Playwright** | Alur inti lintas role, termasuk viewport ponsel |

**Tidak dipakai:** Inertia.js, Vue, React (sesuai keputusan).

### 3.2 Usulan versi & audit kompatibilitas **[USULAN-TEKNIS — dikunci di awal Fase 1]**

| Komponen | Usulan versi | Dasar kompatibilitas (diperiksa 22 Nov 2026) |
|---|---|---|
| PHP | **8.4** (minimal 8.3) | Laravel 13 mensyaratkan PHP ≥ 8.3. Dukungan keamanan PHP 8.2 berakhir 31 Des 2026, sehingga 8.2 tidak dipilih walaupun Filament 5 masih menerimanya |
| Laravel | **13.x** | Rilis 17 Mar 2026; perbaikan bug sampai Q3 2027, keamanan sampai Q1 2028 |
| Filament | **5.x** (terbaru tercatat v5.11.4, 15 Nov 2026) | Persyaratan resmi: PHP 8.2+, Laravel 11.28+, Tailwind 4.1+ untuk tema kustom. Constraint paket `filament/support` 5.11.4 menerima `illuminate/contracts ^11.28\|^12.0\|^13.0` |
| Livewire | **4.x** (ikut Filament; constraint `^4.4.8`) | Filament 5 mensyaratkan Livewire 4. Livewire tidak dipasang terpisah dengan versi yang berbeda |
| Tailwind CSS | **4.1+** dengan plugin `@tailwindcss/vite` | Wajib untuk tema kustom Filament. Tema kustom **diperlukan**, karena halaman Blade kustom memakai kelas Tailwind yang tidak ada di CSS precompiled Filament |
| PostgreSQL | **17.x** (satu versi major untuk development, staging, dan production; minor version diselaraskan) | Alasan memilih satu versi: perbedaan versi antar-environment dapat menyembunyikan perbedaan perilaku (planner, ekstensi, `pg_dump`/restore lintas versi). 17 dipilih karena sudah matang (rilis Sep 2024), didukung komunitas sampai Nov 2029 (melewati masa dukungan Laravel 13), tersedia luas di layanan terkelola dan paket resmi PGDG, dan memiliki semua fitur yang dibutuhkan (partial index, `pg_trgm`, JSONB). Tidak ada fitur versi 18 yang dibutuhkan. Development memakai image `postgres:17`; test CI memakai versi yang sama. Bila penyedia hosting [TD-02] tidak menyediakan 17, standar diganti ke satu versi lain untuk **semua** environment sekaligus |
| Node.js | LTS aktif (hanya untuk build aset) | Vite + Tailwind; tidak berjalan di production runtime |
| Pest | Versi yang kompatibel dengan PHPUnit pada Laravel 13 | Dikunci di Fase 1 |
| Playwright | Versi stabil terbaru saat Fase 1 | Alat uji, bukan dependensi runtime |

**Verifikasi yang wajib dilakukan di awal Fase 1, sebelum versi dikunci:**
1. `composer require` Laravel 13 + Filament 5 di proyek kosong. Pastikan resolusi dependensi berhasil tanpa konflik (membuktikan kompatibilitas penuh, bukan hanya `illuminate/contracts`).
2. Build tema kustom Tailwind 4.1 untuk kedua panel.
3. Kalender: evaluasi `saade/filament-fullcalendar` ^5 dan ^4 **hanya di proyek spike** (SP-07, Bagian 3.4). Tidak dipasang di proyek produksi sebelum lulus.
4. Catat versi final di `composer.lock`/`package-lock.json` dan di dokumen ini.

#### 3.2a Checklist ketersediaan PostgreSQL 17 pada hosting [TD-02]

PostgreSQL 17 tetap menjadi standar. Checklist ini dijalankan saat memilih hosting (sebelum TD-02 diputuskan) dan diulang saat environment staging/production dibuat.

| # | Pemeriksaan | Lulus bila |
|---|---|---|
| H-01 | Versi tersedia | Layanan terkelola menawarkan PostgreSQL 17, **atau** server mengizinkan instalasi PostgreSQL 17 dari repositori resmi (PGDG) |
| H-02 | Ekstensi | `pg_trgm` dapat diaktifkan (`CREATE EXTENSION pg_trgm`) |
| H-03 | Hak akses DB | Dapat membuat role terpisah (user aplikasi dengan hak INSERT/SELECT saja pada `audit_log`, S-07) |
| H-04 | Backup & restore | Backup otomatis harian dan restore teruji dengan alat versi 17 (`pg_dump`/`pg_restore` 17) sesuai TD-06 |
| H-05 | Masa dukungan | Penyedia tidak menjadwalkan penghentian versi 17 sebelum akhir masa dukungan resmi (Nov 2029) tanpa jalur upgrade |
| H-06 | Kesamaan environment | Staging dan production dapat memakai versi major 17 yang sama, dengan minor version yang dapat diselaraskan |

Bila H-01 gagal pada hosting pilihan, opsinya dilaporkan ke pemilik produk: pilih hosting lain, atau ubah standar ke satu versi lain untuk **semua** environment. Perubahan standar memerlukan persetujuan dan tidak dilakukan diam-diam.

### 3.3 Library tambahan (hanya yang benar-benar diperlukan)

| Kebutuhan | Keputusan | Alasan |
|---|---|---|
| Grafik dashboard, klasemen, perbandingan cabang | **Tanpa library tambahan.** Memakai chart widget bawaan Filament (berbasis Chart.js yang sudah dibundel) | FR-DSH-02/03, FR-RNK-03 cukup dengan grafik batang, donat, dan batang bertumpuk |
| KPI | Stats overview widget bawaan Filament | FR-DSH-01 |
| Kalender (P1) | `saade/filament-fullcalendar` ^5 (kandidat) / ^4 (cadangan) **[TD-13]** atau fallback kustom; diputuskan setelah SP-07 | Satu-satunya plugin pihak ketiga yang diusulkan |
| Audit trail | **Dibangun sendiri** | Aturan PRD spesifik (hanya SA, append-only, nilai sebelum/sesudah, role saat aksi). Plugin activity log generik tidak menjamin aturan ini |
| Role/permission | **Dibangun sendiri** (`AccessConfig` + Policies) | TD-08 |
| Riwayat/versi | **Dibangun sendiri** (`HistoryWriter`) | Struktur riwayat per entitas ditetapkan PRD |
| Kualitas kode | Laravel Pint, Larastan (dev dependency) | Static analysis dan format; tidak memengaruhi runtime |

Total dependensi runtime di luar ekosistem inti Laravel/Filament: **maksimal satu** (plugin kalender, bila TD-13 disetujui).

### 3.4 Evaluasi pustaka kalender **[TD-13]**

| Aspek | `saade/filament-fullcalendar` ^4 (v4.0.1) | `saade/filament-fullcalendar` ^5 (v5.0.0) |
|---|---|---|
| Rilis | Lini lama, masih tercantum mendukung Filament 4.x/5.x di tabel kompatibilitas | 27 Okt 2026 (±4 minggu sebelum dokumen ini) |
| Constraint Composer | Tidak terbaca di halaman Packagist; harus diverifikasi saat spike | `php ^8.2`, `filament/filament ^4.0\|^5.0` (terverifikasi) |
| Engine | FullCalendar 6.x (matang, stabil bertahun-tahun) | FullCalendar 7.x (major baru) |
| Kelebihan | Engine matang, risiko bug rendah | Lini yang akan terus dikembangkan; constraint Filament 5 eksplisit; tidak perlu upgrade major tak lama setelah go-live |
| Kekurangan | Lini yang kemungkinan akan ditinggalkan; upgrade ke ^5 di masa depan | Masih sangat baru; FullCalendar 7 adalah major baru sehingga perlu dicek stabilitas dan lisensi tampilan yang dipakai |
| Lisensi | Plugin MIT; tampilan FullCalendar 6 dari plugin standar (daygrid, timegrid, list) berlisensi MIT; timeline/resource/scrollgrid/adaptive premium | Plugin MIT; FullCalendar 7: hanya plugin standar berlisensi MIT yang boleh dipakai; status lisensi tiap plugin **wajib diverifikasi** dari berkas lisensi paket saat SP-07 |

**Usulan:** jadikan **^5.0 sebagai kandidat utama** karena memulai proyek baru di lini terbaru menghindari upgrade major setelah go-live, dengan **^4.0 sebagai cadangan** dan **tampilan mingguan kustom (Livewire, tanpa plugin)** sebagai cadangan terakhir. Keputusan final diambil berdasarkan hasil SP-07.

**Status dependensi:** v5 adalah **kandidat**, bukan dependensi wajib. Tidak ada versi plugin yang dimasukkan ke `composer.json` proyek produksi sebelum SP-07 lulus dan TD-13 diputuskan.

**Kriteria lulus SP-07:**
1. Resolusi Composer bersih dengan versi stack yang dikunci (SP-01).
2. Tampilan bulan dan minggu berjalan, termasuk di viewport 360px (minimal tampilan daftar di ponsel).
3. Event dimuat lewat query ber-scope (AC hanya melihat kegiatan cabangnya; mentor sesuai Policy).
4. **Mode read-only:** drag-and-drop, resize, dan pembuatan event dengan klik tanggal **dinonaktifkan**.
5. Klik event membuka Action "Jadwal Ulang" atau halaman detail. Perubahan jadwal **hanya** lewat `RescheduleKegiatan` (alasan wajib, riwayat versi, Policy, audit, notifikasi).
6. **Hanya fitur FullCalendar berlisensi MIT** yang dipakai: plugin standar untuk tampilan bulan (daygrid), minggu (timegrid), dan daftar (list). Plugin premium tidak dipakai dan tidak dimuat, termasuk **resource, timeline/resource timeline, scrollgrid, dan adaptive**, karena tidak ada kebutuhan PRD untuk tampilan per sumber daya. Tidak ada license key FullCalendar (premium/evaluasi) di konfigurasi. Bukti: daftar plugin yang dimuat dan berkas lisensi paket dicantumkan di laporan spike. Plugin interaksi (drag/klik tanggal) juga tidak diperlukan karena kalender read-only; bila klik event membutuhkannya, status lisensinya diverifikasi lebih dulu.
7. Tidak ada error di konsol browser.

**Aturan tetap (apa pun pustakanya):** kalender adalah **tampilan**. Kalender tidak boleh menjadi jalur penyimpanan alternatif yang melewati validasi atau domain action. Drag-and-drop reschedule tidak masuk MVP. Bila kelak diusulkan, wajib memanggil `RescheduleKegiatan` dengan modal alasan dan memerlukan persetujuan terpisah.


---

## 4. Rancangan Arsitektur Aplikasi

### 4.1 Gaya arsitektur

**Modular monolith Laravel.** Satu codebase, satu deployment, satu database, dan satu sumber business logic untuk semua role. Filament dan Livewire hanya **lapisan presentasi**: tidak ada aturan bisnis yang hanya ada di komponen UI.

```
┌──────────────────────── Browser (desktop & ponsel) ─────────────────────────┐
│  Panel /admin (SA, AP, AC)              Panel /mentor (M, mobile-first)     │
│  Filament Resources, Pages, Widgets     Custom Pages Blade + Livewire       │
│  Polling notifikasi 60 dtk [TD-11]      Filament Actions (modal)            │
└───────────────────────────────┬─────────────────────────────────────────────┘
                                │ HTTPS · cookie sesi · CSRF · Livewire requests
┌───────────────────────────────▼─────────────────────────────────────────────┐
│                       Aplikasi Laravel (monolith)                           │
│                                                                             │
│  PRESENTASI  app/Filament/Admin/**, app/Filament/Mentor/**, Blade views     │
│     │  hanya memanggil ↓  (tanpa aturan bisnis, tanpa query tanpa scope)    │
│  APLIKASI    app/Domain/*/Actions  (satu aksi bisnis = satu class)          │
│     │  memeriksa Policy/Gate → validasi aturan state → transaksi DB         │
│  DOMAIN      Models, Query Scopes, Services (ProgressRecalculator,          │
│              NamaSekolahNormalizer), Events                                 │
│  OTORISASI   app/Policies/* + AccessConfig (matriks Bagian 6, flag A-0x)    │
│  LINTAS      AuditWriter, HistoryWriter, OptimisticLock                     │
│  ASYNC       Listeners (queue database) → Notifications (format Filament)   │
│              Scheduler: pengingat H-1/H+1, pruning notifikasi               │
└───────────────┬────────────────────────────────────────────┬────────────────┘
        ┌───────▼────────┐                           ┌────────▼────────┐
        │ PostgreSQL     │── backup harian ─────────►│ Backup storage  │
        │ data, audit,   │   terenkripsi [TD-06]     │ terpisah        │
        │ queue, session │                           └─────────────────┘
        └────────────────┘
```

### 4.2 Struktur panel **[USULAN-TEKNIS — TD-12]**

| Panel | Path | Pengguna | Isi utama | Karakter UI |
|---|---|---|---|---|
| `admin` | `/admin` | SA, AP, AC | Resources CRUD, detail sekolah, jadwal, sertifikat, progres, dashboard, klasemen, perbandingan cabang; khusus SA: pengguna, master, audit | Tabel padat, filter, desktop-first (tetap responsif) |
| `mentor` | `/mentor` | M | Beranda Saya, Kegiatan Saya, Sekolah Saya, Semua Sekolah *(A-01)*, Jadwal Koordinasi, Klasemen | Kartu besar, aksi ≤ 3 klik, mobile-first (≥ 360px) |

- Satu tabel `users`, satu guard `web`. `User::canAccessPanel()` menentukan panel: role `mentor` hanya ke `mentor`, role lain hanya ke `admin`. Akun nonaktif ditolak di kedua panel.
- Navigasi di panel `admin` disaring per role lewat Policy (`viewAny`). Contoh: menu Pengguna, Data Master, dan Audit hanya muncul untuk SA. Ini hanya penyaringan tampilan; penegakannya tetap di Policy dan route.
- **Alasan dua panel, bukan empat:** SA, AP, dan AC memakai halaman yang sama dengan cakupan data berbeda, sehingga cukup dibedakan oleh Policy dan scope. Mentor punya pola kerja yang benar-benar berbeda (mobile, berbasis tugas), sehingga layak mendapat panel sendiri. Empat panel akan menggandakan kode tanpa manfaat.
- Pembagian tanggung jawab per role mengikuti arahan pemilik produk:

| Role | Fokus di panel |
|---|---|
| SA | Akun, penetapan role [TD-08], master data, audit trail, ditambah akses baca/kelola sesuai matriks Bagian 6 |
| AP | Sekolah, mentor (penugasan dan pelaksana), jadwal, sertifikat, sesi terakhir/buka kembali, ubah status dengan alasan, monitoring seluruh cabang |
| AC | Sekolah dan jadwal cabangnya, persetujuan usulan narahubung cabangnya, leaderboard, perbandingan cabang (agregat) |
| M | Jadwal, status dan laporan, reschedule, tambah sesi (sebelum sesi terakhir ditetapkan), kontak awal, usulan narahubung |

### 4.3 Kapan memakai Resource, Custom Page, atau Livewire

| Kebutuhan | Bentuk implementasi | Alasan |
|---|---|---|
| CRUD data dengan tabel + filter + form standar | **Filament Resource** | Sekolah, Pengguna, Master, Usulan Narahubung (antrean persetujuan), Sertifikat (tabel + aksi link), Audit Log (read-only) |
| Detail sekolah dengan tab terpisah Awal/TL | **ViewRecord + Infolist + Relation Managers** | AC-SCH-09: tab Pelatihan Awal dan Tindak Lanjut terpisah |
| Aksi bisnis dengan aturan (reschedule + alasan, ubah status, sesi terakhir, buka kembali, arsip, koreksi cabang, putuskan usulan, input link) | **Filament Action (modal form)** yang memanggil domain action | Form ringkas di modal; validasi dan aturan tetap di domain action |
| Dashboard, KPI, grafik, perbandingan cabang | **Custom Page + Widgets** (Stats, Chart, Table widget) | FR-DSH-01..06 |
| Matriks progres (sekolah × sesi) | **Custom Page + komponen Livewire/Blade kustom** | Struktur kolom dinamis (jumlah sesi berbeda per sekolah) tidak cocok dengan tabel CRUD standar |
| Kalender | **Custom Page + widget kalender** [TD-13] | T-18 (P1) |
| Halaman kerja mentor | **Custom Pages Blade + Livewire** di panel `mentor`, memakai Filament Actions untuk modal | Mobile-first; daftar kartu, bukan tabel |
| Leaderboard | **Custom Page + Table widget + Chart widget** | Dense ranking dihitung di query domain |
| Notifikasi | **Database notifications bawaan Filament** | Lonceng, badge, tandai dibaca, polling [TD-11] |

### 4.4 Penempatan business logic **[USULAN-TEKNIS — wajib]**

| Aturan | Implementasi |
|---|---|
| Semua perubahan data bisnis lewat **domain action** (`app/Domain/<Modul>/Actions/*`) | Contoh: `RegisterSekolah`, `CorrectSekolahCabang`, `ArchiveSekolah`, `ProposeNarahubungChange`, `DecideNarahubungProposal`, `AssignMentor`, `AssignPelaksana`, `CreatePelatihanAwal`, `AddSesiTindakLanjut`, `RescheduleKegiatan`, `ReportKegiatan`, `AdminChangeStatus`, `SetSesiTerakhir`, `ReopenRangkaian`, `RecordKontakAwal`, `SetSertifikatLink` |
| Halaman Create/Edit Filament **tidak menyimpan langsung** lewat Eloquent | Override `handleRecordCreation()` / `handleRecordUpdate()` agar memanggil domain action. Filament Actions memanggil domain action di `->action()` |
| Domain action **mengotorisasi dirinya sendiri** | `Gate::authorize(...)` di dalam action. UI (`->visible()`, `->authorize()`) hanya menyembunyikan tombol; perlindungan sebenarnya ada di domain action. Jadi Livewire request yang dimanipulasi tetap ditolak |
| Aturan state (misalnya mentor tidak boleh menambah sesi setelah sesi terakhir ditetapkan) ada di **Policy + domain action** | Satu sumber kebenaran untuk kedua panel |
| Validasi bisnis (alasan wajib, catatan wajib, Selesai bukan tanggal masa depan) ada di **domain action** | Form Filament boleh menampilkan validasi yang sama untuk UX, tetapi aturan di domain action tetap berlaku |
| **Pest architecture test** | `App\Domain` dilarang bergantung pada `Filament\*` dan `Livewire\*`. `App\Filament` dilarang memanggil `DB::` secara langsung |
| **Model observer audit** sebagai jaring pengaman | Bila ada penulisan Eloquent yang lolos dari domain action, audit tetap tercatat. Riwayat dan aturan bisnis tetap wajib lewat domain action |

### 4.5 Model otorisasi di Filament

| Lapis | Mekanisme Laravel/Filament | Contoh |
|---|---|---|
| 1. Akses panel | `User::canAccessPanel()` + middleware status aktif | Mentor tidak bisa membuka `/admin` |
| 2. Area/menu | Policy `viewAny` (dipakai otomatis oleh Filament untuk navigasi dan akses Resource) | Resource Pengguna/Master/Audit hanya untuk SA (AC-USR-01/02, AC-AUD-02) |
| 3. Baris data | **Query scope domain** `->visibleTo($user)` dipanggil di `getEloquentQuery()` setiap Resource, di opsi `Select`/filter, di Relation Manager, di query widget, dan di halaman kustom | AC hanya melihat sekolah cabangnya (AC-SCH-04/17); mentor melihat semua sekolah bila flag A-01 aktif |
| 4. Aksi per objek | Policy per ability (`update`, `reschedule`, `report`, `setSesiTerakhir`, …) yang membaca `AccessConfig` + aturan state; dipanggil lagi di domain action | Mentor PJ boleh menambah sesi hanya bila belum ada sesi terakhir (AC-JTL-13) |
| 5. Field/kolom | Kolom dan infolist entry dirender sesuai Policy `viewDetail`. Data sensitif tidak dimuat ke public property Livewire | Mentor non-tugas tidak melihat catatan, tautan, atau link sertifikat (R-01, AC-RPT-02, AC-CRT-08) |

**Aturan keras untuk Livewire/Filament:**
1. Jangan menyimpan model lengkap atau field sensitif di **public property** Livewire. State Livewire dikirim ke browser dan bisa dibaca, walaupun dilindungi checksum. Gunakan computed property atau ambil data saat render.
2. `#[Locked]` dipakai pada ID di komponen kustom **hanya sebagai lapisan tambahan, bukan pengganti authorization**. Setiap aksi memuat objek lewat query ber-scope dan memeriksa permission, cabang, dan penugasan di domain action (Bagian 4.11).
3. Opsi dropdown relasi (pilih sekolah, mentor, cabang) **wajib** memakai query ber-scope.
4. Fitur bawaan Filament yang tidak ada di PRD dinonaktifkan [TD-15].

### 4.6 Autentikasi

| Kebutuhan PRD | Implementasi |
|---|---|
| Login (FR-AUTH-01) | Halaman login bawaan Filament di masing-masing panel; satu tabel `users`; email [TD-07] |
| Lockout (Bagian 23) | Throttle login bawaan Filament diperluas dengan penghitung gagal + `dikunci_sampai` [TD-09]; setiap gagal login diaudit |
| Wajib ganti password saat login pertama | Middleware panel: bila `wajib_ganti_password`, arahkan ke halaman profil Filament |
| Ganti password sendiri (FR-AUTH-03) | Halaman profil bawaan Filament (hanya field password dan data dasar) |
| Reset password oleh SA | Action di Resource Pengguna (SA). Reset via email dinonaktifkan [TD-15] |
| Idle timeout (AC-AUTH-05) [TD-04] | Middleware `EnforceIdleTimeout` dengan stempel aktivitas server-side yang tidak diperbarui oleh polling. Rincian dan test: **Bagian 4.10** |
| Akun nonaktif (FR-AUTH-05) | `canAccessPanel()` + middleware cek `aktif` di setiap request; sesi dihapus dari tabel `sessions` saat dinonaktifkan |

### 4.7 Struktur folder **[USULAN-TEKNIS]**

```
app/
  Domain/                       ← business logic (bebas Filament/Livewire)
    Master/ Pengguna/ Sekolah/ Mentor/ Jadwal/ Rangkaian/ Laporan/
    Sertifikat/ Notifikasi/ Dashboard/ Klasemen/ Audit/
      Actions/  Models/  Queries/  Events/  Exceptions/
  Policies/                     ← satu Policy per model (auto-discovery Filament)
  Support/
    Authorization/AccessConfig.php   (matriks Bagian 6 + flag A-01..A-03)
    History/HistoryWriter.php
    Audit/AuditWriter.php, AuditObserver.php
    Progress/ProgressRecalculator.php
    Concurrency/OptimisticLock.php
  Filament/
    Admin/                      ← panel admin (SA, AP, AC)
      Resources/  Pages/  Widgets/
    Mentor/                     ← panel mentor
      Pages/  Widgets/
  Livewire/                     ← komponen kustom (matriks progres, kartu kegiatan)
  Notifications/                ← format database notification Filament
  Listeners/  Console/ (scheduler)
  Providers/Filament/AdminPanelProvider.php, MentorPanelProvider.php
resources/
  views/filament/**             ← Blade halaman kustom
  css/filament/{admin,mentor}/theme.css   ← tema Tailwind 4.1
database/migrations · database/seeders
tests/
  Arch/  Unit/  Feature/<Modul>/  Authorization/  (nama test memuat ID AC)
  e2e/   (Playwright)
```

### 4.8 Alur transaksi kunci

*(Logika sama dengan v1.0; pemanggilnya sekarang Filament Action.)*

**(a) Mentor melaporkan status** — Filament Action di panel mentor → `ReportKegiatan::run()`:

```
Gate::authorize('report', $kegiatan) → DB transaction {
    OptimisticLock::check(kegiatan.version)
    validasi: catatan wajib; Selesai bukan tanggal masa depan
    insert laporan_revisi (versi n+1)
    update kegiatan.status
    ProgressRecalculator::forSekolah(id)
    insert rangkaian_tl_riwayat bila status rangkaian berubah
    AuditWriter::record(...)
    event: KegiatanStatusChanged / RangkaianSelesai / SekolahMenjadiMitra
} → after commit: listener (queue) → database notification
```

**(b) AP menetapkan sesi terakhir** → `SetSesiTerakhir` → validasi sesi bukan Dibatalkan → pindahkan flag dengan konfirmasi (AC-JTL-06) → recalculator → audit + riwayat rangkaian.

**(c) Job harian [TD-10]** → query H-1 (Belum Dimulai) dan H+1 (belum Selesai/Dibatalkan) → notifikasi dengan *dedup key* `(user, jenis, kegiatan, tanggal)`.

### 4.9 Derived state: `ProgressRecalculator`

*(Tidak berubah dari v1.0.)* Satu fungsi murni yang menghitung ulang untuk satu sekolah: posisi tahap (15.3), tanggal mitra (BR-SCH-02), status rangkaian (BR-TL-06/12), baris sertifikat (FR-CRT-01, tidak dihapus saat dibuka kembali), dan pembalikan E8. Hasilnya di-cache di kolom `sekolah`. Tersedia perintah `progress:rebuild`.

### 4.10 Mekanisme idle timeout **[TD-04]**

**Masalah:** Laravel memperbarui masa berlaku sesi pada setiap request yang membawa cookie sesi, termasuk request polling notifikasi Filament (setiap 60 detik). Tanpa penanganan khusus, pengguna yang meninggalkan tab terbuka tidak akan pernah ter-logout.

**Prinsip:** keputusan idle dibuat **di server**, berdasarkan stempel waktu aktivitas yang **hanya** diperbarui oleh request aktivitas pengguna. Masa berlaku sesi bawaan Laravel tidak dipakai sebagai penentu idle.

| Elemen | Rancangan |
|---|---|
| Stempel aktivitas | Kunci sesi `auth.last_activity_at` (waktu server). Diisi saat login berhasil |
| Middleware `EnforceIdleTimeout` | Dipasang di **kedua panel** dan pada endpoint update Livewire (path pasti endpoint Livewire 4 diverifikasi di spike). Urutan langkah di setiap request terautentikasi: (1) baca `last_activity_at`; (2) bila `now - last_activity_at > batas TD-04`, maka logout, `session()->invalidate()`, `regenerateToken()`, dan kembalikan respons tidak terautentikasi (redirect login untuk halaman; respons yang memicu redirect untuk request Livewire); (3) bila belum melewati batas **dan** request tergolong aktivitas, perbarui `last_activity_at`; (4) bila request pasif, **jangan** memperbarui |
| Klasifikasi request pasif | **Endpoint Livewire tidak dikecualikan secara keseluruhan.** Polling notifikasi dan interaksi pengguna memakai endpoint update Livewire yang sama, sehingga klasifikasi dilakukan per request dengan membaca isi payload. **Deny-by-default:** request dianggap aktivitas, kecuali **semua** syarat berikut terpenuhi: (1) setiap komponen di payload adalah komponen notifikasi database Filament; (2) setiap komponen hanya berisi pemanggilan refresh polling, tanpa pemanggilan method lain (misalnya tandai dibaca atau hapus notifikasi tetap dihitung aktivitas); (3) tidak ada pembaruan property (`updates` kosong). Request yang tidak dapat diurai juga dianggap aktivitas. Nama komponen, nama pemanggilan polling, dan struktur payload Livewire 4 dipastikan di SP-06, lalu dikunci dengan test |
| Polling lain | Widget dashboard **tidak** memakai polling otomatis di MVP, agar daftar pasif tetap satu komponen. Bila kelak dibutuhkan, komponennya ditambahkan ke daftar pasif lewat perubahan kode yang ditinjau |
| Masa berlaku sesi Laravel | `SESSION_LIFETIME` diset **≥** batas idle (usulan: sama dengan batas idle + 5 menit), sebagai batas atas cadangan. Penentu idle tetap middleware |
| Saat batas tercapai | Request berikutnya, **termasuk request polling**, langsung mengakhiri sesi di server. Jadi sesi tidak "hidup" di server walaupun tab browser masih terbuka |
| Audit | Logout karena idle dicatat di audit (`aksi = logout_idle`) |

**Pengujian server-side (Pest, wajib — bukan hanya browser):**

| ID | Skenario | Hasil yang diharapkan |
|---|---|---|
| IT-01 | Login → kirim request polling Livewire tiap 60 detik simulasi (`travel`) selama batas + 1 menit, tanpa request aktivitas | Request polling pertama setelah batas mengembalikan respons tidak terautentikasi; sesi di server sudah tidak valid; request halaman berikutnya diarahkan ke login |
| IT-02 | Login → request aktivitas di menit ke-(batas − 1) → polling terus | Sesi tetap valid sampai batas dihitung dari request aktivitas terakhir |
| IT-03 | Request polling **tidak** mengubah `last_activity_at` | Nilai stempel sebelum dan sesudah polling identik |
| IT-04 | Request campuran (polling + aksi) | Dianggap aktivitas; stempel diperbarui |
| IT-05 | Request Livewire dari komponen yang tidak ada di daftar pasif | Dianggap aktivitas (deny-by-default) |
| IT-06 | Logout idle tercatat di audit | Entri `logout_idle` ada |
| IT-07 | Berlaku di kedua panel | IT-01 lulus untuk `/admin` dan `/mentor` |
| IT-08 | Submit form Filament (create/edit sekolah atau jadwal) lewat endpoint update Livewire | Dianggap aktivitas; `last_activity_at` diperbarui |
| IT-09 | Klik aksi Filament (membuka modal lalu menjalankan aksi, misalnya Arsipkan atau Tetapkan Sesi Terakhir) | Setiap request (buka modal dan eksekusi) dianggap aktivitas |
| IT-10 | Reschedule oleh mentor di panel mentor (modal Jadwal Ulang → simpan) | Dianggap aktivitas; stempel diperbarui; idle tidak terjadi selama mentor aktif |
| IT-11 | Interaksi tabel: pencarian, filter, paginasi, sortir | Dianggap aktivitas (ada pembaruan property) |
| IT-12 | Aksi di komponen notifikasi selain polling: tandai dibaca, tandai semua dibaca | Dianggap aktivitas walaupun komponennya sama dengan komponen polling |
| IT-13 | Polling murni berulang (hanya refresh komponen notifikasi, tanpa `updates`) | Tidak memperbarui stempel; setelah batas, polling berikutnya mengakhiri sesi (gabungan IT-01 + IT-03) |

Setiap test IT memeriksa nilai `last_activity_at` **di sesi server** sebelum dan sesudah request, bukan hanya status respons. Payload uji direkam dari interaksi nyata di SP-06 agar sesuai dengan format Livewire 4 yang dipakai.

Test dijalankan di level HTTP terhadap endpoint aplikasi (halaman panel dan endpoint update Livewire), bukan hanya lewat helper komponen. Playwright hanya pelengkap.

### 4.11 Keamanan Livewire & Filament: authorization di backend

**Prinsip:** `#[Locked]`, `->visible()`, `->hidden()`, `->disabled()`, dan `->authorize()` di komponen UI **bukan pengganti authorization**. Fungsinya hanya mengurangi permukaan serangan dan memperbaiki UX. Request Livewire dapat dibuat manual dengan ID, argumen aksi, dan nilai form apa pun.

| Lapisan backend (wajib di setiap aksi) | Rancangan |
|---|---|
| Resolusi objek ber-scope | Objek dari request **selalu** dimuat lewat query ber-scope, misalnya `Kegiatan::visibleTo($user)->findOrFail($id)`, tidak pernah lewat `find($id)` polos. Objek di luar cakupan diperlakukan sebagai tidak ditemukan |
| Pemeriksaan permission | Domain action memanggil `Gate::authorize($ability, $objek)`. Policy memeriksa **role** (dari `AccessConfig`), **cabang** (AC: `objek.sekolah.cabang_id = user.cabang_id`), **penugasan** (M: penanggung jawab aktif atau pelaksana sesi), dan **aturan state** (misalnya sesi terakhir sudah ditetapkan) |
| Objek terkait dalam input | ID lain di dalam input (misalnya `mentor_pelaksana_id`, `sekolah_id` di form jadwal, `narahubung_id` di usulan) divalidasi juga terhadap scope dan aturan. Contoh: mentor pelaksana harus akun mentor aktif; sekolah harus di cabang AC |
| Field terlarang | Input divalidasi dengan daftar field eksplisit per aksi (bukan mass assignment dari seluruh state form). Contoh: AC tidak dapat menyisipkan `cabang_id` saat update sekolah |
| `#[Locked]` | Tetap dipakai pada ID di komponen kustom sebagai lapisan tambahan, **bukan** sebagai kontrol akses |
| Data ke browser | Hanya data yang boleh dilihat role tersebut yang dirender atau disimpan di state komponen. Field sensitif (catatan laporan, tautan pertemuan, link sertifikat, kontak narahubung bila kelak dibatasi) dimuat lewat computed/render, sesuai Policy |

**Pengujian manipulasi & kebocoran (Pest, level request Livewire):**

| ID | Skenario | Hasil yang diharapkan |
|---|---|---|
| LS-01 | AC cabang A memanggil Action Filament (edit, arsip, tambah sesi, reschedule) pada record cabang B dengan mengganti ID di request | Ditolak (403/404); tidak ada perubahan DB; tidak ada entri riwayat |
| LS-02 | Mentor M1 memanggil `report`/`reschedule` pada kegiatan milik M2 dengan ID dimanipulasi | Ditolak; DB tidak berubah |
| LS-03 | Mentor PJ menambah sesi setelah AP menetapkan sesi terakhir lewat request langsung | Ditolak (AC-JTL-13) |
| LS-04 | SA memanggil aksi khusus AP (input link, ubah status, sesi terakhir) lewat request langsung | Ditolak (pengecualian SA, Bagian 6) |
| LS-05 | Mengubah property `#[Locked]` di payload | Livewire menolak request; dan **walaupun** diterima (simulasi tanpa Locked), domain action tetap menolak |
| LS-06 | Menyisipkan field terlarang di state form (`cabang_id`, `is_sesi_terakhir`, `status`) | Field diabaikan atau ditolak; nilai DB tidak berubah |
| LS-07 | Pencarian opsi `Select` (sekolah, mentor) dari akun AC memakai nama sekolah cabang lain | Hasil tidak memuat sekolah cabang lain |
| LS-08 | Opsi filter tabel (mentor, kategori, cabang) untuk AC | Hanya nilai dalam cakupan; filter cabang terkunci |
| LS-09 | **Uji sentinel kebocoran:** seed data cabang B dan data mentor lain dengan string unik (misalnya `SENTINEL-CATATAN-B`, `SENTINEL-LINK-B`, `SENTINEL-TAUTAN-M2`); render setiap halaman, tabel, relation manager, widget, dan respons update Livewire untuk tiap role | String sentinel **tidak muncul** di HTML maupun snapshot JSON Livewire untuk role yang tidak berhak |
| LS-10 | Relation manager dan widget dashboard untuk AC | Hanya data cabangnya, kecuali tabel perbandingan cabang yang berisi angka agregat saja |

LS-01 sampai LS-10 masuk exit criteria Fase 1 (kerangka), lalu diperluas di setiap fase untuk aksi yang baru dibuat.

### 4.12 Katalog domain action (baru di v1.4)

Setiap perubahan data bisnis hanya lewat action berikut. Semua action mengikuti pola yang sama: **muat objek lewat query ber-scope → `Gate::authorize` → validasi aturan → transaksi DB (data + riwayat + audit) → event setelah commit → listener notifikasi (penerima tidak termasuk pelaku, BR-NTF-02)**. Kolom "Aktor" mengikuti matriks Bagian 6 tanpa penambahan kewenangan.

| Action | Aktor (matriks) | Ability | Riwayat | Audit | Event → notifikasi | AC utama |
|---|---|---|---|---|---|---|
| `RegisterSekolah` | SA, AP, AC (C) | `sekolah.create` | — (record baru) | ✔ | — | AC-SCH-01..03, 14, 20 |
| `UpdateSekolah` | SA, AP, AC (C) | `sekolah.update` | via audit (sebelum/sesudah); `cabang_id` tidak dapat diubah di sini | ✔ | — | AC-SCH-13 |
| `SetSekolahKategori` | SA, AP, AC (C) | `sekolah.kategori` | via audit | ✔ | — | AC-SCH-05 |
| `CorrectSekolahCabang` | SA | `sekolah.correct_cabang` | `sekolah_cabang_koreksi` | ✔ | — | AC-SCH-18, 19 |
| `ArchiveSekolah` | SA, AP, AC (C) | `sekolah.archive` | kolom arsip (alasan, oleh, waktu) | ✔ | — | AC-SCH-15; penolakan K-09 |
| `AddNarahubung`, `UpdateNarahubung`, `DeactivateNarahubung`, `SetNarahubungUtama` | SA, AP, AC (C) | `narahubung.manage` | `narahubung_riwayat` | ✔ | — | AC-SCH-12, 16 |
| `ProposeNarahubungChange` | M (T) | `narahubung.propose` | `narahubung_usulan` | ✔ | `UsulanNarahubungDiajukan` → AP + AC cabang [rekomendasi PRD] | AC-SCH-21 |
| `DecideNarahubungProposal` | AP, AC (C) | `narahubung.decide` | `narahubung_usulan` + `narahubung_riwayat` (usulan_id) | ✔ | `UsulanNarahubungDiputuskan` → mentor pengusul [R-07] | AC-SCH-22, 23 |
| `AssignMentor` | SA, AP *(A-02)* | `penugasan.assign` | `penugasan_mentor` | ✔ | `MentorDitugaskan` → mentor [rekomendasi PRD] | AC-RPT-08 |
| `AssignPelaksana` | SA, AP | `kegiatan.assign_pelaksana` | via audit | ✔ | `PelaksanaDitunjuk` → mentor pelaksana [rekomendasi PRD, penugasan] | — (FR-MNT-05) |
| `CreatePelatihanAwal` | SA, AP, AC (C) | `awal.create` | — | ✔ | `JadwalDibuat` → mentor terkait, AP, AC cabang (FR-NTF-02) | AC-JDA-01..06, 08 |
| `AddSesiTindakLanjut` | SA, AP, AC (C); M PJ sebelum sesi terakhir; AP setelah sesi terakhir | `tl.create`, `tl.create_after_last` | — | ✔ | `JadwalDibuat` → mentor terkait, AP, AC cabang | AC-JTL-01..05, 10, 13 |
| `RescheduleKegiatan` | SA, AP, AC (C), M (T) | `kegiatan.reschedule` | `kegiatan_jadwal_riwayat` (alasan wajib) | ✔ | `JadwalDiubah` → mentor terkait, AP, AC cabang | AC-JTL-08; field per role: C-04 |
| `DeleteKegiatan` | SA, AP, AC (C) | `kegiatan.delete` | — (snapshot di audit) | ✔ | — | BR-ACT-09; syarat tambahan: C-02 |
| `ReportKegiatan` | M (T) | `kegiatan.report` | `laporan_revisi`; `rangkaian_tl_riwayat` bila status rangkaian berubah | ✔ | `RangkaianSelesai` → AP (sertifikat); `SesiTerakhirDibatalkan` → AP [R-07] | AC-RPT-01..05, 12; AC-JTL-07, 14, 15 |
| `AdminChangeStatus` | AP | `kegiatan.admin_status` | `laporan_revisi` (peran AP, alasan wajib) | ✔ | sama dengan `ReportKegiatan` | AC-RPT-09, 10 |
| `RecordKontakAwal` | M (T) | `kontak_awal.record` | `kontak_awal` (berversi) | ✔ | — | AC-RPT-11 |
| `SetSesiTerakhir` | AP | `rangkaian.set_last` | `rangkaian_tl_riwayat` | ✔ | `RangkaianSelesai` bila syarat terpenuhi → AP | AC-JTL-06, 07 |
| `ReopenRangkaian` | AP | `rangkaian.reopen` | `rangkaian_tl_riwayat` (alasan wajib) | ✔ | — | AC-JTL-11 |
| `SetSertifikatLink` | AP | `sertifikat.manage_link` | via audit (link lama/baru, FR-CRT-05) | ✔ | `SertifikatTersedia` (saat status berubah menjadi Tersedia) → mentor terkait, AP, AC cabang | AC-CRT-01..05; AC-NTF-05 |
| `CreateUser`, `UpdateUser` (termasuk penetapan role), `DeactivateUser` (cabut sesi; peringatan BR-MNT-04), `ResetUserPassword` | SA | `users.manage`, `roles.assign` | via audit | ✔ | — | AC-USR-01, 03; AC-AUTH-03 |
| CRUD/nonaktif master, `UpdateAppSetting` | SA | `master.manage` | via audit | ✔ | — | AC-USR-02; FR-MST-02 |
| `ProgressRecalculator` (internal, dipanggil action di atas) | sistem | — | `rangkaian_tl_riwayat` (`selesai_otomatis`, `sesi_terakhir_dibatalkan`) | ✔ (aktor sistem) | memancarkan event rangkaian/mitra | AC-JDA-07; AC-JTL-07, 14, 15 |
| `SendScheduledReminders` (job harian) | sistem | — | — | log job | H-1 dan H+1 → mentor terkait, AP, AC cabang (dedup) | AC-NTF-06, 07; AC-RPT-07 |
| Login, logout, gagal login, logout idle | pengguna/sistem | — | — | ✔ | — | AC-AUD-01; IT-06 |
| Tandai notifikasi dibaca | pemilik notifikasi | `notifikasi.own` | — | tidak diaudit (bukan data bisnis) [usulan teknis] | — | AC-NTF-02, 03 |

**Pengujian katalog:** setiap action memiliki test yang memastikan (1) penolakan untuk role/cabang/penugasan yang tidak berhak, (2) baris riwayat tertulis bila kolom Riwayat terisi, (3) satu entri audit dengan nilai sebelum/sesudah, (4) event yang benar dipancarkan (`Event::fake`), dan (5) penerima notifikasi sesuai BR-NTF-01 tanpa pelaku.

---

## 5. Rancangan Database

**Tidak berubah dari v1.0** kecuali dua catatan di bawah. Skema lengkap: lihat `docs/archive/IMPLEMENTATION_PLAN_v1.0.md` Bagian 5, yang tetap berlaku sebagai bagian dari dokumen ini. Ringkasan tabel:

| Kelompok | Tabel |
|---|---|
| Master | `kabupaten_kota`, `cabang`, `kategori_pelatihan`, `jenis_kegiatan`, `metode_kegiatan` |
| Pengguna | `users`, `sessions` |
| Sekolah | `sekolah`, `sekolah_kategori`, `sekolah_cabang_koreksi`, `narahubung`, `narahubung_usulan`, `narahubung_riwayat` |
| Mentor | `penugasan_mentor` |
| Jadwal & Rangkaian | `kegiatan`, `kegiatan_jadwal_riwayat`, `rangkaian_tl_riwayat` |
| Laporan | `laporan_revisi`, `kontak_awal` |
| Sertifikat | `sertifikat` |
| Sistem | `notifications`, `audit_log`, `app_settings`, `jobs`, `failed_jobs`, `job_batches`, `cache` |

Invariant DB yang dipertahankan: UNIQUE(nama_normal, jenjang, kabupaten_kota_id) termasuk sekolah arsip; partial unique narahubung utama aktif; partial unique penugasan aktif; partial unique pelatihan awal tidak Dibatalkan; partial unique sesi terakhir; UNIQUE(sekolah_id, tahap, urutan); CHECK alasan untuk revisi oleh AP; CHECK format link; FK `ON DELETE RESTRICT`.

**Catatan v1.1:**
1. Tabel `notifications` memakai struktur standar Laravel yang dibaca Filament database notifications. Kolom `dedup_key` (nullable, partial unique) ditambahkan lewat migration terpisah; tidak mengganggu Filament.
2. Kolom `version` untuk optimistic lock disertakan sebagai field tersembunyi di form Filament dan diperiksa di domain action.

---

## 6. Matriks Permission Final & Pemetaan ke Permukaan UI

### 6.1 Matriks permission

**Isi matriks tidak berubah dari v1.0** (diturunkan dari PRD v1.3 Bagian 9 dan 14). Tabel lengkap 6.1–6.3 di `docs/archive/IMPLEMENTATION_PLAN_v1.0.md` tetap berlaku, termasuk catatan konsistensi pengecualian SA (`sertifikat.manage_link`, `kegiatan.admin_status`, `kegiatan.report`, `rangkaian.*`, `tl.create_after_last`, `narahubung.decide`, `narahubung.propose`, `kontak_awal.record`).

Ringkasan kode aksi:

| Kelompok | Kode aksi |
|---|---|
| Sekolah & Mentor | `sekolah.view`, `sekolah.create`, `sekolah.update`, `sekolah.correct_cabang`, `sekolah.transfer` (✖ semua), `sekolah.archive`, `sekolah.kategori`, `narahubung.manage`, `narahubung.propose`, `narahubung.decide`, `penugasan.assign` *(A-02)*, `kegiatan.assign_pelaksana`, `kontak_awal.record` |
| Jadwal, Rangkaian, Laporan | `kegiatan.view`, `awal.create`, `tl.create`, `tl.create_after_last`, `kegiatan.reschedule`, `kegiatan.delete`, `kegiatan.report`, `kegiatan.admin_status`, `laporan.view_detail`, `rangkaian.set_last`, `rangkaian.reopen` |
| Sertifikat, Insight, Administrasi | `sertifikat.view_status`, `sertifikat.open_link`, `sertifikat.manage_link`, `dashboard.view`, `dashboard.branch_compare`, `klasemen.view`, `users.manage`, `roles.assign`, `master.manage`, `audit.view`, `notifikasi.own` |

### 6.2 Pemetaan kode aksi ke permukaan UI (baru di v1.1)

| Kode aksi | Panel | Permukaan Filament | Ability Policy |
|---|---|---|---|
| `sekolah.view` | admin, mentor | `SekolahResource` (list/view) · Mentor: halaman "Semua Sekolah" + "Sekolah Saya" | `SekolahPolicy::viewAny/view` |
| `sekolah.create` | admin | `SekolahResource` Create page | `create` |
| `sekolah.update` | admin | `SekolahResource` Edit page (field cabang read-only) | `update` |
| `sekolah.correct_cabang` | admin (SA) | Action "Koreksi Cabang" di view sekolah | `correctCabang` |
| `sekolah.archive` | admin | Action "Arsipkan" (alasan wajib) | `archive` |
| `narahubung.manage` | admin | Relation Manager Narahubung | `NarahubungPolicy::*` |
| `narahubung.propose` | mentor | Action "Usulkan Perubahan" di Sekolah Saya | `propose` |
| `narahubung.decide` | admin (AP, AC) | `UsulanNarahubungResource` (antrean) + Action Setujui/Tolak | `decide` |
| `penugasan.assign` | admin | Action "Tugaskan Mentor" di view sekolah | `PenugasanPolicy::assign` |
| `kegiatan.assign_pelaksana` | admin | Action "Tunjuk Pelaksana" pada sesi | `KegiatanPolicy::assignPelaksana` |
| `awal.create`, `tl.create`, `tl.create_after_last` | admin, mentor (`tl.create` PJ) | Relation Manager Kegiatan (tab Awal / TL) · Mentor: Action "Tambah Sesi" | `createAwal`, `createTl`, `createTlAfterLast` |
| `kegiatan.reschedule` | admin, mentor | Action "Jadwal Ulang" (alasan wajib) | `reschedule` |
| `kegiatan.report` | mentor | Action "Perbarui Status" di kartu kegiatan | `report` |
| `kegiatan.admin_status` | admin (AP) | Action "Ubah Status (Admin)" | `adminStatus` |
| `laporan.view_detail` | admin, mentor | Infolist riwayat revisi | `viewDetail` |
| `rangkaian.set_last`, `rangkaian.reopen` | admin (AP) | Action "Tetapkan Sesi Terakhir", "Buka Kembali" | `setSesiTerakhir`, `reopen` |
| `kontak_awal.record` | mentor | Action "Kontak Awal" | `KontakAwalPolicy::record` |
| `sertifikat.*` | admin, mentor | `SertifikatResource` (tab Menunggu/Tersedia) + kartu di detail sekolah | `SertifikatPolicy::viewStatus/openLink/manageLink` |
| `dashboard.view`, `dashboard.branch_compare` | admin, mentor | Custom Page Dashboard + widgets | Gate `dashboard.*` |
| `klasemen.view` | admin, mentor | Custom Page Klasemen | Gate `klasemen.view` |
| `users.manage`, `roles.assign` | admin (SA) | `UserResource` | `UserPolicy::*` |
| `master.manage` | admin (SA) | Resources master + halaman Setting | Policies master |
| `audit.view` | admin (SA) | `AuditLogResource` (read-only, tanpa create/edit/delete) | `AuditLogPolicy::viewAny/view` |

Setiap permukaan wajib memeriksa ability di sisi server (lewat Policy dan domain action), bukan hanya menyembunyikan tombol.

---

## 7. Daftar Modul & Pemetaan ke FR/AC

Cakupan FR/AC **tidak berubah dari v1.0**. Kolom baru: bentuk implementasi Filament. FR P2 (FR-SCH-09, FR-RNK-04, FR-NTF-04, FR-EXP-01) tidak dikerjakan.

| # | Modul | Bentuk implementasi (v1.1) | FR | AC | Item PRD 28.1 | Prioritas |
|---|---|---|---|---|---|:-:|
| 1 | **M-AUTH** | Login panel Filament, profil, middleware (aktif, wajib ganti password, idle), `canAccessPanel`, Policies, `AccessConfig` | FR-AUTH-01..05 | AC-AUTH-01..05 | T-07, T-08 | P0 |
| 2 | **M-AUD (inti)** | `AuditWriter` + `AuditObserver` | FR-AUD-01, 03 | AC-AUD-01, 03 | T-09 | P0 |
| 3 | **M-MST** | Resources master (SA), halaman Setting, seeder | FR-MST-01/02 | AC-USR-02 | T-10 | P0 |
| 4 | **M-USR** | `UserResource` (SA), Action reset password & nonaktif | FR-USR-01/02, FR-MNT-01 | AC-USR-01/03, AC-AUTH-03 | T-11 | P0 |
| 5 | **M-SCH** | `SekolahResource` (list + filter, create/edit via domain action, view dengan Infolist + tabs), Actions arsip & koreksi cabang | FR-SCH-01..04, 06..08, 10, 11 | AC-SCH-01..11, 13..15, 17..20 | T-12, T-13 | P0 |
| 6 | **M-NRH** | Relation Manager Narahubung, `UsulanNarahubungResource`, Action usulan (mentor) | FR-SCH-05, 12 | AC-SCH-12, 16, 21..23 | T-12a | P0 |
| 7 | **M-MNT** | Actions Tugaskan Mentor / Tunjuk Pelaksana; halaman mentor "Sekolah Saya" | FR-MNT-02..05 | AC-RPT-08 | T-14 | P0 |
| 8 | **M-JDA** | Relation Manager Kegiatan (tab Awal), Action Jadwal Ulang, halaman **daftar Jadwal lintas sekolah** (tab Pelatihan Awal; ber-scope; PRD 11.1) | FR-JDA-01..04 | AC-JDA-01..08 | T-15, T-17 | P0 |
| 9 | **M-JTL** | Relation Manager Kegiatan (tab TL), panel ringkasan juklak, Action Tambah Sesi (admin & mentor), tab Tindak Lanjut di daftar Jadwal lintas sekolah (PRD 11.1) | FR-JTL-01..04, 06, 09 | AC-JTL-01..05, 08..10, 12, 13, 16 | T-16, T-17 | P0 (FR-JTL-04: P1) |
| 10 | **M-RPT** | Panel mentor: Beranda, Kegiatan Saya (kartu + Action status), Jadwal Koordinasi · Panel admin: Custom Page Progres Mentor (Livewire matriks), Action Ubah Status (AP) | FR-RPT-01..08, FR-MNT-04/06 | AC-RPT-01..12 | T-19, T-21, T-22 | P0 (FR-MNT-06: P1) |
| 11 | **M-RGK** | Actions Sesi Terakhir & Buka Kembali; Table widget "Perlu Perhatian AP" | FR-JTL-05, 07, 08, 10 | AC-JTL-06, 07, 11, 14, 15 | T-16a, T-20 | P0 (FR-JTL-08: P1) |
| 12 | **M-CRT** | `SertifikatResource` (tab Menunggu/Tersedia), Action Input Link (AP), kartu di detail sekolah | FR-CRT-01..06 | AC-CRT-01..08 | T-23, T-24 | P0 |
| 13 | **M-NTF** | Database notifications Filament (kedua panel), Listeners queue, scheduler H-1/H+1, pruning | FR-NTF-01..03 | AC-NTF-01..08 | T-25 | P0 |
| 14 | **M-RNK** | Custom Page Klasemen + Table widget + Chart widget | FR-RNK-01..03 | AC-RNK-01..07 | T-26 | P0 (FR-RNK-03: P1) |
| 15 | **M-DSH** | Custom Page Dashboard: Stats widget, Chart widgets, Table widget perbandingan cabang, widget kegiatan mendatang | FR-DSH-01..06 | AC-DSH-01..07 | T-27 | P0 (FR-DSH-05: P1) |
| 16 | **M-AUD (UI)** | `AuditLogResource` read-only (SA) + tampilan diff | FR-AUD-02 | AC-AUD-02 | T-28 | P0 |
| 17 | **M-CAL** | Kalender read-only di panel admin (menu Jadwal, PRD 11.1) dan panel mentor (Kegiatan Saya, PRD 11.2) [TD-13, menunggu SP-07] | — (UI 20.10) | — | T-18 | P1 |

---

## 8. Tahapan Implementasi

Fase, urutan, dan exit criteria berbasis AC **tidak berubah dari v1.0**. Yang diperbarui: deliverable teknis sesuai stack Filament. Durasi tidak dicantumkan karena ukuran tim belum diketahui [TD-03]; kompleksitas relatif S/M/L.

### Fase 0 — Persiapan & Keputusan Teknis (T-01..T-04) · M

| Komponen | Isi |
|---|---|
| Gerbang masuk | PRD v1.3 disetujui ✔ |
| Deliverable | (1) Keputusan TD bergerbang Fase 1; (2) wireframe halaman inti, termasuk **halaman mentor di viewport ponsel**; (3) data awal (cabang, sekolah, mentor + email); (4) nilai setting "lokasi kampus pusat"; (5) **spike teknis** sesuai tabel di bawah; (6) laporan spike berisi bukti (output perintah, hasil test, tangkapan layar ponsel) dan rekomendasi penguncian versi |
| Exit criteria | Semua TD gerbang Fase 1 berstatus "Diputuskan"; desain disetujui; **seluruh butir SP-01..SP-07 lulus**; dokumen ini disetujui pemilik produk |

**Spike teknis Fase 0.** Kode spike bersifat sekali pakai, dikerjakan di branch atau repositori terpisah, dan **tidak di-merge** ke kode produksi. Plugin kalender **tidak dipasang** di proyek produksi sebelum SP-07 lulus.

| ID | Yang harus dibuktikan | Bukti | Kriteria lulus |
|---|---|---|---|
| SP-01 | Kombinasi versi seluruh stack berjalan: PHP 8.4, Laravel 13, Filament 5, Livewire 4, Tailwind 4.1 (Vite), PostgreSQL 17, Pest, Playwright | `composer install`/`npm run build` bersih; `php artisan about`; Pest dan Playwright berjalan di CI spike | Tanpa konflik dependensi; versi tercatat di laporan |
| SP-02 | Dua panel memakai **satu tabel `users`** dengan pembatasan role yang benar | Test Pest: mentor ditolak di `/admin`; SA/AP/AC ditolak di `/mentor`; akun nonaktif ditolak di keduanya | Semua test lulus |
| SP-03 | Data lintas cabang **tidak bocor** lewat Resource (list/view), dropdown `Select` (opsi awal dan pencarian), filter, relation manager, dan request Livewire | Test LS-01, LS-07, LS-08, LS-09 versi mini (satu Resource sekolah + satu relation manager + satu Action) | Tidak ada sentinel cabang lain di respons AC; aksi lintas cabang ditolak |
| SP-04 | Halaman mentor nyaman di ponsel | Prototipe halaman "Kegiatan Saya" (kartu + modal Perbarui Status) diuji di Playwright viewport 360×800 dan di minimal satu ponsel fisik | Tanpa scroll horizontal; target sentuh ≥ 44px; alur ubah status ≤ 3 ketukan; ditinjau perwakilan pengguna |
| SP-05 | Domain action dapat diuji **tanpa** Filament/Livewire | Satu domain action contoh (misalnya `RescheduleKegiatan`) dengan Policy, transaksi, riwayat, dan audit; test Pest memanggil action langsung; Pest `arch()` memastikan `App\Domain` tidak memakai `Filament\*`/`Livewire\*` | Test lulus tanpa booting panel; arch test lulus |
| SP-06 | Idle timeout tetap berfungsi saat polling berjalan | Middleware `EnforceIdleTimeout` versi spike + test IT-01..IT-03 di level HTTP; identifikasi pasti nama komponen dan payload polling notifikasi Filament 5 serta path endpoint update Livewire 4 | IT-01..IT-03 dan IT-08..IT-12 lulus (polling murni tidak memperbarui aktivitas; submit form, aksi, reschedule, interaksi tabel, dan aksi notifikasi memperbaruinya) |
| SP-07 | Pemilihan pustaka kalender [TD-13] | Pasang `saade/filament-fullcalendar` **^5.0** lalu **^4.0** di proyek spike; render jadwal read-only; klik event membuka Action "Jadwal Ulang" yang memanggil domain action | Lihat kriteria TD-13 (3.4). Bila kedua versi gagal, pakai fallback kustom |


### Fase 1 — Fondasi (T-05..T-09) · L

| Komponen | Isi |
|---|---|
| Gerbang masuk | Fase 0 selesai; TD-01 (versi), 02, 03, 04, 06, 07, 08, 09, 12, 14, 15 diputuskan; TD-05 untuk audit |
| Deliverable | Proyek Laravel + Filament dengan versi terkunci; panel `admin` dan `mentor` + tema kustom; fitur bawaan dinonaktifkan [TD-15]; CI (Pint, Larastan, Pest, build aset); environment dev/staging + backup harian [TD-06]; migration seluruh skema Bagian 5; autentikasi (login, lockout, wajib ganti password, idle timeout berbasis aktivitas, akun nonaktif); `AccessConfig` + Policies (deny-by-default) + query scope `visibleTo()`; `AuditWriter`, `AuditObserver`, `HistoryWriter`, `OptimisticLock`; navigasi per role; Pest architecture tests |
| Exit criteria | AC-AUTH-01..05 lulus; AC-AUD-01/03 untuk login/logout/gagal login; partial unique index terbukti menolak data invalid; authorization matrix test terbangkit dari `AccessConfig` berjalan; architecture test lulus; **IT-01..IT-13 lulus di level HTTP (server-side)**; **LS-01..LS-10 lulus untuk aksi yang sudah ada**; restore backup staging teruji |

### Fase 2 — Master & Pengguna (T-10, T-11) · S

| Komponen | Isi |
|---|---|
| Gerbang masuk | Fase 1 selesai |
| Deliverable | Seeder (35 kab/kota, 6 kategori, Pelatihan/Pendampingan, Online/Offline); Resources master (SA) tanpa delete untuk data terpakai; `UserResource` (SA) + reset password + nonaktif dengan pencabutan sesi |
| Exit criteria | AC-USR-01..03, AC-AUTH-03 lulus; FR-MST-02 terverifikasi; audit tercatat |

### Fase 3 — Sekolah, Narahubung & Mentor (T-12, T-12a, T-13, T-14) · L

| Komponen | Isi |
|---|---|
| Gerbang masuk | Fase 2 selesai |
| Deliverable | `SekolahResource` (list + filter AND + kolom 13.2 + paginasi; create/edit via domain action; peringatan kemiripan trigram di form); view sekolah (Infolist + tab Ringkasan & Riwayat); Actions arsip dan koreksi cabang; Relation Manager narahubung + riwayat; `UsulanNarahubungResource` + Action usulan di panel mentor; Actions penugasan; halaman mentor "Sekolah Saya" dan "Semua Sekolah" *(A-01)*; domain events disiapkan |
| Exit criteria | AC-SCH-01..23 lulus (AC-SCH-09/10/19 diuji ulang di Fase 4/6); AC-SCH-06 ≤ 2 detik pada 5.000 sekolah [A-04]; test scoping AC pada list, view, relation select, dan filter lulus |

### Fase 4 — Penjadwalan (T-15, T-16, T-17, T-18) · L

| Komponen | Isi |
|---|---|
| Gerbang masuk | Fase 3 selesai; TD-13 diputuskan sebelum T-18 |
| Deliverable | Halaman daftar Jadwal lintas sekolah (tab Pelatihan Awal/Tindak Lanjut, query ber-scope, filter tanggal/status/mentor); Relation Manager Kegiatan (tab Awal & TL, metode/lokasi awal terkunci, urutan otomatis, panel juklak); Actions Tambah Sesi (admin & mentor PJ), Tunjuk Pelaksana, Jadwal Ulang (alasan + riwayat versi); optimistic lock di form; Custom Page Kalender (P1) **read-only** dengan pustaka hasil SP-07: tanpa drag-and-drop/resize/klik-buat, klik event membuka Action Jadwal Ulang |
| Exit criteria | AC-JDA-01..08, AC-JTL-01..05, 08, 09, 10, 12, 16, AC-SCH-09, AC-SCH-19 lulus; E9 teruji; LS-01/02/06 untuk aksi jadwal lulus; test membuktikan kalender tidak menyediakan jalur penyimpanan selain `RescheduleKegiatan` |

### Fase 5 — Laporan, Progres & Penyelesaian Rangkaian (T-19, T-20, T-21, T-22, T-16a) · L

| Komponen | Isi |
|---|---|
| Gerbang masuk | Fase 4 selesai |
| Deliverable | Panel mentor: Beranda Saya, Kegiatan Saya (kartu + Action Perbarui Status ≤ 3 klik), Jadwal Koordinasi (data koordinasi saja, R-01), Action Kontak Awal; Action Ubah Status (AP); `ProgressRecalculator` + `progress:rebuild`; Actions Sesi Terakhir & Buka Kembali; label Perlu Pembaruan; Custom Page Progres Mentor (Livewire matriks sekolah × sesi); widget Perlu Perhatian AP |
| Exit criteria | AC-RPT-01..12, AC-JTL-06/07/11/13/14/15, AC-JDA-07 lulus; unit test `ProgressRecalculator` mencakup tabel 15.3 serta E6, E8, E15, E20, E22; Playwright di viewport 360px untuk alur lapor; test memastikan state Livewire halaman mentor tidak memuat catatan/tautan/link milik sekolah non-tugas |

### Fase 6 — Sertifikat (T-23, T-24) · M

| Komponen | Isi |
|---|---|
| Gerbang masuk | Fase 5 selesai |
| Deliverable | Baris sertifikat otomatis; `SertifikatResource` (Menunggu/Tersedia); Action Input/Ubah Link (AP) dengan audit nilai lama/baru; kartu di detail sekolah; aturan akses buka link |
| Exit criteria | AC-CRT-01..08, AC-SCH-10 lulus; SA terbukti ditolak saat input link (UI dan request Livewire langsung) |

### Fase 7 — Notifikasi (T-25) · M

| Komponen | Isi |
|---|---|
| Gerbang masuk | Fase 6 selesai; TD-05 (notifikasi), TD-10, TD-11 diputuskan |
| Deliverable | Database notifications Filament di kedua panel (polling 60 detik); Listeners queue untuk pemicu FR-NTF-02 + R-07; penerima sesuai BR-NTF-01, tanpa pelaku (BR-NTF-02); job harian H-1/H+1 dengan dedup key; pruning; worker queue + cron terkonfigurasi di staging |
| Exit criteria | AC-NTF-01..08 lulus; job harian idempoten; IT-01..IT-13 diulang dengan polling notifikasi final dan tetap lulus |

### Fase 8 — Klasemen & Dashboard (T-26, T-27) · M

| Komponen | Isi |
|---|---|
| Gerbang masuk | Fase 6 selesai (klasemen boleh mulai setelah Fase 3) |
| Deliverable | Custom Page Klasemen (Table + Chart widget, dense ranking, kategori 0 tetap tampil); Dashboard per role (Stats, Chart, Table widget perbandingan cabang tanpa drill-down untuk AC, widget kegiatan mendatang); query agregat di `app/Domain/Dashboard/Queries` |
| Exit criteria | AC-RNK-01..07, AC-DSH-01..07, AC-CRT-07 lulus; seluruh rumus terbukti mengecualikan kegiatan Dibatalkan; dashboard ≤ 3 detik p95 pada 5.000 sekolah / 50.000 kegiatan [A-04] |

### Fase 9 — Audit UI (T-28) · S

| Komponen | Isi |
|---|---|
| Gerbang masuk | Fase 1 (paralel sejak Fase 3) |
| Deliverable | `AuditLogResource` read-only (SA): filter pengguna/entitas/aksi/tanggal, tampilan diff, termasuk pengguna nonaktif |
| Exit criteria | AC-AUD-02 lulus; tidak ada action create/edit/delete pada audit |

### Fase 10 — Hardening & Rilis (T-29..T-32) · M

| Komponen | Isi |
|---|---|
| Gerbang masuk | Fase 1–9 selesai; TD-02, TD-06 terverifikasi di production; TD-16 diputuskan |
| Deliverable | Uji keamanan (lintas cabang, IDOR via URL dan request Livewire, XSS catatan, CSRF, brute force); uji beban [TD-16]; production + backup + monitoring (worker queue, scheduler, `failed_jobs`); input data awal manual (T-29); UAT; pelatihan; go-live |
| Exit criteria | Seluruh AC P0 lulus UAT; tidak ada temuan keamanan berat/tinggi; restore production teruji; dokumen operasional tersedia |

### Ringkasan urutan

```
F0 ─► F1 ─► F2 ─► F3 ─► F4 ─► F5 ─► F6 ─► F7 ─► F10
                    │                  └──► F8 ──┘
                    └──► F9 (paralel) ───────────┘
```

---

## 9. Strategi Pengujian

| Jenis | Alat | Cakupan |
|---|---|---|
| **Feature test per AC** | Pest + helper testing Livewire/Filament | Setiap AC PRD menjadi minimal satu test; nama test memuat ID AC. Aksi Filament diuji lewat pemanggilan Action di komponen, bukan hanya lewat domain action |
| **Authorization matrix test** | Pest (dataset dari `AccessConfig`) | Aksi × role × scope diuji di **dua lapis**: domain action (Gate) dan permukaan Filament (halaman, Action, relation select). Wajib menguji kasus *deny* |
| **Architecture test** | Pest `arch()` | `App\Domain` bebas dari Filament/Livewire; Filament tanpa `DB::` langsung; Policies ada untuk setiap model bisnis |
| **Unit test domain** | Pest | `ProgressRecalculator`, normalisasi nama, aturan sesi terakhir, rumus metrik (data campuran berisi kegiatan Dibatalkan) |
| **DB constraint test** | Pest + PostgreSQL test DB | Setiap partial unique index dan CHECK |
| **Manipulasi & kebocoran Livewire** | Pest (level request Livewire) | LS-01..LS-10 (Bagian 4.11): manipulasi ID dan argumen aksi, field terlarang, property `#[Locked]`, opsi Select/filter, **uji sentinel** pada HTML dan snapshot Livewire |
| **Idle timeout server-side** | Pest (level HTTP) | IT-01..IT-13 (Bagian 4.10) di kedua panel |
| **E2E** | Playwright | Alur inti lintas role; viewport ponsel 360px untuk panel mentor |
| **Uji beban** | k6 [TD-16] atau alternatif | Dataset sintetis 5.000 sekolah / 50.000 kegiatan [A-04] |
| **UAT** | Checklist AC P0 | Gerbang go-live |

Test berjalan di PostgreSQL (bukan SQLite), karena partial index, trigram, dan JSONB khusus PostgreSQL.

**Gerbang cakupan acceptance criteria (CI):** sebuah skrip di CI mengambil seluruh ID AC dari `docs/PRD.md` (saat ini 100 AC) dan memastikan setiap ID muncul di nama minimal satu test Pest atau Playwright. Build gagal bila ada AC tanpa test. AC P1 boleh ditandai "ditunda" secara eksplisit, tetapi tidak boleh hilang diam-diam.

---

## 10. Risiko & Potensi Konflik

### 10.1 Risiko khusus Filament/Livewire (baru di v1.1)

| ID | Risiko | Dampak | Mitigasi |
|---|---|---|---|
| F-R1 | Business logic bocor ke UI: form Filament secara default langsung menyimpan via Eloquent, sehingga riwayat, alasan wajib, atau recalculator terlewat | Tinggi | Override create/update page ke domain action; semua Action memanggil domain action; Pest architecture test; observer audit sebagai jaring pengaman; checklist code review |
| F-R2 | Kebocoran scope cabang lewat permukaan sekunder: opsi `Select` relasi, filter, relation manager, global search, query widget | Tinggi | Satu query scope `visibleTo()` dipakai di semua permukaan; global search dinonaktifkan [TD-15]; authorization test per permukaan |
| F-R3 | State Livewire terbaca di browser; ID, argumen aksi, dan state form dapat dimanipulasi | Tinggi | `#[Locked]` **bukan** kontrol akses; objek dimuat lewat query ber-scope; domain action memeriksa permission, cabang, penugasan, dan state; field input eksplisit; test LS-01..LS-10 termasuk uji sentinel (4.11) |
| F-R4 | Polling notifikasi memperpanjang sesi sehingga idle timeout tidak pernah tercapai | Sedang | Stempel aktivitas server-side, klasifikasi pasif deny-by-default, sesi diakhiri di server; test IT-01..IT-13 (4.10) |
| F-R5 | Perubahan major Filament cukup cepat (v3 → v4 → v5 dalam ±2 tahun) | Sedang | Kunci versi major; hindari plugin pihak ketiga; kode bisnis terpisah di `app/Domain` sehingga upgrade hanya menyentuh presentasi; alokasikan waktu upgrade setelah MVP |
| F-R6 | Ketergantungan pada plugin kalender; v5 masih baru (FullCalendar 7) | Rendah | Hanya untuk fitur P1; diputuskan lewat SP-07; cadangan ^4 dan fallback kustom; read-only; tanpa plugin premium |
| F-R14 | Komponen atau payload polling berubah saat upgrade Filament/Livewire sehingga klasifikasi pasif salah | Sedang | Klasifikasi deny-by-default (bila tidak dikenali, dianggap aktivitas: sesi bisa lebih lama, tetapi tidak ada request yang salah dianggap pasif); IT-03 menangkap perubahan; ditinjau ulang setiap upgrade |
| F-R7 | UX panel Filament kurang nyaman di ponsel untuk mentor | Sedang | Panel mentor terpisah dengan halaman kustom berbasis kartu; validasi lewat spike Fase 0 dan Playwright mobile |
| F-R8 | Halaman dengan struktur dinamis (matriks progres) tidak cocok dengan tabel Filament | Sedang | Custom Page + komponen Livewire; query agregat tunggal; paginasi per sekolah |
| F-R9 | Pipeline build aset: tema kustom butuh Node + Tailwind 4.1 | Rendah | Build aset di CI; artefak dideploy; Node tidak dibutuhkan di runtime production |
| F-R10 | Fitur tenancy Filament tidak cocok dengan model akses (AP lintas cabang, mentor lintas cabang) | Sedang | Tidak memakai tenancy [TD-14]; scoping domain |
| F-R11 | Optimistic lock tidak tersedia bawaan di form Filament | Rendah | Field `version` tersembunyi + pengecekan di domain action + notifikasi konflik ke pengguna |
| F-R12 | Kompatibilitas penuh Laravel 13 + Filament 5 belum dibuktikan di proyek ini (constraint paket mendukung) | Rendah | Verifikasi `composer` di spike Fase 0; fallback Laravel 12 hanya bila gagal, dengan catatan masa dukungan lebih pendek |
| F-R13 | Ukuran payload Livewire dan performa tabel besar | Sedang | Paginasi server 25/50 baris; eager loading di `modifyQueryUsing`; kolom cache di `sekolah`; hindari tabel tanpa paginasi |

### 10.2 Risiko teknis umum

| ID | Risiko | Dampak | Mitigasi |
|---|---|---|---|
| T-R1 | Status turunan tidak sinkron | Tinggi | `ProgressRecalculator` dalam transaksi; `progress:rebuild`; test invariant |
| T-R2 | Logika penyelesaian rangkaian kompleks | Tinggi | Fungsi murni + test tabel; riwayat rangkaian |
| T-R3 | Race condition antaraktor | Sedang | Optimistic lock; partial unique index |
| T-R4 | Job harian dobel/terlewat | Sedang | Dedup key; idempoten; monitoring `failed_jobs`; worker + cron diawasi [TD-02] |
| T-R5 | Keahlian tim terhadap Filament/Livewire [TD-03] | Sedang | Spike Fase 0; pola implementasi didokumentasikan di Fase 1 |
| T-R6 | Volume tak diketahui [A-04] | Sedang | Indeks dan agregasi DB; queue bisa beralih dari database ke Redis tanpa mengubah domain |

### 10.3 Risiko keamanan

| ID | Risiko | Mitigasi |
|---|---|---|
| S-01 | IDOR lintas cabang/mentor (URL atau parameter request Livewire) | Query scope + Policy di setiap Resource, Action, dan domain action; authorization matrix test |
| S-02 | **Kebocoran data lewat state Livewire** (menggantikan risiko Inertia props di v1.0) | Lihat F-R3 |
| S-03 | XSS lewat catatan dan alasan | Escape default Blade; larangan `{!! !!}` untuk input pengguna; CSP header |
| S-04 | Brute force login | Throttle Filament diperluas + lockout [TD-09]; gagal login diaudit |
| S-05 | Sesi tetap aktif setelah akun dinonaktifkan | Session driver database; hapus sesi saat nonaktif; cek `aktif` di middleware |
| S-06 | Link sertifikat publik di penyedia eksternal | Di luar kendali sistem; link hanya dirender untuk yang berhak; `rel="noopener noreferrer"` |
| S-07 | Audit diubah lewat akses DB langsung | Pemisahan hak user DB [TD-02]; backup terenkripsi |
| S-08 | Data narahubung terbaca mentor seluruh Jateng *(A-01)* | Diterima sesuai asumsi; flag A-01 memungkinkan pembatasan cepat |

### 10.4 Risiko performa

| ID | Risiko | Mitigasi |
|---|---|---|
| P-01 | N+1 di tabel sekolah | Eager loading; kolom cache; subquery agregat; test jumlah query |
| P-02 | Agregasi dashboard berat | Agregasi SQL ber-indeks; cache 60 detik bila perlu |
| P-03 | Pencarian kemiripan nama | Index trigram GIN |
| P-04 | Pertumbuhan `audit_log` | Index tepat; partisi bulanan bila perlu [TD-05] |
| P-05 | Polling notifikasi dari banyak pengguna | Query notifikasi ber-indeks (`notifiable_id`, `read_at`); interval 60 detik |

### 10.5 Potensi konflik business rules

K-01 sampai K-10 tetap berlaku. **K-06 dan K-09 sudah diputuskan pemilik produk (v1.4): fitur terkait ditunda dari MVP.** Matriks permission tidak berubah dan tidak ada kewenangan baru untuk role mana pun.

| ID | Temuan | Status | Keputusan untuk MVP |
|---|---|---|---|
| K-06 | Sekolah arsip tidak dapat bergabung kembali (BR-SCH-13; PRD tidak punya fitur batal-arsip) | **[DIPUTUSKAN v1.4] Ditunda dari MVP** | Fitur aktivasi ulang tidak dibangun. Sekolah arsip tetap arsip. Bila dibutuhkan kelak, diajukan sebagai perubahan PRD |
| K-09 | PD-25 mewajibkan kegiatan yang belum selesai dibatalkan saat sekolah mundur; AC berwenang mengarsipkan tetapi tidak berwenang mengubah status kegiatan | **[DIPUTUSKAN v1.4] Pembatalan otomatis ditunda dari MVP** | Hak pembatalan tetap sesuai matriks: mentor T (`kegiatan.report`) dan AP dengan alasan (`kegiatan.admin_status`); SA dan AC tidak. Domain action `ArchiveSekolah` (dipanggil SA, AP, atau AC) **menolak** arsip selama ada kegiatan Belum Dimulai/Sedang Berlangsung. Pesan penolakan memuat alasan, daftar kegiatan (tahap, nama sesi, tanggal, status, mentor pelaksana), dan pihak yang berwenang membatalkan. Kegiatan Selesai/Dibatalkan tidak menghalangi |

Butir K lain: K-01 (pengecualian SA), K-02 (BR-AWL-04 juga berlaku untuk AP), K-03 (badge "dibuka kembali"), K-04 (`pelaksana_ditunjuk_khusus`, sesuai rekomendasi PRD E3), K-07 (TD-08, diputuskan), dan K-08 (PJ vs pelaksana) diimplementasikan sesuai v1.0. **K-05 dan K-10 dikembalikan menjadi usulan yang menunggu konfirmasi** karena menambah perilaku yang tidak tertulis di PRD; lihat Bagian 13.4 (temuan C-03 dan C-02).

---

## 11. Penanganan Asumsi A-01 s.d. A-04

*(Tidak berubah dari v1.0, ditambah lokasi implementasi Filament.)*

| Asumsi | Titik implementasi | Bila asumsi berubah |
|---|---|---|
| **A-01** Mentor melihat seluruh sekolah Jateng | Flag `access.mentor_can_view_all_schools` di `AccessConfig`, dibaca query scope mentor. Halaman "Semua Sekolah" di panel mentor hanya muncul bila flag aktif | Ubah flag ke `false`: scope otomatis hanya sekolah tugas, menu tersembunyi. Test matriks dijalankan untuk kedua nilai |
| **A-02** Penugasan mentor oleh AP | Daftar role untuk `penugasan.assign` di `AccessConfig` | Ubah daftar role |
| **A-03** Mentor lintas cabang | Tanpa constraint mentor–cabang | Tambah validasi di `AssignMentor` bila dibatasi |
| **A-04** Volume belum ditentukan | Target NFR diuji dengan 5.000 sekolah / 50.000 kegiatan | Ulangi uji beban dengan angka nyata |

Asumsi tidak dicatat sebagai keputusan di kode maupun dokumentasi. Konfigurasi asumsi diberi komentar "PRD 27.3 A-0x (asumsi)".

---

## 12. Gerbang Review Sebelum Coding

Coding produksi dimulai hanya bila seluruh butir berikut terpenuhi. **Status dokumen tidak diubah menjadi Approved tanpa persetujuan eksplisit pemilik produk.**

| # | Butir | Status |
|---|---|---|
| G-01 | Persetujuan eksplisit Implementation Plan (v1.4 atau revisi berikutnya) | ⏳ Menunggu |
| G-02 | TD-01 versi stack (PHP 8.4, Laravel 13, Filament 5, Livewire 4, Tailwind 4.1, PostgreSQL 17) | ✔ Diputuskan |
| G-03 | TD-04 idle 60 menit + test server-side | ✔ Diputuskan |
| G-04 | TD-08 permission statis; akun & role oleh SA | ✔ Diputuskan |
| G-05 | TD-12 dua panel; TD-14 tanpa tenancy | ✔ Diputuskan |
| G-06 | K-06 dan K-09 | ✔ Diputuskan (ditunda dari MVP) |
| G-07 | TD-02 hosting + checklist PostgreSQL 17 (H-01..H-06) | ⏳ Menunggu keputusan operasional |
| G-08 | TD-03 tim & penanggung jawab operasional | ⏳ Menunggu keputusan operasional |
| G-09 | TD-05 retensi audit (minimal bagian audit) | ⏳ Menunggu keputusan operasional |
| G-10 | TD-06 backup, RPO/RTO, penanggung jawab | ⏳ Menunggu keputusan operasional |
| G-11 | TD-09 kebijakan password & lockout | ⏳ Menunggu keputusan operasional |
| G-12 | TD-07 identitas login; TD-15 fitur bawaan Filament yang dinonaktifkan | ⏳ Menunggu |
| G-13 | Temuan konsistensi C-01..C-04 (Bagian 13.4) dikonfirmasi, atau perilaku konservatifnya diterima | ⏳ Menunggu |
| G-14 | Spike Fase 0: SP-01 sampai SP-06 lulus | ⏳ Belum dilaksanakan |
| G-15 | Desain UI halaman inti disetujui | ⏳ Menunggu |

Boleh diputuskan kemudian: TD-13 (setelah SP-07, sebelum T-18), TD-10 dan TD-11 (sebelum Fase 7), TD-16 (sebelum Fase 10).


---

## 13. Laporan Konsistensi PRD ↔ Implementation Plan (v1.4)

### 13.1 Metode

1. Perbandingan baris per baris matriks permission PRD Bagian 9 (35 kapabilitas) dengan matriks Bagian 6 dokumen ini.
2. Pemeriksaan otomatis cakupan ID: seluruh AC (100) dan FR (73) di PRD dibandingkan dengan Bagian 7 (modul) dan Bagian 8 (exit criteria fase).
3. Penelusuran setiap aturan bisnis yang menulis riwayat, audit, atau notifikasi ke domain action (Bagian 4.12).
4. Penelusuran butir K, TD, dan rekomendasi teknis terhadap keputusan PRD untuk mendeteksi perilaku yang tidak tertulis di PRD.

### 13.2 Hasil per butir pemeriksaan

| # | Butir | Hasil | Bukti / catatan |
|---|---|---|---|
| 1 | Tidak ada keputusan bisnis yang berubah tanpa persetujuan | ⚠ → diperbaiki | Dua penanganan teknis ternyata menambah perilaku yang tidak tertulis di PRD: K-10 (syarat hapus kegiatan lebih ketat) dan K-05 (penugasan diakhiri otomatis saat arsip). Keduanya **dikembalikan menjadi usulan** yang menunggu konfirmasi (C-02, C-03). Selebihnya sesuai PRD v1.3 |
| 2 | Tidak ada fitur MVP yang bertentangan dengan PRD | ✔ (dengan dua penyelarasan) | Fitur P2 tidak dikerjakan; non-goal dipatuhi (tanpa akun sekolah, tanpa impor, tanpa WhatsApp, tanpa ekspor); fitur bawaan Filament di luar PRD dinonaktifkan (TD-15). Dua kekurangan terhadap PRD ditambahkan tanpa perubahan bisnis: **daftar Jadwal lintas sekolah** (PRD 11.1) dan **kalender di panel mentor** (PRD 11.2). TD-15 diklarifikasi: pencarian tabel (FR-SCH-06) tetap aktif |
| 3 | Seluruh role memiliki hak akses sesuai matriks | ✔ (1 selisih, 1 detail) | 34 dari 35 kapabilitas identik. Selisih: "Tambah sesi TL" untuk mentor; PRD matriks menulis T (penanggung jawab + pelaksana) sedangkan PD-04/BR-ACT-12 menulis penanggung jawab; dokumen ini mengikuti PJ (C-01). Detail yang belum tertulis: field kegiatan yang boleh diubah per role (C-04) |
| 4 | Aturan cabang dan penugasan mentor diterapkan di backend | ✔ | Query scope `visibleTo()` di semua permukaan baca (4.5); Policy memeriksa role, cabang, penugasan, dan state; domain action memanggil `Gate::authorize` (4.4, 4.11); ID terkait dalam input divalidasi; penerima notifikasi dihitung di server (AC cabang sekolah); klasemen dan perbandingan cabang sengaja global sesuai keputusan PRD; test LS-01..LS-10 dan authorization matrix test. Flag A-01 hanya memengaruhi **baca**; aksi tulis mentor selalu mensyaratkan penugasan |
| 5 | Riwayat, audit, dan notifikasi tidak terlewat di domain action | ⚠ → diperbaiki | v1.3 hanya menyebut 16 action contoh; action untuk ubah sekolah, kategori, kelola narahubung langsung, hapus kegiatan, pengguna, dan master belum terdaftar. v1.4 menambahkan **katalog lengkap (4.12)** dengan kolom riwayat, audit, dan event per action, plus aturan pengujian katalog |
| 6 | Seluruh acceptance criteria memiliki pengujian | ✔ | 100/100 AC terpetakan ke modul (Bagian 7) dan ke exit criteria fase (Bagian 8); 69/69 FR non-P2 terpetakan. Aturan "setiap AC ≥ 1 test" ditegakkan dengan gerbang CI (Bagian 9) |
| 7 | K-06 dan K-09 tercatat sebagai keputusan penundaan MVP | ✔ | Bagian 10.5 dan G-06 |
| 8 | TD-02, TD-03, TD-05, TD-06, TD-09 ditandai menunggu keputusan operasional | ✔ | Bagian 2.1 dan G-07..G-11. TD-07 dan TD-15 juga masih terbuka (G-12) |

### 13.3 Penyelarasan yang dilakukan di v1.4 (tanpa perubahan bisnis)

| # | Perubahan | Dasar PRD |
|---|---|---|
| A-1 | Halaman daftar Jadwal lintas sekolah (tab Pelatihan Awal / Tindak Lanjut) di panel admin | PRD 11.1 menu Jadwal |
| A-2 | Kalender read-only juga di "Kegiatan Saya" panel mentor (tetap menunggu SP-07) | PRD 11.2 |
| A-3 | Klarifikasi: global search dinonaktifkan, pencarian/filter tabel tetap ada | FR-SCH-06/07 |
| A-4 | Katalog domain action lengkap | BR riwayat/audit/notifikasi (Bagian 14 PRD) |
| A-5 | Gerbang CI cakupan AC | PRD Bagian 19 |

### 13.4 Temuan yang membutuhkan konfirmasi pemilik produk

| ID | Temuan | Usulan | Dampak bila tidak dikonfirmasi |
|---|---|---|---|
| C-01 | Mentor yang boleh menambah sesi TL: PRD matriks menulis "T" (penanggung jawab **atau** pelaksana), sedangkan PD-04 dan BR-ACT-12 menulis "mentor penanggung jawab" | Ikuti PD-04/BR-ACT-12: **hanya mentor penanggung jawab**. Mentor pelaksana sesi tertentu tidak menambah sesi | Implementasi memakai PJ (pilihan yang lebih sempit, tidak menambah kewenangan) |
| C-02 | K-10: syarat hapus kegiatan. PRD BR-ACT-09: kegiatan tanpa laporan boleh dihapus. Usulan teknis v1.0 menambah syarat "belum pernah di-reschedule" agar riwayat jadwal (BR-ACT-06) tidak ikut terhapus | (a) Ikuti PRD apa adanya, dan riwayat jadwal kegiatan yang dihapus tetap tersimpan di audit; **atau** (b) syarat tambahan K-10. Usulan: **(a)**, karena tidak mengubah aturan PRD dan riwayat tetap terjaga lewat audit | Implementasi mengikuti PRD (a) |
| C-03 | K-05: saat sekolah diarsipkan, penugasan mentor aktif diakhiri otomatis (`alasan_selesai = arsip`). PRD tidak mengatur | Setujui pengakhiran otomatis (riwayat penugasan tetap ada; sekolah arsip hilang dari "Sekolah Saya") **atau** biarkan penugasan tetap aktif | Penugasan dibiarkan aktif; sekolah arsip tetap tampil dengan label Arsip di "Sekolah Saya" |
| C-04 | Field kegiatan yang boleh diubah per role belum tertulis rinci. Matriks: mentor T hanya "ubah tanggal/waktu/lokasi/tautan" | Mentor: hanya tanggal, waktu, lokasi, tautan (sesuai matriks). Admin (SA, AP, AC cabang): juga nama sesi, jenis, metode, keterangan, urutan (FR-JDA-04 "ubah jadwal", AC-JTL-05). Semua perubahan field jadwal tercatat di `kegiatan_jadwal_riwayat` | Mentor dibatasi ke empat field matriks; perubahan metode oleh mentor tidak tersedia |

Kolom "Dampak bila tidak dikonfirmasi" menunjukkan perilaku paling konservatif yang dipakai bila keputusan belum ada saat fase terkait dimulai. Perilaku tersebut tidak menambah kewenangan role mana pun.

---

### Sumber pemeriksaan kompatibilitas (22 Nov 2026)

- Filament 5.x installation: https://filamentphp.com/docs/5.x/introduction/installation
- Filament 5.x upgrade guide: https://filamentphp.com/docs/5.x/upgrade-guide
- Packagist `filament/support` (v5.11.4, constraint Laravel & Livewire): https://packagist.org/packages/filament/support
- Laravel 13 release notes: https://laravel.com/docs/13.x/releases.md
- Masa dukungan Laravel: https://www.endoflife.date/laravel
- Plugin kalender `saade/filament-fullcalendar`: https://filamentphp.com/plugins/saade-fullcalendar
- Packagist `saade/filament-fullcalendar` (v5.0.0, 27 Okt 2026; constraint `filament/filament ^4.0|^5.0`; FullCalendar 7): https://packagist.org/packages/saade/filament-fullcalendar
