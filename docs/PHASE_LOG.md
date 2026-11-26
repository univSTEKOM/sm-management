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

## Fase 4: Penjadwalan (T-15, T-16, T-17)

### Audit sebelum implementasi
Sudah ada dari fase sebelumnya dan tidak diulang: tabel `kegiatan` (indeks unik satu pelatihan awal aktif per sekolah, satu penanda sesi terakhir), `RescheduleKegiatan` + riwayat versi + optimistic lock, `KegiatanPolicy`, `ScopeResolver::applyToKegiatanQuery`, ability `awal.create`, `tl.create`, `tl.create_after_last`, `kegiatan.assign_pelaksana` di `AccessConfig`, `ProgressRecalculator`, Relation Manager Awal/TL, halaman mentor "Kegiatan Saya". Yang belum ada: action pembuatan jadwal Awal dan sesi TL, Tunjuk Pelaksana, ubah nama sesi, notifikasi jadwal, halaman Jadwal lintas sekolah. Listener `JadwalDiubah` ternyata belum ada, sehingga BR-ACT-07 belum terpenuhi; dilengkapi di fase ini.

### Hasil
- Domain (`app/Domain/Jadwal/Actions`): `CreateJadwalAwal`, `CreateSesiTindakLanjut`, `AssignPelaksanaKegiatan`, `UpdateKegiatanDetail`, validasi bersama `KegiatanRules`. Matriks permission (`AccessConfig`) tidak diubah; ditambah ability Policy yang memetakan ke ability yang sudah ada (`createAwal`, `createTl`, `createTlAfterLast`, `assignPelaksanaBaru`, `updateDetail`, `assignPelaksana`).
- Notifikasi (`SendSekolahNotifications`): jadwal baru (pelaksana, AP, AC cabang), jadwal diubah (oleh mentor ke admin; oleh admin ke pelaksana), pelaksana ditunjuk. Pelaku tidak menerima notifikasinya sendiri.
- UI admin: halaman **Jadwal** lintas sekolah (tab Pelatihan Awal / Tindak Lanjut, filter status, mentor, cabang, rentang tanggal, tanpa aksi); di detail sekolah: Jadwalkan Pelatihan Awal, Tambah Sesi TL, Tunjuk Pelaksana, Ubah Detail, Riwayat Jadwal, ringkasan "Online x · Offline y (acuan juklak 4+1)".
- UI mentor: Tambah Sesi Tindak Lanjut di detail sekolah (mentor penanggung jawab saja); jadwal ulang tetap dari Kegiatan Saya.
- Test (akhir, setelah pemeriksaan tambahan): Pest 360 lulus (1771 assertion), Playwright 6 lulus, Larastan 0 error, Pint bersih.
- Status pelatihan sekolah memakai progres kegiatan yang sama (`StatusPelatihan::derive`), diuji dari jadwal dibuat sampai sesi berlangsung.

### Konflik yang perlu keputusan pemilik
**K4-01 (DISETUJUI pemilik, 24 Nov 2026).** Halaman Jadwal lintas sekolah read-only untuk semua role; Admin Cabang tetap membuat dan menjadwal ulang di sekolah cabangnya sendiri sesuai matriks, tidak di cabang lain; pengelolaan lewat detail sekolah. Uraian awal konflik:

**K4-01. "Admin Cabang read-only" vs PRD.** Arahan Fase 4 meminta Admin Cabang melihat jadwal lintas sekolah read-only. PRD 13.4/13.5 dan `AccessConfig` memberi Admin Cabang hak membuat dan menjadwal ulang di cabangnya (`awal.create`, `tl.create`, `kegiatan.reschedule`). Matriks tidak diubah. Penyelesaian sementara: halaman Jadwal lintas sekolah read-only untuk **semua** role, sedangkan membuat dan menjadwal ulang dilakukan dari detail sekolah sesuai matriks (AC tetap bisa di cabangnya). Bila pemilik ingin AC benar-benar tanpa hak tulis, itu perubahan matriks (`awal.create`, `tl.create`, `kegiatan.reschedule`, `kegiatan.delete` untuk `admin_cabang` menjadi NONE) dan perlu keputusan.

