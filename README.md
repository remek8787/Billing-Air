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

## Akun default awal

- Admin: `admin` / `admin123`
- Collector: `collector` / `collector123`

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
