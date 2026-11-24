# Implementation Plan
## Jadwal Sekolah Mantap Terpusat

| Atribut | Nilai |
|---|---|
| Versi | **1.1** (Draft untuk review) |
| Tanggal | 22 November 2026 |
| Acuan | `docs/PRD.md` versi 1.3 (disetujui) |
| Status | **Menunggu review. Belum disetujui. Coding belum boleh dimulai** sampai dokumen ini disetujui dan keputusan teknis gerbang Fase 1 (Bagian 2) diputuskan |
| Versi sebelumnya | `docs/archive/IMPLEMENTATION_PLAN_v1.0.md` |

### Riwayat Perubahan

| Versi | Tanggal | Perubahan |
|---|---|---|
| 1.0 | 22 Nov 2026 | Draft awal: stack usulan Laravel + Inertia + Vue/React + PostgreSQL |
| 1.1 | 22 Nov 2026 | **Revisi Filament-first** sesuai keputusan stack pemilik produk: Laravel + Filament + Livewire + Blade + Tailwind + PostgreSQL + queue database + Pest + Playwright. Diubah: Bagian 2 (TD-01 diputuskan, TD-12 diganti struktur panel, TD-13..TD-16 baru), Bagian 3 (stack + audit kompatibilitas versi), Bagian 4 (arsitektur dua panel, penempatan business logic, otorisasi di Filament), Bagian 6 (pemetaan permission ke permukaan UI; isi matriks **tidak berubah**), Bagian 7 (jenis implementasi Filament per modul), Bagian 8 (deliverable fase), Bagian 9 (alat uji), Bagian 10 (risiko khusus Filament/Livewire), Bagian 12 (gerbang). Tidak berubah: Bagian 1 (kecuali penyesuaian istilah), Bagian 5 (skema DB), Bagian 11 (asumsi), seluruh keputusan bisnis PRD |

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

### 2.1 Daftar TD (v1.1)

