# Restu Finance

**Restu Finance** adalah aplikasi manajemen keuangan pribadi (*Personal Finance Management*) yang modern, responsif, aman, dan siap digunakan pada jaringan lokal (LAN) maupun perangkat mobile (PWA).

Aplikasi ini menggunakan arsitektur backend **Laravel** sebagai *Single Source of Truth* untuk seluruh kalkulasi keuangan dan state data, dipadukan dengan antarmuka frontend SPA (Single Page Application) yang cepat dan interaktif.

---

## Fitur Utama

- **Single Source of Truth Financial Calculation**: Seluruh kalkulasi saldo dompet, ringkasan pengeluaran/pemasukan bulanan, pemakaian anggaran (*budget*), dan progres target (*goal*) dihitung secara konsisten di backend.
- **Transactions Management**: Pencatatan transaksi pendapatan (*income*) dan pengeluaran (*expense*) dengan filter kategori, dompet, dan tanggal.
- **Multi-Wallet Support**: Pengelolaan beberapa akun/dompet (Cash, Bank, E-Wallet) dengan pencatatan saldo awal dan kalkulasi saldo berjalan dinamis.
- **Custom Categories**: Manajemen kategori kustom dengan pemilihan ikon dan warna.
- **Budget Monitoring**: Penetapan batas anggaran bulanan per kategori dengan indikator real-time pemakaian & peringatan over-budget.
- **Savings Goals**: Penetapan target tabungan dengan batas waktu (*deadline*) dan pelacakan persentase capaian dari alokasi transaksi.
- **Recurring Transactions**: Transaksi berulang otomatis (Harian, Mingguan, Bulanan, Tahunan) dilengkapi *duplicate prevention* dan prosesor background/artisan.
- **LAN Share & QR Code**: Akses instan dari perangkat lain di jaringan Wi-Fi/LAN yang sama melalui alamat IP otomatis dan QR Code dinamis (tanpa hardcode IP).
- **Progressive Web App (PWA)**: Dukungan instalasi aplikasi di perangkat Android, iOS, dan Desktop, lengkap dengan Service Worker dan Web App Manifest.
- **Mobile Responsive UI**: Tampilan adaptif untuk smartphone, tablet, dan desktop dengan penanganan area aman (*safe-area-inset*) dan proteksi horizontal overflow.
- **Security Hardening**:
  - Otentikasi berbasis sesi & isolasi data multi-user.
  - Rate limiting pada endpoint login (`throttle:login`).
  - Proteksi XSS pada input/output teks.
  - Mass assignment protection pada model Eloquent.
  - Mekanisme ganti kata sandi yang aman.

---

## Tech Stack & Arsitektur (v1.2.0)

- **Backend**: Laravel 11 / 12 / 13 (PHP 8.3+)
  - **Skinny Controllers**: `FinanceController` & `RecurringTransactionController` bertindak murni sebagai orchestrator data.
  - **Dedicated Form Requests (`app/Http/Requests/`)**: Validasi input terisolasi dengan penanganan HTTP 422 JSON terstandarisasi dan autentikasi otomatis via `BaseApiRequest`.
  - **Database Index Optimization**: Compound index pada `transactions (user_id, date)`, `(user_id, wallet)`, `(user_id, category)`, `wallets (user_id, is_active)`, `budgets (user_id, category)`, dan `recurring_transactions (user_id, is_active, next_date)`.
  - **Export CSV RFC 4180**: Endpoint `GET /api/transactions/export` dengan UTF-8 BOM (`\xEF\xBB\xBF`) untuk kompatibilitas penuh Microsoft Excel & filter dinamis.
- **Database**: MySQL / MariaDB (atau SQLite untuk automated testing)
- **Frontend**: Vanilla JavaScript Modular (ES Modules)
  - `public/js/utils.js`: Format mata uang (IDR), tanggal, sanitasi XSS (DOMPurify/HTML escape), helper toast.
  - `public/js/theme.js`: Engine tema (Light, Dark, Special 8-Bit Retro), dynamic favicon SVG, animasi canvas bintang, mascot pixel interaktif.
  - `public/js/audio.js`: Synthesizer Web Audio API (efek koin, lompat, peringatan 8-bit) dan pemutar YouTube BGM.
  - `public/js/api.js`: Fetch wrapper dengan CSRF injection otomatis dan penanganan HTTP 401.
  - `public/js/wallets.js`: Manajemen dompet (CRUD & kalkulasi saldo).
  - `public/js/budgets.js`: Monitoring anggaran & batas pengeluaran.
  - `public/js/goals.js`: Pelacakan target tabungan.
  - `public/js/recurring.js`: Antarmuka transaksi berulang.
  - `public/js/transactions.js`: Riwayat transaksi, live search, alokasi kebutuhan split needs, dan RFC 4180 CSV export.
  - `public/js/app.js`: State manager, router SPA, LAN QR code scanner, & PWA service worker bootstrapper.