### Asumsi baru
| ID | Asumsi | Dasar |
|---|---|---|
| F4-A01 | Lokasi pelatihan awal diambil dari pengaturan "lokasi kampus pusat"; bila kosong, jadwal ditolak dengan pesan. Detail ruang opsional ditambahkan setelah tanda "—" | FR-JDA-03 |
| F4-A02 | Penambahan sesi TL ditolak setelah rangkaian "Selesai Program" sampai AP membukanya kembali | BR-TL-09 |
| F4-A03 | Penanda sesi terakhir yang sesinya Dibatalkan dianggap tidak berlaku, sehingga mentor boleh menambah sesi | BR-TL-12 |
| F4-A04 | Setelah sesi terakhir berlaku ditetapkan, hanya AP yang menambah sesi (SA dan AC juga ditolak, mengikuti `tl.create_after_last`) | Matriks |
| F4-A05 | Mentor tidak mengubah nama sesi, jenis, atau keterangan; hanya jadwal (tanggal, waktu, lokasi, tautan) | C-04 |
| F4-A06 | Nomor urut tidak dipakai ulang: sesi Dibatalkan tetap memegang nomornya, sesi baru = nomor terbesar + 1 | AC-JTL-02, AC-JTL-16 |
| F4-A07 | Mentor pelaksana default = mentor penanggung jawab; hanya SA/AP yang boleh menunjuk mentor lain, dan hanya untuk kegiatan yang belum Selesai/Dibatalkan | `kegiatan.assign_pelaksana` |
| F4-A08 | Tanggal jadwal boleh di masa lalu (pencatatan susulan) | PRD tidak melarang |

### Belum dikerjakan di fase ini
- ~~Kalender (T-18)~~: dikerjakan setelah spike SP-07, lihat bagian "Kalender jadwal" di bawah.
- Hapus kegiatan (`kegiatan.delete`, BR-ACT-09/E10), sesi terakhir, buka kembali rangkaian, dan notifikasi sertifikat: Fase 5.
- Jadwal Koordinasi mentor: Fase 5.

### Pemeriksaan tambahan Fase 4 (24 Nov 2026)
Dua cacat ditemukan dan diperbaiki: (1) `CreateSesiTindakLanjut` tidak menghitung ulang posisi sekolah, sehingga sekolah tetap "Mitra – Belum Tindak Lanjut" setelah sesi pertama dibuat (sekarang memanggil `ProgressRecalculator`); (2) tabel riwayat belum append-only di database walau `AGENTS.md` menyatakannya; migrasi `2026_11_24_000001` memasang trigger pada `kegiatan_jadwal_riwayat`, `laporan_revisi`, `rangkaian_tl_riwayat`, `kontak_awal`, `narahubung_riwayat`, `sekolah_cabang_koreksi`. Aksi pembuatan kini mengembalikan model yang sudah di-refresh (kolom `version` terisi).

| Pemeriksaan | Bukti |
|---|---|
| Nomor urut aman dari request bersamaan | `scripts/concurrency-check.php`: 8 proses paralel pada satu sekolah menghasilkan nomor 1..8 tanpa duplikat dan tanpa error. Uji mutasi: tanpa `lockForUpdate` hanya 1 sesi berhasil dan sisanya gagal karena indeks unik (jadi database tetap mencegah duplikat, kunci baris membuat semuanya berhasil berurutan). Test Pest memastikan kunci baris diambil sebelum `max(urutan)` dan indeks unik menolak duplikat |
| Optimistic lock | Proses paralel: 6 simpan dari versi yang sama, 1 menang dan 5 ditolak "Data telah diubah pengguna lain"; 6 jadwal ulang paralel menghasilkan tepat 1 baris riwayat. Test: simpan basi tidak menimpa, versi lama ditolak oleh aksi lain, form tabel mengirim versi yang dimuat |
| Notifikasi tidak ganda, pelaku tidak menerima | Tiap penerima tepat 1 untuk jadwal baru dan jadwal diubah, juga bila aksi berjalan dalam transaksi luar; aksi yang gagal tidak mengirim apa pun; `send()` kini juga membuang penerima ganda |
| Isolasi Admin Cabang lewat request langsung | Seluruh aksi (buat Awal, buat sesi, jadwal ulang, ubah detail, tunjuk pelaksana) ditolak untuk cabang lain; HTTP: 404 untuk halaman sekolah, daftar Jadwal tidak memuat nama sekolah cabang lain; panggilan komponen yang dipalsukan tidak menulis apa pun |
| Status sekolah | Dijadwalkan, dijadwal ulang, berjalan, selesai, dan dibatalkan diuji lewat `ReportKegiatan` dan `ProgressRecalculator` (posisi, tanggal mitra, sertifikat Awal, kembali ke Belum TL saat sesi satu-satunya dibatalkan) |
| Sesi terakhir | Penetapan sesi terakhir (aksi AP) adalah Fase 5 dan belum dibuat. Aturan penyelesaian yang bergantung padanya diuji: sesi lain belum selesai, sesi terakhir sendiri belum selesai, tanpa penanda (E22), sesi Dibatalkan non-terakhir (AC-JTL-14), sesi terakhir dibatalkan (AC-JTL-15); database menolak penanda kedua dan penanda pada pelatihan awal |
| Riwayat jadwal | Versi berurutan dengan nilai sebelum/sesudah, pelaku, alasan; aksi yang ditolak tidak meninggalkan riwayat; UPDATE/DELETE langsung ke tabel riwayat ditolak trigger; kegiatan berriwayat tidak dapat dihapus; riwayat bertahan setelah kegiatan dibatalkan |

