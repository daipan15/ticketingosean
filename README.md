# OSEAN — Ticketing & Festival Web Application

Aplikasi pemesanan tiket konser/festival OSEAN berbasis PHP Native & MySQL dengan antarmuka neo-brutalis modern (Tailwind CSS).

---

## Panduan Menjalankan Project (Untuk User Baru / Setelah Git Clone)

Jika Anda baru saja melakukan `git clone` atau berpindah komputer, ikuti langkah-langkah mudah berikut:

### 1. Pastikan Prasyarat Terpasang
- **XAMPP** (dengan Apache dan MySQL aktif)
- **Web Browser** (Chrome / Edge / Firefox)

---

### 2. Tempatkan Folder di `htdocs`
Folder project **wajib** berada di dalam direktori `htdocs` XAMPP dan disarankan bernama `ticketingosean`:
```
C:\xampp\htdocs\ticketingosean\
```
> *Jika nama folder repo di GitHub berbeda, pastikan rename foldernya menjadi `ticketingosean` agar semua path URL tidak error.*

---

### 3. Nyalakan Apache & MySQL
1. Buka aplikasi **XAMPP Control Panel**.
2. Klik tombol **Start** pada modul **Apache**.
3. Klik tombol **Start** pada modul **MySQL**.
*(Pastikan indikator kedua modul berubah menjadi hijau)*.

---

### 4. Import Database `osean_db`
1. Buka browser dan kunjungi: **[http://localhost/phpmyadmin](http://localhost/phpmyadmin)**
2. Di sidebar kiri, klik **New** (Baru), masukkan nama database: `osean_db`, lalu klik **Create** (Buat).
3. Klik database `osean_db` yang baru dibuat.
4. Klik tab **Import** di menu atas.
5. Klik tombol **Choose File** / **Pilih File**, lalu arahkan ke file database project:
   ```
   C:\xampp\htdocs\ticketingosean\osean_db.sql
   ```
6. Scroll ke bagian paling bawah dan klik tombol **Import** (atau **Go** / **Kirim**).
7. Tunggu hingga muncul pesan hijau sukses (*Import has been successfully finished*).

---

### 5. Cek Konfigurasi Database (Jika Diperlukan)
Buka file `osean-backend/config.php`:
```php
define('DB_HOST',    'localhost');
define('DB_USER',    'root');
define('DB_PASS',    ''); // Sesuaikan jika MySQL Anda memiliki password
define('DB_NAME',    'osean_db');
```
*Jika menggunakan XAMPP standar tanpa password root, Anda tidak perlu mengubah apapun.*

---

### 6. Akses Halaman Web
Buka browser dan akses halaman-halaman berikut:

- **Beranda / Landing Page**:  
  [http://localhost/ticketingosean/osean-frontend/landing_page.html](http://localhost/ticketingosean/osean-frontend/landing_page.html)
- **Beli Tiket & Pembayaran**:  
  [http://localhost/ticketingosean/osean-frontend/buy_tickets_payment.html](http://localhost/ticketingosean/osean-frontend/buy_tickets_payment.html)
- **Tiket Saya**:  
  [http://localhost/ticketingosean/osean-frontend/my_tickets.html](http://localhost/ticketingosean/osean-frontend/my_tickets.html)
- **Login**:  
  [http://localhost/ticketingosean/osean-frontend/login.html](http://localhost/ticketingosean/osean-frontend/login.html)
- **Register (Daftar Akun)**:  
  [http://localhost/ticketingosean/osean-frontend/register.html](http://localhost/ticketingosean/osean-frontend/register.html)
- **Admin Panel**:  
  [http://localhost/ticketingosean/osean-frontend/admin_panel.html](http://localhost/ticketingosean/osean-frontend/admin_panel.html)

---

### 7. Akun Default untuk Login
- **Akun Admin**:
  - Email: `admin@osean.com`
  - Password: `admin123`
- **Akun User Pembeli**:
  - Anda dapat langsung mendaftar akun baru melalui menu **Register** (`register.html`), atau login dengan akun user jika sudah ada.