| ID | Topik | Status v1.1 | Usulan / Keputusan | Gerbang |
|---|---|---|---|---|
| **TD-01 (PD-30a)** | Stack aplikasi | **[DIPUTUSKAN]** stack; **versi menunggu persetujuan** | Stack: Laravel, Filament, Livewire, Blade, Tailwind CSS, PostgreSQL, queue database, Pest, Playwright. Usulan versi: lihat 3.2 | Fase 1 (versi dikunci di awal Fase 1) |
| **TD-02 (PD-30b)** | Hosting & infrastruktur | Terbuka | VPS/cloud; PostgreSQL terkelola atau terpisah; staging dan production terpisah; PHP-FPM + Nginx; worker queue dan cron scheduler sebagai proses terkelola (supervisor/systemd) | Fase 1 (environment), go-live |
| **TD-03 (PD-30c)** | Tim pengembang & kepemilikan operasional | Terbuka | Tetapkan developer, admin server, dan pemilik backup | Fase 1 |
| **TD-04 (PD-27)** | Batas waktu sesi idle | Terbuka | 60 menit. **Catatan v1.1:** polling Livewire (notifikasi) dapat memperpanjang sesi terus-menerus. Idle timeout harus dihitung dari aktivitas pengguna, bukan dari request polling (lihat 4.6 dan F-R4) | Fase 1 |
| **TD-05 (PD-20)** | Retensi audit log dan notifikasi | Terbuka | Audit ≥ 5 tahun tanpa penghapusan otomatis di MVP; notifikasi 90 hari dengan pruning terjadwal | Audit: Fase 1. Notifikasi: Fase 7 |
| **TD-06 (PD-29)** | Backup, RPO/RTO, penanggung jawab | Terbuka | Backup harian otomatis terenkripsi, retensi ≥ 30 hari, RPO 24 jam, RTO 8 jam, uji restore bulanan | Fase 1; wajib terverifikasi sebelum go-live |
| TD-07 | Identitas login | Terbuka | Email (unik). Login memakai halaman login bawaan Filament | Fase 1 |
| TD-08 | Pengelolaan role & permission oleh SA | Terbuka | **Matriks tetap di kode**; SA mengelola penetapan role ke akun. **Tidak memakai plugin editor permission** (misalnya Filament Shield/spatie-permission UI), karena aturan "hanya AP" dan "SA dilarang" (PD-05, PD-10) bisa dilanggar lewat editor runtime. Lihat K-07 | Fase 1 |
| TD-09 | Kebijakan password & lockout | Terbuka | Minimal 8 karakter; lockout 15 menit setelah 5 kali gagal (throttle login bawaan Filament diperluas); wajib ganti password saat login pertama | Fase 1 |
| TD-10 | Jam pengiriman pengingat H-1 dan H+1 | Terbuka | Satu job harian pukul 07.00 WIB | Fase 7 |
| TD-11 | Mekanisme pembaruan notifikasi | Terbuka | **Database notifications bawaan Filament dengan polling 60 detik** (memenuhi AC-NTF-01 tanpa server WebSocket). Alternatif Laravel Reverb tidak direkomendasikan untuk MVP karena butuh proses dan infrastruktur tambahan | Fase 7 |
| ~~TD-12~~ | ~~Varian frontend Vue/React~~ | **Dihapus** | Tidak relevan, karena Inertia/Vue/React tidak dipakai | — |
| **TD-12 (baru)** | Struktur panel Filament | Terbuka | **Dua panel** dengan satu backend, satu tabel `users`, dan satu guard: `admin` (SA, AP, AC) dan `mentor` (M). Lihat 4.2 | Fase 1 |
| TD-13 (baru) | Pustaka kalender | Terbuka | `saade/filament-fullcalendar` ^4 (mendukung Filament 4/5, FullCalendar 6, hanya tampilan gratis berlisensi MIT: bulan/minggu/daftar). Kalender berprioritas P1, jadi divalidasi dulu lewat spike. Fallback: tampilan daftar mingguan kustom dengan Livewire tanpa plugin | Fase 4 (T-18) |
| TD-14 (baru) | Multi-tenancy Filament untuk cabang | Terbuka | **Tidak memakai fitur tenancy Filament.** Tenancy Filament dirancang untuk pengguna yang berpindah antar-tenant lewat URL, sedangkan di sini AP dan SA melihat lintas cabang, AC terkunci di satu cabang, dan mentor bekerja lintas cabang [A-03]. Pembatasan cabang memakai query scope domain + Policy (4.5) | Fase 1 |
| TD-15 (baru) | Fitur bawaan Filament yang tidak ada di PRD | Terbuka | **Dinonaktifkan di MVP:** global search, bulk delete, ekspor/impor bawaan, registrasi, reset password via email. Alasan: di luar lingkup PRD (ekspor = P2, impor ditiadakan, reset password oleh SA) dan berisiko membocorkan data lintas cabang. Yang **diaktifkan:** halaman login, halaman profil (ganti password), database notifications | Fase 1 |
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
| PostgreSQL | **16 atau 17** | Fitur yang dipakai (partial index, pg_trgm, JSONB) tersedia di kedua versi |
| Node.js | LTS aktif (hanya untuk build aset) | Vite + Tailwind; tidak berjalan di production runtime |
| Pest | Versi yang kompatibel dengan PHPUnit pada Laravel 13 | Dikunci di Fase 1 |
| Playwright | Versi stabil terbaru saat Fase 1 | Alat uji, bukan dependensi runtime |

**Verifikasi yang wajib dilakukan di awal Fase 1, sebelum versi dikunci:**
1. `composer require` Laravel 13 + Filament 5 di proyek kosong. Pastikan resolusi dependensi berhasil tanpa konflik (membuktikan kompatibilitas penuh, bukan hanya `illuminate/contracts`).
2. Build tema kustom Tailwind 4.1 untuk kedua panel.
3. Bila TD-13 disetujui: pasang `saade/filament-fullcalendar` ^4 dan pastikan kompatibel dengan versi Filament yang dikunci.
4. Catat versi final di `composer.lock`/`package-lock.json` dan di dokumen ini.

### 3.3 Library tambahan (hanya yang benar-benar diperlukan)