Catatan batas: ketiga skenario paralel memakai proses PHP nyata pada database sementara (`sm_concurrency_check`, dibuat dan dihapus skrip), bukan bagian suite Pest karena Pest berjalan dalam satu transaksi. Skrip dijalankan manual.


### Kalender jadwal (T-18, TD-13, SP-07) — 24 Nov 2026
**Keputusan TD-13: `saade/filament-fullcalendar` ^4.0 (v4.0.1, FullCalendar 6.1.22).** Disetujui pemilik produk. Cadangan ^5.0 (FullCalendar 7) tidak dipakai: hook DOM-nya ber-class hash dan perlu ditulis ulang, tanpa keuntungan untuk kebutuhan ini.

| Kriteria SP-07 | Bukti |
|---|---|
| Composer bersih di stack terkunci | `composer require` ^4.0 dan ^5.0 (dry-run) resolve tanpa konflik; ^4.0 dipasang di `composer.json` |
| Bulan, minggu, agenda; 360px | Playwright desktop dan 360px: tanpa scroll horizontal, ponsel membuka tampilan Agenda |
| Event lewat query ber-scope | `KalenderQuery` memakai `ScopeResolver::applyToKegiatanQuery(..., 'kegiatan.view')`; filter hanya mempersempit |
| Read-only | `onEventDrop`, `onEventResize`, `onDateSelect` dinetralkan; plugin hanya `dayGrid`, `timeGrid`, `list` (tanpa `interaction`); `editable` dan `selectable` false; `headerActions` dan `modalActions` kosong |
| Perubahan jadwal hanya lewat `RescheduleKegiatan` | Aksi "Jadwal Ulang" di widget; test memindai sumber widget (hanya `RescheduleKegiatan`, tanpa `save/update/create/DB::`) |
| Hanya fitur MIT | Plugin terdaftar dikunci test pada kedua panel; tanpa license key. Paket MIT, `@fullcalendar/core,daygrid,timegrid,list` MIT |
| Tanpa error konsol | Playwright memeriksa konsol di semua skenario |

**Risiko yang diketahui:** (1) dist paket v4 menyertakan kode plugin premium (tidak aktif kecuali didaftarkan; kami tidak mendaftarkannya, dikunci test). Bila kebijakan menuntut bundel tanpa kode premium, perlu widget sendiri di atas paket `@fullcalendar/*` MIT. (2) ^4 adalah lini lama; upgrade ke ^5 mungkin diperlukan kelak. (3) Package v4 punya method Livewire publik penulis (`onEventDrop`, `onEventResize`, `onDateSelect`); jangan dihapus override-nya saat upgrade paket.

**Yang dibuat:** `app/Domain/Jadwal/Support/KalenderQuery.php` (query ber-scope + filter), `KalenderEvent.php` (pemetaan event), `app/Filament/Widgets/KalenderJadwalWidget.php`, halaman `app/Filament/Admin/Pages/KalenderJadwal.php` (menu Jadwal > Kalender) dan `app/Filament/Mentor/Pages/KalenderJadwal.php`, view `resources/views/filament/widgets/kalender-*.blade.php`, `resources/css/kalender.css`. Plugin didaftarkan di kedua panel. `AccessConfig` dan domain action tidak diubah.

**Perilaku:** tampilan Bulan, Minggu, Agenda; blok event memuat sekolah, jam, jenis, mentor, dan label status; warna menurut jenis (Awal biru, Tindak Lanjut hijau), kegiatan dibatalkan tetap tampil abu-abu, bercoret, dan berlabel "Dibatalkan". Filter jenis, cabang (disembunyikan untuk Admin Cabang), mentor, status; nilai filter tak dikenal menghasilkan daftar kosong. Klik event membuka detail (lokasi dan tautan hanya bila policy `viewDetail` mengizinkan) dengan tombol "Jadwal Ulang" bila Policy mengizinkan.

