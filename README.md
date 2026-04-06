# DENTA TIRTA

Aplikasi billing air berbasis **PHP + SQLite + Tailwind CDN** dengan role:

- **admin**
- **collector**
- **pelanggan**

## Fitur

- Login multi-role
- Kelola data pelanggan
- Buat akun login pelanggan
- Input meter bulanan (manual)
- Hitung tagihan otomatis per periode:
  - Abonemen default: **Rp 30.000**
  - Tarif per m³ default: **Rp 2.500**
- Lihat daftar tagihan + status lunas/belum
- Pelanggan bisa cek tagihan sendiri per bulan
- Admin bisa kelola user dan ubah tarif
- **Tanggal pemasangan pelanggan**
- **Tanggal pembayaran** saat tandai lunas / update pembayaran
- **Diskon pembayaran opsional** per tagihan
- **Cetak tagihan dan kwitansi pembayaran**
- **ID pelanggan format DSA + 4 digit** tampil lebih konsisten
- **Dashboard tutorial + info update fitur**
- **Pencarian tabel dan pemilih pelanggan** untuk mempercepat input

## Akun default awal

- Admin: `admin` / `admin123`
- Collector: `collector` / `collector123`

## Update terbaru

Beberapa improve kecil yang sudah ditambahkan untuk versi terbaru:

- Dashboard sekarang menampilkan **ringkasan hasil update** dan **cara penggunaan fitur**.
- Tutorial penggunaan dipusatkan di dashboard agar admin/collector tidak bingung alurnya.
- Panduan instalasi PWA juga dipusatkan di dashboard.
- Riwayat tagihan, pembayaran, diskon, dan kwitansi sudah lebih jelas untuk operasional harian.

## Alur penggunaan singkat

1. Tambah pelanggan dari menu **Pelanggan**.
2. Isi **tanggal pemasangan** dan data kontak pelanggan.
3. Input meter bulanan di menu **Input Meter**.
4. Proses pembayaran di menu **Tagihan** dengan opsi **tanggal bayar**, **metode**, dan **diskon**.
5. Cetak **Tagihan** atau **Kwitansi** sesuai kebutuhan.
6. Pelanggan login untuk cek status tagihan dan riwayat pembayaran sendiri.

## Menjalankan di lokal

> Butuh PHP 8+ dengan extension PDO SQLite aktif.

```bash
php -S 0.0.0.0:8080
```

Lalu buka:

`http://localhost:8080`

Database otomatis dibuat di file `database.sqlite` saat pertama dijalankan.

## Catatan deploy

Aplikasi ini **tidak bisa jalan di GitHub Pages** karena GitHub Pages hanya untuk static HTML/CSS/JS dan tidak mengeksekusi PHP.

Untuk deploy gunakan hosting yang support PHP:

- cPanel hosting
- VPS (Nginx/Apache + PHP)
- layanan PHP hosting lainnya

## Struktur file inti

- `index.php` - login
- `dashboard.php` - dashboard role-based
- `customers.php` - data pelanggan + akun pelanggan
- `readings.php` - input meter bulanan
- `bills.php` - daftar tagihan + status bayar
- `users.php` - manajemen user admin/collector
- `settings.php` - tarif + password admin
- `profile.php` - ganti password user aktif
- `db.php` - inisialisasi DB + seeding awal
- `functions.php` - helper dan logika billing
