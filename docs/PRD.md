# Product Requirements Document
## Jadwal Sekolah Mantap Terpusat

| Atribut | Nilai |
|---|---|
| Versi dokumen | 1.3 (Seluruh PD bisnis ditutup) |
| Tanggal | 22 November 2026 |
| Status | Siap dipakai sebagai acuan desain dan pengembangan. Tersisa asumsi (27.3), rekomendasi (27.4), dan PD teknis (27.5) |
| Jenis produk | Web-based School Partnership & Training Schedule Management System |
| Pengguna | Internal panitia pelaksana (tanpa akun sekolah) |

**Konvensi penandaan dalam dokumen ini**

| Tanda | Arti |
|---|---|
| **[KEPUTUSAN]** | Sudah dikonfirmasi pemilik bisnis. Mengikat. |
| **[REKOMENDASI]** | Usulan analis. Belum mengikat. Boleh diterapkan sementara, tetapi harus mudah diubah. |
| **[ASUMSI]** | Asumsi sementara dari pemilik bisnis. Dipakai untuk desain, belum final, harus mudah diubah. |
| **[PD-xx]** | Merujuk ke Pending Business Decision nomor xx di Bagian 27. |

Kode role: **SA** = Superadmin, **AP** = Admin Pusat, **AC** = Admin Cabang, **M** = Mentor.

---

## 1. Executive Summary

Jadwal Sekolah Mantap Terpusat adalah aplikasi web internal untuk mengelola seluruh siklus kemitraan sekolah program Sekolah Mantap di Jawa Tengah. Siklusnya dimulai dari pendaftaran sekolah, pelatihan awal di kampus pusat, lalu rangkaian tindak lanjut, sampai penerbitan sertifikat.

Sistem memisahkan dua proses yang selama ini rawan tercampur:

1. **Pelatihan Awal**: satu kegiatan per sekolah. Kegiatan ini menjadi syarat sekolah resmi berstatus mitra.
2. **Tindak Lanjut**: rangkaian sesi online/offline dengan jumlah fleksibel. Pola juklak 4 online + 1 offline hanya acuan.

Mentor login untuk memperbarui status setiap kegiatan, menulis catatan, dan mengubah jadwal atas permintaan sekolah. Semua perubahan tercatat riwayatnya dan admin mendapat notifikasi. Admin Pusat menjadi satu-satunya pihak yang menginput link sertifikat. Admin Cabang bekerja dalam cakupan cabangnya. Superadmin memegang kendali global dan audit trail.

MVP berfokus pada penjadwalan, manajemen sekolah dan mentor, monitoring progres, sertifikat, dashboard, notifikasi dalam aplikasi, dan audit trail.

---

## 2. Product Overview

| Aspek | Deskripsi |
|---|---|
| Nama | Jadwal Sekolah Mantap Terpusat |
| Platform | Aplikasi web responsif (desktop sebagai prioritas, tetap dapat dipakai di ponsel oleh mentor di lapangan) |
| Cakupan wilayah | Seluruh kabupaten/kota di Jawa Tengah |
| Pengguna | Superadmin, Admin Pusat, Admin Cabang, Mentor |
| Nilai utama | Satu sumber kebenaran untuk status sekolah, jadwal, progres mentor, dan sertifikat |

Kapabilitas inti:
- Basis data sekolah tanpa duplikasi, terhubung ke cabang, mentor, narahubung, dan kategori pelatihan.
- Penjadwalan Pelatihan Awal dan Tindak Lanjut secara terpisah.
- Pelaporan progres mentor yang menempel langsung pada kegiatan terjadwal, sehingga mentor tidak perlu input ulang.
- Pencatatan sertifikat per tahap beserta status ketersediaannya.
- Dashboard dan klasemen kategori berdasarkan sekolah unik.
- Notifikasi dalam aplikasi dan audit trail menyeluruh.

---

## 3. Background & Business Context

Program Sekolah Mantap bermitra dengan sekolah-sekolah di Jawa Tengah. Operasionalnya dikelola kantor pusat dan beberapa cabang. Alurnya:

1. Sekolah didaftarkan sebagai calon mitra.
2. Sekolah mengikuti **pelatihan awal di kampus pusat**. Setelah selesai, sekolah **resmi menjadi mitra** **[KEPUTUSAN]**.
3. Sekolah mitra menjalani **tindak lanjut** berupa beberapa sesi pelatihan/pendampingan. Pola juklak 4 online + 1 offline, tetapi realisasinya dapat berbeda (contoh: SMAN 15 menjalani 3 online + 1 offline).
4. Sertifikat diterbitkan untuk pelatihan awal dan untuk rangkaian tindak lanjut.

Sekolah dapat mengikuti satu atau lebih kategori pelatihan. Daftar kategori saat ini **[KEPUTUSAN]**:

1. Jasa Layanan Bisnis
2. Konten Kreator, Live Sales, Musik AI + DJ
3. Akuntan Bisnis dan Perpajakan
4. Pengacara Bisnis
5. Arsitek Kontraktor Sipil
6. Barbershop

---

## 4. Problem Statement

1. Belum ada satu tempat yang menunjukkan sekolah mana yang sudah bermitra dan sampai tahap mana perkembangannya.
2. Jadwal pelatihan awal dan tindak lanjut tersebar dan rawan tercampur.
3. Progres mentor sulit dipantau tanpa menanyai mentor satu per satu.
4. Ketersediaan sertifikat tidak terlihat. Kegiatan yang sudah selesai tetapi sertifikatnya belum terbit sulit diidentifikasi.
5. Tidak ada data agregat kategori pelatihan yang paling banyak diikuti sekolah.
6. Perubahan jadwal oleh sekolah tidak tercatat riwayatnya dan tidak sampai ke admin tepat waktu.
7. Tidak ada jejak audit atas perubahan data.

---

## 5. Product Goals

| ID | Tujuan | Indikator keberhasilan (usulan, **[REKOMENDASI]**) |
|---|---|---|
| G1 | Satu sumber data sekolah mitra se-Jawa Tengah | 100% sekolah aktif tercatat dengan cabang dan mentor |
| G2 | Memisahkan dan memantau pelatihan awal dan tindak lanjut | Setiap kegiatan terhubung ke tepat satu sekolah dan satu tahap |
| G3 | Progres terlihat tanpa membuka laporan satu per satu | Status setiap sekolah terlihat di daftar sekolah dan dashboard |
| G4 | Status sertifikat transparan | Dashboard menampilkan jumlah sertifikat "Menunggu Penerbitan" |
| G5 | Perubahan jadwal terkendali | 100% perubahan jadwal memiliki riwayat dan memicu notifikasi admin |
| G6 | Akuntabilitas | Seluruh aksi tulis tercatat di audit trail |

---

## 6. Non-Goals

Hal berikut **tidak** termasuk cakupan produk ini atau MVP:

1. Akun dan portal untuk pihak sekolah **[KEPUTUSAN]**.
2. Pembuatan atau desain file sertifikat. Sistem hanya menyimpan link **[KEPUTUSAN]**.
3. Notifikasi WhatsApp, ekspor Excel, dan pencarian lanjutan. Ketiganya masuk pengembangan berikutnya **[KEPUTUSAN]**.
4. Presensi atau data per peserta (guru/siswa) di dalam kegiatan.
5. Materi pelatihan atau LMS.
6. Pembayaran, keuangan, atau honor mentor.
7. Kegiatan dengan banyak sekolah sekaligus. Setiap jadwal hanya berlaku untuk satu sekolah **[KEPUTUSAN]**.
8. Verifikasi laporan mentor oleh admin. Laporan langsung valid **[KEPUTUSAN]**.
9. Transfer sekolah antarcabang dan pemindahan riwayat kegiatan **[KEPUTUSAN]**.
10. Kontak awal sebagai jenis kegiatan terjadwal. Kontak awal dicatat sebagai checklist **[KEPUTUSAN]**.
11. Pengelolaan akun oleh selain Superadmin **[KEPUTUSAN]**.

---

## 7. Stakeholders

| Stakeholder | Kepentingan |
|---|---|
| Pimpinan program Sekolah Mantap | Gambaran capaian kemitraan dan kategori populer |
| Superadmin (pengelola sistem) | Kendali akses, konfigurasi, dan audit |
| Admin Pusat | Operasional seluruh sekolah, penugasan mentor, sertifikat |
| Admin Cabang | Operasional sekolah di cabangnya |
| Mentor | Melaksanakan kegiatan dan melaporkan progres |
| Sekolah mitra (tidak memakai sistem) | Penerima layanan. Data narahubungnya dikelola sistem |
| Tim UI/UX, Architect, Developer | Acuan perancangan dan pembangunan |

---

## 8. User Personas

### 8.1 Superadmin — "Pengelola Sistem"
- **Tujuan:** sistem aman, akses tepat, jejak perubahan lengkap.
- **Kebutuhan:** manajemen semua pengguna dan role, konfigurasi master, akses audit trail.
- **Frustrasi:** tidak tahu siapa mengubah apa.

### 8.2 Admin Pusat — "Koordinator Program"
- **Tujuan:** semua sekolah bergerak sesuai tahap, mentor tertugaskan, sertifikat terbit.
- **Kebutuhan:** daftar sekolah dengan filter cepat, penugasan mentor, input link sertifikat, notifikasi perubahan jadwal.
- **Frustrasi:** harus merekap progres manual dari banyak mentor.

### 8.3 Admin Cabang — "Koordinator Wilayah"
- **Tujuan:** sekolah di cabangnya terjadwal dan terpantau.
- **Kebutuhan:** tampilan yang otomatis terbatas pada cabangnya, input jadwal cepat.
- **Frustrasi:** data cabang lain yang mengganggu, ketergantungan pada pusat untuk informasi sertifikat.

### 8.4 Mentor — "Pelaksana Lapangan"
- **Tujuan:** melaporkan progres secepat mungkin, sering dari ponsel.
- **Kebutuhan:** daftar sekolah tugasnya, tombol ubah status, catatan, reschedule saat sekolah meminta.
- **Frustrasi:** formulir panjang dan input ulang data yang sudah ada.

---

## 9. User Roles & Permission Matrix

Legenda: ✔ = boleh, ✖ = tidak boleh, **C** = hanya data cabangnya, **T** = hanya sekolah/kegiatan yang ditugaskan kepadanya, **?** = belum diputuskan (lihat PD).

| Kapabilitas | SA | AP | AC | M |
|---|:-:|:-:|:-:|:-:|
| **Sekolah** | | | | |
| Lihat daftar dan detail sekolah | ✔ | ✔ | C | ✔ (seluruh Jateng) **[ASUMSI A-01]** |
| Tambah sekolah (cabang dipilih AP; AC otomatis cabangnya) | ✔ | ✔ | C | ✖ |
| Ubah data sekolah | ✔ | ✔ | C | ✖ |
| Arsipkan sekolah (alasan wajib) | ✔ | ✔ **[KEPUTUSAN]** | C **[KEPUTUSAN]** | ✖ |
| Pindahkan sekolah ke cabang lain | ✖ | ✖ | ✖ | ✖ | 
| Koreksi cabang salah input (hanya sekolah tanpa kegiatan, alasan wajib) | ✔ **[KEPUTUSAN]** | ✖ | ✖ | ✖ |
| Atur kategori pelatihan sekolah | ✔ | ✔ | C | ✖ |
| Kelola narahubung | ✔ | ✔ | C | Usul perubahan (T) **[KEPUTUSAN]** |
| Setujui/tolak usulan narahubung dari mentor | ✖ | ✔ **[KEPUTUSAN]** | C **[KEPUTUSAN]** | ✖ |
| **Penugasan mentor** | | | | |
| Tugaskan/ganti mentor penanggung jawab | ✔ | ✔ **[ASUMSI A-02]** | ✖ | ✖ |
| Tunjuk mentor pelaksana berbeda per sesi | ✔ | ✔ **[KEPUTUSAN]** | ✖ | ✖ |
| **Jadwal (Pelatihan Awal & Tindak Lanjut)** | | | | |
| Lihat jadwal | ✔ | ✔ | C | Semua, sebatas info koordinasi **[KEPUTUSAN]** (R-01) |
| Buat jadwal Pelatihan Awal | ✔ | ✔ | C | ✖ |
| Tambah sesi Tindak Lanjut | ✔ | ✔ | C | T, hanya sebelum AP menetapkan sesi terakhir **[KEPUTUSAN]** |
| Tambah sesi setelah sesi terakhir ditetapkan | ✖ | ✔ **[KEPUTUSAN]** | ✖ | ✖ |
| Ubah tanggal/waktu/lokasi/tautan (reschedule) | ✔ | ✔ | C | T **[KEPUTUSAN]** |
| Hapus jadwal (yang belum punya laporan) | ✔ | ✔ | C | ✖ |
| Ubah status kegiatan | ✖ | ✔ dengan alasan wajib **[KEPUTUSAN]** | ✖ | T **[KEPUTUSAN]** |
| Tulis/edit catatan laporan | ✖ | ✖ | ✖ | T **[KEPUTUSAN]** |
| Lihat laporan detail dan riwayat revisi | ✔ | ✔ | C | T saja **[KEPUTUSAN]** |
| Isi checklist kontak awal | ✖ | ✖ | ✖ | T **[KEPUTUSAN]** |
| Tetapkan sesi terakhir / buka kembali rangkaian TL | ✖ | ✔ **[KEPUTUSAN]** | ✖ | ✖ |
| **Sertifikat** | | | | |
| Lihat status sertifikat | ✔ | ✔ | C | ✔ |
| Buka link sertifikat | ✔ | ✔ | C **[KEPUTUSAN]** | T **[KEPUTUSAN]** |
| Input/ubah/ganti link sertifikat | ✖ **[KEPUTUSAN]** | ✔ **[KEPUTUSAN]** | ✖ | ✖ |
| **Dashboard & Klasemen** | | | | |
| Dashboard | ✔ global | ✔ global | C | T **[REKOMENDASI]** |
| Klasemen kategori (seluruh Jateng) | ✔ | ✔ | ✔ **[KEPUTUSAN]** | ✔ **[KEPUTUSAN]** |
| Perbandingan performa antarcabang (agregat) | ✔ **[KEPUTUSAN]** | ✔ **[KEPUTUSAN]** | ✔ agregat, tanpa detail sekolah cabang lain **[KEPUTUSAN]** | ✖ **[REKOMENDASI]** |
| **Pengguna & Konfigurasi** | | | | |
| Kelola akun Superadmin/Admin Pusat | ✔ **[KEPUTUSAN]** | ✖ | ✖ | ✖ |
| Kelola akun Admin Cabang | ✔ **[KEPUTUSAN]** | ✖ | ✖ | ✖ |
| Kelola akun Mentor | ✔ **[KEPUTUSAN]** | ✖ | ✖ | ✖ |
| Kelola role & permission | ✔ | ✖ | ✖ | ✖ |
| Kelola data master (cabang, kabupaten, kategori, jenis, metode) | ✔ **[KEPUTUSAN]** | ✖ | ✖ | ✖ |
| **Audit** | | | | |
| Lihat audit trail | ✔ **[KEPUTUSAN]** | ✖ | ✖ | ✖ |
| **Notifikasi** | | | | |
| Terima notifikasi dalam aplikasi | ✔ | ✔ | C | T |

Catatan penting:
- Pembatasan cabang (C) **wajib ditegakkan di sisi server**, bukan hanya disembunyikan di UI.
- Mentor yang melihat sekolah di luar penugasannya hanya mendapat akses baca. **[REKOMENDASI]**
- Akses mentor ke seluruh sekolah Jawa Tengah tetap **[ASUMSI A-01]**. Mentor tidak dapat membuka laporan detail milik mentor lain **[KEPUTUSAN]**.
- "T" untuk mentor mencakup mentor penanggung jawab sekolah dan mentor pelaksana sesi yang ditunjuk AP.

---

## 10. Business Process Flow

### 10.1 Alur utama siklus sekolah

