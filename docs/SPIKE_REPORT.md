# Laporan Spike Fase 0 (SP-01 s.d. SP-06)

Tanggal: 23 November 2026 · Acuan: `docs/IMPLEMENTATION_PLAN.md` v1.4 Bagian 8

## Ringkasan

| Spike | Hasil | Bukti |
|---|---|---|
| SP-01 Kombinasi versi | ✔ Lulus | PHP 8.4.28, Laravel 13.43.0, Filament 5.12.0, Livewire 4.4.9, Tailwind 4.3.5 (memenuhi ≥ 4.1) dengan Vite 8.3.8, PostgreSQL 17.12, Pest 4.7.8, Playwright 1.65.1. Composer tanpa konflik, tema Tailwind dua panel ter-build, seluruh migrasi berjalan di PostgreSQL 17 |
| SP-02 Dua panel, satu tabel users | ✔ Lulus | `tests/Feature/Spike/SP02PanelAccessTest.php`: 15 test |
| SP-03 Tidak ada kebocoran lintas cabang | ✔ Lulus | `tests/Feature/Spike/SP03ScopeLeakTest.php`: 19 test (sentinel di HTML, akses URL lintas cabang, pencarian/filter, dropdown, relation manager, manipulasi ID via komponen dan endpoint HTTP Livewire, injeksi `cabang_id`), ditambah `tests/Feature/Authorization/PermissionMatrixTest.php`: 48 test |
| SP-04 Halaman mentor nyaman di ponsel | ✔ Lulus (otomatis) | `tests/e2e/sp04-mentor-mobile.spec.ts` di viewport 360×800: tanpa scroll horizontal, target sentuh ≥ 44px, ubah status dalam 3 ketukan + isi catatan, reschedule tersimpan, data mentor lain tidak tampil. Tangkapan layar: `storage/e2e/*.png`. **Belum diuji di ponsel fisik** dan belum ditinjau perwakilan pengguna |
| SP-05 Domain action tanpa Filament/Livewire | ✔ Lulus | `tests/Feature/Spike/SP05DomainActionTest.php`: 12 test; `tests/Arch/ArchitectureTest.php`: 5 test |
| SP-06 Idle timeout dengan polling | ✔ Lulus | `tests/Feature/Spike/SP06IdleTimeoutTest.php`: 13 test (IT-01..IT-13 kecuali IT-07 digabung dengan IT-01 untuk kedua panel), di level HTTP terhadap endpoint Livewire |
| SP-07 Kalender | Belum dijalankan | Di luar cakupan tahap ini |

Tambahan: `tests/Feature/Spike/AuditImmutabilityTest.php` (8 test) membuktikan audit log ditolak untuk diubah/dihapus lewat model, query builder, SQL mentah, dan TRUNCATE (trigger PostgreSQL).

Total: **Pest 120 passed, 0 failed (537 assertions)** · **Playwright 2 passed** · **Larastan level 5: 0 error** · **Pint: bersih**.

## Temuan teknis selama spike

| # | Temuan | Penanganan |
|---|---|---|
| 1 | Snapshot Livewire 4 memakai nama kelas komponen (`Filament\Livewire\DatabaseNotifications`), bukan alias. Klasifikasi awal akan menganggap polling sebagai aktivitas, sehingga idle timeout tidak pernah terpicu | `ActivityClassifier` memakai nama kelas; dikunci oleh IT-03/IT-13 |
| 2 | Livewire melewati persistent middleware untuk rute yang baru diproses sampai state di-flush. Di PHP-FPM setiap request adalah proses baru (tidak berdampak di production); di test yang memakai satu instance aplikasi, ini menyembunyikan middleware | Helper test memanggil `Livewire::flushState()` per request. **Catatan:** bila kelak memakai Laravel Octane, perilaku ini wajib diverifikasi ulang |
| 3 | Kolom `notifications.data` harus JSON di PostgreSQL agar query Filament berjalan | Migrasi memakai `json` |
| 4 | Akses file dari container ke drive Windows sangat lambat (boot 22 detik) | `vendor/` di container memakai Docker volume Linux (boot 1,8 detik) |
| 5 | `artisan serve` membuang environment variable container dan membaca `.env` | Server dev memakai built-in server PHP langsung |
| 6 | Chrome memaksa HTTPS untuk hostname `app` (TLD `.app` ada di daftar HSTS preload) | E2E memakai nama container |
| 7 | Browser Playwright butuh library sistem yang tidak ada di WSL (perlu sudo) | E2E dijalankan di image resmi Playwright (`tests/e2e/run.sh`) |
| 8 | Query builder melewati proteksi model audit | Trigger PostgreSQL append-only pada `audit_log` |

## Asumsi yang dipakai (perlu konfirmasi)

- C-01: tambah sesi Tindak Lanjut hanya oleh mentor penanggung jawab.
- C-04: mentor hanya mengubah tanggal, waktu, lokasi, tautan; admin juga metode.
- TD-09: wajib ganti password saat login pertama diaktifkan; lockout belum diimplementasikan.
- Data E2E: akun demo berpassword `password`, hanya dibuat di environment `local`/`testing`.

## Cara menjalankan

```sh
docker compose up -d                                  # PHP 8.4 + PostgreSQL 17 (dev only)
docker compose exec -u 1000:1000 app php vendor/bin/pest
npm run build                                         # tema Tailwind dua panel
tests/e2e/run.sh                                      # Playwright (reseed DB lokal)
```