| Kebutuhan | Keputusan | Alasan |
|---|---|---|
| Grafik dashboard, klasemen, perbandingan cabang | **Tanpa library tambahan.** Memakai chart widget bawaan Filament (berbasis Chart.js yang sudah dibundel) | FR-DSH-02/03, FR-RNK-03 cukup dengan grafik batang, donat, dan batang bertumpuk |
| KPI | Stats overview widget bawaan Filament | FR-DSH-01 |
| Kalender (P1) | `saade/filament-fullcalendar` ^4 **[TD-13]** atau fallback kustom | Satu-satunya plugin pihak ketiga yang diusulkan |
| Audit trail | **Dibangun sendiri** | Aturan PRD spesifik (hanya SA, append-only, nilai sebelum/sesudah, role saat aksi). Plugin activity log generik tidak menjamin aturan ini |
| Role/permission | **Dibangun sendiri** (`AccessConfig` + Policies) | TD-08 |
| Riwayat/versi | **Dibangun sendiri** (`HistoryWriter`) | Struktur riwayat per entitas ditetapkan PRD |
| Kualitas kode | Laravel Pint, Larastan (dev dependency) | Static analysis dan format; tidak memengaruhi runtime |

Total dependensi runtime di luar ekosistem inti Laravel/Filament: **maksimal satu** (plugin kalender, bila TD-13 disetujui).

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
2. ID objek di komponen kustom diberi atribut `#[Locked]`. Setiap aksi tetap memeriksa Policy, karena request Livewire dapat dimanipulasi.
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
| Idle timeout (AC-AUTH-05) [TD-04] | Middleware `TrackUserActivity` mencatat waktu aktivitas terakhir **kecuali** request polling notifikasi. Melewati batas: logout + redirect login. Ini perlu karena polling 60 detik akan terus memperbarui masa berlaku sesi Laravel |
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
| 8 | **M-JDA** | Relation Manager Kegiatan (tab Awal), Action Jadwal Ulang | FR-JDA-01..04 | AC-JDA-01..08 | T-15, T-17 | P0 |
| 9 | **M-JTL** | Relation Manager Kegiatan (tab TL), panel ringkasan juklak, Action Tambah Sesi (admin & mentor) | FR-JTL-01..04, 06, 09 | AC-JTL-01..05, 08..10, 12, 13, 16 | T-16, T-17 | P0 (FR-JTL-04: P1) |
| 10 | **M-RPT** | Panel mentor: Beranda, Kegiatan Saya (kartu + Action status), Jadwal Koordinasi · Panel admin: Custom Page Progres Mentor (Livewire matriks), Action Ubah Status (AP) | FR-RPT-01..08, FR-MNT-04/06 | AC-RPT-01..12 | T-19, T-21, T-22 | P0 (FR-MNT-06: P1) |
| 11 | **M-RGK** | Actions Sesi Terakhir & Buka Kembali; Table widget "Perlu Perhatian AP" | FR-JTL-05, 07, 08, 10 | AC-JTL-06, 07, 11, 14, 15 | T-16a, T-20 | P0 (FR-JTL-08: P1) |
| 12 | **M-CRT** | `SertifikatResource` (tab Menunggu/Tersedia), Action Input Link (AP), kartu di detail sekolah | FR-CRT-01..06 | AC-CRT-01..08 | T-23, T-24 | P0 |
| 13 | **M-NTF** | Database notifications Filament (kedua panel), Listeners queue, scheduler H-1/H+1, pruning | FR-NTF-01..03 | AC-NTF-01..08 | T-25 | P0 |
| 14 | **M-RNK** | Custom Page Klasemen + Table widget + Chart widget | FR-RNK-01..03 | AC-RNK-01..07 | T-26 | P0 (FR-RNK-03: P1) |
| 15 | **M-DSH** | Custom Page Dashboard: Stats widget, Chart widgets, Table widget perbandingan cabang, widget kegiatan mendatang | FR-DSH-01..06 | AC-DSH-01..07 | T-27 | P0 (FR-DSH-05: P1) |
| 16 | **M-AUD (UI)** | `AuditLogResource` read-only (SA) + tampilan diff | FR-AUD-02 | AC-AUD-02 | T-28 | P0 |
| 17 | **M-CAL** | Custom Page Kalender [TD-13] | — (UI 20.10) | — | T-18 | P1 |

---

## 8. Tahapan Implementasi

Fase, urutan, dan exit criteria berbasis AC **tidak berubah dari v1.0**. Yang diperbarui: deliverable teknis sesuai stack Filament. Durasi tidak dicantumkan karena ukuran tim belum diketahui [TD-03]; kompleksitas relatif S/M/L.

