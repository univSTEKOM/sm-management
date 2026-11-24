# Jadwal Sekolah Mantap Terpusat — Instruksi Proyek

Sistem penjadwalan kemitraan dan pelatihan sekolah (Jawa Tengah). Bahasa UI dan dokumen: Indonesia.

## Dokumen acuan (urutan prioritas)

1. `docs/PRD.md` (v1.3, disetujui) — aturan bisnis (BR-*), kebutuhan (FR-*), acceptance criteria (AC-*).
2. `docs/IMPLEMENTATION_PLAN.md` (v1.4) — keputusan teknis (TD-*), arsitektur, urutan fase.
3. `docs/SPIKE_REPORT.md` — hasil Fase 0, temuan teknis, asumsi yang perlu dikonfirmasi.
4. `docs/PHASE_LOG.md` — ringkasan hasil per fase dan asumsi baru.

Jangan mengubah aturan bisnis, matriks permission (`AccessConfig`), atau scope MVP tanpa keputusan pemilik produk.
Jangan memasang Laravel Boost. Jangan menyentuh production.

## Stack (terkunci)

PHP 8.4 · Laravel 13 · Filament 5 · Livewire 4 · Tailwind 4.1+ (Vite) · PostgreSQL 17 · Pest 4 · Playwright · Larastan level 5 · Pint.

## Arsitektur (wajib)

- Business logic di `app/Domain/<Modul>/Actions` (kelas `final` dengan `handle()`), bebas Filament/Livewire.
  Pola: muat objek ber-scope → `Gate::forUser($actor)->authorize()` → validasi → transaksi (data + riwayat + `AuditWriter`) → event.
- Filament hanya memanggil domain action (`RunsDomainAction`), tidak menyimpan model langsung.
- Otorisasi: matriks statis `app/Support/Authorization/AccessConfig.php` + `ScopeResolver` + Policy (deny-by-default).
  Record di luar scope diselesaikan lewat query ber-scope (404), bukan disembunyikan di UI saja.
- `audit_log` append-only (trigger PostgreSQL). Tabel riwayat juga append-only.
- Dua panel: `/admin` (SA, AP, AC) dan `/mentor` (M). Satu tabel `users`.

## Lingkungan dev (Docker)

WSL tidak punya `pdo_pgsql`; semua perintah PHP dijalankan di container.

```sh
docker compose up -d
docker compose exec -u 1000:1000 app php vendor/bin/pest            # test (DB: sm_management_test)
docker compose exec -u 1000:1000 app php vendor/bin/phpstan analyse --memory-limit=1G
docker compose exec -u 1000:1000 app php vendor/bin/pint
docker compose exec -u 1000:1000 app php artisan migrate:fresh --seed  # DB dev: sm_management
npm run build                                                       # tema Filament (host)
tests/e2e/run.sh                                                    # Playwright (reseed DB dev)
```

Server dev: `docker compose exec -d -u 1000:1000 -w /app/public app php -S 0.0.0.0:8000 /app/vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php`
(jangan `artisan serve`: ia membaca `.env` dan membuang env container). Akun demo di `database/seeders/DemoSeeder.php`, password `password`.

`.env` milik developer tidak boleh diubah tanpa memeriksa dampaknya; container memakai env di `compose.yaml`.
Test dipaksa ke database `*_test` (`phpunit.xml` + pengaman di `tests/TestCase.php`).

## Sinkronisasi Composer (dua salinan `vendor/`)

`vendor/` ada dua: Docker volume `vendor` (dipakai container, cepat) dan folder host `vendor/` (IDE, Vite plugin Filament).
`composer.lock` adalah satu-satunya sumber kebenaran. Setiap kali `composer.json`/`composer.lock` berubah:

1. Ubah dependency **hanya di container**: `docker compose exec -u 1000:1000 -e COMPOSER_HOME=/tmp/composer app composer require ...`
   (platform dikunci `php 8.4.0` di `composer.json`, jadi lock tetap valid untuk PHP 8.4).
2. Pasang ke salinan host dari lock yang sama: `composer install --ignore-platform-reqs` di host
   (jangan `composer update` di host; PHP host 8.5 tanpa `pdo_pgsql`/`zip`).
3. Verifikasi keduanya sama: `scripts/check-vendor-sync.sh` (membandingkan daftar paket terpasang host vs container),
   lalu jalankan Pest.

Jika container dibuat ulang dan volume `vendor` kosong: `docker compose exec -u 1000:1000 app composer install`.

## Aturan kerja

- Kerjakan per fase sesuai Implementation Plan Bagian 8; setiap fitur punya test Pest/Playwright; jalankan test setelah tiap bagian.
- Nama test memuat ID AC/BR terkait.
- Commit setelah satu fase selesai dan seluruh test lulus.
- Keputusan yang belum ditetapkan: pakai asumsi paling aman (paling sedikit kewenangan), catat di `docs/PHASE_LOG.md`.