```
[AP/AC] Daftarkan sekolah (calon mitra) + cabang (dipilih AP; AC = cabangnya; terkunci) + kategori + narahubung
        │
        ▼
[AP] Tugaskan mentor penanggung jawab ke sekolah
        │
        ▼
[M] Isi checklist kontak awal (sudah menghubungi sekolah)
        │
        ▼
[AP/AC] Buat jadwal Pelatihan Awal (kampus pusat)
        │
        ▼
[M] Ubah status: Belum Dimulai ─► Sedang Berlangsung ─► Selesai (+catatan)
        │                                         └─► Dibatalkan (+catatan) ─► jadwal ulang
        ▼
[Sistem] Pelatihan Awal Selesai ⇒ sekolah resmi MITRA (tanggal mitra tercatat)
        │                       ⇒ sertifikat Pelatihan Awal: "Menunggu Penerbitan"
        ▼
[AP] Input link sertifikat Pelatihan Awal ⇒ "Tersedia"
        │
        ▼
[AP/AC/M] Buat sesi Tindak Lanjut 1..n (online/offline, jumlah fleksibel)
        │
        ▼
[M] Perbarui status + catatan tiap sesi; reschedule bila diminta sekolah
        │
        ▼
[AP] Tetapkan sesi terakhir; seluruh sesi yang tidak Dibatalkan berstatus Selesai
        │
        ▼
[Sistem] Rangkaian Tindak Lanjut selesai ⇒ sekolah "Selesai Program"
        │                               ⇒ sertifikat Tindak Lanjut: "Menunggu Penerbitan"
        │                               ⇒ notifikasi ke AP untuk mengurus sertifikat
        ▼
[AP] Input link sertifikat Tindak Lanjut ⇒ "Tersedia"
```

### 10.2 Alur reschedule oleh mentor

```
Sekolah meminta perubahan ─► [M] buka kegiatan ─► ubah tanggal/waktu/lokasi/tautan + alasan
   ─► [Sistem] simpan versi lama di riwayat jadwal
   ─► [Sistem] kirim notifikasi dalam aplikasi ke mentor terkait, AP, dan AC cabang sekolah
   ─► [Sistem] catat di audit trail
```

### 10.3 Alur laporan mentor

```
[M] buka "Kegiatan Saya" ─► pilih kegiatan ─► pilih status ─► tulis catatan (wajib) ─► simpan
   ─► laporan langsung valid (tanpa verifikasi)
   ─► edit berikutnya membuat revisi baru; revisi lama tetap tersimpan
```

---

## 11. Information Architecture

### 11.1 Navigasi Admin (SA/AP/AC)

```
├── Dashboard
├── Sekolah
│   ├── Daftar Sekolah (tab: Semua | Calon Mitra | Mitra – Tindak Lanjut | Selesai Program | Arsip)
│   └── Detail Sekolah
│       ├── Ringkasan (identitas, cabang, mentor, narahubung, kategori)
│       ├── Pelatihan Awal (jadwal, laporan, sertifikat)
│       ├── Tindak Lanjut (daftar sesi, laporan, sertifikat)
│       └── Riwayat (perubahan jadwal, penugasan mentor, narahubung, rangkaian TL)
├── Jadwal
│   ├── Pelatihan Awal (daftar + kalender)
│   └── Tindak Lanjut (daftar + kalender)
├── Progres Mentor (monitoring per mentor dan per sekolah)
├── Sertifikat (tab: Menunggu Penerbitan | Tersedia)
├── Klasemen Kategori
├── Notifikasi
├── Data Master (SA saja)
│   ├── Cabang
│   ├── Kabupaten/Kota
│   ├── Kategori Pelatihan
│   ├── Jenis Kegiatan
│   └── Metode Kegiatan
├── Pengguna (SA saja)
├── Audit Trail (SA saja)
└── Profil & Ganti Password
```

### 11.2 Navigasi Mentor

```
├── Beranda Saya (kegiatan hari ini & mendatang, kegiatan terlambat diperbarui)
├── Sekolah Saya (yang ditugaskan)
├── Kegiatan Saya (daftar + kalender; aksi cepat ubah status)
├── Semua Sekolah (lihat saja, seluruh Jateng) [Asumsi A-01]
├── Jadwal Koordinasi (jadwal semua mentor, info ringkas)
├── Klasemen Kategori
├── Notifikasi
└── Profil & Ganti Password
```

---

## 12. Functional Requirements

Prioritas: **P0** = wajib MVP, **P1** = MVP bila waktu memungkinkan, **P2** = pengembangan berikutnya.

| ID | Kebutuhan | Prioritas |
|---|---|:-:|
| **FR-AUTH** | **Autentikasi & Otorisasi** | |
| FR-AUTH-01 | Login dengan email/username dan password | P0 |
| FR-AUTH-02 | Logout dan kedaluwarsa sesi otomatis | P0 |
| FR-AUTH-03 | Ganti password sendiri; reset password oleh SA | P0 |
| FR-AUTH-04 | RBAC empat role dengan pembatasan cabang di sisi server | P0 |
| FR-AUTH-05 | Akun nonaktif tidak bisa login, tetapi riwayat aktivitasnya tetap ada | P0 |
| **FR-SCH** | **Sekolah** | |
| FR-SCH-01 | CRUD sekolah sesuai cakupan role | P0 |
| FR-SCH-02 | Setiap sekolah wajib terhubung ke satu cabang dan satu kabupaten/kota. Cabang dipilih manual oleh AP saat pendaftaran; untuk AC otomatis cabangnya | P0 |
| FR-SCH-03 | Pencegahan duplikasi (lihat BR-SCH-03) | P0 |
| FR-SCH-04 | Relasi banyak kategori per sekolah | P0 |
| FR-SCH-05 | Kelola narahubung: satu atau lebih per sekolah, tepat satu utama, nama dan nomor WhatsApp wajib, riwayat perubahan disimpan | P0 |
| FR-SCH-12 | Usulan perubahan narahubung dari mentor: tidak langsung berlaku, menunggu persetujuan AC cabang sekolah atau AP; usulan dan keputusan tercatat di riwayat | P0 |
| FR-SCH-06 | Pencarian berdasarkan nama sekolah | P0 |
| FR-SCH-07 | Filter: kabupaten/kota, cabang, status tahap, mentor, kategori, status sertifikat | P0 |
| FR-SCH-08 | Halaman detail sekolah dengan pemisahan Pelatihan Awal dan Tindak Lanjut | P0 |
| FR-SCH-09 | Pencarian lanjutan (multi-kriteria tersimpan, rentang tanggal) | P2 |
| FR-SCH-10 | Arsip sekolah oleh AP/AC sesuai cakupan, alasan wajib; kegiatan yang belum selesai dibatalkan dengan alasan; riwayat tidak dihapus | P0 |
| FR-SCH-11 | Field cabang terkunci setelah sekolah tersimpan; tidak ada fitur transfer antarcabang. SA dapat mengoreksi cabang hanya bila sekolah belum memiliki kegiatan, dengan alasan wajib dan tercatat di audit trail | P0 |
| **FR-MNT** | **Mentor & Penugasan** | |
| FR-MNT-01 | Akun mentor dibuat, diubah, dan dinonaktifkan hanya oleh SA | P0 |
| FR-MNT-02 | AP menugaskan dan mengganti satu mentor penanggung jawab per sekolah, dengan riwayat penugasan (penugasan oleh AP: Asumsi A-02) | P0 |
| FR-MNT-03 | Mentor dapat ditugaskan lintas cabang | P0 |
| FR-MNT-04 | Daftar "Sekolah Saya" dan "Kegiatan Saya" untuk mentor | P0 |
| FR-MNT-05 | AP menunjuk mentor pelaksana berbeda untuk sesi tertentu | P0 |
| FR-MNT-06 | Jadwal koordinasi: mentor melihat jadwal mentor lain sebatas info koordinasi (R-01) | P1 |
| **FR-JDA** | **Jadwal Pelatihan Awal** | |
| FR-JDA-01 | Buat jadwal pelatihan awal untuk satu sekolah | P0 |
| FR-JDA-02 | Maksimal satu pelatihan awal aktif (tidak Dibatalkan) per sekolah | P0 |
| FR-JDA-03 | Pelatihan awal selalu offline di kampus pusat; metode dan lokasi terisi otomatis | P0 |
| FR-JDA-04 | Ubah dan reschedule jadwal dengan riwayat versi | P0 |
| **FR-JTL** | **Jadwal Tindak Lanjut** | |
| FR-JTL-01 | Buat banyak sesi per sekolah (oleh AP, AC cabang, atau mentor penanggung jawab), masing-masing online/offline | P0 |
| FR-JTL-02 | Urutan sesi otomatis (Sesi 1, 2, 3, ...) dengan nama sesi yang bisa diubah | P0 |
| FR-JTL-03 | Tidak ada batas minimum/maksimum jumlah sesi | P0 |
| FR-JTL-04 | Indikator pembanding dengan pola juklak (informasi, bukan validasi) | P1 |
| FR-JTL-05 | AP menetapkan sesi terakhir; rangkaian selesai otomatis saat seluruh sesi yang tidak Dibatalkan berstatus Selesai; AP mendapat notifikasi sertifikat | P0 |
| FR-JTL-07 | AP dapat membuka kembali rangkaian yang sudah selesai untuk sesi tambahan, dengan alasan; tercatat di riwayat rangkaian | P0 |
| FR-JTL-08 | Daftar untuk AP: sekolah yang semua sesi aktifnya Selesai tetapi belum memiliki sesi terakhir, atau yang sesi terakhirnya Dibatalkan | P1 |
| FR-JTL-09 | Setelah AP menetapkan sesi terakhir, tombol tambah sesi tidak tersedia bagi mentor; AP menambah sesi dan memperbarui penetapan sesi terakhir | P0 |
| FR-JTL-10 | Jika sesi terakhir Dibatalkan, tanda sesi terakhir dianggap tidak berlaku dan AP wajib menetapkan sesi lain | P0 |
| FR-JTL-06 | Sesi tindak lanjut hanya bisa dibuat setelah pelatihan awal sekolah Selesai | P0 |
| **FR-RPT** | **Laporan Progres Mentor** | |
| FR-RPT-01 | Mentor mengubah status kegiatan: Belum Dimulai / Sedang Berlangsung / Selesai / Dibatalkan | P0 |
| FR-RPT-02 | Catatan wajib setiap kali menyimpan pembaruan laporan | P0 |
| FR-RPT-03 | Edit laporan menghasilkan revisi baru; seluruh revisi dapat dilihat | P0 |
| FR-RPT-04 | Laporan menempel pada kegiatan terjadwal; mentor tidak menginput ulang data jadwal | P0 |
| FR-RPT-05 | Monitoring progres: matriks sekolah × tahap/sesi dengan status | P0 |
| FR-RPT-06 | Penanda kegiatan "Perlu Pembaruan" (H+1 dari tanggal kegiatan, status belum Selesai/Dibatalkan) | P0 |
| FR-RPT-07 | AP dapat mengubah status kegiatan dengan alasan wajib; tercatat sebagai revisi; bukan verifikasi | P0 |
| FR-RPT-08 | Checklist kontak awal per sekolah oleh mentor (sudah dihubungi, tanggal, catatan) dengan riwayat revisi | P0 |
| **FR-CRT** | **Sertifikat** | |
| FR-CRT-01 | Satu catatan sertifikat Pelatihan Awal dan satu catatan sertifikat Tindak Lanjut per sekolah | P0 |
| FR-CRT-02 | Status sertifikat diturunkan otomatis (lihat 15.4) | P0 |
| FR-CRT-03 | AP input/ubah link sertifikat, dengan validasi format URL | P0 |
| FR-CRT-04 | Daftar sertifikat "Menunggu Penerbitan" | P0 |
| FR-CRT-05 | Riwayat perubahan link sertifikat (via audit trail) | P0 |
| FR-CRT-06 | Link dapat dibuka oleh SA, AP, AC (sekolah cabangnya), dan mentor (sekolah tanggung jawabnya) | P0 |
| **FR-DSH** | **Dashboard** | |
| FR-DSH-01 | Kartu KPI sesuai cakupan role (lihat Bagian 22) | P0 |
| FR-DSH-02 | Grafik perkembangan sekolah per tahap | P0 |
| FR-DSH-03 | Statistik sertifikat Tersedia vs Menunggu Penerbitan | P0 |
| FR-DSH-04 | Ringkasan klasemen kategori (top 6) | P0 |
| FR-DSH-06 | Perbandingan performa antarcabang **[KEPUTUSAN]**: jumlah sekolah mitra, % sesi selesai, jumlah kegiatan belum diperbarui, jumlah sertifikat belum terbit. AC melihat agregat seluruh cabang tanpa detail sekolah cabang lain | P0 |
| FR-DSH-05 | Daftar kegiatan 7 hari ke depan dan kegiatan yang perlu pembaruan | P1 |
| **FR-RNK** | **Klasemen Kategori** | |
| FR-RNK-01 | Hitung sekolah mitra unik per kategori, urut menurun, beri peringkat | P0 |
| FR-RNK-02 | Data sepanjang waktu, cakupan seluruh Jateng, terlihat oleh semua admin dan mentor | P0 |
| FR-RNK-03 | Grafik batang horizontal | P1 |
| FR-RNK-04 | Filter periode | P2 |
| **FR-NTF** | **Notifikasi** | |
| FR-NTF-01 | Notifikasi dalam aplikasi (ikon lonceng, jumlah belum dibaca, daftar) | P0 |
| FR-NTF-02 | Pemicu MVP **[KEPUTUSAN]**: jadwal baru (termasuk sesi tambahan dari mentor), perubahan jadwal, pengingat jadwal H-1, sertifikat tersedia, laporan belum diperbarui H+1, rangkaian TL selesai (ke AP). Penerima: mentor terkait, AP, dan AC cabang sekolah. Pemicu tambahan **[REKOMENDASI]**: penugasan mentor baru, usulan perubahan narahubung | P0 |
| FR-NTF-03 | Tandai dibaca / tandai semua dibaca | P0 |
| FR-NTF-04 | Notifikasi WhatsApp | P2 |
| **FR-MST** | **Data Master** | |
| FR-MST-01 | CRUD cabang, kabupaten/kota, kategori pelatihan, jenis kegiatan, metode kegiatan; hanya SA | P0 |
| FR-MST-02 | Data master yang sudah dipakai hanya bisa dinonaktifkan, tidak dihapus | P0 |
| **FR-USR** | **Pengguna** | |
| FR-USR-01 | CRUD seluruh akun (termasuk mentor) hanya oleh SA; AC wajib punya cabang | P0 |
| FR-USR-02 | Nonaktifkan akun tanpa menghapus riwayat | P0 |
| **FR-AUD** | **Audit Trail** | |
| FR-AUD-01 | Catat seluruh aksi tulis (create/update/delete/login/logout/gagal login) beserta nilai sebelum dan sesudah | P0 |
| FR-AUD-02 | Halaman audit trail khusus SA dengan filter pengguna, entitas, aksi, dan tanggal | P0 |
| FR-AUD-03 | Log tidak dapat diubah atau dihapus melalui aplikasi | P0 |
| **FR-EXP** | **Ekspor** | |
| FR-EXP-01 | Ekspor Excel daftar sekolah, jadwal, sertifikat | P2 |

---

## 13. Module Specifications

### 13.1 Modul Dashboard

| Komponen | Spesifikasi |
|---|---|
| Deskripsi | Halaman pertama setelah login. Berisi ringkasan kondisi program sesuai cakupan pengguna. |
| Tujuan | Memberi gambaran progres tanpa membuka data satu per satu. |
| Aktor | SA, AP, AC, M |
| Input | Tidak ada input manual. Opsional filter cabang (SA/AP). |
| Proses | Agregasi data sekolah, kegiatan, dan sertifikat sesuai cakupan role (SA/AP global, AC cabang, M penugasan). Klasemen selalu global. |
| Output | Kartu KPI, grafik tahap sekolah, statistik sertifikat, ringkasan klasemen, tabel perbandingan performa antarcabang (agregat), daftar kegiatan mendatang dan yang perlu pembaruan. |
| Business rules | BR-DSH-01..03 |
| Hak akses | Semua role, dengan cakupan berbeda (Bagian 9) |
| Acceptance criteria | AC-DSH-01..05 (Bagian 19) |

### 13.2 Modul Data Sekolah (Daftar)

| Komponen | Spesifikasi |
|---|---|
| Deskripsi | Tabel semua sekolah dalam cakupan pengguna. |
| Tujuan | Memindai status sekolah dengan cepat. |
| Aktor | SA, AP, AC, M (baca) |
| Input | Kata kunci pencarian, filter (kabupaten/kota, cabang, status tahap, mentor, kategori, status sertifikat), urutan kolom. |
| Proses | Query terfilter sesuai cakupan role; paginasi di server. |
| Output | Kolom: Nama Sekolah, Kabupaten/Kota, Cabang, Posisi Tahap, Kontak Awal (✔/✖), Mentor, Narahubung Utama (nama + telepon), Status Pelatihan Awal, Progres Tindak Lanjut ("3/4 sesi selesai"), Status Sertifikat (Awal/TL), aksi Lihat Detail. |
| Business rules | BR-SCH-01..06, BR-ACC-01..03 |
| Hak akses | Lihat: semua (AC terbatas cabang). Tambah/ubah: SA, AP, AC (cabang). |
| Acceptance criteria | AC-SCH-01..08 |

