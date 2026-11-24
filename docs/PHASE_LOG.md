# Log Fase Implementasi

Ringkasan hasil per fase, asumsi baru, dan pekerjaan yang masih terbuka. Acuan: `docs/IMPLEMENTATION_PLAN.md` v1.4 Bagian 8.

## Fase 0 — Spike (SP-01 s.d. SP-06)

Lihat `docs/SPIKE_REPORT.md`. Diterima pemilik produk 23 Nov 2026.

## Fase 2 — Master & Pengguna (T-10, T-11) — 23 Nov 2026

### Hasil

| Deliverable | Implementasi | Bukti test |
|---|---|---|
| Seeder master (35 kab/kota, 6 kategori, Pelatihan/Pendampingan, Online/Offline, lokasi kampus) | `MasterDataSeeder` (idempoten) | `MasterDataActionTest` BR-USR-03 |
| Resources master (SA) | `app/Filament/Admin/Resources/Master/*` (Cabang, Kabupaten/Kota, Kategori Pelatihan, Jenis Kegiatan, Metode Kegiatan), grup menu "Data Master" | `MasterResourceTest` |
| Tanpa delete untuk data terpakai (FR-MST-02) | `DeleteMasterData` hanya untuk data yang tidak direferensikan (`MasterRegistry::isUsed`); data terpakai dinonaktifkan (`SetMasterDataAktif`); FK `RESTRICT` sebagai cadangan | `MasterDataActionTest` FR-MST-02 (4 test), `MasterResourceTest` |
| Data master nonaktif tidak ditawarkan untuk input baru | Validasi di `RegisterSekolah`, `UpdateSekolah`, `RescheduleKegiatan`, `CreateUser`/`UpdateUser`; dropdown hanya aktif (nilai lama tetap boleh dipertahankan) | `MasterResourceTest`, `UserActionTest` |
| Halaman Setting (`UpdateAppSetting`) | `PengaturanAplikasi` (lokasi kampus pusat) | `MasterDataActionTest`, `MasterResourceTest` |
| `UserResource` (SA) | `app/Filament/Admin/Resources/Users` + action `CreateUser`, `UpdateUser`, `DeactivateUser`, `ActivateUser`, `ResetUserPassword` | `UserActionTest` (18 kasus), `UserResourceTest` (17 kasus) |
| Reset password | Password sementara, wajib ganti, reset penghitung gagal login, semua sesi diakhiri, password tidak masuk audit | `UserActionTest` FR-AUTH-03 |
| Nonaktif dengan pencabutan sesi | Baris `sessions` dihapus + `remember_token` dirotasi (`SessionRevoker`); sesi yang tersisa ditolak panel (403) | `UserActionTest` FR-USR-02, `UserResourceTest` AC-AUTH-03 |
| Ganti password sendiri (sisa Fase 1) | `App\Filament\Auth\EditProfile` + `ChangeOwnPassword`; menghapus `wajib_ganti_password`; sesi saat ini tetap valid | `UserResourceTest` FR-AUTH-03 (HTTP), Playwright |
| Audit | `master.create/update/activate/deactivate/delete`, `setting.update`, `user.create/update/activate/deactivate/reset_password/password_change`; `login_gagal` mencatat `alasan: akun_nonaktif` | Semua test di atas memeriksa entri audit |

Exit criteria:
- **AC-USR-01 lulus**: AP dan AC mendapat 403 di halaman pengguna, langsung di komponen maupun di domain action.
- **AC-USR-02 lulus**: AP, AC, dan mentor mendapat 403 di 5 halaman master dan halaman Setting.
- **AC-USR-03 lulus**: laporan mentor yang dinonaktifkan tetap tercatat atas namanya.
- **AC-AUTH-03 lulus**: login akun nonaktif ditolak dan diaudit, riwayat lama tetap ada, dan sesi yang masih berjalan kehilangan akses.
- **FR-MST-02 terverifikasi.**
- **Audit tercatat.**

