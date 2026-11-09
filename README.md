# SIPUS-Del - Sistem Informasi Perpustakaan IT Del

Aplikasi pengelolaan perpustakaan berbasis Laravel 11 untuk tugas praktikum Minggu 6 PPW.

## Kredensial Uji Pustakawan
* **Email**: `pustakawan@del.ac.id`
* **Password**: `password123`

## Cara Menjalankan Aplikasi
1. Clone repository
2. Jalankan `composer install`
3. Salin `.env.example` ke `.env` dan atur koneksi database (`sipus-del`)
4. Jalankan migrasi dan seeder:
   ```bash
   php artisan migrate:fresh --seed

   ### 1. Pengujian SOAP Service (Check Stok)
![SOAP Check Stok](docs/screenshots/01-soap-check-stok.png)

### 2. Autentikasi Sanctum (Login)
![Auth Login](docs/screenshots/02-auth-login.png)

### 3. Katalog Buku Terpaginasi & Filter
![GET Katalog Buku](docs/screenshots/03-get-katalog-buku.png)

### 4. Tambah Buku Baru (POST 201 Created)
![POST Tambah Buku](docs/screenshots/04-post-tambah-buku.png)

### 5. Update Stok Buku (PATCH 200 OK)
![PATCH Update Stok](docs/screenshots/05-patch-update-stok.png)

### 6. Transaksi Peminjaman Buku (ACID Transaction)
* **Peminjaman Sukses (201 Created):**
  ![Peminjaman Sukses](docs/screenshots/06a-post-peminjaman-sukses.png)
* **Penolakan Stok Habis / Duplicate Loan (422 / 409):**
  ![Peminjaman Gagal](docs/screenshots/06b-post-peminjaman-gagal.png)

### 7. Integrasi Referensi Eksternal (OpenLibrary)
![GET Referensi Eksternal](docs/screenshots/07-get-referensi-eksternal.png)