**Asumsi baru (perlu keputusan):**
- **K-KAL-01:** Mentor melihat seluruh kegiatan (sesuai `kegiatan.view` mentor = koordinasi) dan hanya dapat menjadwal ulang miliknya (`kegiatan.reschedule` = ASSIGNED). Tampilan lintas sekolah tetap read-only. Tidak ada perubahan matriks.
- **K-KAL-02:** Event dibatasi 1000 per rentang tampilan. Tanggal dan jam berasal dari satu tanggal + jam mulai/selesai (kegiatan tidak lintas hari).
- **K-KAL-03:** Di layar < 640px kalender dibuka pada Agenda lewat skrip kecil di view (menunggu kalender siap, maksimal 5 detik).

**Test:** `tests/Feature/Jadwal/KalenderTest.php` (24 test: tanggal/jam, lintas bulan/tahun, filter tidak bocor lintas cabang, klik membuka detail yang benar dan 404 di luar scope, kegiatan batal tetap tampil, perubahan jadwal langsung terlihat, tidak ada jalur simpan selain `RescheduleKegiatan`) dan `tests/e2e/fase4-kalender.spec.ts` (4 skenario: tiga tampilan, filter, detail, isolasi Admin Cabang, jadwal ulang mentor, 360px).

### Tema merah (identitas visual) — 24 Nov 2026
Perubahan presentasi saja: tanpa perubahan logika bisnis, database, permission, policy, atau alur. Tanpa dependency baru.

- **Satu sumber token:** `app/Support/Theme/BrandPalette.php` (hex spesifikasi, skala `primary` dengan shade 600 = `#B91C1C`, skala `gray` = slate) dipakai kedua panel. `resources/css/tema.css` mencerminkan nilai yang sama sebagai CSS variable (`--brand-*`); test mengunci keduanya agar tidak bergeser. Warna merek tidak ditulis ulang di view atau kelas lain (dijaga test).
- **Override Filament terpusat** (`tema.css`, mode terang saja; mode gelap tetap bawaan Filament): sidebar putih dengan garis tepi, menu aktif berlatar soft red dengan bilah merah, topbar bersih, hover tabel halus, aksen merah tipis di kartu login.
- **Kalender:** Pelatihan Awal merah, Tindak Lanjut biru `#1E40AF` (hijau dihindari karena merah-hijau sulit dibedakan penderita buta warna), Dibatalkan abu-abu bercoret berlabel. Tombol toolbar putih, aktif merah penuh.
- **Status tidak dibuat merah.** Badge tanpa warna eksplisit sebelumnya mengikuti warna `primary`; kini diberi warna bermakna lewat method enum: `StatusKegiatan::color()` (abu/kuning/hijau/merah-hanya-untuk-Dibatalkan, pemetaan sama dengan halaman Jadwal), `Tahap::color()` (Awal merah, Tindak Lanjut biru, selaras kalender), `Jenjang::color()` abu, `PosisiTahap::color()` biru informatif. Badge status kegiatan di daftar kegiatan sekolah dan infolist sebelumnya tidak berwarna; kini berwarna sesuai status.
- **Kontras** dicek test (WCAG AA): putih di atas merah primer, merah di atas soft red, teks utama dan sekunder di atas latar dan permukaan, putih di atas tiga warna event.

**Temuan, tidak diubah:** (1) `danger` Filament tetap merah (bahaya/hapus/dibatalkan); karena `primary` kini juga merah, tombol destruktif dan tombol utama berdekatan hue. Pembeda: aksi destruktif umumnya berada di modal konfirmasi dan berlabel jelas. Bila mau dibedakan tegas, perlu keputusan desain. (2) Halaman mentor "Kegiatan Saya" memakai peta status sendiri (Sedang Berlangsung = biru) berbeda dari admin (kuning); dibiarkan karena sudah punya makna di UI mentor. (3) Dasbor dengan kartu statistik dan grafik belum ada (fase dasbor); palet siap dipakai lewat `primary` dan token `--brand-*`.

### Dasbor: klasemen kategori pelatihan (FR-RNK-01/03, FR-DSH-04) — 24 Nov 2026
Grafik batang horizontal "Klasemen Kategori Pelatihan" di dasbor panel admin dan mentor (kategori pelatihan yang paling banyak dipilih sekolah mitra, seluruh Jawa Tengah). Hanya membaca data; tidak ada perubahan skema, permission (`klasemen.view` sudah ALL untuk semua role), atau aturan bisnis.