### 13.3 Modul Detail Sekolah

| Komponen | Spesifikasi |
|---|---|
| Deskripsi | Pusat informasi satu sekolah. |
| Tujuan | Melihat seluruh perkembangan sekolah di satu halaman, dengan pelatihan awal dan tindak lanjut terpisah. |
| Aktor | SA, AP, AC (cabang), M |
| Input | ID sekolah. |
| Proses | Memuat identitas, cabang, mentor penanggung jawab dan riwayatnya, narahubung beserta riwayatnya, kategori, checklist kontak awal, kegiatan per tahap, laporan, dan sertifikat. |
| Output | Header: nama, status tahap, cabang, mentor, tanggal mitra. Tab Ringkasan, Pelatihan Awal, Tindak Lanjut, Riwayat. Setiap kegiatan menampilkan jadwal, metode, lokasi/tautan, status, catatan terakhir, dan tombol "Lihat revisi". Kartu sertifikat per tahap dengan status dan link (jika berhak). Mentor yang tidak ditugaskan tidak melihat catatan laporan maupun link sertifikat. |
| Business rules | BR-SCH-*, BR-ACT-*, BR-CRT-* |
| Hak akses | Sesuai matriks Bagian 9 |
| Acceptance criteria | AC-SCH-09..12 |

### 13.4 Modul Manajemen Jadwal Pelatihan Awal

| Komponen | Spesifikasi |
|---|---|
| Deskripsi | Membuat dan mengelola satu kegiatan pelatihan awal per sekolah. |
| Tujuan | Menjadwalkan gerbang masuk kemitraan. |
| Aktor | AP, AC (cabang), SA; M (reschedule dan status untuk kegiatan yang ditugaskan) |
| Input | Sekolah (wajib), tanggal (wajib), waktu mulai–selesai (wajib), jenis kegiatan (wajib, master), metode dan lokasi (otomatis: Offline, kampus pusat; detail ruang dapat dilengkapi), mentor pelaksana (default mentor penanggung jawab; AP boleh menunjuk mentor lain), keterangan (opsional). Status awal otomatis "Belum Dimulai". |
| Proses | Validasi: sekolah dalam cakupan dan tidak diarsipkan; belum ada pelatihan awal yang aktif atau Selesai; sekolah punya mentor penanggung jawab (**[REKOMENDASI]**). Simpan, catat audit, kirim notifikasi jadwal baru ke mentor terkait, AP, dan AC cabang. |
| Output | Kegiatan tersimpan dan muncul di Detail Sekolah, Jadwal, dan Kegiatan Saya milik mentor. |
| Business rules | BR-ACT-01..10, BR-AWL-01..04 |
| Hak akses | Buat/hapus: SA, AP, AC (cabang). Reschedule: SA, AP, AC (cabang), M (ditugaskan). |
| Acceptance criteria | AC-JDA-01..07 |

### 13.5 Modul Manajemen Jadwal Tindak Lanjut

| Komponen | Spesifikasi |
|---|---|
| Deskripsi | Membuat dan mengelola rangkaian sesi tindak lanjut per sekolah. |
| Tujuan | Mencatat realisasi sesi secara fleksibel. |
| Aktor | AP, AC (cabang), SA; M penanggung jawab (tambah sesi, reschedule, status); AP saja (tetapkan sesi terakhir, buka kembali rangkaian) |
| Input | Sekolah, tanggal, waktu, jenis kegiatan (Pelatihan/Pendampingan), metode (Online/Offline), lokasi (offline) atau tautan (online), mentor pelaksana, keterangan, nomor urut (otomatis, bisa diubah), nama sesi (default "Sesi n"). Penanda sesi terakhir: hanya AP. |
| Proses | Validasi: pelatihan awal sekolah sudah Selesai; dalam cakupan. Urutan otomatis = urutan terbesar + 1. Panel ringkasan menampilkan jumlah sesi online/offline terhadap acuan juklak 4+1, sebagai informasi saja; deviasi tidak memerlukan alasan. Sesi tambahan dari mentor memicu notifikasi jadwal baru. Rangkaian selesai otomatis bila AP sudah menetapkan sesi terakhir, sesi terakhir itu berstatus Selesai, dan seluruh sesi yang tidak Dibatalkan berstatus Selesai. AP lalu mendapat notifikasi sertifikat. Sesi Dibatalkan tidak wajib diselesaikan, tetapi riwayatnya tetap disimpan. |
| Output | Daftar sesi berurutan di tab Tindak Lanjut, contoh: "Sesi 1 – Online – Selesai", "Sesi 2 – Online – Belum Dimulai". |
| Business rules | BR-TL-01..07 |
| Hak akses | Tambah sesi dan reschedule: SA, AP, AC (cabang), M penanggung jawab (tambah sesi hanya sebelum sesi terakhir ditetapkan; setelahnya hanya AP). Hapus: SA, AP, AC (cabang). Sesi terakhir dan buka kembali: AP saja. |
| Acceptance criteria | AC-JTL-01..08 |

### 13.6 Modul Laporan Progres Mentor

| Komponen | Spesifikasi |
|---|---|
| Deskripsi | Mentor memperbarui status dan catatan pada kegiatan yang sudah dijadwalkan. Laporan tidak dibuat sebagai formulir terpisah. |
| Tujuan | Progres terlihat real-time tanpa input ulang. |
| Aktor | M penanggung jawab dan pelaksana (tulis), AP (ubah status dengan alasan), SA/AC (baca) |
| Input | Kegiatan (dipilih dari daftar), status baru, catatan (wajib, minimal 10 karakter **[REKOMENDASI]**). |
| Proses | Simpan sebagai revisi laporan baru (versi n+1). Status kegiatan diperbarui. Jika Selesai pada pelatihan awal: sekolah menjadi Mitra. Jika AP sudah menetapkan sesi terakhir dan seluruh sesi yang tidak Dibatalkan berstatus Selesai: rangkaian TL selesai dan AP mendapat notifikasi sertifikat. Kirim notifikasi ke admin terkait. Catat audit. |
| Output | Status terbaru tampil di semua tampilan. Riwayat revisi dapat dibuka (versi, waktu, oleh, status, catatan). |
| Monitoring | Halaman Progres Mentor: tabel per sekolah dengan kolom Pelatihan Awal, Sesi 1..n, Status Tahap, Pembaruan Terakhir; filter mentor/cabang; penanda "Perlu Pembaruan". |
| Checklist | [Kontak Awal ✔/✖] → [Pelatihan Awal] → [Sesi 1] → ... → [Sesi terakhir] → [Selesai Program]. Kontak awal adalah checklist per sekolah, bukan kegiatan terjadwal **[KEPUTUSAN]**; tidak memblokir pelaporan (R-02). Item kegiatan dibentuk dinamis dari jadwal. |
| Business rules | BR-RPT-01..07 |
| Hak akses | Tulis: mentor penanggung jawab dan mentor pelaksana sesi. Ubah status oleh admin: AP saja, alasan wajib. Baca detail: SA, AP, AC (cabang), mentor yang ditugaskan; mentor lain tidak dapat membuka laporan detail. |
| Acceptance criteria | AC-RPT-01..08 |

### 13.7 Modul Manajemen Sertifikat

| Komponen | Spesifikasi |
|---|---|
| Deskripsi | Mencatat link sertifikat Pelatihan Awal dan Tindak Lanjut per sekolah. |
| Tujuan | Membedakan sertifikat Tersedia dan yang belum, serta memantau penerbitan yang tertunda. |
| Aktor | AP (tulis), SA/AC/M (baca sesuai izin) |
| Input | Sekolah, tahap (Awal/TL), URL link, tanggal terbit (opsional), catatan (opsional). |
| Proses | Validasi URL (http/https). Input hanya bila tahap terkait sudah selesai (BR-CRT-04). Simpan, catat audit (nilai lama dan baru). |
| Output | Status: Belum Berlaku / Menunggu Penerbitan / Tersedia. Link dapat diklik dan dibuka di tab baru. |
| Business rules | BR-CRT-01..07 |
| Hak akses | Input/ubah/ganti: hanya AP. Lihat status: semua sesuai cakupan. Buka link: SA, AP, AC (sekolah cabangnya), mentor (sekolah tanggung jawabnya). |
| Acceptance criteria | AC-CRT-01..07 |

### 13.8 Modul Klasemen Kategori Pelatihan

| Komponen | Spesifikasi |
|---|---|
| Deskripsi | Peringkat kategori berdasarkan jumlah sekolah unik peserta. |
| Tujuan | Mengetahui kategori yang paling diminati. |
| Aktor | SA, AP, AC, M (global) |
| Input | Tidak ada di MVP (sepanjang waktu, seluruh Jateng). P2: filter periode. |
| Proses | `COUNT(DISTINCT sekolah_id)` per kategori dari relasi sekolah–kategori, hanya untuk sekolah yang sudah resmi mitra. Urut menurun. Jumlah sama mendapat peringkat sama (dense ranking **[REKOMENDASI]**). Kategori dengan 0 sekolah tetap ditampilkan. |
| Output | Tabel Peringkat / Kategori / Jumlah Sekolah, ditambah grafik batang horizontal. Catatan kaki: "Satu sekolah dapat terhitung di lebih dari satu kategori." |
| Business rules | BR-RNK-01..04 |
| Hak akses | Lihat: semua admin dan mentor |
| Acceptance criteria | AC-RNK-01..05 |

### 13.9 Modul Notifikasi Dalam Aplikasi

| Komponen | Spesifikasi |
|---|---|
| Deskripsi | Pemberitahuan di dalam aplikasi atas peristiwa penting. |
| Aktor | Semua role (penerima) |
| Pemicu MVP **[KEPUTUSAN]** | (1) Jadwal baru, termasuk sesi tambahan dari mentor; (2) perubahan jadwal; (3) pengingat jadwal H-1; (4) sertifikat tersedia; (5) laporan belum diperbarui H+1 (status belum Selesai/Dibatalkan); (6) rangkaian TL selesai → AP untuk sertifikat. Penerima (1)–(5): mentor terkait, AP, dan AC cabang sekolah. |
| Pemicu tambahan **[REKOMENDASI]** | Penugasan/penggantian mentor → mentor terkait; usulan perubahan narahubung → AP dan AC cabang; keputusan atas usulan → mentor pengusul; sesi terakhir Dibatalkan → AP. |
| Output | Lonceng dengan badge jumlah belum dibaca, daftar notifikasi dengan tautan ke objek terkait. |
| Business rules | BR-NTF-01..03 |
| Acceptance criteria | AC-NTF-01..04 |

### 13.10 Modul Data Master

Seluruh data master hanya dikelola Superadmin **[KEPUTUSAN]**.

| Master | Field minimum | Catatan |
|---|---|---|
| Cabang | Kode, nama, alamat, status aktif | Tidak ada pemetaan otomatis kabupaten ke cabang **[KEPUTUSAN]** |
| Kabupaten/Kota | Kode, nama | Di-seed dengan 35 kabupaten/kota Jawa Tengah |
| Kategori Pelatihan | Nama, deskripsi, status aktif | Di-seed dengan 6 kategori **[KEPUTUSAN]** |
| Jenis Kegiatan | Nama, status aktif | Seed: Pelatihan, Pendampingan **[KEPUTUSAN]** |
| Metode Kegiatan | Nama, status aktif | Seed: Online, Offline **[KEPUTUSAN]** |

### 13.11 Modul Pengguna

| Komponen | Spesifikasi |
|---|---|
| Input | Nama, email/username, telepon, role, cabang (wajib untuk AC), status aktif |
| Aktor | Superadmin saja, termasuk untuk akun mentor **[KEPUTUSAN]** |
| Proses | Buat akun dengan password awal dan wajib ganti saat login pertama **[REKOMENDASI]**. Nonaktifkan, bukan hapus. |
| Aturan | Mentor yang dinonaktifkan dan masih punya penugasan aktif: sistem memperingatkan dan meminta penugasan ulang (BR-MNT-04). |

### 13.12 Modul Audit Trail

| Komponen | Spesifikasi |
|---|---|
| Data dicatat | Waktu, pengguna (termasuk yang sudah nonaktif), role saat aksi, aksi, entitas, ID entitas, nilai sebelum, nilai sesudah, alamat IP, user agent |
| Tampilan | Tabel dengan filter (pengguna, entitas, aksi, rentang tanggal) dan tampilan diff sebelum/sesudah |
| Hak akses | SA saja |
| Retensi | PD-20 |

---

## 14. Business Rules

### 14.1 Akses (ACC)
| ID | Aturan | Sumber |
|---|---|---|
| BR-ACC-01 | Superadmin memiliki akses global tanpa batas cabang. | Keputusan |
| BR-ACC-02 | Admin Cabang hanya dapat melihat dan mengelola sekolah, jadwal, dan data turunannya di cabangnya. | Keputusan |
| BR-ACC-03 | Pembatasan akses ditegakkan di server untuk setiap permintaan, termasuk akses langsung via URL/API. | Rekomendasi |
| BR-ACC-04 | Tidak ada akun untuk sekolah. | Keputusan |
| BR-ACC-05 | Mentor dapat melihat seluruh sekolah di Jawa Tengah. Perubahan hanya pada kegiatan sekolah yang ditugaskan kepadanya. | Asumsi A-01 (lihat) / Rekomendasi (batas ubah) |

### 14.2 Sekolah (SCH)
| ID | Aturan | Sumber |
|---|---|---|
| BR-SCH-01 | Setiap sekolah wajib terhubung ke tepat satu cabang dan satu kabupaten/kota. | Keputusan |
| BR-SCH-02 | Sekolah resmi menjadi Mitra ketika pelatihan awalnya berstatus Selesai. Tanggal mitra = tanggal kegiatan pelatihan awal. | Keputusan / Rekomendasi (tanggal) |
| BR-SCH-03 | NPSN tidak digunakan. Duplikasi dicegah dengan kombinasi unik nama sekolah ternormalisasi + jenjang + kabupaten/kota, ditambah peringatan kemiripan nama saat input. | Keputusan (tanpa NPSN) / Rekomendasi (mekanisme) |
| BR-SCH-04 | Satu sekolah boleh memiliki lebih dari satu kategori pelatihan. Pasangan sekolah–kategori tidak boleh ganda. | Keputusan |
| BR-SCH-05 | Sekolah yang mundur diarsipkan oleh AP atau AC sesuai cakupan, dengan alasan wajib. Kegiatan yang belum selesai dibatalkan dengan catatan alasan. Riwayat sekolah dan kegiatannya tidak boleh dihapus. | Keputusan |
| BR-SCH-06 | Sekolah boleh memiliki beberapa narahubung dengan tepat satu narahubung utama. Nama dan nomor WhatsApp wajib. Perubahan disimpan sebagai riwayat; data lama tidak dihapus. | Keputusan |
| BR-SCH-07 | Jenjang sekolah: SMA, SMK, atau MA. | Keputusan |
| BR-SCH-09 | Cabang dipilih manual oleh AP saat pendaftaran; sekolah yang didaftarkan AC otomatis masuk cabang AC tersebut. Tidak ada pemetaan otomatis dari kabupaten. | Keputusan |
| BR-SCH-10 | Sekolah tidak dapat dipindahkan ke cabang lain. Tidak ada fitur transfer sekolah maupun pemindahan riwayat kegiatan. | Keputusan |
| BR-SCH-12 | Superadmin boleh mengoreksi cabang yang salah input hanya bila sekolah belum memiliki kegiatan sama sekali, dengan alasan wajib dan tercatat di audit trail. Setelah ada kegiatan, cabang tidak dapat diubah. | Keputusan |
| BR-SCH-13 | Arsip lalu daftar ulang tidak boleh dipakai untuk mengakali anti-duplikasi: sekolah arsip tetap dihitung dalam pengecekan duplikat. | Keputusan |
| BR-SCH-11 | Mentor dapat mengusulkan perubahan narahubung sekolah tanggung jawabnya. Usulan tidak langsung berlaku dan menunggu persetujuan AC cabang sekolah atau AP. Riwayat mencatat data sebelum, data usulan, pengusul, pemutus (setuju/tolak), waktu pengajuan, dan waktu keputusan. | Keputusan |
| BR-SCH-08 | Sekolah tidak memiliki status keseluruhan; status hanya berlaku per kegiatan/sesi. Posisi tahap sekolah dihitung otomatis (15.3). | Keputusan |

