# Menjalankan EcoBank Laravel dengan XAMPP

EcoBank sekarang menggunakan Laravel 12, Blade, Eloquent, sesi Laravel, dan MySQL XAMPP. PHP 8.2 atau lebih baru serta Composer diperlukan.

## Persiapan

1. Nyalakan **MySQL** dari XAMPP Control Panel.
2. Buat database bernama `ecobank` di phpMyAdmin jika belum ada.
3. Dari folder proyek, jalankan:

```powershell
Copy-Item .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
```

Jika database MySQL memakai port, username, atau password berbeda, sesuaikan `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, dan `DB_PASSWORD` di `.env`. Migration memanfaatkan tabel EcoBank yang sudah ada, menambahkan kolom role/relasi yang diperlukan, dan tidak menghapus data lama.

## Jalankan aplikasi

```powershell
php artisan serve
```

Buka `http://127.0.0.1:8000`. Gunakan URL ini, bukan membuka file HTML langsung. Halaman aplikasi disajikan melalui route Laravel dan view Blade.

## Akun admin

Daftarkan akun melalui halaman masuk/daftar. Setelah itu, ubah role akun tersebut melalui phpMyAdmin:

```sql
UPDATE users SET role = 'admin' WHERE email = 'email-admin@contoh.com';
```

Keluar lalu masuk kembali setelah mengubah role. Akun baru selalu ber-role `user`.

## Endpoint utama

| Metode | URL                      | Kegunaan                                |
| ------ | ------------------------ | --------------------------------------- |
| GET    | `/api/prices`            | Daftar harga aktif                      |
| POST   | `/api/prices`            | Tambah harga (admin)                    |
| PUT    | `/api/prices/{id}`       | Ubah harga (admin)                      |
| DELETE | `/api/prices/{id}`       | Hapus jenis yang belum dipakai          |
| GET    | `/api/transactions`      | Riwayat akun atau semua transaksi admin |
| POST   | `/api/transactions`      | Ajukan setoran pengguna                 |
| PUT    | `/api/transactions/{id}` | Setujui/batalkan transaksi (admin)      |
| POST   | `/api/auth`              | Daftar, masuk, atau keluar              |
| GET    | `/api/auth/me`           | Memeriksa sesi                          |

Nilai transaksi dihitung server dari harga aktif. Pengguna hanya dapat melihat transaksinya sendiri; perubahan harga dan keputusan transaksi memerlukan akun admin.