- **Hitungan** (`app/Domain/Sekolah/Support/KlasemenKategori.php`): sekolah mitra unik per kategori (bukan jumlah sesi), hanya sekolah resmi mitra (`tanggal_mitra` terisi; calon mitra tidak dihitung), sekolah mitra yang diarsipkan tetap dihitung, kategori tanpa sekolah tampil 0, urut menurun, peringkat sama untuk jumlah sama (dense ranking). Selalu global, tidak mengikuti cabang atau penugasan (BR-RNK-03, AC-RNK-04/07).
- **Widget** `app/Filament/Widgets/KlasemenKategoriChart.php` (Chart.js bawaan Filament, tanpa dependency baru): batang horizontal, peringkat 1 merah tua dan lainnya merah merek, sumbu bilangan bulat, label berisi peringkat, nama (dibungkus), dan jumlah sehingga angka terbaca tanpa hover. Catatan kaki PRD "Satu sekolah dapat terhitung di lebih dari satu kategori." tampil di bawah judul. Didaftarkan di kedua panel.
- **Data demo:** `DemoSeeder` menambah tiga sekolah mitra (pelatihan awal selesai 120 hari lalu, di luar tampilan kalender) dengan kategori, dan satu kategori pada calon mitra untuk membuktikan ia tidak terhitung.

**Asumsi baru (perlu keputusan):**
- **K-DSH-01:** kategori nonaktif tetap muncul selama masih dipegang sekolah mitra; yang nonaktif dan kosong disembunyikan. PRD tidak mengatur kategori nonaktif.
- **K-DSH-02:** semua kategori ditampilkan (bukan hanya 6 teratas seperti FR-DSH-04) karena seed hanya 6 kategori. Bila kategori bertambah banyak, perlu batas tampilan atau tabel lengkap di halaman Klasemen.
- **K-DSH-03:** tabel peringkat dan filter periode (FR-RNK-04, P2) belum dibuat; yang diminta hanya grafik di dasbor.

**Tidak dikerjakan (bagian dasbor lain):** kartu KPI, grafik tahap sekolah, statistik sertifikat, perbandingan cabang, dan daftar kegiatan.

**Test:** `tests/Feature/Dashboard/KlasemenKategoriTest.php` (14 test: AC-RNK-01..07, BR-RNK-01..04, akses per role, struktur grafik) dan `tests/e2e/fase4-dashboard-klasemen.spec.ts` (3 skenario: grafik tergambar di canvas dengan data benar, Admin Cabang dan mentor melihat angka yang sama, 360px tanpa scroll horizontal).

### Dasbor Sekolah Mitra: tabel, bukan kartu (revisi UI, 24 Nov 2026)
Dasbor kedua panel kini `app/Filament/Pages/DashboardSekolahMitra.php` dengan urutan tetap: header, ringkasan statistik (`SekolahMitraStats`), klasemen kategori (`KlasemenKategoriChart`), daftar sekolah (`SekolahMitraTable`). Tampilan saja; tidak ada perubahan business logic, skema, atau permission. Widget `AccountWidget` dihapus dari dasbor (akun sudah ada di menu pengguna).

- **Tabel:** satu sekolah per baris, 11 kolom sesuai brief (Nama, Jenjang, Kab/Kota, Status program, Mentor PJ, Narahubung, Sertifikat, Status MOU, Progres TL, Update mentor, Aksi Detail). Pagination 10/25/50, pencarian nama, filter AND (kab/kota, jenjang, status program, mentor, MOU). Baris dan tombol Detail membuka halaman sekolah sesuai panel. Header menempel (sticky) di dalam kotak tabel setinggi maks 70vh; pada layar kecil tabel menggulir horizontal di dalam kotaknya sendiri, halaman tidak.
- **Scope:** `Sekolah::visibleTo(user)` yang sama dengan resource Sekolah (404/baris tak tampil di luar scope). Sekolah arsip disembunyikan; kolom info alamat/cabang tidak diulang karena ada di halaman detail.
- **Update mentor:** tanggal revisi laporan terbaru (`laporan_revisi`) dari seluruh kegiatan sekolah; "Belum ada" bila kosong.
- **Asumsi D-01:** "sekolah mitra" di tabel = semua sekolah dalam scope pengguna (mitra resmi dan calon mitra), dibedakan lewat kolom Status program; hitungan mitra resmi ada di kartu statistik. Perlu konfirmasi bila tabel hanya boleh memuat mitra resmi.
- **Asumsi D-02:** filter kategori pelatihan, cabang, sertifikat, dan arsip yang ada di daftar Sekolah belum dibawa ke dasbor; ada di menu Sekolah Mitra.

Test: `tests/Feature/Dashboard/DashboardSekolahMitraTest.php` (10) dan `tests/e2e/fase4-dashboard-sekolah.spec.ts` (3).