### 14.3 Mentor (MNT)
| ID | Aturan | Sumber |
|---|---|---|
| BR-MNT-01 | Penugasan mentor ke sekolah ditentukan Admin Pusat. | Asumsi A-02 |
| BR-MNT-02 | Mentor dapat menangani sekolah lintas cabang. | Asumsi A-03 |
| BR-MNT-03 | Setiap penggantian mentor tersimpan sebagai riwayat penugasan (mentor, mulai, selesai, oleh siapa). | Rekomendasi |
| BR-MNT-04 | Mentor tidak dapat dinonaktifkan tanpa peringatan bila masih memiliki penugasan aktif. | Rekomendasi |
| BR-MNT-05 | Satu sekolah memiliki satu mentor penanggung jawab. | Keputusan |
| BR-MNT-06 | AP boleh menunjuk mentor pelaksana berbeda untuk sesi tertentu. | Keputusan |

### 14.4 Kegiatan umum (ACT)
| ID | Aturan | Sumber |
|---|---|---|
| BR-ACT-01 | Setiap kegiatan berlaku untuk tepat satu sekolah. | Keputusan |
| BR-ACT-02 | Setiap kegiatan berada pada tepat satu tahap: Pelatihan Awal atau Tindak Lanjut. | Keputusan |
| BR-ACT-03 | Status kegiatan: Belum Dimulai, Sedang Berlangsung, Selesai, Dibatalkan. | Keputusan |
| BR-ACT-04 | Status kegiatan diubah oleh mentor. Hanya Admin Pusat yang boleh mengubah status laporan mentor, dengan alasan wajib; perubahan masuk riwayat revisi dan bukan merupakan verifikasi. | Keputusan |
| BR-ACT-05 | Mentor boleh mengubah jadwal (tanggal, waktu, lokasi, tautan) secara langsung bila sekolah meminta. | Keputusan |
| BR-ACT-06 | Setiap perubahan jadwal wajib menyimpan versi sebelumnya. | Keputusan |
| BR-ACT-07 | Setiap perubahan jadwal oleh mentor memicu notifikasi ke admin. | Keputusan |
| BR-ACT-08 | Reschedule mewajibkan isian alasan perubahan. | Rekomendasi |
| BR-ACT-09 | Kegiatan yang sudah memiliki laporan tidak dapat dihapus; gunakan status Dibatalkan. | Rekomendasi |
| BR-ACT-10 | Kegiatan offline wajib berisi lokasi. Kegiatan online wajib berisi tautan pertemuan. | Rekomendasi |
| BR-ACT-11 | Jadwal kegiatan berstatus Selesai atau Dibatalkan tidak dapat di-reschedule. | Rekomendasi |
| BR-ACT-12 | Mentor penanggung jawab boleh menambah sesi Tindak Lanjut untuk sekolahnya. Admin terkait mendapat notifikasi jadwal baru dan aktivitas tercatat di riwayat. | Keputusan |

### 14.5 Pelatihan Awal (AWL)
| ID | Aturan | Sumber |
|---|---|---|
| BR-AWL-01 | Setiap sekolah mengikuti satu kali pelatihan awal. | Keputusan |
| BR-AWL-02 | Pelatihan awal dilaksanakan di kampus pusat. | Keputusan |
| BR-AWL-03 | Pelatihan awal yang dibatalkan dijadwal ulang sebagai kegiatan baru; kegiatan lama tetap tersimpan berstatus Dibatalkan. Hanya satu pelatihan awal yang boleh berstatus Selesai per sekolah. | Keputusan |
| BR-AWL-04 | Pelatihan awal tidak dapat diubah dari Selesai kembali ke status lain setelah sertifikatnya Tersedia. | Rekomendasi |

### 14.6 Tindak Lanjut (TL)
| ID | Aturan | Sumber |
|---|---|---|
| BR-TL-01 | Tindak lanjut adalah proses terpisah dari pelatihan awal. | Keputusan |
| BR-TL-02 | Jumlah sesi fleksibel. Sistem tidak memvalidasi jumlah atau komposisi online/offline. | Keputusan |
| BR-TL-03 | Pola 4 online + 1 offline hanya ditampilkan sebagai acuan. | Keputusan |
| BR-TL-04 | Sesi diberi nomor urut unik per sekolah. Nama sesi dapat diubah. | Keputusan |
| BR-TL-05 | Sesi TL hanya bisa dibuat setelah pelatihan awal sekolah Selesai. | Keputusan (aturan bisnis awal no. 1) |
| BR-TL-06 | Admin Pusat menetapkan sesi terakhir; penetapan tidak diserahkan kepada mentor. Rangkaian TL selesai bila sesi terakhir berstatus Selesai dan seluruh sesi yang tidak Dibatalkan berstatus Selesai. Sesi Dibatalkan tidak wajib diselesaikan dan riwayatnya tetap disimpan. | Keputusan |
| BR-TL-07 | Maksimal satu sesi bertanda "sesi terakhir" per sekolah. | Rekomendasi |
| BR-TL-08 | Saat rangkaian selesai, AP mendapat notifikasi untuk mengurus sertifikat TL. | Keputusan |
| BR-TL-09 | AP boleh membuka kembali rangkaian yang sudah selesai bila ada sesi tambahan, dengan alasan; tercatat di riwayat rangkaian. | Keputusan |
| BR-TL-10 | Setelah AP menetapkan sesi terakhir, mentor tidak boleh menambah sesi sendiri. Mentor mengajukan atau menginformasikan kebutuhan sesi tambahan kepada AP; AP menambah sesi dan memperbarui penetapan sesi terakhir. | Keputusan |
| BR-TL-12 | Jika sesi terakhir Dibatalkan, rangkaian tidak dianggap selesai; AP wajib menetapkan sesi lain sebagai sesi terakhir. | Keputusan |
| BR-TL-11 | Deviasi jumlah atau metode sesi dari pola 4+1 tidak memerlukan alasan khusus; catatan kegiatan cukup. | Keputusan |

### 14.7 Laporan (RPT)
| ID | Aturan | Sumber |
|---|---|---|
| BR-RPT-01 | Laporan melekat pada kegiatan. Mentor tidak menginput ulang data jadwal. | Keputusan |
| BR-RPT-02 | Setiap kegiatan memerlukan catatan dari mentor. Catatan wajib diisi setiap kali menyimpan pembaruan. | Keputusan / Rekomendasi (setiap simpan) |
| BR-RPT-03 | Mentor boleh mengedit laporan. Setiap edit membuat revisi baru dan revisi lama tidak dapat dihapus. | Keputusan |
| BR-RPT-04 | Laporan langsung valid tanpa verifikasi admin. | Keputusan |
| BR-RPT-05 | Hanya mentor penanggung jawab sekolah dan mentor pelaksana sesi terkait yang dapat menulis laporan (R-05). Setelah penggantian mentor, mentor lama kehilangan akses tulis, dan laporannya tetap tercatat atas namanya. | Rekomendasi |
| BR-RPT-06 | Status progres sekolah harus terlihat di daftar sekolah tanpa membuka laporan. | Keputusan |
| BR-RPT-07 | Status "Selesai" tidak boleh diset untuk kegiatan yang tanggalnya masih di masa depan. | Rekomendasi |
| BR-RPT-08 | Mentor tidak dapat membuka atau mengubah laporan detail milik mentor lain. Jadwal mentor lain terlihat sebatas info koordinasi. | Keputusan |
| BR-RPT-09 | Mentor wajib mencatat kontak awal dengan sekolah sebagai checklist di laporan progres (sudah dihubungi, tanggal, catatan), dengan riwayat revisi. Kontak awal bukan kegiatan terjadwal. | Keputusan |

### 14.8 Sertifikat (CRT)
| ID | Aturan | Sumber |
|---|---|---|
| BR-CRT-01 | Sertifikat Pelatihan Awal diberikan setelah kegiatan pelatihan awal Selesai. | Keputusan |
| BR-CRT-02 | Sertifikat Tindak Lanjut diberikan satu kali setelah seluruh rangkaian selesai (setelah sesi terakhir). | Keputusan |
| BR-CRT-03 | Link sertifikat hanya dapat diinput, diubah, atau diganti oleh Admin Pusat (termasuk Superadmin tidak). | Keputusan |
| BR-CRT-04 | Link hanya dapat diinput bila tahap terkait sudah selesai. | Rekomendasi |
| BR-CRT-05 | Sertifikat boleh belum tersedia walaupun kegiatan sudah selesai. | Keputusan |
| BR-CRT-06 | Tidak semua kegiatan otomatis memiliki sertifikat. Sesi TL individual tidak memiliki sertifikat. | Keputusan |
| BR-CRT-07 | Link sertifikat dapat dibuka oleh SA, AP, AC untuk sekolah cabangnya, dan mentor untuk sekolah tanggung jawabnya. Pengelolaan link tetap hanya AP. | Keputusan |

### 14.9 Klasemen (RNK)
| ID | Aturan | Sumber |
|---|---|---|
| BR-RNK-01 | Jumlah dihitung berdasarkan sekolah unik per kategori, bukan jumlah sesi. | Keputusan |
| BR-RNK-02 | Sekolah dengan beberapa kategori terhitung di setiap kategorinya. | Keputusan |
| BR-RNK-03 | Data sepanjang waktu, cakupan seluruh Jawa Tengah, terlihat oleh seluruh admin dan mentor. | Keputusan |
| BR-RNK-04 | Hanya sekolah yang sudah resmi mitra yang dihitung. Sekolah mitra yang kemudian diarsipkan tetap dihitung (R-04). | Keputusan / Rekomendasi (arsip) |

### 14.10 Dashboard & Notifikasi
| ID | Aturan | Sumber |
|---|---|---|
| BR-DSH-01 | Data dashboard mengikuti cakupan role, kecuali klasemen yang selalu global. | Keputusan |
| BR-DSH-02 | "Kegiatan terjadwal" = kegiatan berstatus Belum Dimulai atau Sedang Berlangsung. | Keputusan |
| BR-DSH-03 | "Kegiatan belum terlaksana" = berstatus Belum Dimulai dengan tanggal sudah lewat. | Keputusan |
| BR-DSH-04 | "Kegiatan belum diperbarui" = sudah H+1 dari tanggal kegiatan dan status belum Selesai/Dibatalkan. | Keputusan (PD-14) |
| BR-DSH-05 | Perbandingan cabang memakai metrik: jumlah sekolah mitra, % sesi selesai (R-03), jumlah kegiatan belum diperbarui, jumlah sertifikat Menunggu Penerbitan. AC melihat agregat seluruh cabang tanpa detail sekolah cabang lain. | Keputusan |
| BR-NTF-01 | Notifikasi dikirim kepada mentor terkait, Admin Pusat, dan Admin Cabang yang menaungi sekolah. Pengingat jadwal H-1; pengingat laporan H+1 bila status belum Selesai/Dibatalkan. Rangkaian TL selesai → AP. | Keputusan |
| BR-NTF-02 | Notifikasi tidak dikirim ke pelaku aksi itu sendiri. | Rekomendasi |
| BR-NTF-03 | Notifikasi disimpan minimal 90 hari. | Rekomendasi |

### 14.11 Audit (AUD)
| ID | Aturan | Sumber |
|---|---|---|
| BR-AUD-01 | Audit trail wajib dan hanya dapat dilihat Superadmin. | Keputusan |
| BR-AUD-02 | Akun nonaktif tetap mempertahankan riwayat aktivitasnya. | Keputusan |
| BR-AUD-03 | Log audit bersifat append-only dan tidak dapat diubah atau dihapus melalui aplikasi. | Rekomendasi |

### 14.12 Pengguna & Master (USR)
| ID | Aturan | Sumber |
|---|---|---|
| BR-USR-01 | Hanya Superadmin yang membuat, mengubah, dan menonaktifkan akun, termasuk akun mentor. Admin Pusat tidak punya hak pengelolaan akun. | Keputusan |
| BR-USR-02 | Data master hanya dikelola Superadmin. | Keputusan |
| BR-USR-03 | Jenis kegiatan: Pelatihan, Pendampingan. Metode: Online, Offline. | Keputusan |

---

## 15. Status & Workflow Definitions

Status dipisahkan menjadi lima dimensi agar tidak tumpang tindih. Hanya **Status Kegiatan** yang diinput manual oleh mentor. Dimensi lain **diturunkan otomatis** sehingga tidak ada dua sumber kebenaran.

| Dimensi | Melekat pada | Diisi oleh | Dasar |
|---|---|---|---|
| 15.1 Status Kegiatan | Setiap kegiatan | Mentor (manual) | **[KEPUTUSAN]** poin 7 |
| 15.2 Status Jadwal | Setiap kegiatan | Sistem (turunan riwayat) | Kebutuhan riwayat reschedule |
| 15.3 Posisi Tahap Sekolah | Sekolah | Sistem (turunan, bukan status) | Keputusan no. 4 dan 11; label **[REKOMENDASI]** |
| 15.4 Status Sertifikat | Sertifikat per tahap per sekolah | Sistem (turunan) | **[REKOMENDASI]** |
| 15.5 Progres Tindak Lanjut | Sekolah | Sistem (turunan) | **[REKOMENDASI]** |

### 15.1 Status Kegiatan **[KEPUTUSAN]**

| Status | Arti |
|---|---|
| Belum Dimulai | Terjadwal, belum dilaksanakan (status awal) |
| Sedang Berlangsung | Pelaksanaan sedang berjalan |
| Selesai | Kegiatan terlaksana |
| Dibatalkan | Kegiatan tidak dilaksanakan |

Transisi yang diizinkan **[REKOMENDASI]**:

```
Belum Dimulai ──► Sedang Berlangsung ──► Selesai
      │                   │
      └──► Selesai        └──► Dibatalkan
      └──► Dibatalkan
Selesai/Dibatalkan ──► (koreksi) status lain: diizinkan dengan catatan wajib dan tercatat sebagai revisi,
                       kecuali BR-AWL-04
```

### 15.2 Status Jadwal (turunan)

| Label | Kondisi |
|---|---|
| Sesuai Rencana | Tidak ada riwayat perubahan jadwal |
| Dijadwalkan Ulang (n×) | Ada n versi jadwal sebelumnya |
| Perlu Pembaruan | Sudah H+1 dari tanggal kegiatan dan status belum Selesai/Dibatalkan **[KEPUTUSAN]** |

### 15.3 Posisi Tahap Sekolah (turunan, bukan status)

**[KEPUTUSAN]** Status hanya berlaku per kegiatan/sesi, bukan untuk sekolah secara keseluruhan. Sekolah tidak memiliki field status yang diinput siapa pun. Yang ada hanya *posisi tahap*, dihitung otomatis dari status kegiatannya, untuk filter dan penghitungan dashboard.

| Posisi | Kondisi turunan |
|---|---|
| Calon Mitra | Belum ada pelatihan awal berstatus Selesai |
| Mitra – Belum Tindak Lanjut | Pelatihan awal Selesai (keputusan no. 4), belum ada sesi TL yang tidak Dibatalkan |
| Mitra – Tindak Lanjut | Ada minimal satu sesi TL yang tidak Dibatalkan, rangkaian belum selesai |
| Selesai Program | AP sudah menetapkan sesi terakhir, sesi terakhir berstatus Selesai, dan seluruh sesi yang tidak Dibatalkan berstatus Selesai |

Label "Sudah Berjalan / Hampir Selesai / Selesai" dari kebutuhan awal tidak dipakai sebagai status sekolah. Sekolah yang mundur tidak diberi status; sekolah diarsipkan dengan alasan (BR-SCH-05) dan tampil di tab Arsip.

### 15.4 Status Sertifikat (turunan)

| Status | Kondisi |
|---|---|
| Belum Berlaku | Tahap terkait belum selesai |
| Menunggu Penerbitan | Tahap terkait selesai, link kosong |
| Tersedia | Link sudah diinput AP |

### 15.5 Progres Tindak Lanjut (turunan)

Format tampilan: `x/y sesi selesai`, dengan x = jumlah sesi Selesai dan y = jumlah sesi yang tidak Dibatalkan. Ditambah ringkasan komposisi "Online a · Offline b" dan pembanding juklak "acuan 4+1" sebagai informasi saja.

---

## 16. Data Requirements

Tipe data ditulis secara logis dan tidak mengikat teknologi basis data.

### 16.1 Sekolah
| Field | Tipe | Wajib | Catatan |
|---|---|:-:|---|
| id | ID | ✔ | |
| nama | Teks | ✔ | Disimpan juga versi ternormalisasi untuk cek duplikat |
| jenjang | Enum (SMA, SMK, MA) | ✔ | Keputusan |
| alamat | Teks | ✖ | |
| kabupaten_kota_id | FK | ✔ | |
| cabang_id | FK | ✔ | Terkunci setelah tersimpan; tidak ada transfer. Koreksi oleh SA hanya bila belum ada kegiatan (BR-SCH-12) |
| posisi_tahap | Enum (turunan/cache) | ✔ | 15.3. Bukan status yang diinput |
| tanggal_mitra | Tanggal | ✖ | Diisi otomatis |
| status_arsip | Boolean | ✔ | |
| alasan_arsip, diarsipkan_oleh, waktu_arsip | Teks/FK/Waktu | Saat arsip | Alasan wajib |
| dibuat/diubah oleh & waktu | Metadata | ✔ | |