Tambahan dari keputusan 23 Nov 2026:
- **Butir 5:** IT-02..IT-13 kini juga diuji di panel kedua, yaitu blok `second panel` di `SP06IdleTimeoutTest` (11 test). IT-01/IT-07 sudah mencakup kedua panel. Dengan ini IT-01..IT-13 terverifikasi di kedua panel pada level HTTP.
- **Butir 1 dan 2:** `AGENTS.md`/`CLAUDE.md` diganti dengan instruksi proyek. Prosedur sinkronisasi Composer ada di sana, ditambah `scripts/check-vendor-sync.sh`.

### Angka pengujian

- **Pest:** 217 passed, 0 failed (1.018 assertions). Sebelumnya 120.
- **Playwright:** 4 passed (2 × SP-04 mobile 360, 2 × Fase 2 desktop).
- **Larastan level 5:** 0 error. **Pint:** bersih.

### Perbaikan selama fase

1. **Test menghapus database dev.** Sebelum fase ini, `DB_DATABASE` dari env container (`$_SERVER`) mengalahkan `<env force>` di `phpunit.xml`.
   - Perbaikan: `<server name="DB_DATABASE" force="true">`.
   - Pengaman: `tests/TestCase.php` menolak menjalankan test pada database yang namanya tidak berakhiran `_test`.
2. **Batas memori test.** Proses test kehabisan memori 128 MB karena manifest ikon membesar seiring jumlah test dalam satu proses. Perbaikan: `memory_limit=512M` khusus test di `phpunit.xml`.
3. **Flag wajib ganti password tidak pernah dihapus.** Halaman profil bawaan tidak menghapus `wajib_ganti_password`, sehingga akun baru akan terkunci di halaman profil. Perbaikan: halaman profil kustom.
4. **Kode BPS factory bentrok.** Kode kabupaten di factory bisa bentrok dengan kode hasil seed. Factory sekarang memakai `99xx`.

### Asumsi baru (perlu konfirmasi)

| ID | Asumsi | Alasan (paling aman) |
|---|---|---|
| F2-A01 | Tabel `kabupaten_kota` diberi kolom `aktif`. PRD 16.10 tidak mencantumkannya, tetapi FR-MST-02 berlaku untuk seluruh data master | Tanpa kolom ini, kabupaten yang terpakai tidak bisa dinonaktifkan maupun dihapus |
| F2-A02 | Data master yang belum pernah dipakai boleh dihapus SA | FR-MST-02 hanya melarang penghapusan data terpakai |
| F2-A03 | SA tidak dapat mengubah role, menonaktifkan, atau me-reset password **akunnya sendiri** lewat menu Pengguna. Password sendiri diganti lewat Profil | Mencegah sistem terkunci tanpa Superadmin aktif |
| F2-A04 | Mentor yang masih punya penugasan aktif **ditolak** saat dinonaktifkan atau diganti role-nya sampai ditugaskan ulang. Modal menampilkan peringatan dan jumlah sekolahnya | PRD E4: "peringatan dan paksa penugasan ulang sebelum nonaktif". Action penugasan dibuat di Fase 3, jadi sampai saat itu penugasan diakhiri lewat Fase 3 |
| F2-A05 | Pengguna tidak dapat mengubah nama/email sendiri di Profil, hanya password | BR-USR-01: hanya SA yang mengubah akun |
| F2-A06 | Perubahan role, atau perubahan cabang Admin Cabang, mengakhiri semua sesi pengguna itu | Hak akses baru berlaku segera |
| F2-A07 | `cabang_id` mentor opsional (informasi saja), tidak memengaruhi scope; untuk SA/AP selalu kosong | PRD 16.4 |
| F2-A08 | Mengaktifkan kembali Admin Cabang ditolak bila cabangnya nonaktif | Mencegah AC aktif tanpa cabang aktif |

### Masih terbuka

