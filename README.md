# OSEAN — Ticketing & Festival Web Application

Aplikasi pemesanan tiket konser/festival OSEAN berbasis PHP Native & MySQL dengan antarmuka neo-brutalis modern (Tailwind CSS).

---

## Daftar Isi
- [Setup Lokal (Development)](#setup-lokal-development)
- [Deployment ke Hosting Production](#deployment-ke-hosting-production)
- [Konfigurasi Environment Variables](#konfigurasi-environment-variables)
- [Checklist Pre-Launch](#checklist-pre-launch)
- [Akun Default](#akun-default)
- [Arsitektur](#arsitektur)

---

## Setup Lokal (Development)

### Prasyarat
- **XAMPP** (Apache + MySQL)
- **PHP 8.1+**
- **Web Browser** (Chrome / Edge / Firefox)

### Langkah-langkah

**1. Clone & Tempatkan di htdocs**
```
C:\xampp\htdocs\ticketingosean\
```

**2. Salin dan isi file environment**
```bash
cp .env.example .env
```
Edit `.env` dengan konfigurasi lokal kamu (lihat bagian [Konfigurasi Environment Variables](#konfigurasi-environment-variables)).

**3. Import Database**
1. Buka [http://localhost/phpmyadmin](http://localhost/phpmyadmin)
2. Buat database baru: `osean_db`
3. Import file `osean_db.sql` — sudah berisi schema lengkap + data awal (semua migration sudah digabung)

**4. Nyalakan XAMPP**
- Start **Apache** dan **MySQL** dari XAMPP Control Panel

**5. Akses Aplikasi**
| Halaman | URL |
|---------|-----|
| Landing Page | http://localhost/ticketingosean/osean-frontend/landing_page.html |
| Beli Tiket | http://localhost/ticketingosean/osean-frontend/buy_tickets_payment.html |
| Tiket Saya | http://localhost/ticketingosean/osean-frontend/my_tickets.html |
| Login | http://localhost/ticketingosean/osean-frontend/login.html |
| Register | http://localhost/ticketingosean/osean-frontend/register.html |
| Admin Panel | http://localhost/ticketingosean/osean-frontend/admin_panel.html |

---

## Deployment ke Hosting Production

### Shared Hosting (cPanel)

**1. Upload Files**
Upload semua file **kecuali** folder `midtrans/` ke direktori public hosting (biasanya `public_html/ticketingosean/` atau langsung ke `public_html/`).

**2. Buat dan isi file `.env`**  
Upload file `.env` ke root project (satu level di atas `osean-backend/`). Isi dengan kredensial production.

> [!CAUTION]
> **JANGAN** upload `.env` ke folder `public_html` yang bisa diakses publik!
> Pastikan `.env` berada di atas `public_html/` atau dilindungi oleh `.htaccess`.

**3. Import Database**
1. Buat database baru via cPanel → MySQL Databases
2. Import `osean_db.sql` — cukup file ini saja, sudah all-in-one

**4. Konfigurasi Database di `.env`**
```ini
DB_HOST=localhost
DB_USER=cpaneluser_dbuser
DB_PASS=password_db_production
DB_NAME=cpaneluser_osean
```

**5. Ganti Password Admin**
Jalankan di phpMyAdmin:
```bash
# Generate hash baru via SSH atau CLI:
php -r "echo password_hash('PASSWORD_KUAT_BARU', PASSWORD_BCRYPT, ['cost' => 12]);"
```
```sql
UPDATE users
SET password_hash = '$2y$12$HASIL_HASH_DI_SINI'
WHERE email = 'admin@osean.com' AND role = 'admin';
```

**6. Set Midtrans Notification URL**  
Di Midtrans Dashboard → Settings → Payment Notification URL:
```
https://domainmu.com/ticketingosean/osean-backend/api/midtrans_notification.php
```

### VPS (Nginx/Ubuntu)

Jika menggunakan VPS dengan Nginx, tambahkan konfigurasi berikut ke server block:

```nginx
# Blokir akses ke .env
location ~ /\.env {
    deny all;
}

# Blokir akses ke file .htaccess (tidak digunakan di Nginx)
location ~ /\.ht {
    deny all;
}

# PHP execution di uploads diblokir
location ~* ^/ticketingosean/osean-backend/uploads/.*\.php$ {
    deny all;
}
```

---

## Konfigurasi Environment Variables

Buat file `.env` di root project berdasarkan `.env.example`:

| Variabel | Keterangan | Contoh |
|----------|------------|--------|
| `DB_HOST` | Host database | `localhost` |
| `DB_USER` | Username database | `root` |
| `DB_PASS` | Password database | `secret123` |
| `DB_NAME` | Nama database | `osean_db` |
| `APP_ALLOWED_ORIGINS` | Origin CORS yang diizinkan (pisah koma) | `https://tiket.osean.ac.id` |
| `SMTP_HOST` | SMTP server | `smtp.gmail.com` |
| `SMTP_PORT` | SMTP port | `587` |
| `SMTP_USER` | Email Gmail | `email@gmail.com` |
| `SMTP_PASS` | Gmail App Password (16 karakter) | `xxxx xxxx xxxx xxxx` |
| `SMTP_FROM_NAME` | Nama pengirim email | `OSEAN Ticketing` |
| `GOOGLE_CLIENT_ID` | Google OAuth Client ID | `xxx.apps.googleusercontent.com` |
| `MIDTRANS_SERVER_KEY` | Midtrans Server Key | `Mid-server-xxx` |
| `MIDTRANS_CLIENT_KEY` | Midtrans Client Key | `Mid-client-xxx` |
| `MIDTRANS_IS_PRODUCTION` | Mode production Midtrans | `true` / `false` |

> [!TIP]
> Gmail App Password: Google Account → Security → 2-Step Verification → App Passwords

---

## Checklist Pre-Launch

Pastikan semua item ini sudah dilakukan sebelum go-live:

### Keamanan
- [ ] File `.env` diisi dengan kredensial production dan **tidak** dicommit ke Git
- [ ] Password admin default `admin123` sudah diganti
- [ ] Domain CORS di `.env` sudah diisi dengan domain production (`APP_ALLOWED_ORIGINS`)
- [ ] SSL/HTTPS sudah aktif di hosting
- [ ] Pastikan `osean-backend/uploads/` tidak bisa diakses via URL publik untuk file non-gambar

### Midtrans
- [ ] `MIDTRANS_IS_PRODUCTION=true` di `.env`
- [ ] Notification URL sudah diset di Midtrans Dashboard
- [ ] Test payment end-to-end dengan nominal minimal (Rp 1.000)

### Email
- [ ] Gmail App Password sudah diisi di `.env`
- [ ] Test kirim email verifikasi dengan akun baru

### Database
- [ ] Semua migration SQL sudah diimport
- [ ] Password admin sudah diganti di DB

### Performa
- [ ] Pastikan PHP OPcache diaktifkan di hosting
- [ ] Aktifkan GZIP compression di server

---

## Akun Default

> [!WARNING]
> Ganti password admin segera setelah pertama kali login!

| Role | Email | Password Default |
|------|-------|-----------------|
| Admin | `admin@osean.com` | `admin123` ⚠️ |
| User | (daftar via Register) | - |

---

## Arsitektur

```
ticketingosean/
├── .env                    # 🔒 Kredensial (tidak di Git)
├── .env.example            # Template env
├── .gitignore
├── osean_db.sql            # Schema + data awal
├── migration_*.sql         # Migration scripts
│
├── osean-backend/          # PHP Backend
│   ├── config.php          # Konfigurasi utama (load .env)
│   ├── phpmailer/          # PHPMailer library
│   ├── uploads/            # Upload bukti transfer
│   └── api/                # REST API Endpoints
│       ├── login.php
│       ├── register.php
│       ├── google_auth.php
│       ├── verify.php
│       ├── payment_create.php
│       ├── payment_status.php
│       ├── payment_cancel.php
│       ├── midtrans_notification.php  # Webhook Midtrans
│       ├── my_tickets.php
│       ├── ticket_verify.php          # Check-in scanner
│       ├── admin_*.php                # Admin endpoints
│       └── ...
│
└── osean-frontend/         # Static HTML Frontend
    ├── landing_page.html
    ├── buy_tickets_payment.html
    ├── my_tickets.html
    ├── login.html
    ├── register.html
    ├── admin_panel.html
    └── assets/
```

### Flow Pembayaran
```
User → payment_create.php → Midtrans Snap (popup)
     ↑                              ↓
payment_status.php ←    midtrans_notification.php (webhook)
     ↓
my_tickets.php (tampilkan tiket)
```
