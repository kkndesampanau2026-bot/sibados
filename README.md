# SIBADOS — Sistem Booking Asisten Dosen

Aplikasi web untuk pembagian dan penempatan **pasangan Asisten Dosen** ke setiap praktikum
melalui mekanisme booking **siapa cepat dia dapat** yang terstruktur dan terkontrol.

## Konsep Inti

Empat entitas utama, dan hubungan di antaranya adalah kunci sistem ini:

- **Kelas (rombel)** — sekelompok mahasiswa satu angkatan, mis. *Kelas A Angkatan 2024
  (semester V)*. Setiap rombel punya **satu ketua kelas**.
- **Mata kuliah praktikum** — melekat pada satu semester.
- **Praktikum** — perpotongan rombel × mata kuliah. **Inilah unit yang dibooking.**
- **Pasangan Asdos** — dua Asdos yang dipasangkan Koordinator **untuk satu mata kuliah**.
  **Inilah yang dipilih**, bukan Asdos perorangan.

Satu rombel mengampu beberapa mata kuliah praktikum sekaligus. Contoh nyata:

```
Kelas A Angkatan 2024 (semester V)
  ketua kelas: Ahmad
      ├── Praktikum Pemrograman Mobile      → pilih pasangan  (Fajar & Rina)
      ├── Praktikum Kecerdasan Bisnis       → pilih pasangan  (Fajar & Rina)
      └── Praktikum Audit Sistem Informasi  → pilih pasangan  (Sinta & Tono)
```

Ahmad login **sekali**, lalu memilih satu pasangan untuk **tiap** praktikum kelasnya.
Slot yang sudah diambil kelas lain tidak bisa direbut — siapa cepat dia dapat.

### Aturan pasangan

- Pasangan dibentuk **per mata kuliah**. Pasangan Pemrograman Mobile tidak dapat dipakai
  untuk praktikum Kecerdasan Bisnis, walau orangnya sama.
- Seorang Asdos **boleh tergabung dalam beberapa pasangan** — dengan rekan berbeda untuk
  mata kuliah berbeda.
- **Kuota dihitung per individu**, bukan per pasangan. Satu booking memakai kuota kedua
  anggota. Sebuah pasangan hanya bisa dipilih bila **kedua** anggotanya masih punya sisa
  kuota; kalau salah satu penuh, pasangan itu ikut 🔴 penuh.

### Kuota dua lapis

Supaya satu Asdos tidak diborong semua kelas, kuota dibatasi pada dua lapis dan booking
hanya lolos bila **keduanya** masih ada sisa:

| Lapis | Kolom | Fungsi |
| --- | --- | --- |
| **Kuota per mata kuliah** | `course_asdos.max_practicums` | Membatasi berapa kelas boleh memilih Asdos ini **pada satu mata kuliah**. Ini pembatas utamanya. |
| **Kuota total** | `asdos.max_classes` | Batas atas beban lintas seluruh mata kuliah. |

Contoh — Fajar: kuota total 9, kuota Pemrograman Mobile 2, kuota Kecerdasan Bisnis 2.

```
Kelas A pilih Fajar & Rina di Prak. Pemrograman Mobile  ->  PMB 1/2  🟢 tersedia
Kelas B pilih Fajar & Rina di Prak. Pemrograman Mobile  ->  PMB 2/2  🔴 penuh
Kelas C mencoba hal yang sama                           ->  DITOLAK
        "Kuota Asdos Fajar untuk Pemrograman Mobile sudah penuh (2/2 praktikum)."

Kelas C pilih Fajar & Rina di Prak. Kecerdasan Bisnis   ->  KBS 0/2  🟢 tersedia
        (kuota terpisah per mata kuliah, jadi tetap bisa)
```

Kuota per mata kuliah dihitung **lintas pasangan**: kalau kuota Fajar di Pemrograman
Mobile habis, semua pasangan yang memuat Fajar di mata kuliah itu ikut penuh. Koordinator
mengaturnya di menu **Penempatan Matkul** — tiap sel matriks berisi centang plus angka
`terpakai/kuota`. Nilai bawaan diatur di menu Pengaturan (bawaan **2**).

## Stack

| Lapisan | Teknologi |
| --- | --- |
| Backend | Laravel 13 · PHP 8.3 |
| Frontend | React 19 + Inertia.js 3 + Tailwind CSS 4 (Vite 8) |
| Database | MySQL 8.4 |
| Autentikasi | Laravel session auth, login-only + middleware `role` |
| Ekspor | Laravel Excel 4 (maatwebsite/excel) |
| Pengujian | PHPUnit (68 test) |