### Fase 0 — Persiapan & Keputusan Teknis (T-01..T-04) · M

| Komponen | Isi |
|---|---|
| Gerbang masuk | PRD v1.3 disetujui ✔ |
| Deliverable | (1) Keputusan TD bergerbang Fase 1; (2) wireframe halaman inti, termasuk **halaman mentor di viewport ponsel**; (3) data awal (cabang, sekolah, mentor + email); (4) nilai setting "lokasi kampus pusat"; (5) **spike teknis** (prototipe sekali pakai, bukan kode produksi): Laravel 13 + Filament 5 + dua panel + tema Tailwind 4.1 + satu halaman kartu mentor di ponsel + bukti scoping AC pada Resource, relation select, dan filter |
| Exit criteria | Semua TD gerbang Fase 1 berstatus "Diputuskan"; desain disetujui; hasil spike membuktikan kompatibilitas versi dan kelayakan UI mentor; dokumen ini berstatus "Disetujui" |

### Fase 1 — Fondasi (T-05..T-09) · L

| Komponen | Isi |
|---|---|
| Gerbang masuk | Fase 0 selesai; TD-01 (versi), 02, 03, 04, 06, 07, 08, 09, 12, 14, 15 diputuskan; TD-05 untuk audit |
| Deliverable | Proyek Laravel + Filament dengan versi terkunci; panel `admin` dan `mentor` + tema kustom; fitur bawaan dinonaktifkan [TD-15]; CI (Pint, Larastan, Pest, build aset); environment dev/staging + backup harian [TD-06]; migration seluruh skema Bagian 5; autentikasi (login, lockout, wajib ganti password, idle timeout berbasis aktivitas, akun nonaktif); `AccessConfig` + Policies (deny-by-default) + query scope `visibleTo()`; `AuditWriter`, `AuditObserver`, `HistoryWriter`, `OptimisticLock`; navigasi per role; Pest architecture tests |
| Exit criteria | AC-AUTH-01..05 lulus; AC-AUD-01/03 untuk login/logout/gagal login; partial unique index terbukti menolak data invalid; authorization matrix test terbangkit dari `AccessConfig` berjalan; architecture test lulus; idle timeout terbukti tetap berlaku walau polling aktif; restore backup staging teruji |

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
| Deliverable | Relation Manager Kegiatan (tab Awal & TL, metode/lokasi awal terkunci, urutan otomatis, panel juklak); Actions Tambah Sesi (admin & mentor PJ), Tunjuk Pelaksana, Jadwal Ulang (alasan + riwayat versi); optimistic lock di form; Custom Page Kalender (P1) |
| Exit criteria | AC-JDA-01..08, AC-JTL-01..05, 08, 09, 10, 12, 16, AC-SCH-09, AC-SCH-19 lulus; E9 teruji |

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
| Exit criteria | AC-NTF-01..08 lulus; job harian idempoten; polling notifikasi tidak memperpanjang idle timeout |

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
| **Kebocoran state Livewire** | Pest | Snapshot komponen halaman mentor non-tugas tidak memuat `catatan`, `tautan_pertemuan`, atau `link` |
| **E2E** | Playwright | Alur inti lintas role; viewport ponsel 360px untuk panel mentor |
| **Uji beban** | k6 [TD-16] atau alternatif | Dataset sintetis 5.000 sekolah / 50.000 kegiatan [A-04] |
| **UAT** | Checklist AC P0 | Gerbang go-live |

Test berjalan di PostgreSQL (bukan SQLite), karena partial index, trigram, dan JSONB khusus PostgreSQL.

---

## 10. Risiko & Potensi Konflik

### 10.1 Risiko khusus Filament/Livewire (baru di v1.1)

