# Staging, backup, dan CI (sisa Fase 1, TD-06)

Dokumen ini menyiapkan berkas yang bisa dibuat di repositori. Penyediaan server, domain, dan kredensial
adalah keputusan operasional pemilik produk; tidak ada yang disentuh di production.

## CI
`.github/workflows/ci.yml`: Pint, Larastan, Pest (job `backend`), lalu Playwright (job `e2e`). Postgres 17 sebagai service.
Belum pernah dijalankan di GitHub (belum ada remote); jalankan setelah push pertama dan perbaiki bila perlu.

## Staging (perlu keputusan)
Belum dibuat. Diperlukan: host, domain, `APP_KEY`, kredensial DB, dan akun SMTP. Persyaratan yang sudah diketahui:
PHP 8.4 + `pdo_pgsql`, PostgreSQL 17, `APP_ENV=staging`, `APP_DEBUG=false`, session driver `database`, `php artisan migrate --force`,
`npm run build`. Data staging memakai seeder demo, bukan salinan production.

## Backup harian
`scripts/backup-db.sh` (pg_dump format custom, retensi 30 hari, enkripsi GPG opsional, verifikasi `pg_restore --list`).
Pasang di cron server, kredensial lewat variabel `PG*` dari berkas env milik root. Uji pemulihan secara berkala:
`pg_restore --dbname=<db_kosong> sm-<stempel>.dump`. Belum terpasang di server mana pun.