## Menjalankan

Prasyarat: PHP 8.3+, Composer, Node 20+, dan MySQL yang berjalan (mis. lewat Laragon).

```bash
composer install
npm install

cp .env.example .env          # sesuaikan kredensial DB bila perlu
php artisan key:generate

# Buat database terlebih dahulu:
#   CREATE DATABASE sibados CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
php artisan migrate --seed

npm run dev                   # terminal 1 (mode pengembangan)
php artisan serve             # terminal 2  ->  http://localhost:8000
```

Untuk produksi: `npm run build`, lalu layani lewat web server.

## Akun Bawaan

Seluruh akun demo memakai kata sandi `password`.

| Peran | Email | Keterangan |
| --- | --- | --- |
| Admin / Koordinator | `admin@sibados.test` | Akses penuh |
| Asdos | `andi.asdos@…`, `budi.asdos@…` | Sem. I (ALP, LGD) |
| Asdos | `citra.asdos@…`, `dimas.asdos@…` | Sem. III (KAI, RPL) |
| Asdos | `fajar.asdos@…`, `rina.asdos@…`, `sinta.asdos@…`, `tono.asdos@…` | Sem. V (PMB, KBS, ASI) |
| Ketua Kelas | `ahmad.2024a@sibados.test` | Kelas A Angkatan 2024 |
| Ketua Kelas | `bayu.2024b@…`, `cindy.2024c@…` | Kelas B & C Angkatan 2024 |
| Ketua Kelas | `dewi.2025a@…`, `eka.2025b@…`, `farhan.2025c@…` | Angkatan 2025 |
| Ketua Kelas | `gilang.2026a@…`, `hana.2026b@…`, `irfan.2026c@…` | Angkatan 2026 |

> **Booking awalnya TERTUTUP.** Masuk sebagai Admin → **Pengaturan** → centang
> *Buka booking* agar ketua kelas dapat melakukan booking.

Untuk instalasi bersih tanpa data demo, hapus pemanggilan `DemoSeeder::class` di
[DatabaseSeeder.php](database/seeders/DatabaseSeeder.php).

## Data Awal

7 mata kuliah praktikum, 9 rombel (3 angkatan × 3 kelas), **21 praktikum**, 8 Asdos, dan
9 pasangan:

| Angkatan | Semester | Kelas | Mata kuliah praktikum |
| --- | --- | --- | --- |
| 2024 | V | A, B, C | Pemrograman Mobile · Kecerdasan Bisnis · Audit Sistem Informasi |
| 2025 | III | A, B, C | Kecerdasan Artifisial · Rekayasa Perangkat Lunak |
| 2026 | I | A, B, C | Algoritma & Pemrograman · Logika Diskrit |

Saat Admin membuat rombel baru, praktikum untuk seluruh mata kuliah aktif pada semester
rombel tersebut dibuat otomatis. Tombol **Sinkronkan** pada halaman detail kelas
menambahkan mata kuliah yang baru muncul kemudian.

## Alur Sistem

```
Admin  →  Mata Kuliah  →  Kelas (rombel)  →  praktikum dibuat otomatis
                                 ↓
              Penempatan Matkul: siapa boleh menangani matkul apa
                                 ↓
                 Pasangan Asdos: pasangkan 2 Asdos per matkul
                                 ↓
              Ketua kelas login → lihat semua praktikum kelasnya
                                 ↓
              pilih PASANGAN per praktikum → Booking (siapa cepat)
                                 ↓
            Validasi → penempatan otomatis → kedua Asdos melihatnya
```

## Aturan Booking

Seluruh aturan terkumpul di [BookingService.php](app/Services/BookingService.php) dan
diverifikasi oleh [BookingRuleTest.php](tests/Feature/BookingRuleTest.php).

| Aturan | Penerapan |
| --- | --- |
| Yang dibooking adalah pasangan, bukan Asdos perorangan | `bookings.pair_id` |
| Pasangan hanya berlaku untuk mata kuliah tempat ia dibentuk | `asdos_pairs.course_id` dicek terhadap `practicums.course_id` |
| Kuota per mata kuliah membatasi jumlah kelas | `course_asdos.max_practicums`, dihitung lintas pasangan |
| Kuota total menjadi batas atas lintas mata kuliah | `asdos.max_classes` |
| Kedua lapis kuota diperiksa untuk kedua anggota pasangan | Booking ditolak bila salah satu habis |
| Satu praktikum dapat menampung satu atau beberapa pasangan | `practicums.max_asdos` (bawaan 1) |
| Slot praktikum yang sudah terisi tidak dapat direbut | Dicek terhadap booking aktif; hanya Admin yang dapat membatalkan |
| Seorang Asdos tidak boleh masuk dua kali ke satu praktikum | Dicek lintas pasangan pada praktikum yang sama |
| Anggota pasangan harus sudah ditugaskan pada matkul tersebut | Tabel pivot `course_asdos` |
| Satu mahasiswa hanya boleh menjadi ketua satu rombel | Unique pada `classes.representative_id` |