### 16.2 Sekolah_Kategori
| Field | Tipe | Wajib | Catatan |
|---|---|:-:|---|
| sekolah_id | FK | ✔ | Unik bersama kategori_id |
| kategori_id | FK | ✔ | |
| tanggal_ditambahkan | Tanggal | ✔ | Persiapan filter periode (P2) |

### 16.3 Narahubung
| Field | Tipe | Wajib |
|---|---|:-:|
| id, sekolah_id | ID/FK | ✔ |
| nama | Teks | ✔ |
| jabatan | Teks | ✖ |
| nomor WhatsApp | Teks | ✔ |
| email | Teks | ✖ |
| is_utama | Boolean | ✔ |
| status_aktif | Boolean | ✔ |

**Usulan_Narahubung:** id, sekolah_id, narahubung_id (kosong bila menambah narahubung baru), data_sebelum, data_usulan, diusulkan_oleh (mentor), waktu_pengajuan, status (Diajukan / Disetujui / Ditolak), diputuskan_oleh (AP atau AC), waktu_keputusan, alasan_penolakan **[REKOMENDASI]**.

**Riwayat_Narahubung:** id, narahubung_id, data_sebelum, data_sesudah, diubah_oleh, usulan_id (bila berasal dari usulan mentor), waktu. Narahubung tidak dihapus, hanya dinonaktifkan.

### 16.4 Pengguna
| Field | Tipe | Wajib | Catatan |
|---|---|:-:|---|
| id | ID | ✔ | |
| nama, email/username | Teks | ✔ | Email/username unik |
| telepon | Teks | ✖ | |
| role | Enum (SA, AP, AC, M) | ✔ | |
| cabang_id | FK | AC: ✔ | Mentor: opsional, sebagai informasi saja |
| password_hash | Teks | ✔ | |
| status_aktif | Boolean | ✔ | |
| last_login | Waktu | ✖ | |

### 16.5 Penugasan_Mentor
| Field | Tipe | Wajib |
|---|---|:-:|
| id, sekolah_id, mentor_id | ID/FK | ✔ |
| mulai, selesai | Tanggal | mulai ✔ |
| ditugaskan_oleh | FK pengguna | ✔ |

Aturan: maksimal satu penugasan aktif (selesai = kosong) per sekolah **[KEPUTUSAN]**.

### 16.6 Kegiatan
| Field | Tipe | Wajib | Catatan |
|---|---|:-:|---|
| id, sekolah_id | ID/FK | ✔ | |
| tahap | Enum (AWAL, TINDAK_LANJUT) | ✔ | |
| urutan | Angka | TL: ✔ | Unik per sekolah per tahap |
| nama_sesi | Teks | ✔ | Default "Pelatihan Awal" atau "Sesi n" |
| jenis_kegiatan_id | FK | ✔ | |
| metode_id | FK | ✔ | |
| tanggal | Tanggal | ✔ | |
| waktu_mulai, waktu_selesai | Waktu (WIB) | ✔ | selesai > mulai |
| lokasi | Teks | offline: ✔ | |
| tautan_pertemuan | URL | online: ✔ | |
| mentor_id | FK | ✔ | Mentor pelaksana. Default mentor penanggung jawab; AP boleh menunjuk mentor lain |
| dibuat_oleh | FK | ✔ | Admin atau mentor |
| keterangan | Teks | ✖ | |
| status | Enum 15.1 | ✔ | |
| is_sesi_terakhir | Boolean | TL | Ditetapkan AP saja |
| jumlah_reschedule | Angka (turunan) | | |

### 16.7 Riwayat_Jadwal
| Field | Tipe | Wajib |
|---|---|:-:|
| id, kegiatan_id | ID/FK | ✔ |
| versi | Angka | ✔ |
| snapshot sebelum (tanggal, waktu, lokasi, tautan, metode) | JSON/kolom | ✔ |
| snapshot sesudah | JSON/kolom | ✔ |
| alasan | Teks | ✔ (Rekomendasi) |
| diubah_oleh, waktu | FK/Waktu | ✔ |

### 16.8 Laporan_Revisi
| Field | Tipe | Wajib |
|---|---|:-:|
| id, kegiatan_id | ID/FK | ✔ |
| versi | Angka | ✔ |
| status | Enum 15.1 | ✔ |
| catatan | Teks | ✔ |
| ditulis_oleh | FK pengguna (mentor atau AP) | ✔ |
| alasan_perubahan_admin | Teks | Wajib bila ditulis AP |
| waktu | Waktu | ✔ |

Revisi terbaru adalah laporan aktif. Status kegiatan disinkronkan dengan revisi terbaru.

### 16.9 Sertifikat
| Field | Tipe | Wajib | Catatan |
|---|---|:-:|---|
| id, sekolah_id | ID/FK | ✔ | Unik (sekolah_id, tahap) |
| tahap | Enum (AWAL, TINDAK_LANJUT) | ✔ | |
| kegiatan_acuan_id | FK | ✔ | Pelatihan awal atau sesi terakhir |
| link | URL | ✖ | Kosong = Menunggu Penerbitan |
| tanggal_terbit | Tanggal | ✖ | |
| diinput_oleh, waktu | FK/Waktu | ✖ | |

### 16.10 Master
- **Cabang**: id, kode, nama, alamat, aktif.
- **Kabupaten_Kota**: id, kode, nama.
- **Kategori_Pelatihan**: id, nama, deskripsi, aktif.
- **Jenis_Kegiatan**: id, nama, aktif.
- **Metode_Kegiatan**: id, nama, butuh_lokasi, butuh_tautan, aktif.

### 16.11 Notifikasi
id, penerima_id, tipe, judul, pesan, ref_entitas, ref_id, dibaca (boolean), waktu.

### 16.12 Audit_Log
id, waktu, pengguna_id, role_saat_aksi, aksi, entitas, entitas_id, nilai_sebelum, nilai_sesudah, ip, user_agent.

### 16.13 Kontak_Awal (checklist mentor)
id, sekolah_id, mentor_id, sudah_dihubungi (boolean), tanggal_kontak, catatan, versi, waktu. Setiap edit membentuk versi baru. Bukan kegiatan terjadwal **[KEPUTUSAN]**.

### 16.14 Riwayat_Rangkaian_TL
id, sekolah_id, aksi (tetapkan sesi terakhir / selesai otomatis / buka kembali), alasan, oleh (AP atau sistem), waktu.

---

## 17. Entity Relationship Overview

```
Cabang 1───* Sekolah *───1 Kabupaten_Kota
                │
   ┌────────────┼──────────────┬───────────────┬──────────────┐
   │            │              │               │              │
   * Narahubung ──* Riwayat_Narahubung
   * Usulan_Narahubung (mentor → disetujui/ditolak AP/AC)
   * Kontak_Awal (checklist, berversi)
   * Riwayat_Rangkaian_TL
   * Sekolah_Kategori *──1 Kategori_Pelatihan
                │
                * Penugasan_Mentor *──1 Pengguna(role=Mentor)
                │
                * Kegiatan ──* Riwayat_Jadwal
                │   │  └─────* Laporan_Revisi *──1 Pengguna(Mentor)
                │   ├──1 Jenis_Kegiatan
                │   ├──1 Metode_Kegiatan
                │   └──1 Pengguna(Mentor pelaksana)
                │
                * Sertifikat (maks 2: AWAL, TINDAK_LANJUT) ──1 Kegiatan acuan

Pengguna 1───* Notifikasi
Pengguna 1───* Audit_Log
Cabang 1───* Pengguna(role=Admin Cabang)
```

Kardinalitas kunci:
- Sekolah 1 : 0..1 Kegiatan AWAL aktif (riwayat yang Dibatalkan boleh lebih dari satu).
- Sekolah 1 : 0..n Kegiatan TINDAK_LANJUT.
- Sekolah 1 : 0..2 Sertifikat.
- Sekolah n : m Kategori.
- Sekolah 1 : 0..1 Penugasan mentor aktif, dengan 0..n riwayat.

---

## 18. User Stories

### Autentikasi & Pengguna
- **US-01** As a pengguna, I want to login dengan akun saya, so that saya dapat mengakses data sesuai kewenangan.
- **US-02** As a Superadmin, I want to membuat dan menonaktifkan akun beserta rolenya, so that hanya orang yang berhak yang mengakses sistem.
- **US-03** As a Superadmin, I want to riwayat aktivitas pengguna nonaktif tetap tersimpan, so that jejak audit tidak hilang.

### Sekolah
- **US-04** As an Admin Pusat, I want to menambahkan sekolah beserta cabang, kabupaten, kategori, dan narahubung, so that sekolah tercatat di satu tempat.
- **US-05** As an Admin Cabang, I want to hanya melihat dan mengelola sekolah cabang saya, so that saya fokus dan tidak mengubah data cabang lain.
- **US-06** As an admin, I want to diperingatkan bila sekolah yang saya input mirip dengan yang sudah ada, so that tidak terjadi duplikasi.
- **US-07** As an admin, I want to mencari dan memfilter sekolah berdasarkan kabupaten, cabang, status, mentor, dan kategori, so that saya cepat menemukan sekolah yang relevan.
- **US-08** As an admin, I want to melihat detail sekolah dengan riwayat pelatihan awal dan tindak lanjut terpisah, so that perkembangan sekolah jelas.

### Mentor & Penugasan
- **US-09** As an Admin Pusat, I want to menugaskan mentor ke sekolah dari cabang mana pun, so that setiap sekolah punya penanggung jawab.
- **US-10** As an Admin Pusat, I want to mengganti mentor dengan riwayat tersimpan, so that pergantian tanggung jawab dapat ditelusuri.
- **US-11** As a Mentor, I want to melihat daftar sekolah dan kegiatan yang ditugaskan kepada saya, so that saya tahu pekerjaan saya.
- **US-12** As a Mentor, I want to melihat seluruh sekolah di Jawa Tengah, so that saya punya konteks program secara menyeluruh.

### Jadwal
- **US-13** As an admin, I want to membuat jadwal pelatihan awal untuk satu sekolah, so that sekolah dapat memulai program.
- **US-14** As an admin, I want to membuat sesi tindak lanjut sebanyak yang dibutuhkan, online maupun offline, so that realisasi lapangan tercatat apa adanya.
- **US-15** As an admin, I want to melihat perbandingan jumlah sesi dengan acuan juklak, so that saya tahu deviasinya tanpa sistem memblokir.
- **US-16** As a Mentor, I want to mengubah jadwal langsung saat sekolah meminta, so that jadwal selalu mutakhir tanpa menunggu admin.
- **US-17** As an admin, I want to mendapat notifikasi saat mentor mengubah jadwal, so that saya selalu tahu perubahan.
- **US-18** As an admin, I want to melihat riwayat versi jadwal, so that saya tahu jadwal awal dan perubahannya.

### Laporan Progres
- **US-19** As a Mentor, I want to mengubah status kegiatan dan menulis catatan dalam satu langkah, so that pelaporan cepat.
- **US-20** As a Mentor, I want to mengedit laporan yang keliru, so that data benar, dengan riwayat revisi tetap tersimpan.
- **US-21** As an admin, I want to melihat progres semua sekolah dalam satu tabel, so that saya tidak perlu membuka laporan satu per satu.
- **US-22** As an admin, I want to melihat kegiatan yang tanggalnya lewat tetapi belum diperbarui, so that saya dapat menindaklanjuti mentor.

### Sertifikat
- **US-23** As an Admin Pusat, I want to menginput link sertifikat pelatihan awal dan tindak lanjut, so that sertifikat dapat diakses.
- **US-24** As an Admin Pusat, I want to melihat daftar tahap selesai yang sertifikatnya belum tersedia, so that tidak ada sertifikat yang terlewat.
- **US-25** As an Admin Cabang or Mentor, I want to melihat status sertifikat, so that saya dapat menjawab pertanyaan sekolah.

### Dashboard & Klasemen
- **US-26** As an admin, I want to melihat ringkasan jumlah sekolah per tahap, kegiatan, dan sertifikat, so that saya memahami kondisi program sekilas.
- **US-27** As an admin, I want to melihat klasemen kategori berdasarkan jumlah sekolah unik, so that saya tahu kategori paling diminati.

### Audit & Notifikasi
- **US-28** As a Superadmin, I want to melihat audit trail dengan nilai sebelum dan sesudah, so that setiap perubahan dapat dipertanggungjawabkan.
- **US-29** As a pengguna, I want to melihat notifikasi dalam aplikasi, so that saya tahu peristiwa penting yang menyangkut saya.

### Tambahan v1.2
- **US-30** As a Mentor, I want to menambah sesi Tindak Lanjut untuk sekolah tanggung jawab saya, so that permintaan sesi dari sekolah langsung tercatat.
- **US-31** As an Admin Pusat, I want to menetapkan sesi terakhir dan membuka kembali rangkaian bila perlu, so that penyelesaian rangkaian dan sertifikat terkendali.
- **US-32** As an Admin Pusat, I want to mengubah status laporan mentor dengan alasan, so that kesalahan dapat dikoreksi tanpa menghapus riwayat.
- **US-33** As a Mentor, I want to mencentang bahwa sekolah sudah saya hubungi, so that admin tahu kontak awal sudah dilakukan.
- **US-34** As a Mentor, I want to mengusulkan perubahan narahubung, so that data kontak sekolah tetap akurat.
- **US-35** As an Admin Cabang, I want to melihat perbandingan agregat performa seluruh cabang, so that saya tahu posisi cabang saya.
- **US-36** As an admin, I want to mengarsipkan sekolah yang mundur dengan alasan, so that daftar aktif bersih tanpa kehilangan riwayat.
- **US-37** As an Admin Pusat, I want to menunjuk mentor pelaksana berbeda untuk sesi tertentu, so that sesi tetap berjalan saat mentor penanggung jawab berhalangan.

---

## 19. Acceptance Criteria

Format: **Given** (kondisi) / **When** (aksi) / **Then** (hasil).

### Autentikasi & Akses
- **AC-AUTH-01** Given kredensial valid, when login, then pengguna diarahkan ke Dashboard sesuai role dalam ≤ 3 detik.
- **AC-AUTH-02** Given kredensial salah, when login, then muncul pesan generik "Email/username atau password salah" dan percobaan tercatat di audit trail.
- **AC-AUTH-03** Given akun nonaktif, when login, then akses ditolak; dan riwayat aktivitas akun tetap tampil di audit trail.
- **AC-AUTH-04** Given AC cabang A, when mengakses URL/API detail sekolah cabang B, then server mengembalikan penolakan akses (403) dan tidak ada data yang bocor.
- **AC-AUTH-05** Given sesi tidak aktif melewati batas waktu (PD-27), when pengguna melakukan aksi, then pengguna diminta login ulang.

