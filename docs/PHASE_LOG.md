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