- **Lockout login (TD-09)** belum diimplementasikan. Catatan risiko: Filament membatasi login 5 percobaan per menit **per IP**. Di kantor atau kampus dengan satu IP publik bersama, beberapa pengguna yang login bersamaan bisa ikut tertahan. Ini perlu diputuskan bersama TD-09.
- **Pesan login gagal** masih bawaan Filament ("Kredensial yang diberikan tidak dapat ditemukan."), sedangkan AC-AUTH-02 meminta "Email/username atau password salah". Ini sisa Fase 1.
- **Sisa Fase 1** yang belum dikerjakan: CI pipeline, environment staging dan backup harian (TD-06), serta navigasi per role yang lengkap.
- **SP-04:** uji perangkat fisik dan tinjauan pengguna (sebelum MVP dinyatakan siap). **SP-07:** saat fase kalender. **LS-05:** saat ada komponen `#[Locked]`.

## Fase 3 — Sekolah, Narahubung & Mentor (T-12, T-12a, T-13, T-14) — 23 Nov 2026

### Keputusan pemilik produk (23 Nov 2026)

- **D-01 Label MOU.** Setiap sekolah diberi label "Sudah MOU" atau "Belum MOU".
- **D-02 Status pelatihan keseluruhan.** Statusnya diturunkan dari data dan hanya untuk tampilan; tidak disimpan atau diinput.
  - *Belum dimulai:* belum ada kegiatan (awal maupun TL) yang Sedang Berlangsung atau Selesai.
  - *Sedang berlangsung:* sudah ada kegiatan yang berjalan atau selesai, tetapi program belum selesai.
  - *Sudah selesai:* posisi tahap Selesai Program (PRD 15.3).
  - Label "Sudah Berjalan / Hampir Selesai / Selesai" tidak dipakai.
- **D-03 MOU dicatat manual oleh Admin Pusat.** Status "Sudah MOU" bergantung pada apakah admin sudah memasukkan dokumen MOU.
  - Ability baru `sekolah.mou` hanya untuk Admin Pusat (ALL). SA, AC dan M tidak punya akses.
  - Ability ini ditambahkan ke `AccessConfig` dan ke golden matrix `PermissionMatrixTest` dengan komentar sumber keputusan.

### Hasil

- **MOU.** Migrasi `2026_11_23_000002_add_mou_to_sekolah` menambah kolom berikut:
  - `mou_dokumen_url` (CHECK `^https?://`);
  - `mou_tanggal`, `mou_dicatat_oleh`, `mou_dicatat_at`.

  Action `RecordSekolahMou` punya dua operasi, keduanya diaudit:
  - `handle`: mencatat atau memperbarui MOU;
  - `revoke`: membatalkan MOU, wajib dengan alasan.
- **Status turunan.**
  - Enum `StatusPelatihan` (D-02) dan `StatusSertifikat` (PRD 15.4; "Belum Berlaku" ditampilkan sebagai "Belum Tersedia").
  - Progres TL "x/y sesi selesai" (PRD 15.5).
  - Hitungan diambil lewat `withCount` (`Sekolah::ringkasanCounts()`); scope `whereStatusPelatihan` dipakai untuk filter.
- **Domain actions.**
  - `ArchiveSekolah`: wajib alasan; ditolak jika masih ada kegiatan terbuka.
  - `CorrectSekolahCabang`: hanya SA; hanya jika sekolah belum punya kegiatan apa pun; wajib alasan; mencatat `sekolah_cabang_koreksi` dan audit.
  - `ManageNarahubung`: add, update, deactivate, setUtama. Selalu ada tepat satu narahubung utama aktif. Setiap perubahan menulis `narahubung_riwayat` dan memakai optimistic lock.
  - `ProposeNarahubungChange` dan `DecideNarahubungProposal`: persetujuan diterapkan lewat `ManageNarahubung`, dan riwayat menyimpan `usulan_id`; penolakan wajib alasan.
  - `AssignMentor`: SA dan AP; penugasan lama ditutup dengan alasan; mentor boleh lintas cabang.
  - `RegisterSekolah` kini juga menulis riwayat narahubung pertama dan memvalidasi format nomor WA.