| ID | Risiko | Dampak | Mitigasi |
|---|---|---|---|
| F-R1 | Business logic bocor ke UI: form Filament secara default langsung menyimpan via Eloquent, sehingga riwayat, alasan wajib, atau recalculator terlewat | Tinggi | Override create/update page ke domain action; semua Action memanggil domain action; Pest architecture test; observer audit sebagai jaring pengaman; checklist code review |
| F-R2 | Kebocoran scope cabang lewat permukaan sekunder: opsi `Select` relasi, filter, relation manager, global search, query widget | Tinggi | Satu query scope `visibleTo()` dipakai di semua permukaan; global search dinonaktifkan [TD-15]; authorization test per permukaan |
| F-R3 | State Livewire terbaca di browser; property dapat dimanipulasi | Tinggi | Tanpa field sensitif di public property; `#[Locked]` untuk ID; Policy dicek ulang di setiap Action dan domain action; test kebocoran state |
| F-R4 | Polling notifikasi memperpanjang sesi sehingga idle timeout tidak pernah tercapai | Sedang | Middleware aktivitas yang mengabaikan request polling; test khusus (Fase 1/7) |
| F-R5 | Perubahan major Filament cukup cepat (v3 → v4 → v5 dalam ±2 tahun) | Sedang | Kunci versi major; hindari plugin pihak ketiga; kode bisnis terpisah di `app/Domain` sehingga upgrade hanya menyentuh presentasi; alokasikan waktu upgrade setelah MVP |
| F-R6 | Ketergantungan pada plugin kalender | Rendah | Hanya untuk fitur P1; fallback daftar mingguan kustom; tidak memakai plugin premium FullCalendar |
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

*(Tidak berubah dari v1.0.)* K-01 sampai K-10 tetap berlaku. Dua butir masih membutuhkan konfirmasi bisnis:

| ID | Temuan | Usulan |
|---|---|---|
| K-06 ⚠ | Sekolah arsip tidak dapat bergabung kembali (BR-SCH-13 + tidak ada fitur batal-arsip) | Tidak diimplementasikan di MVP. Perlu keputusan: apakah perlu "aktifkan kembali", oleh siapa, dengan alasan? |
| K-09 ⚠ | Arsip oleh AC mewajibkan pembatalan kegiatan, padahal status hanya boleh diubah mentor/AP (PD-05) | Pembatalan otomatis oleh sistem sebagai bagian aksi arsip, memakai alasan arsip, tercatat sebagai revisi "sistem (arsip)" |

Butir K lain diimplementasikan sesuai penanganan di v1.0: K-01 pengecualian SA, K-02 BR-AWL-04 berlaku juga untuk AP, K-03 badge "dibuka kembali", K-04 `pelaksana_ditunjuk_khusus`, K-05 penugasan diakhiri saat arsip, K-07 [TD-08], K-08 PJ vs pelaksana, K-10 hapus kegiatan lebih ketat.

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

Coding dimulai hanya bila seluruh butir berikut terpenuhi. **Dokumen ini tidak otomatis disetujui setelah direvisi.**

- [ ] Implementation Plan v1.1 disetujui pemilik produk dan tim teknis
- [ ] Usulan versi TD-01 (PHP 8.4, Laravel 13, Filament 5, Livewire 4, Tailwind 4.1, PostgreSQL 16/17) disetujui
- [ ] TD-02, TD-03, TD-04, TD-06, TD-07, TD-08, TD-09, TD-12, TD-14, TD-15 diputuskan (gerbang Fase 1)
- [ ] TD-05 diputuskan, minimal untuk retensi audit
- [ ] Spike Fase 0 berhasil (kompatibilitas versi, UI mentor di ponsel, scoping AC)
- [ ] Desain UI halaman inti disetujui
- [ ] K-06 dan K-09 dikonfirmasi, atau diterima untuk ditangani setelah MVP

TD-10, TD-11 (menjelang Fase 7), TD-13 (sebelum T-18), dan TD-16 (sebelum Fase 10) boleh diputuskan kemudian.

---

### Sumber pemeriksaan kompatibilitas (22 Nov 2026)

- Filament 5.x installation: https://filamentphp.com/docs/5.x/introduction/installation
- Filament 5.x upgrade guide: https://filamentphp.com/docs/5.x/upgrade-guide
- Packagist `filament/support` (v5.11.4, constraint Laravel & Livewire): https://packagist.org/packages/filament/support
- Laravel 13 release notes: https://laravel.com/docs/13.x/releases.md
- Masa dukungan Laravel: https://www.endoflife.date/laravel
- Plugin kalender `saade/filament-fullcalendar`: https://filamentphp.com/plugins/saade-fullcalendar