Status ketersediaan pasangan: 🟢 Tersedia · 🟡 Hampir Penuh (sisa 1) · 🔴 Penuh ·
🔵 Sudah Dibooking (pada praktikum tersebut) · ⚪ Nonaktif.

## Keamanan

- **Race condition** — setiap booking berjalan dalam transaksi database dengan
  `SELECT ... FOR UPDATE` pada baris praktikum, pasangan, lalu kedua anggotanya (urutan
  id menaik, menghindari deadlock). Inilah yang menjamin "siapa cepat dia dapat" tetap
  adil sekaligus melindungi kuota individu.
- **Proteksi lapisan database** — unique index `bookings_active_unique` pada
  `(practicum_id, pair_id, active_flag)`. Kolom `active_flag` bernilai `1` saat aktif dan
  `NULL` saat dibatalkan; MySQL mengabaikan `NULL` pada unique index, sehingga satu
  pasangan hanya boleh punya satu baris aktif per praktikum tetapi riwayat pembatalan
  tetap boleh berulang. Unique `asdos_pairs_unique` mencegah pasangan kembar (id anggota
  selalu disimpan terurut, jadi urutan input tidak berpengaruh).
- **RBAC** — middleware `role:admin|asdos|perwakilan`; ketua kelas hanya dapat mengakses
  praktikum rombelnya sendiri.
- Password hashing, CSRF, validasi input server-side, throttle login (6/menit), dan
  audit log pada tabel `booking_logs`.

Uji konkurensi nyata (6 worker, dua kelas berebut slot kuota mata kuliah terakhir secara
serentak) menghasilkan tepat 1 booking; kelas yang kalah menerima pesan
*"Kuota Asdos Fajar untuk Kecerdasan Bisnis sudah penuh"*.

## Struktur Database

```
users ──┬── asdos ──┬── course_asdos ──── courses
        │           │   (max_practicums = kuota per mata kuliah)
        │           │                        │
        │           └── asdos_pairs ─────────┤
        │                    │               │
        └── classes.representative_id        │
                   │                         │
                   └──── practicums ─────────┘
                              │
                          bookings ──── booking_logs
                        (practicum_id, pair_id)

settings
```

- `classes` = rombel (class_name, angkatan, semester, representative_id)
- `practicums` = class_id × course_id, dengan max_asdos dan is_locked
- `asdos_pairs` = course_id + dua anggota (asdos_one_id, asdos_two_id)

## Antarmuka

Sistem desain terpusat di dua berkas, sehingga perubahan tema cukup dilakukan di sana:

- [resources/css/app.css](resources/css/app.css) — token warna, bayangan, animasi, dan
  gaya baris tabel. Semua warna berasal dari token (`brand`, `canvas`, `surface`, `ink`,
  `line`); tidak ada warna mentah yang tersebar di halaman.
- [resources/js/Components/UI.jsx](resources/js/Components/UI.jsx) — pustaka komponen
  (Card, Button, Badge, Field, Modal, Meter, Avatar, EmptyState, Notice, tabel, dll).

Catatan responsif:

- Sidebar tetap di layar ≥1024px; di bawah itu berubah menjadi laci geser plus navigasi
  bawah untuk pintasan.
- Modal tampil sebagai *bottom sheet* di layar kecil dan dialog tengah di layar besar.
- Tabel lebar digulir horizontal **di dalam kartunya**, tidak pernah membuat halaman
  ikut melebar. Sudah diverifikasi: pada lebar 390px, `scrollWidth === clientWidth`
  di seluruh halaman.
- Area aman iOS (`env(safe-area-inset-bottom)`) dihormati oleh navigasi bawah dan modal.

## Catatan Aset

Dua hal yang pernah menjadi sumber bug dan kini dijaga:

1. **Direktif `@fonts`.** Font Instrument Sans dibangkitkan oleh `laravel-vite-plugin`
   sebagai berkas CSS terpisah. Tanpa `@fonts` di [app.blade.php](resources/views/app.blade.php),
   berkas font tetap ter-build tetapi tidak pernah dimuat browser sehingga halaman diam-diam
   jatuh ke font sistem.