- **Event dan notifikasi.**
  - Event `UsulanNarahubungDiajukan`, `UsulanNarahubungDiputuskan` dan `MentorDitugaskan` dikirim setelah commit.
  - Listener `SendSekolahNotifications` mengirim notifikasi database Filament ke AP dan AC cabang (usulan), ke pengusul (keputusan), dan ke mentor (penugasan). Aktor tidak menerima notifikasi atas tindakannya sendiri.
- **Panel admin — Daftar Sekolah Mitra** (grup navigasi "Sekolah Mitra").
  - Kolom: No, nama + kabupaten, jenjang, cabang, MOU, status pelatihan, posisi tahap + progres TL, penanggung jawab, narahubung (jabatan, WA dengan tautan wa.me), sertifikat Awal/TL.
  - Filter (AND): cabang, kabupaten, jenjang, posisi, status pelatihan, mentor, kategori, MOU, sertifikat, arsip.
  - Jumlah query per halaman tetap (diuji: N+1 terdeteksi lewat uji mutasi).
- **Panel admin — Detail Sekolah.**
  - Tab: Ringkasan, Penugasan Mentor, Progres & Catatan, Riwayat (narahubung dan koreksi cabang), Sertifikat.
  - Relation manager: Narahubung, Pelatihan Awal, Tindak Lanjut. Kegiatan dipisah per tahap (AC-SCH-09).
  - Header action: Tetapkan/Ganti Mentor, Edit; di grup "Lainnya": Catat/Perbarui MOU, Batalkan MOU, Koreksi Cabang, Arsipkan. Setiap action tampil sesuai policy.
- **Antrean usulan.** `UsulanNarahubungResource` (`/admin/usulan-narahubung`):
  - badge jumlah usulan terbuka; aksi Setujui dan Tolak;
  - ber-scope `narahubung.decide`, sehingga AC hanya melihat cabangnya dan SA tidak punya akses.
- **Panel mentor.** Menu "Sekolah":
  - default filter "Sekolah Saya"; filter bisa dikosongkan untuk melihat semua sekolah (A-01);
  - detail memakai infolist yang sama, plus action "Usulkan Perubahan Narahubung" (hanya untuk sekolah yang ditugaskan);
  - tab Progres & Catatan dan tautan sertifikat hanya tampil untuk sekolah yang ditugaskan.

### Angka pengujian

- Pest: 269 lulus (1286 assertion). Termasuk `SekolahActionTest`, `SekolahResourceTest` dan `SekolahRegistrationTest`, dengan nama test memuat ID AC-SCH.
- Playwright: 5 lulus.
  - Spec baru `fase3-narahubung`: mentor mengusulkan narahubung, AP menyetujui, narahubung tampil di detail sekolah.
  - Spec ini menunggu 61 detik karena rate limit login 5 per menit per IP.
- AC-SCH-06: query daftar sekolah (scope, filter status, eager load, paginasi 25) pada 5.002 sekolah berjalan 124–347 ms di container dev. Data diukur di dalam transaksi yang di-rollback.
- Larastan level 5: 0 error. Pint: bersih.

### Asumsi baru (perlu konfirmasi)