### Sekolah
- **AC-SCH-01** Given AP, when menyimpan sekolah tanpa cabang atau kabupaten, then penyimpanan ditolak dengan pesan field wajib.
- **AC-SCH-02** Given ada "SMAN 1 Semarang" di Kota Semarang, when admin input "sman 1  semarang" di Kota Semarang, then penyimpanan ditolak sebagai duplikat.
- **AC-SCH-03** Given input nama yang mirip (bukan identik), when mengetik nama, then sistem menampilkan daftar sekolah serupa sebagai peringatan.
- **AC-SCH-04** Given AC cabang A, when membuka daftar sekolah, then hanya sekolah cabang A yang tampil, dan filter cabang terkunci pada A.
- **AC-SCH-05** Given sekolah dengan kategori X dan Y, when disimpan, then sekolah muncul pada filter kategori X dan filter kategori Y.
- **AC-SCH-06** Given daftar sekolah, when memakai pencarian "15", then tampil sekolah yang namanya mengandung "15" dalam ≤ 2 detik untuk 5.000 data.
- **AC-SCH-07** Given kombinasi filter kabupaten + status + mentor, when diterapkan, then hasil memenuhi semua filter (logika AND).
- **AC-SCH-08** Given daftar sekolah, then setiap baris menampilkan status tahap, mentor, narahubung utama, progres TL, dan status sertifikat kedua tahap.
- **AC-SCH-09** Given detail sekolah, then pelatihan awal dan tindak lanjut tampil di bagian/tab terpisah dan tidak ada kegiatan yang muncul di keduanya.
- **AC-SCH-10** Given sertifikat Tersedia dan pengguna berhak, when klik link, then link terbuka di tab baru.
- **AC-SCH-11** Given sekolah yang sudah memiliki kegiatan, when admin mencoba menghapus, then sistem hanya menawarkan arsip.
- **AC-SCH-12** Given narahubung tanpa nama atau nomor WhatsApp, atau sekolah tanpa tepat satu narahubung utama, when disimpan, then ditolak.
- **AC-SCH-13** Given sekolah sudah tersimpan, then field cabang tampil read-only untuk AP, AC, dan M, tidak ada menu transfer, dan permintaan API pengubahan cabang dari role tersebut ditolak (403).
- **AC-SCH-18** Given sekolah tanpa kegiatan, when SA mengoreksi cabang dengan alasan, then berhasil dan audit trail mencatat cabang lama, cabang baru, dan alasan. Given tanpa alasan, then ditolak.
- **AC-SCH-19** Given sekolah yang sudah memiliki minimal satu kegiatan (termasuk yang Dibatalkan), when SA mencoba mengoreksi cabang, then ditolak.
- **AC-SCH-20** Given sekolah X diarsipkan, when admin mendaftarkan sekolah dengan nama + jenjang + kabupaten yang sama, then ditolak sebagai duplikat.
- **AC-SCH-21** Given mentor mengusulkan perubahan narahubung, then data narahubung belum berubah, usulan berstatus Diajukan, dan AP serta AC cabang sekolah menerima notifikasi.
- **AC-SCH-22** Given AC cabang sekolah atau AP menyetujui usulan, then data narahubung berubah dan riwayat mencatat data sebelum, data usulan, pengusul, pemutus, waktu pengajuan, dan waktu keputusan. Given ditolak, then data tidak berubah dan penolakan tercatat.
- **AC-SCH-23** Given AC cabang lain atau mentor, when mencoba menyetujui usulan, then ditolak (403).
- **AC-SCH-14** Given AC cabang A mendaftarkan sekolah, then cabang otomatis A dan tidak dapat dipilih lain. Given AP mendaftarkan sekolah, then AP wajib memilih cabang secara manual.
- **AC-SCH-15** Given AP/AC mengarsipkan sekolah tanpa alasan, then ditolak. Given dengan alasan, then sekolah pindah ke tab Arsip dan seluruh riwayat tetap dapat dibuka.
- **AC-SCH-16** Given narahubung diubah, then versi sebelumnya tetap tampil di riwayat narahubung.
- **AC-SCH-17** Given AC cabang A, when mengakses detail sekolah cabang B, then ditolak (403).

### Jadwal Pelatihan Awal
- **AC-JDA-01** Given sekolah tanpa pelatihan awal aktif, when admin membuat jadwal lengkap, then kegiatan tersimpan dengan status "Belum Dimulai".
- **AC-JDA-02** Given sekolah sudah memiliki pelatihan awal yang tidak Dibatalkan, when admin membuat pelatihan awal kedua, then ditolak dengan pesan "Sekolah sudah memiliki pelatihan awal".
- **AC-JDA-03** Given pelatihan awal Dibatalkan, when admin membuat jadwal baru, then tercipta kegiatan baru dan kegiatan lama tetap tampil berstatus Dibatalkan.
- **AC-JDA-08** Given sekolah sudah memiliki pelatihan awal Selesai, when membuat pelatihan awal lain, then ditolak.
- **AC-JDA-04** Given form pelatihan awal, then metode terkunci Offline dan lokasi terisi kampus pusat.
- **AC-JDA-05** Given waktu selesai ≤ waktu mulai, when simpan, then ditolak.
- **AC-JDA-06** Given AC cabang A, when memilih sekolah pada form jadwal, then hanya sekolah cabang A yang tersedia.
- **AC-JDA-07** Given mentor M melaporkan pelatihan awal Selesai, then status sekolah berubah menjadi "Mitra – Belum Tindak Lanjut", tanggal mitra terisi, dan sertifikat Awal berstatus "Menunggu Penerbitan".

### Jadwal Tindak Lanjut
- **AC-JTL-01** Given sekolah berstatus Calon Mitra, when admin membuat sesi TL, then ditolak.
- **AC-JTL-02** Given sekolah Mitra dengan 2 sesi, when admin menambah sesi, then sesi baru otomatis bernomor urut 3 dengan nama default "Sesi 3".
- **AC-JTL-03** Given sekolah dengan 3 online + 1 offline, then sistem tetap menerima data dan menampilkan "Online 3 · Offline 1 (acuan juklak 4+1)" tanpa error.
- **AC-JTL-04** Given sekolah dengan 7 sesi, then sistem menerima tanpa batas.
- **AC-JTL-05** Given nama sesi diubah menjadi "Workshop Offline", then nama baru tampil di semua tampilan dan nomor urut tidak berubah.
- **AC-JTL-06** Given AP menandai sesi lain sebagai sesi terakhir, then tanda lama dipindahkan setelah konfirmasi. Given SA, AC, atau mentor, then opsi penandaan tidak tersedia dan API menolak (403).
- **AC-JTL-07** Given AP sudah menetapkan sesi terakhir, sesi terakhir berstatus Selesai, dan seluruh sesi yang tidak Dibatalkan berstatus Selesai, then posisi sekolah menjadi "Selesai Program", sertifikat TL "Menunggu Penerbitan", dan AP menerima notifikasi. Given masih ada sesi Belum Dimulai atau Sedang Berlangsung, then rangkaian belum selesai walaupun sesi terakhir sudah Selesai.
- **AC-JTL-09** Given sesi TL Offline tanpa lokasi, or Online tanpa tautan, when simpan, then ditolak.
- **AC-JTL-08** Given mentor M ditugaskan ke sekolah S, when M reschedule sesi S dengan alasan, then jadwal baru tersimpan, versi lama tersimpan di riwayat, notifikasi terkirim ke admin terkait, dan audit tercatat.
- **AC-JTL-10** Given mentor penanggung jawab sekolah S, when menambah sesi TL, then sesi tersimpan, AP dan AC cabang S menerima notifikasi jadwal baru, dan aktivitas tercatat di riwayat.
- **AC-JTL-11** Given rangkaian sudah selesai, when AP membuka kembali tanpa alasan, then ditolak. Given dengan alasan, then posisi sekolah kembali ke "Mitra – Tindak Lanjut" dan aksi tercatat di riwayat rangkaian.
- **AC-JTL-12** Given komposisi sesi berbeda dari 4+1, when disimpan, then tidak ada isian alasan yang diwajibkan.
- **AC-JTL-13** Given AP sudah menetapkan sesi terakhir, when mentor mencoba menambah sesi, then tombol tidak tersedia dan API menolak (403). Given AP menambah sesi, then AP dapat memindahkan tanda sesi terakhir ke sesi baru.
- **AC-JTL-14** Given sekolah dengan 5 sesi: 4 Selesai dan 1 Dibatalkan (bukan sesi terakhir), dan sesi terakhir termasuk yang Selesai, then rangkaian selesai.
- **AC-JTL-15** Given sesi terakhir berubah menjadi Dibatalkan, then rangkaian tidak selesai, sekolah muncul di daftar FR-JTL-08, dan AP wajib menetapkan sesi terakhir baru.
- **AC-JTL-16** Given sesi Dibatalkan, then sesi tersebut tetap tampil di riwayat Tindak Lanjut dengan status dan catatannya.

### Laporan Progres
- **AC-RPT-01** Given mentor M ditugaskan ke sekolah S, when M mengubah status kegiatan S tanpa catatan, then ditolak dengan pesan "Catatan wajib diisi".
- **AC-RPT-02** Given mentor M tidak ditugaskan ke sekolah S, when M membuka kegiatan S, then tombol ubah status dan reschedule tidak tersedia, catatan laporan detail tidak ditampilkan (hanya info koordinasi), dan permintaan langsung ke API ditolak (403).
- **AC-RPT-03** Given laporan versi 1, when M mengedit catatan, then terbentuk versi 2 dan versi 1 tetap dapat dilihat lengkap dengan waktu dan penulisnya.
- **AC-RPT-04** Given M menyimpan laporan, then laporan langsung tampil sebagai status terbaru tanpa langkah persetujuan.
- **AC-RPT-05** Given form laporan, then mentor tidak perlu mengisi tanggal, waktu, sekolah, atau metode karena semuanya diambil dari jadwal.
- **AC-RPT-06** Given halaman Progres Mentor, then admin melihat status setiap kegiatan seluruh sekolah dalam cakupannya pada satu tabel.
- **AC-RPT-07** Given kegiatan bertanggal kemarin (H+1) berstatus Belum Dimulai atau Sedang Berlangsung, then kegiatan berlabel "Perlu Pembaruan" dan notifikasi terkirim ke mentor terkait, AP, dan AC cabang.
- **AC-RPT-08** Given mentor diganti dari M1 ke M2, then M1 tidak dapat lagi menulis laporan sekolah tersebut dan laporan M1 sebelumnya tetap tampil atas nama M1.
- **AC-RPT-09** Given AP mengubah status kegiatan tanpa alasan, then ditolak. Given dengan alasan, then terbentuk revisi baru atas nama AP dan revisi mentor sebelumnya tetap tersimpan.
- **AC-RPT-10** Given SA atau AC, when mencoba mengubah status kegiatan, then aksi tidak tersedia dan API menolak (403).
- **AC-RPT-11** Given mentor mencentang "sudah dihubungi" lalu mengubahnya, then kedua versi tersimpan di riwayat checklist kontak awal.
- **AC-RPT-12** Given laporan mentor disimpan, then laporan langsung berlaku tanpa menunggu tindakan admin.

### Sertifikat
- **AC-CRT-01** Given pengguna SA, AC, atau M, when membuka kartu sertifikat, then tidak ada tombol input/ubah link, dan permintaan API ditolak (403).
- **AC-CRT-02** Given AP, when input link tidak diawali http:// atau https://, then ditolak.
- **AC-CRT-03** Given pelatihan awal belum Selesai, when AP input link sertifikat Awal, then ditolak (berlaku jika BR-CRT-04 disetujui).
- **AC-CRT-04** Given tahap selesai tanpa link, then status "Menunggu Penerbitan" dan sekolah muncul di tab Menunggu Penerbitan.
- **AC-CRT-05** Given AP mengubah link yang sudah ada, then audit trail menyimpan link lama dan baru.
- **AC-CRT-06** Given sesi TL non-terakhir berstatus Selesai, then tidak ada catatan sertifikat untuk sesi tersebut.
- **AC-CRT-07** Given dashboard, then jumlah Tersedia + Menunggu Penerbitan = jumlah tahap yang selesai dalam cakupan.
- **AC-CRT-08** Given AC cabang A atau mentor yang bukan penanggung jawab sekolah S, when mencoba membuka link sertifikat S (cabang lain/bukan tanggung jawab), then link tidak ditampilkan dan API menolak (403).

### Klasemen
- **AC-RNK-01** Given sekolah S ikut kategori A dan B, then S terhitung 1 di A dan 1 di B.
- **AC-RNK-02** Given sekolah S memiliki 5 sesi kegiatan, then S tetap terhitung 1 pada setiap kategorinya.
- **AC-RNK-03** Given klasemen, then kategori terurut menurun berdasarkan jumlah, dan kategori dengan jumlah sama mendapat peringkat sama.
- **AC-RNK-04** Given AC cabang A, when membuka klasemen, then angka yang tampil adalah total seluruh Jawa Tengah.
- **AC-RNK-05** Given kategori tanpa sekolah, then kategori tetap tampil dengan jumlah 0.
- **AC-RNK-06** Given sekolah Calon Mitra dengan kategori A, then sekolah tersebut tidak terhitung di kategori A.
- **AC-RNK-07** Given pengguna mentor, when membuka klasemen, then klasemen seluruh Jawa Tengah tampil.

### Dashboard & Notifikasi
- **AC-DSH-01** Given AC cabang A, then seluruh KPI dashboard (kecuali klasemen dan perbandingan antarcabang) hanya menghitung data cabang A.
- **AC-DSH-02** Given SA/AP, then KPI menghitung seluruh Jawa Tengah.
- **AC-DSH-03** Given total sekolah per tahap, then jumlah semua tahap = total sekolah tidak diarsipkan dalam cakupan.
- **AC-DSH-04** Given perubahan status oleh mentor, when admin memuat ulang dashboard, then angka sudah mutakhir.
- **AC-DSH-05** Given dashboard dibuka, then halaman termuat dalam ≤ 3 detik untuk 5.000 sekolah.
- **AC-DSH-06** Given SA, AP, atau AC, then tabel perbandingan cabang menampilkan untuk setiap cabang: jumlah sekolah mitra, % sesi selesai, jumlah kegiatan belum diperbarui, jumlah sertifikat Menunggu Penerbitan.
- **AC-DSH-07** Given AC cabang A, when mengklik baris cabang B pada tabel perbandingan, then tidak ada daftar sekolah cabang B yang dapat dibuka.
- **AC-NTF-01** Given mentor reschedule, then admin penerima (BR-NTF-01) melihat badge notifikasi bertambah 1 saat halaman dimuat ulang atau dalam ≤ 60 detik.
- **AC-NTF-02** Given klik notifikasi, then pengguna diarahkan ke kegiatan terkait dan notifikasi tertandai dibaca.
- **AC-NTF-03** Given aksi dilakukan oleh pengguna X, then X tidak menerima notifikasi atas aksinya sendiri.
- **AC-NTF-04** Given admin baru menugaskan mentor, then mentor menerima notifikasi penugasan.
- **AC-NTF-05** Given AP mengisi link sertifikat, then mentor sekolah terkait, AP, dan AC cabang sekolah menerima notifikasi "Sertifikat tersedia".
- **AC-NTF-06** Given sudah H+1 dari tanggal kegiatan dan status belum Selesai/Dibatalkan, then mentor dan admin terkait menerima notifikasi.
- **AC-NTF-07** Given H-1 sebelum kegiatan berstatus Belum Dimulai, then mentor terkait, AP, dan AC cabang menerima notifikasi jadwal mendatang.
- **AC-NTF-08** Given rangkaian TL selesai, then AP menerima notifikasi untuk mengurus sertifikat TL.

### Audit
- **AC-AUD-01** Given aksi create/update/delete apa pun, then satu entri audit tercatat dengan pengguna, waktu, entitas, nilai sebelum, dan nilai sesudah.
- **AC-AUD-02** Given pengguna non-SA, when mengakses halaman/API audit, then ditolak (403).
- **AC-AUD-03** Given SA, then tidak tersedia aksi untuk mengubah atau menghapus entri audit.

### Pengguna & Master
- **AC-USR-01** Given AP atau AC, when mengakses halaman/API pengguna (termasuk akun mentor), then ditolak (403).
- **AC-USR-02** Given AP atau AC, when mengakses halaman/API data master, then ditolak (403).
- **AC-USR-03** Given SA menonaktifkan akun mentor, then riwayat laporan mentor tetap tampil atas namanya.

---

## 20. UI/UX Requirements

1. **Gaya:** dashboard administratif yang profesional dan bersih, dengan sidebar kiri berisi navigasi sesuai role.
2. **Halaman prioritas:** daftar sekolah dan progresnya bisa diakses dalam satu klik dari mana saja.
3. **Tabel sekolah:** kolom wajib sesuai 13.2; status ditampilkan sebagai badge berwarna yang konsisten (lihat bawah); kolom sticky untuk nama sekolah; filter berupa chip di atas tabel; paginasi di server; jumlah hasil terlihat.
4. **Progres TL:** progress bar mini ditambah teks "x/y".
5. **Sertifikat:** ikon dan badge jelas: Tersedia (hijau, dengan tautan), Menunggu Penerbitan (kuning), Belum Berlaku (abu-abu).
6. **Detail sekolah:** header ringkas, lalu tab terpisah untuk Pelatihan Awal dan Tindak Lanjut. Timeline sesi vertikal.
7. **Aksi cepat mentor:** di "Kegiatan Saya", setiap kartu memiliki tombol status. Klik tombol membuka panel kecil berisi pilihan status + catatan + Simpan. Target: pembaruan status ≤ 3 klik.
8. **Reschedule:** satu dialog berisi tanggal, waktu, lokasi/tautan, dan alasan. Riwayat versi tampil di bawah kegiatan.
9. **Responsif:** semua halaman mentor dioptimalkan untuk lebar ≥ 360px. Tabel admin berubah menjadi kartu di ponsel.
10. **Kalender:** tampilan bulan/minggu untuk jadwal dengan warna berbeda per tahap (Awal vs TL).
11. **Bahasa:** antarmuka berbahasa Indonesia. Tanggal ditampilkan dalam format `dd MMM yyyy` dan waktu dalam WIB.
12. **Konsistensi warna status kegiatan [REKOMENDASI]:** Belum Dimulai (abu-abu), Sedang Berlangsung (biru), Selesai (hijau), Dibatalkan (merah), Perlu Pembaruan (oranye).
13. **Empty state dan konfirmasi:** setiap aksi yang mengubah status ke Dibatalkan, mengarsipkan, atau menonaktifkan meminta konfirmasi.
14. **Aksesibilitas:** status tidak hanya dibedakan dengan warna, tetapi juga dengan teks/ikon; kontras sesuai WCAG AA.
15. **Kontak awal:** checkbox "Sekolah sudah dihubungi" + tanggal + catatan pada kartu sekolah di "Sekolah Saya".
16. **Cabang terkunci:** field cabang tampil read-only setelah sekolah tersimpan.
17. **Alasan wajib:** dialog arsip sekolah, buka kembali rangkaian, dan ubah status oleh AP selalu memuat isian alasan wajib.