- **PWA**: Service Worker Cache-First (Static Assets v1.2.0) & Network-Only (API Data), Web App Manifest
- **QR Code Engine**: qrcode.min.js

---

## Persyaratan Sistem

- PHP >= 8.2 (Direkomendasikan PHP 8.3+)
  - Ekstensi PHP: `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `sockets`
- Composer 2.x
- MySQL / MariaDB (misalnya via Laragon / XAMPP)
- Web Browser modern (Google Chrome, Mozilla Firefox, Safari, Microsoft Edge)

---

## Panduan Instalasi

### 1. Clone / Siapkan Project
Buka direktori project di terminal atau PowerShell:
```bash
cd "C:\laragon\www\Pengatur uang"
```

### 2. Install Dependensi PHP
```bash
composer install
```

### 3. Konfigurasi Environment (`.env`)
Salin file `.env.example` menjadi `.env` jika belum ada:
```bash
copy .env.example .env
```
Generate Application Key:
```bash
php artisan key:generate
```

Sesuaikan konfigurasi database pada file `.env`:
```ini
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=pengatur_uang
DB_USERNAME=root
DB_PASSWORD=
```

### 4. Database Migration & Seeding
Jalankan migrasi database untuk membuat tabel:
```bash
php artisan migrate
```

Jika ingin mengisi data awal (kategori default, dompet contoh):
```bash
php artisan db:seed
```

---

## Menjalankan Server

### Menjalankan Server Lokal (Localhost Saja)
```bash
php artisan serve
```
Aplikasi dapat diakses melalui browser di: `http://127.0.0.1:8000`

### Menjalankan Server untuk Akses Jaringan Lokal (LAN Share)
Untuk mengakses aplikasi dari HP / Laptop lain di jaringan Wi-Fi yang sama:
```bash
php artisan serve --host=0.0.0.0 --port=8000
```
Buka menu **Pengaturan / LAN Share** di aplikasi untuk melihat alamat IP lokal dan scan QR Code yang disediakan.

---

## Akun Pengguna / Development Login

Untuk pengujian lokal, buat akun baru melalui antarmuka login/register atau gunakan user default jika telah menjalankan seeder.

Mekanisme otentikasi menggunakan session-based authentication dengan proteksi isolasi data sehingga setiap pengguna hanya dapat melihat dan memodifikasi data keuangannya sendiri.

---

## Transaksi Berulang (Recurring Transactions)

Transaksi berulang yang telah jatuh tempo dapat diproses secara manual melalui tombol proses di aplikasi atau secara terjadwal menggunakan perintah artisan:

```bash
php artisan recurring:process
```

Perintah ini dapat didaftarkan ke scheduler (Cron job / Windows Task Scheduler) untuk pemrosesan harian:
```bash
php artisan schedule:run
```

---

## Progressive Web App (PWA)

Aplikasi telah memenuhi standar PWA:
- **Manifest File**: `public/manifest.webmanifest`
- **Service Worker**: `public/sw.js`
- **Instalasi**: Klik ikon *Install* atau *Add to Home Screen* pada browser mobile / desktop untuk memasang Restu Finance sebagai aplikasi mandiri (*standalone app*).

---

## Pengujian Otomatis (Automated Testing)

Project ini dilengkapi dengan rangkaian automated feature tests dan unit tests menyeluruh yang mencakup:
- Wallet API & isolasi user
- Transaction API & kalkulasi saldo
- Category API
- Budget API & kalkulasi penggunaan limit
- Savings Goal API & capaian target
- Recurring Transactions API & duplikasi proteksi
- Profil & ganti password
- LAN Info resolver
- Security hardening (Auth enforcement, rate limiting, XSS protection)

Jalankan seluruh test suite:
```bash
php artisan test
```

---

## Catatan Keamanan (Security Notes)

1. **Autentikasi API**: Semua endpoint API dilindungi oleh middleware sesi dan verifikasi otentikasi. Request tanpa sesi yang valid akan menerima response HTTP 401 (*Unauthenticated*).
2. **Rate Limiting**: Endpoint login dilindungi oleh rate limiter `throttle:login` untuk mencegah serangan brute force.
3. **User Isolation**: Seluruh kueri basis data (pembacaan, penambahan, pengubahan, penghapusan) selalu diikatkan ke ID pengguna yang sedang aktif (`user_id`).
4. **Environment Variables**: Jangan membagikan file `.env` atau commit data sensitif (kunci rahasia, password database) ke repository publik.