| ID | Asumsi | Alasan |
|---|---|---|
| F3-A01 | Dokumen MOU disimpan sebagai tautan (http/https), sama seperti sertifikat; tanggal MOU opsional | Tidak ada fitur unggah berkas di MVP |
| F3-A02 | Tautan dokumen MOU terlihat oleh role admin yang berhak melihat sekolah; mentor hanya melihat status Sudah/Belum MOU | Paling sedikit kewenangan |
| F3-A03 | Arsip ditolak selama sekolah masih punya kegiatan Belum Dimulai/Sedang Berlangsung | Mengikuti K-09 v1.3: kegiatan diselesaikan atau dibatalkan dulu |
| F3-A04 | Pembatalan MOU wajib alasan, dan alasannya disimpan di audit | Jejak perubahan status |
| F3-A05 | Usulan perubahan untuk narahubung yang sudah nonaktif tidak bisa disetujui, hanya ditolak | Mencegah menghidupkan data nonaktif tanpa sengaja |
| F3-A06 | Nomor WA divalidasi longgar: angka, spasi, tanda hubung, awalan +, minimal 7 digit. Tautan wa.me mengubah awalan 0 menjadi 62 | Format nomor di lapangan beragam |
| F3-A07 | Penggantian mentor tanpa alasan dicatat dengan keterangan "Diganti" | Alasan tidak diwajibkan PRD |
| F3-A08 | AC-SCH-17 ("ditolak 403") dipenuhi dengan 404 dari query ber-scope | Sesuai arsitektur (Implementation Plan 4.5): record di luar scope tidak boleh terkonfirmasi ada |

### Masih terbuka / di luar fase ini

- **Dashboard dan klasemen** (UI brief bagian Dashboard) menunggu Fase 8.
- **Menu Jadwal** (Awal, TL, Kalender) dan halaman jadwal lintas sekolah (AC read-only) menunggu Fase 4.
- **Pengelolaan tautan sertifikat** menunggu Fase 6.
- **Kontak awal** (`kontak_awal.record`) belum punya UI.
- **Item terbuka dari Fase 2** tetap berlaku: TD-09 lockout, AC-AUTH-02, CI/staging/backup, SP-04 di perangkat fisik, SP-07, LS-05.

## Tindak lanjut Fase 1/2: kebijakan login (TD-09, AC-AUTH-02)

Keputusan pemilik: F2-A01..A03 disetujui (status aktif Kab/Kota; SA tidak mengubah role/menonaktifkan/mereset akunnya sendiri; password hanya lewat Profil). F2-A04..A08 **belum disetujui**, menunggu keputusan setelah detailnya dibaca.

Diimplementasikan (`app/Support/Auth/LoginThrottle.php`, `app/Filament/Auth/Login.php`, dipakai kedua panel):

| Lapisan | Aturan |
|---|---|
| Per akun (semua IP) | 5 kegagalan berturut-turut mengunci akun 15 menit (`gagal_login`, `dikunci_sampai`); password benar pun ditolak selama terkunci; kunci kedaluwarsa memulai hitungan baru; login sukses mereset |
| Per akun + IP | 5 kegagalan / 15 menit; berlaku juga untuk email yang tidak ada, dengan respons identik (tidak membocorkan keberadaan akun) |
| Per IP | 50 kegagalan / 15 menit untuk menahan tebakan massal; cukup longgar agar banyak staf di satu IP publik tidak saling terblokir |

- Throttle bawaan Filament (5/menit/IP) dimatikan; hanya kegagalan yang dihitung, login sukses tidak memakai jatah.
- Pesan gagal: "Email/username atau password salah." (sama untuk akun ada/tidak). Percobaan gagal diaudit `login_gagal`; percobaan yang diblokir diaudit `login_diblokir`.
- Konsekuensi yang diterima: pihak lain dapat mengunci akun korban 15 menit dengan 5 tebakan (inheren pada TD-09). Pemulihan: tunggu 15 menit atau SA mereset password.
- Spec E2E Fase 3 tidak lagi menunggu 61 detik.
- Tests: `tests/Feature/Auth/LoginThrottleTest.php` (10 test, termasuk pengguna dengan IP sama tidak saling terblokir).

CI (`.github/workflows/ci.yml`), skrip backup (`scripts/backup-db.sh`) dan catatan staging (`docs/ops/STAGING_DAN_BACKUP.md`) dibuat; staging belum ada (butuh keputusan host/domain/kredensial), CI belum pernah dijalankan di GitHub.