---

## 21. Non-Functional Requirements

Target angka bersifat **[REKOMENDASI]** dan perlu dikonfirmasi berdasarkan volume nyata (Asumsi A-04).

| Kategori | Kebutuhan |
|---|---|
| Performa | Halaman daftar dan dashboard ≤ 3 detik (p95) untuk 5.000 sekolah dan 50.000 kegiatan; aksi simpan ≤ 1 detik |
| Skalabilitas | Penambahan sekolah, cabang, kategori, dan pengguna tanpa perubahan kode (melalui data master) |
| Ketersediaan | ≥ 99% pada jam kerja |
| Responsivitas | Mendukung desktop (≥ 1280px), tablet, dan ponsel (≥ 360px); dua versi terbaru Chrome, Edge, Firefox, Safari |
| Konsistensi data | Integritas relasi wajib (FK). Status turunan dihitung ulang dalam transaksi yang sama dengan perubahan sumbernya |
| Validasi | Validasi di sisi klien dan server; server adalah sumber kebenaran |
| Duplikasi | Lihat BR-SCH-03; normalisasi nama (huruf kecil, spasi berlebih dihapus, singkatan baku seperti "SMA Negeri" menjadi "SMAN" **[REKOMENDASI]**) |
| Audit | Seluruh aksi tulis tercatat (Bagian 13.12) |
| Backup | Backup harian otomatis, retensi ≥ 30 hari, uji pemulihan berkala; target RPO ≤ 24 jam dan RTO ≤ 8 jam (PD-29) |
| Zona waktu | Seluruh waktu disimpan konsisten dan ditampilkan dalam WIB |
| Teknologi | **Belum ditentukan.** Framework, basis data, hosting, dan arsitektur diputuskan tim teknis (PD-30) |
| Maintainability | Daftar enum status dan master data dapat dikonfigurasi tanpa mengubah logika inti bila memungkinkan |

---

## 22. Reporting & Dashboard Requirements

### 22.1 KPI Dashboard (Admin)

| KPI | Definisi | Cakupan |
|---|---|---|
| Total Sekolah Terdaftar | Sekolah tidak diarsipkan | Role |
| Total Sekolah Mitra | Posisi tahap ∈ {Mitra – Belum TL, Mitra – TL, Selesai Program} | Role |
| Sekolah dalam Pelatihan Awal | Calon Mitra dengan pelatihan awal aktif (tidak Dibatalkan) | Role |
| Sekolah dalam Tindak Lanjut | Posisi Mitra – Tindak Lanjut | Role |
| Sekolah Selesai Program | Posisi Selesai Program | Role |
| Kegiatan Terjadwal | BR-DSH-02 | Role |
| Kegiatan Belum Terlaksana | BR-DSH-03 | Role |
| Kegiatan Belum Diperbarui | BR-DSH-04 | Role |
| Sertifikat Tersedia / Menunggu Penerbitan | 15.4, dipisah Awal dan TL | Role |
| Klasemen Kategori (ringkas) | Bagian 13.8 | Global |

### 22.2 Visualisasi
- Grafik donat/batang: distribusi sekolah per status tahap.
- Grafik batang bertumpuk: sertifikat Tersedia vs Menunggu per tahap.
- Grafik batang horizontal: klasemen kategori.
- Perbandingan performa antarcabang **[KEPUTUSAN]**: tabel dan grafik per cabang dengan metrik BR-DSH-05. SA, AP, dan AC melihat agregat seluruh cabang; AC tidak dapat membuka detail sekolah cabang lain.

### 22.3 Dashboard Mentor **[REKOMENDASI]**
- Jumlah sekolah yang ditugaskan, kegiatan minggu ini, dan kegiatan yang perlu pembaruan.
- Daftar kegiatan hari ini dan mendatang.

### 22.4 Laporan
- MVP: laporan berupa tampilan di layar (daftar sekolah terfilter, progres mentor, sertifikat).
- P2: ekspor Excel **[KEPUTUSAN]**.

---

## 23. Security Requirements

1. **Autentikasi:** password disimpan dengan hashing yang kuat dan ber-salt. Kebijakan panjang minimal 8 karakter **[REKOMENDASI]**. Pembatasan percobaan login (rate limiting/lockout sementara).
2. **Otorisasi:** RBAC ditambah scoping cabang dan scoping penugasan mentor, diperiksa di server untuk setiap endpoint.
3. **Sesi:** token/sesi dengan kedaluwarsa (PD-27), cookie aman (HttpOnly, Secure, SameSite) bila memakai cookie.
4. **Transport:** seluruh lalu lintas melalui HTTPS.
5. **Proteksi umum:** pencegahan SQL injection, XSS (escape catatan mentor dan input teks), CSRF, dan IDOR (ID objek selalu dicek kepemilikan/cakupannya).
6. **Link sertifikat dan tautan pertemuan:** hanya ditampilkan kepada pengguna yang login dan berhak. Link ditampilkan apa adanya dan dibuka dengan `rel="noopener noreferrer"`. Pengaturan izin di sisi penyedia file (misal Google Drive) adalah tanggung jawab AP dan berada di luar kendali sistem.
7. **Data pribadi:** nomor telepon/email narahubung dan pengguna hanya terlihat oleh pengguna yang login. Tidak ada endpoint publik.
8. **Audit:** log append-only, hanya SA yang dapat melihat, termasuk login gagal.
9. **Akun nonaktif:** sesi aktifnya langsung dicabut saat dinonaktifkan.
10. **Backup:** terenkripsi dan disimpan terpisah dari server utama **[REKOMENDASI]**.

---

## 24. Edge Cases

| # | Kasus | Penanganan |
|---|---|---|
| E1 | Sekolah ikut beberapa kategori | Terhitung di tiap kategori (BR-RNK-02) |
| E2 | Pelatihan awal dibatalkan | Dijadwalkan ulang sebagai kegiatan baru; yang lama tetap berstatus Dibatalkan **[KEPUTUSAN]** |
| E3 | Mentor diganti di tengah rangkaian TL | Sesi mendatang otomatis berpindah ke mentor baru, kecuali sesi yang mentor pelaksananya ditunjuk khusus oleh AP **[REKOMENDASI]**; laporan lama tetap atas nama mentor lama |
| E4 | Mentor dinonaktifkan dengan penugasan aktif | Peringatan dan paksa penugasan ulang sebelum nonaktif (BR-MNT-04) |
| E5 | Permintaan memindah sekolah ke cabang lain | Tidak didukung **[KEPUTUSAN]**: cabang terkunci dan tidak ada fitur transfer. Salah input pada sekolah tanpa kegiatan dikoreksi SA (BR-SCH-12) |
| E6 | Rangkaian sudah selesai, lalu sekolah meminta sesi tambahan | AP membuka kembali rangkaian dengan alasan, sesi ditambahkan, lalu AP menetapkan sesi terakhir baru **[KEPUTUSAN]**. Link sertifikat TL yang sudah ada tetap tersimpan **[REKOMENDASI]** |
| E7 | Mentor menandai Selesai pada kegiatan bertanggal masa depan | Ditolak (BR-RPT-07); mentor diminta reschedule ke tanggal pelaksanaan |
| E8 | Status pelatihan awal dikoreksi dari Selesai ke status lain | Sekolah kembali menjadi Calon Mitra bila belum ada sesi TL dan sertifikat Awal belum Tersedia; selain itu ditolak |
| E9 | Dua pengguna mengedit kegiatan bersamaan | Optimistic locking: penyimpanan kedua ditolak dengan pesan "Data telah diubah pengguna lain, muat ulang" |
| E10 | Admin menghapus kegiatan yang sudah ada laporan | Ditolak, arahkan ke status Dibatalkan (BR-ACT-09) |
| E11 | Kategori dinonaktifkan padahal masih dipakai sekolah | Kategori tetap tercatat di sekolah dan klasemen (dengan label nonaktif); tidak dapat dipilih untuk sekolah baru |
| E12 | Nama sekolah berubah (misal penggantian nomenklatur) | Nama diedit, riwayat di audit; pengecekan duplikat dijalankan ulang |
| E13 | Link sertifikat rusak atau salah | AP mengubah link; nilai lama tersimpan di audit |
| E14 | Sekolah mundur dari program | Kegiatan yang belum selesai dibatalkan dengan alasan; sekolah diarsipkan AP/AC sesuai cakupan dengan alasan wajib; riwayat tidak dihapus **[KEPUTUSAN]** |
| E15 | Sesi TL dibatalkan | Tidak dihitung dalam y pada progres "x/y"; nomor urut tidak digeser. Tidak wajib diselesaikan dalam syarat penyelesaian rangkaian; riwayat tetap disimpan **[KEPUTUSAN]** |
| E16 | Mentor reschedule berkali-kali | Setiap versi tersimpan; label "Dijadwalkan Ulang (n×)" |
| E17 | AC mencoba membuat jadwal untuk sekolah yang mentornya dari cabang lain | Diizinkan karena yang menentukan adalah cabang sekolah, bukan cabang mentor |
| E18 | Sekolah belum memiliki mentor saat jadwal dibuat | Ditolak; tetapkan mentor penanggung jawab terlebih dahulu **[REKOMENDASI]** |
| E19 | Mentor perlu sesi tambahan setelah AP menetapkan sesi terakhir | Mentor tidak dapat menambah sendiri; mentor mengajukan atau menginformasikan ke AP, lalu AP menambah sesi dan memperbarui sesi terakhir **[KEPUTUSAN]**. Tidak ada form pengajuan khusus di MVP (R-06) |
| E20 | Sesi yang ditetapkan sebagai sesi terakhir berstatus Dibatalkan | Rangkaian tidak selesai; AP wajib menetapkan sesi lain dan mendapat notifikasi (R-07) **[KEPUTUSAN]** |
| E21 | Mentor mengusulkan perubahan narahubung | Tidak langsung berlaku; menunggu persetujuan AC cabang atau AP; seluruh jejak usulan tersimpan **[KEPUTUSAN]** |
| E23 | Dua usulan narahubung untuk data yang sama diajukan bersamaan | Usulan yang disetujui lebih dulu berlaku; usulan lain ditandai "perlu ditinjau ulang" karena data sebelumnya sudah berubah **[REKOMENDASI]** |
| E22 | Semua sesi Selesai, tetapi AP belum menetapkan sesi terakhir | Rangkaian belum selesai; sekolah muncul di daftar FR-JTL-08 |

---

## 25. Dependencies

| # | Ketergantungan | Dampak |
|---|---|---|
| D1 | Keputusan tim teknis atas PD teknis (27.5) dan validasi asumsi (27.3) | Arsitektur dan target NFR |
| D2 | Data awal: daftar cabang, pemetaan kabupaten ke cabang, daftar sekolah, daftar mentor | Go-live |
| D3 | Keputusan teknologi dan hosting (PD-30) | Arsitektur dan jadwal |
| D4 | Penyedia penyimpanan sertifikat eksternal (misal Google Drive) | Ketersediaan link sertifikat |
| D6 | Juklak resmi program | Acuan tampilan pola 4+1 |

---

## 26. Risks & Constraints

| # | Risiko / Kendala | Dampak | Mitigasi |
|---|---|---|---|
| R1 | Penyelesaian rangkaian bergantung pada penetapan sesi terakhir oleh AP; bila terlewat, rangkaian tidak pernah selesai | Sedang | Daftar FR-JTL-08 dan notifikasi |
| R2 | Mentor dapat mengubah jadwal dan status tanpa verifikasi | Sedang | Riwayat versi, notifikasi admin, audit trail, dan catatan wajib |
| R3 | Mentor melihat seluruh data sekolah se-Jateng, termasuk kontak narahubung | Sedang | Hanya baca; akses tercatat; ditinjau ulang setelah MVP (keputusan bersifat "sementara") |
| R4 | Tanpa impor, seluruh data awal diinput manual: beban input besar dan rawan duplikat | Tinggi | Aturan normalisasi, peringatan kemiripan, form input cepat |
| R5 | Kepatuhan mentor memperbarui status | Sedang | Label "Perlu Pembaruan" dan dashboard monitoring |
| R6 | Link sertifikat bersifat publik di penyedia eksternal | Sedang | Edukasi pengaturan izin oleh AP; di luar kendali sistem |
| R7 | Keputusan "sementara" (akses mentor, klasemen sepanjang waktu) berubah kemudian | Sedang | Desain akses dan filter yang dapat dikonfigurasi |
| K1 | Tidak ada akun sekolah | Sekolah tidak dapat melihat jadwal sendiri | Sesuai keputusan |
| K2 | Teknologi belum diputuskan | Estimasi belum final | PD-30 |

---

## 27. Open Questions & Pending Business Decisions

Versi 1.3: seluruh PD bisnis sudah ditutup dan diterapkan ke seluruh bagian PRD. Asumsi tetap berstatus **[ASUMSI]** dan tidak diubah menjadi keputusan.

Prioritas: **Tinggi** = memblokir pengembangan modul terkait, **Sedang** = dibutuhkan sebelum UAT, **Rendah** = dapat diputuskan setelah MVP.

### 27.1 PD Ditutup