2. **Berkas `public/hot`.** Berkas ini dibuat `npm run dev` dan membuat Laravel menyajikan
   URL server dev Vite. Bila proses dev dimatikan paksa, berkas itu tertinggal dan seluruh
   aset gagal dimuat. `npm run build` kini otomatis menghapusnya lewat skrip `postbuild`.
   Bila tampilan tiba-tiba polos tanpa gaya, hapus `public/hot` atau jalankan `npm run build`.

## Peta Fitur

**Admin / Koordinator**
Dashboard statistik · CRUD Mata Kuliah, Kelas (rombel), Asdos, Pasangan Asdos, Pengguna ·
detail kelas dengan pengelolaan praktikum (tambah, hapus, kunci, sinkronkan) · Penempatan
Matkul (matriks Asdos × Mata Kuliah lengkap dengan kuota per sel) · Booking (tempatkan / ganti
pasangan / batalkan) ·
Praktikum Belum Terisi · **Ekspor Excel** · Riwayat Booking · Pengaturan periode booking
& countdown.

**Asisten Dosen**
Dashboard · **Halaman Profil** (ubah nama/email/nomor HP, ganti kata sandi, lihat data
akademik) · Pasangan Saya (dengan siapa, di mata kuliah apa) · Mata kuliah beserta
kuota dan pemakaiannya · Praktikum Saya (muncul otomatis setelah dibooking) lengkap dengan nama rekan
dan kontak ketua kelas.

**Ketua Kelas**
**Halaman Profil** (ubah nama/email/nomor HP, ganti kata sandi, lihat progres kelas) ·
Melihat seluruh praktikum rombelnya dalam satu halaman · Daftar pasangan Asdos yang
tersedia per praktikum, lengkap dengan sisa kuota mata kuliah tiap anggota · Booking dengan dialog
konfirmasi · Countdown periode booking. Tampilan dioptimalkan untuk HP.

## Halaman Profil

Tersedia di `/profil` untuk **Asisten Dosen** dan **Ketua Kelas** (Koordinator tidak
memilikinya — data akun diatur lewat menu Pengguna). Dapat diakses dari sidebar maupun
menu pengguna di pojok kanan atas.

Yang **dapat diubah sendiri**: nama, email (identitas login), nomor HP, dan kata sandi
— penggantian kata sandi mewajibkan kata sandi lama.

Yang **hanya ditampilkan** karena menjadi kewenangan Koordinator: NIM, semester, status
akun, kuota, pasangan Asdos, mata kuliah yang ditangani, serta penempatan kelas. Ketua
kelas juga melihat progres pengisian praktikum rombelnya.

## Ekspor Excel

Tombol **Ekspor Excel** pada halaman Booking & Penempatan mengunduh berkas `.xlsx` yang
**mengikuti penyaring yang sedang aktif** (status dan mata kuliah), dengan nama berkas
`booking-asdos-<status>-<tanggal>.xlsx`.

Kolomnya: No · Angkatan · Kelas · Semester · Mata Kuliah · Kode MK · Pasangan Asdos ·
Asdos 1 · NIM Asdos 1 · Asdos 2 · NIM Asdos 2 · Ketua Kelas · No. HP Ketua Kelas · Status ·
Waktu Booking · Waktu Dibatalkan · Dibatalkan Oleh · Catatan.

NIM dan nomor HP ditulis sebagai **teks** lewat value binder khusus di
[BookingsExport.php](app/Exports/BookingsExport.php). Tanpa itu Excel memperlakukannya
sebagai bilangan dan angka nol di depan (mis. `081300002024`) akan hilang.

## Pengujian

```bash
php artisan test
```

Membutuhkan database `sibados_test`:

```sql
CREATE DATABASE sibados_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

- [BookingRuleTest.php](tests/Feature/BookingRuleTest.php) — 24 test aturan bisnis, termasuk 5 test kuota per mata kuliah.
- [AccessAndFlowTest.php](tests/Feature/AccessAndFlowTest.php) — 21 test hak akses & alur HTTP.
- [ExportAndDetailTest.php](tests/Feature/ExportAndDetailTest.php) — 10 test ekspor Excel
  (termasuk unduhan xlsx sungguhan) dan detail kontak Asdos.
- [ProfileTest.php](tests/Feature/ProfileTest.php) — 13 test halaman profil: hak akses,
  ubah data diri, ganti kata sandi, dan penjagaan data milik Koordinator.

## Belum Termasuk (tahap berikutnya)

Notifikasi in-app/email/WhatsApp, export Excel & PDF, statistik lanjutan, dan upload foto
Asdos.