| ID | Hasil | Dasar |
|---|---|---|
| PD-01 | Admin Pusat menetapkan sesi terakhir. Rangkaian TL selesai setelah seluruh sesi yang direncanakan berstatus Selesai. Admin Pusat lalu mendapat notifikasi sertifikat, dan boleh membuka kembali rangkaian untuk sesi tambahan. Mentor tidak menetapkan sesi terakhir | Keputusan v1.2. Rincian turunan: PD-35, PD-36 |
| PD-02 | Sesi TL hanya dapat dibuat setelah pelatihan awal Selesai | Aturan bisnis awal no. 1, keputusan no. 4 |
| PD-03 | Mentor melihat jadwal mentor lain sebatas info koordinasi, tetapi tidak dapat membuka atau mengubah laporan detail milik mentor lain | Keputusan v1.2. Akses ke seluruh sekolah tetap Asumsi A-01 |
| PD-04 | Mentor penanggung jawab boleh menambah sesi untuk sekolahnya. Admin terkait mendapat notifikasi jadwal baru, dan aktivitas tercatat di riwayat | Keputusan v1.2 |
| PD-05 | Hanya Admin Pusat yang boleh mengubah status laporan mentor, dengan alasan wajib dan tercatat di riwayat revisi. Ini bukan verifikasi; laporan mentor tetap langsung valid | Keputusan v1.2; tafsiran dikonfirmasi v1.3: Superadmin dan Admin Cabang tidak diberi hak ini |
| PD-06 | Pelatihan awal selalu offline di kampus pusat. Admin Cabang boleh menjadwalkannya untuk sekolah cabangnya | Keputusan no. 4 dan 22 |
| PD-07 | Admin Cabang boleh mengarsipkan sekolah cabangnya | Keputusan no. 22, ditegaskan PD-25 |
| PD-08 | Hanya Superadmin yang membuat, mengubah, dan menonaktifkan akun, termasuk akun mentor. Admin Pusat tidak punya hak pengelolaan akun | Keputusan v1.2; tafsiran dikonfirmasi v1.3: AP mengatur penugasan dan mentor pelaksana, tanpa hak atas akun |
| PD-09 | Data master hanya dikelola Superadmin | Keputusan v1.2 |
| PD-10 | Hanya Admin Pusat yang menginput, mengubah, dan mengganti link sertifikat | Keputusan no. 19 |
| PD-11 | AC membuka sertifikat sekolah cabangnya; mentor membuka sertifikat sekolah tanggung jawabnya | Keputusan v1.2 |
| PD-12 | Boleh beberapa narahubung dengan tepat satu narahubung utama. Nama dan nomor WhatsApp wajib. Mentor boleh mengusulkan perubahan. Riwayat disimpan dan data lama tidak dihapus | Keputusan v1.2. Rincian turunan: PD-38 |
| PD-13 | Mentor boleh melihat leaderboard | Keputusan v1.2 |
| PD-14 | Notifikasi dikirim ke mentor terkait, AP, dan AC cabang sekolah. Pengingat jadwal H-1. Pengingat laporan H+1 bila status belum Selesai/Dibatalkan | Keputusan v1.2 |
| PD-15 | Satu mentor penanggung jawab per sekolah. AP boleh menunjuk mentor pelaksana berbeda untuk sesi tertentu | Keputusan v1.2 |
| PD-16 | Kontak awal wajib dicatat mentor sebagai checklist di laporan progres, bukan sebagai kegiatan terjadwal | Keputusan v1.2 |
| PD-17 | Leaderboard hanya menghitung sekolah yang sudah resmi mitra | Keputusan v1.2 |
| PD-18 | Cabang dipilih saat pendaftaran (oleh AP; AC otomatis cabangnya sendiri). Tidak ada pemetaan otomatis dari kabupaten. Sekolah tidak dapat dipindah cabang; tidak ada fitur transfer | Keputusan v1.2. Rincian turunan: PD-37 |
| PD-19 | Jenis kegiatan: Pelatihan, Pendampingan | Keputusan v1.2 |
| PD-21 | NPSN tidak digunakan | Keputusan no. 5 |
| PD-22 | Pelatihan awal yang dibatalkan dijadwal ulang sebagai kegiatan baru; kegiatan lama tetap berstatus Dibatalkan. Hanya satu pelatihan awal yang boleh Selesai per sekolah | Keputusan v1.2 |
| PD-23 | Terjadwal = Belum Dimulai atau Sedang Berlangsung. Belum terlaksana = Belum Dimulai dengan tanggal sudah lewat | Keputusan v1.2 |
| PD-24 | Tidak ada status sekolah secara keseluruhan; posisi tahap hanya hasil turunan | Keputusan no. 11 |
| PD-25 | Kegiatan yang belum selesai dibatalkan dengan alasan. Sekolah diarsipkan AP/AC sesuai cakupan, dengan alasan wajib. Riwayat tidak dihapus | Keputusan v1.2 |
| PD-26 | Jenjang: SMA, SMK, MA | Keputusan no. 5 |
| PD-31 | Tidak ada impor data lama | Keputusan no. 32 |
| PD-32 | Deviasi dari pola 4+1 tidak memerlukan alasan khusus | Keputusan v1.2 |
| PD-33 | Kategori melekat pada sekolah (banyak-ke-banyak) | Keputusan no. 2 |
| PD-34 | Metrik perbandingan cabang: jumlah sekolah mitra, % sesi selesai, jumlah kegiatan belum diperbarui, jumlah sertifikat belum terbit. AC melihat agregat seluruh cabang tanpa detail sekolah cabang lain | Keputusan v1.2 |
| PD-35 | Setelah AP menetapkan sesi terakhir, mentor tidak boleh menambah sesi sendiri. Mentor mengajukan/menginformasikan ke AP; AP menambah sesi dan memperbarui sesi terakhir | Keputusan v1.3 |
| PD-36 | Sesi Dibatalkan tidak wajib diselesaikan. Rangkaian selesai bila seluruh sesi yang tidak Dibatalkan Selesai. Jika sesi terakhir Dibatalkan, AP wajib menetapkan sesi lain; rangkaian tidak otomatis selesai. Riwayat sesi batal disimpan | Keputusan v1.3 |
| PD-37 | SA boleh mengoreksi cabang hanya bila sekolah belum punya kegiatan, dengan alasan wajib dan tercatat di audit. Setelah ada kegiatan, cabang tidak dapat diubah. Arsip lalu daftar ulang tidak boleh dipakai untuk mengakali anti-duplikasi | Keputusan v1.3 |
| PD-38 | Usulan narahubung dari mentor menunggu persetujuan AC cabang sekolah atau AP. Riwayat mencatat data sebelum, data usulan, pengusul, pemutus, waktu pengajuan, dan waktu keputusan | Keputusan v1.3 |

### 27.2 PD Terbuka: Membutuhkan Konfirmasi

Tidak ada PD bisnis yang terbuka per versi 1.3.

### 27.3 Asumsi Aktif (belum final, tidak diperlakukan sebagai keputusan)

| ID | Asumsi | Dampak bila berubah |
|---|---|---|
| A-01 | Mentor dapat melihat seluruh sekolah di Jawa Tengah | Scoping baca mentor (FR-AUTH-04) |
| A-02 | Penugasan mentor dilakukan Admin Pusat | Matriks akses dan FR-MNT-02 |
| A-03 | Mentor dapat menangani sekolah lintas cabang | Relasi penugasan |
| A-04 | Jumlah cabang, mentor, dan sekolah belum ditentukan. Target performa sementara memakai acuan 5.000 sekolah | Target NFR |

Asumsi A-05 (aturan penyelesaian Tindak Lanjut) ditutup karena sudah diputuskan melalui PD-01.

### 27.4 Rekomendasi yang Diterapkan sebagai Aturan Awal MVP

Disetujui sebagai aturan awal MVP, tetapi tetap berstatus **[REKOMENDASI]**, bukan keputusan.

| ID | Rekomendasi | Lokasi |
|---|---|---|
| R-01 | Info koordinasi jadwal mentor lain: sekolah, tanggal, waktu, metode, mentor pelaksana, status kegiatan. Tanpa catatan laporan dan tautan pertemuan | Bagian 9 |
| R-02 | Checklist kontak awal tidak memblokir mentor mengirim laporan. Sekolah yang belum dihubungi tampil sebagai indikator di Progres Mentor | 13.6 |
| R-03 | % sesi selesai = sesi Selesai ÷ seluruh sesi yang tidak Dibatalkan | BR-DSH-05 |
| R-04 | Sekolah mitra yang kemudian diarsipkan tetap terhitung di leaderboard (perhitungan sepanjang waktu) | BR-RNK-04 |
| R-05 | Laporan sesi dapat ditulis mentor penanggung jawab dan mentor pelaksana sesi tersebut | BR-RPT-05 |
| R-06 *(baru)* | Pengajuan sesi tambahan dari mentor ke AP setelah sesi terakhir ditetapkan tidak memakai form khusus di MVP; cukup lewat komunikasi atau catatan kegiatan | BR-TL-10, E19 |
| R-07 *(baru)* | Notifikasi ke AP saat sesi terakhir berstatus Dibatalkan, dan ke mentor pengusul saat usulan narahubung diputuskan | 13.9 |
| R-08 *(baru)* | Alasan penolakan usulan narahubung diisi pemutus | 16.3 |
| R-09 *(baru)* | Usulan narahubung yang datanya sudah berubah sejak diajukan ditandai "perlu ditinjau ulang" | E23 |

**Aturan konsistensi metrik [KEPUTUSAN]:** kegiatan berstatus Dibatalkan tidak pernah dihitung sebagai kegiatan aktif, terjadwal, belum terlaksana, belum diperbarui, maupun penyebut persentase. Rumus terkait: BR-DSH-02 sampai 05, 15.3, 15.5, dan 22.1.

### 27.5 Diserahkan ke Tim Teknis

| ID | Topik | Usulan awal |
|---|---|---|
| PD-20 | Retensi audit log dan notifikasi | Audit ≥ 5 tahun; notifikasi 90 hari |
| PD-27 | Batas waktu sesi login idle | 60 menit |
| PD-29 | Target RPO/RTO dan tanggung jawab backup | RPO 24 jam, RTO 8 jam |
| PD-30 | Teknologi, hosting, dan tim pengembang | Diputuskan tim teknis |

---

## 28. MVP Scope

Sesuai keputusan poin 15–16, MVP mencakup:

| Area | Termasuk di MVP |
|---|---|
| Autentikasi & RBAC | Login, 4 role, scoping cabang dan penugasan |
| Manajemen Sekolah | CRUD, anti-duplikasi, kategori, cabang terkunci (koreksi SA bila belum ada kegiatan), arsip dengan alasan, narahubung dengan riwayat dan persetujuan usulan mentor, pencarian dan filter dasar, detail sekolah |
| Mentor | Akun (dikelola SA), penugasan penanggung jawab, mentor pelaksana per sesi, riwayat penugasan, jadwal koordinasi |
| Penjadwalan | Pelatihan awal dan tindak lanjut fleksibel, tambah sesi oleh mentor, sesi terakhir oleh AP, buka kembali rangkaian, reschedule dengan riwayat, kalender |
| Monitoring Progres | Status kegiatan oleh mentor, ubah status oleh AP dengan alasan, catatan wajib, revisi, checklist kontak awal, halaman Progres Mentor, penanda Perlu Pembaruan (H+1) |
| Sertifikat | Pencatatan per tahap, input link oleh AP, status turunan |
| Dashboard | KPI per role, grafik, ringkasan klasemen, perbandingan performa antarcabang |
| Klasemen | Sekolah unik per kategori, sepanjang waktu |
| Notifikasi | Dalam aplikasi: jadwal baru, perubahan jadwal, H-1, H+1, sertifikat tersedia, rangkaian selesai |
| Data Master & Pengguna | CRUD dan nonaktif |
| Audit Trail | Pencatatan menyeluruh dan halaman khusus SA |

### 28.1 Daftar Prioritas Pengembangan (Tracking)

Kolom **Status** diisi tim: `Belum Mulai` / `Dikerjakan` / `Review` / `Selesai`. Urutan fase mengikuti dependensi teknis.

| ID | Fase | Item | Prioritas | Bergantung pada | PD terkait | Status |
|---|---|---|:-:|---|---|---|
| T-01 | 0. Persiapan | Konfirmasi seluruh PD bisnis | P0 | — | — | Selesai |
| T-02 | 0. Persiapan | Keputusan teknologi dan hosting | P0 | — | 30 | Belum Mulai |
| T-03 | 0. Persiapan | Desain UI (wireframe dan hi-fi) halaman inti | P0 | T-01 | — | Belum Mulai |
| T-04 | 0. Persiapan | Pengumpulan data awal (cabang, kabupaten, sekolah, mentor) | P0 | — | — | Belum Mulai |
| T-05 | 1. Fondasi | Setup proyek, environment, CI, backup | P0 | T-02 | 29 | Belum Mulai |
| T-06 | 1. Fondasi | Skema data dan migrasi | P0 | T-01, T-02 | — | Belum Mulai |
| T-07 | 1. Fondasi | Autentikasi, sesi, ganti/reset password | P0 | T-06 | 27 | Belum Mulai |
| T-08 | 1. Fondasi | RBAC dan scoping cabang/penugasan (server) | P0 | T-07 | — | Belum Mulai |
| T-09 | 1. Fondasi | Audit trail (middleware pencatatan) | P0 | T-06 | 20 | Belum Mulai |
| T-10 | 2. Master & Pengguna | Data master + seed (35 kab/kota, 6 kategori, jenis, metode) | P0 | T-08 | — | Belum Mulai |
| T-11 | 2. Master & Pengguna | Manajemen akun oleh SA (termasuk mentor) dan nonaktif | P0 | T-08 | — | Belum Mulai |
| T-12 | 3. Sekolah | CRUD sekolah, anti-duplikasi (termasuk sekolah arsip), kategori, cabang terkunci + koreksi SA, arsip dengan alasan | P0 | T-10 | — | Belum Mulai |
| T-12a | 3. Sekolah | Narahubung, riwayat, usulan perubahan dari mentor dengan persetujuan AC/AP | P0 | T-12 | — | Belum Mulai |
| T-13 | 3. Sekolah | Daftar sekolah: pencarian dan filter | P0 | T-12 | — | Belum Mulai |
| T-14 | 3. Sekolah | Penugasan mentor penanggung jawab, mentor pelaksana per sesi, riwayat | P0 | T-11, T-12 | — | Belum Mulai |
| T-15 | 4. Jadwal | Jadwal Pelatihan Awal (offline kampus pusat, jadwal ulang sebagai kegiatan baru) | P0 | T-14 | — | Belum Mulai |
| T-16 | 4. Jadwal | Jadwal Tindak Lanjut (sesi fleksibel, tambah sesi oleh mentor sebelum sesi terakhir ditetapkan, urutan) | P0 | T-15 | — | Belum Mulai |
| T-16a | 4. Jadwal | Penyelesaian rangkaian: sesi terakhir oleh AP, aturan sesi Dibatalkan, selesai otomatis, buka kembali, riwayat rangkaian | P0 | T-16, T-19 | — | Belum Mulai |
| T-17 | 4. Jadwal | Reschedule dan riwayat jadwal | P0 | T-15 | — | Belum Mulai |
| T-18 | 4. Jadwal | Tampilan kalender | P1 | T-15 | — | Belum Mulai |
| T-19 | 5. Progres | Laporan mentor: status, catatan, revisi; ubah status oleh AP dengan alasan | P0 | T-15 | — | Belum Mulai |
| T-20 | 5. Progres | Status turunan (tahap sekolah, progres TL) | P0 | T-16, T-19 | — | Belum Mulai |
| T-21 | 5. Progres | Halaman Progres Mentor, label Perlu Pembaruan (H+1), checklist kontak awal | P0 | T-20 | — | Belum Mulai |
| T-22 | 5. Progres | Halaman mentor (Beranda, Sekolah Saya, Kegiatan Saya, Jadwal Koordinasi) yang ramah ponsel | P0 | T-19 | — | Belum Mulai |
| T-23 | 6. Sertifikat | Pencatatan sertifikat dan status turunan | P0 | T-20 | — | Belum Mulai |
| T-24 | 6. Sertifikat | Halaman Sertifikat (Menunggu/Tersedia) | P0 | T-23 | — | Belum Mulai |
| T-25 | 7. Notifikasi | Notifikasi dalam aplikasi: jadwal baru, perubahan, H-1, H+1, sertifikat tersedia, rangkaian selesai | P0 | T-17, T-19, T-23 | — | Belum Mulai |
| T-26 | 8. Insight | Klasemen kategori (sekolah mitra unik; admin dan mentor) | P0 | T-12 | — | Belum Mulai |
| T-27 | 8. Insight | Dashboard per role dan perbandingan performa antarcabang | P0 | T-20, T-23, T-26 | — | Belum Mulai |
| T-28 | 9. Audit UI | Halaman audit trail SA | P0 | T-09 | — | Belum Mulai |
| T-29 | 10. Rilis | Input data awal manual melalui aplikasi (tanpa impor) | P0 | T-12, T-14 | — | Belum Mulai |
| T-30 | 10. Rilis | Uji keamanan dasar (akses lintas cabang, IDOR, XSS) | P0 | Semua P0 | — | Belum Mulai |
| T-31 | 10. Rilis | UAT dengan AP, AC, dan Mentor berdasarkan Acceptance Criteria | P0 | Semua P0 | — | Belum Mulai |
| T-32 | 10. Rilis | Pelatihan pengguna dan go-live | P0 | T-31 | — | Belum Mulai |

Kriteria MVP selesai: seluruh item P0 berstatus Selesai dan seluruh Acceptance Criteria P0 lulus UAT.

---

## 29. Future Development Scope

| ID | Fitur | Sumber | Prioritas usulan |
|---|---|---|:-:|
| F-01 | Notifikasi WhatsApp (pengingat jadwal ke mentor/narahubung, perubahan jadwal) | Keputusan | Tinggi |
| F-02 | Ekspor Excel (sekolah, jadwal, progres, sertifikat) | Keputusan | Tinggi |
| F-03 | Pencarian lanjutan dan filter tersimpan | Keputusan | Sedang |
| F-04 | Filter periode pada klasemen dan dashboard | Rekomendasi | Sedang |
| F-05 | Pembatasan akses mentor hanya ke sekolah yang ditugaskan (bila keputusan "sementara" berubah) | Rekomendasi | Sedang |
| F-06 | Impor massal sekolah via template | Rekomendasi | Sedang |
| F-07 | Laporan cetak/PDF per sekolah | Rekomendasi | Rendah |
| F-08 | Notifikasi email | Rekomendasi | Rendah |
| F-09 | Pengingat otomatis H-1 kegiatan | Rekomendasi | Sedang |
| F-10 | Integrasi data sekolah resmi (Dapodik/NPSN) | Rekomendasi | Rendah |
| F-11 | Portal baca saja untuk sekolah (memerlukan keputusan baru karena saat ini Non-Goal) | Rekomendasi | Rendah |
| F-12 | Analitik kinerja mentor (ketepatan pembaruan, jumlah reschedule) | Rekomendasi | Rendah |

---

*Akhir dokumen. Revisi berikutnya akan memperbarui status PD dan menaikkan versi dokumen.*
