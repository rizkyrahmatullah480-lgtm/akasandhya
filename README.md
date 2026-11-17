# Invitaku — Platform Undangan Digital + Absensi QR

Invitaku adalah aplikasi undangan digital yang dikelola sepenuhnya oleh admin/panitia. Admin membuat acara, memilih desain, mengelola daftar tamu, membuat tautan undangan unik, lalu mengirimkannya melalui WhatsApp. Setiap tamu mendapat satu tautan dan satu QR Code pribadi. Petugas di pintu masuk memindai QR Code untuk mencatat kehadiran secara real-time.

Aplikasi ini **tidak memakai data palsu atau tombol statis**: frontend SvelteKit terhubung langsung ke REST API Laravel dan database MySQL.

> Versi ini adalah pembangunan ulang dari prototipe lama. Folder `legacy_akasandhya/` hanya disimpan sebagai referensi dan tidak dipakai oleh aplikasi.

---

## 1. Peran Pengguna

| Peran | Login | Hak akses |
|---|---|---|
| **Admin** | Ya | Dashboard, Acara, Desain Undangan, Data Tamu, Undangan, Scanner, Laporan Kehadiran, Pengaturan akun petugas |
| **Scanner** | Ya | Hanya halaman Scanner Kehadiran: pilih acara, pindai QR / input token manual, validasi, konfirmasi check-in |
| **Tamu** | Tidak | Membuka tautan `/undangan/{token}` untuk melihat undangan, detail acara, QR Code, dan status kehadiran |

### Akun demo (dibuat oleh seeder)

| Peran | Email | Password |
|---|---|---|
| Admin | `admin@invitaku.com` | `admin123` |
| Scanner | `scanner@invitaku.com` | `scanner123` |
| Scanner | `petugas2@invitaku.com` | `scanner123` |

---

## 2. Fitur Utama

- **Dashboard admin** — total acara/tamu, status pengiriman undangan, jumlah kehadiran, grafik per acara, dan check-in terbaru.
- **Manajemen acara** — CRUD acara, unggah banner, pilih desain undangan, detail statistik tamu dan kehadiran.
- **Galeri desain** — 12 template siap pakai dengan kategori, warna, tipografi, dan ornamen berbeda.
- **Manajemen tamu** — tambah/edit/hapus tamu, filter kategori dan status, impor CSV, pencarian, unduh QR, dan kirim WhatsApp.
- **Undangan unik** — setiap tamu mendapat token acak 48 karakter (bukan ID database). Satu tamu = satu undangan = satu QR.
- **QR Code** — dibuat di server sebagai SVG pada `/api/public/invitations/{token}/qr`; tombol unduh PNG dibuat di browser.
- **WhatsApp Click-to-Chat** — bekerja tanpa konfigurasi API. Status diubah menjadi `queued`, lalu admin menandainya `sent` setelah benar-benar mengirim.
- **WhatsApp Business Cloud API (opsional)** — status `sent` hanya diset setelah provider menerima pesan. Jika gagal/tidak dikonfigurasi, status menjadi `failed` beserta pesan error. Tidak ada klaim sukses palsu.
- **Scanner kehadiran** — kamera belakang, start/stop, input token manual, validasi sebelum konfirmasi, dan pencegahan check-in ganda (transaksi database + unique constraint).
- **Laporan kehadiran** — daftar hadir/belum hadir per acara, filter tanggal, dan ekspor CSV yang kompatibel dengan Excel.
- **Halaman tamu responsif** — desain undangan, nama tamu, detail acara, tombol Google Maps, QR Code, status sudah check-in, dan halaman token tidak valid.

Status pengiriman undangan: `not_sent`, `queued`, `sent`, `failed`.

---

## 3. Teknologi

| Bagian | Teknologi |
|---|---|
| Backend | Laravel 9, PHP 8.0+, Sanctum, Form Request, MySQL |
| Frontend | SvelteKit 3, Svelte 5, TypeScript, Tailwind CSS 4, Vite |
| QR | `bacon/bacon-qr-code` (SVG di server), `qrcode` (PNG di browser) |
| Scanner | `html5-qrcode` |
| WhatsApp | Click-to-Chat (`wa.me`) + Meta WhatsApp Business Cloud API |

---

## 4. Struktur Proyek

```text
akasandhya/
├── backend/                  Laravel REST API
│   ├── app/Http/Controllers/Api
│   ├── app/Http/Requests
│   ├── app/Models
│   ├── app/Services/         InvitationService, WhatsAppService
│   ├── config/whatsapp.php
│   ├── database/migrations
│   ├── database/seeders
│   ├── routes/api.php
│   └── tests/Feature
├── frontend/                 SvelteKit
│   ├── src/lib/components
│   ├── src/lib/services/api.ts
│   ├── src/lib/types/index.ts
│   ├── src/routes/admin
│   ├── src/routes/login
│   ├── src/routes/undangan/[token]
│   └── src/env.ts
├── legacy_akasandhya/        Prototipe lama (tidak dipakai)
├── start-invitaku.bat        Launcher Windows/XAMPP
└── README.md
```

---

## 5. Prasyarat

- Windows + XAMPP (MySQL), atau MySQL/MariaDB yang setara.
- PHP **8.0+** dengan ekstensi `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`.
- Composer 2.
- Node.js 20+ dan npm.
- Browser modern. Kamera hanya bisa diakses lewat `https://` atau `localhost`.

---

## 6. Menjalankan Cepat di Windows (XAMPP)

Dari folder root proyek:

1. Klik dua kali `start-invitaku.bat`.
2. Script akan:
   - menyalakan MySQL XAMPP bila belum aktif,
   - membuat database `db_invitaku` bila belum ada,
   - memasang dependency bila `vendor/` atau `node_modules/` belum ada,
   - menjalankan migrasi dan mengisi data demo bila database masih kosong,
   - membuat symbolic link storage,
   - menjalankan backend di `http://127.0.0.1:8000`,
   - menjalankan frontend di `http://localhost:5173` dan membuka browser.
3. Login dengan akun demo di atas.

Untuk **mengulang data demo dari nol** (menghapus seluruh data yang ada):

```bat
start-invitaku.bat --reset
```

Perintah tersebut menjalankan `migrate:fresh --seed`. Jangan dipakai bila sudah ada data penting.

---

## 7. Instalasi Manual

### 7.1 Database

1. Nyalakan MySQL dari XAMPP Control Panel.
2. Buat database (bila belum ada):

```sql
CREATE DATABASE db_invitaku CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 7.2 Backend Laravel

```bash
cd backend
copy .env.example .env
composer install
php artisan key:generate
```

Sesuaikan `backend/.env`:

```env
APP_URL=http://localhost:8000
APP_TIMEZONE=Asia/Jakarta

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=db_invitaku
DB_USERNAME=root
DB_PASSWORD=

FRONTEND_URL=http://localhost:5173
CORS_ALLOWED_ORIGINS=http://localhost:5173,http://127.0.0.1:5173
FILESYSTEM_DISK=public
```

Lalu:

```bash
php artisan migrate:fresh --seed
php artisan storage:link
php artisan serve --host=127.0.0.1 --port=8000
```

API berjalan di `http://127.0.0.1:8000/api`.

### 7.3 Frontend SvelteKit

Buka terminal baru:

```bash
cd frontend
copy .env.example .env
npm install
npm run dev
```

Isi `frontend/.env`:

```env
PUBLIC_API_URL=http://127.0.0.1:8000/api
```

Buka `http://localhost:5173`.

> `frontend/src/env.ts` mendefinisikan `PUBLIC_API_URL` dengan default `http://127.0.0.1:8000/api`, jadi frontend tetap bisa berjalan tanpa file `.env` pada lingkungan lokal.

---

## 8. Alur Penggunaan

1. **Admin membuat acara** di menu **Acara** → isi nama, jenis, penyelenggara, tanggal, waktu, lokasi, dan desain undangan.
2. **Admin menambahkan tamu** di menu **Data Tamu** → satu per satu, atau impor CSV dengan kolom `name`, `whatsapp_number`, `category`.
3. Sistem otomatis membuat **token unik 48 karakter**, tautan undangan, dan QR Code untuk setiap tamu.
4. **Admin mengirim undangan** dari menu **Data Tamu** / **Undangan**:
   - **Click-to-Chat**: tombol membuka WhatsApp dengan pesan yang sudah terisi. Setelah pesan benar-benar dikirim, admin menekan **Tandai Terkirim**.
   - **Business API**: pesan dikirim oleh server; status diperbarui berdasarkan respons provider.
5. **Tamu membuka tautan** `/undangan/{token}` tanpa login. Halaman menampilkan nama tamu, detail acara, QR Code, tombol Maps, dan status kehadiran.
6. **Petugas scanner** login, memilih acara, menyalakan kamera, lalu memindai QR tamu. Setelah validasi, petugas mengonfirmasi check-in.
7. **Admin memantau** kehadiran di **Dashboard** dan **Laporan Kehadiran**, lalu mengekspor CSV bila diperlukan.

### Format tautan publik

```text
http://localhost:5173/undangan/{token-48-karakter}
```

Token tidak dapat ditebak dan tidak menggunakan ID database.

---

## 9. Konfigurasi WhatsApp

### 9.1 Mode Click-to-Chat (default, tanpa API)

Tidak perlu token API. Pastikan:

```env
FRONTEND_URL=http://localhost:5173
WHATSAPP_API_ENABLED=false
```

Cara kerja:

1. Admin memilih **Kirim WhatsApp → Click-to-Chat**.
2. Backend membuat URL `https://wa.me/62...?text=...` dan menyimpan status `queued`.
3. Admin mengirim pesan di WhatsApp, lalu menekan **Tandai Terkirim** di aplikasi.
4. Status berubah menjadi `sent`.

Mode ini tidak dapat mengetahui apakah pesan benar-benar sampai, sehingga aplikasi tidak pernah otomatis mengklaim `sent`.

### 9.2 Mode WhatsApp Business Cloud API (opsional)

Isi `backend/.env`:

```env
WHATSAPP_API_ENABLED=true
WHATSAPP_API_VERSION=v20.0
WHATSAPP_PHONE_NUMBER_ID=123456789012345
WHATSAPP_ACCESS_TOKEN=EAAG...
WHATSAPP_TEMPLATE_NAME=nama_template_undangan
WHATSAPP_TEMPLATE_LANGUAGE=id
WHATSAPP_WEBHOOK_VERIFY_TOKEN=token-rahasia-anda
```

Langkah singkat di Meta:

1. Buat aplikasi di [Meta for Developers](https://developers.facebook.com/) dan tambahkan produk **WhatsApp**.
2. Ambil **Phone Number ID** dan buat **Permanent Access Token** (System User) dengan izin `whatsapp_business_messaging`.
3. Jika mengirim ke luar jendela layanan 24 jam, buat **message template** yang disetujui Meta. Template harus memiliki **6 parameter body** dengan urutan:
   1. nama penerima (di Mode Santri: nama orang tua/wali)
   2. nama acara
   3. tanggal acara
   4. waktu acara
   5. lokasi acara
   6. tautan undangan
4. Daftarkan webhook:
   - Callback URL: `https://api.domainanda.com/api/webhooks/whatsapp`
   - Verify token: samakan dengan `WHATSAPP_WEBHOOK_VERIFY_TOKEN`
   - Subscribe ke status pesan.
5. Uji dari menu **Data Tamu** dengan mode **Business API**.

Perilaku status:

- Provider menerima pesan → `sent` + `provider_message_id`.
- Provider menolak / API belum dikonfigurasi → HTTP 422 + status `failed` + `delivery_error`.
- Webhook `sent`/`delivered`/`read` → tetap `sent`.
- Webhook `failed` → `failed` + alasan dari provider.

> **Keamanan:** `WHATSAPP_ACCESS_TOKEN` hanya boleh berada di `backend/.env`. Jangan pernah menaruhnya di frontend, repository publik, atau variabel `PUBLIC_*`.

Pesan Click-to-Chat dapat diganti lewat variabel opsional `WHATSAPP_MESSAGE_TEMPLATE` (lihat komentar di `backend/.env.example`). Placeholder yang didukung: `{nama_tamu}`, `{nama_acara}`, `{tanggal_acara}`, `{waktu_acara}`, `{lokasi_acara}`, `{link_undangan}`. Di Mode Santri, `{nama_tamu}` berisi nama orang tua/wali (bila diisi); undangan publik tetap menampilkan nama santri.

---

## 10. Endpoint API Utama

Semua respons menggunakan format `{ "success": true|false, "message"?: string, "data"?: ... }`.

### Publik

| Method | Endpoint | Keterangan |
|---|---|---|
| `POST` | `/api/login` | Login admin/scanner, mengembalikan token Sanctum |
| `GET` | `/api/public/invitations/{token}` | Data undangan tamu tanpa login |
| `GET` | `/api/public/invitations/{token}/qr` | QR Code SVG |
| `GET` | `/api/webhooks/whatsapp` | Verifikasi webhook Meta |
| `POST` | `/api/webhooks/whatsapp` | Update status pengiriman dari Meta |

### Terautentikasi (admin & scanner)

| Method | Endpoint | Keterangan |
|---|---|---|
| `GET` | `/api/user` | Profil pengguna aktif |
| `PUT` | `/api/user/profile` | Ubah nama/no. HP |
| `PUT` | `/api/user/password` | Ubah password |
| `POST` | `/api/logout` | Cabut token aktif |
| `GET` | `/api/templates` | Galeri desain |
| `GET` | `/api/template-categories` | Kategori desain |
| `GET` | `/api/templates/{idOrSlug}` | Detail desain |
| `GET` | `/api/scanner/events` | Daftar acara + jumlah tamu/hadir |
| `POST` | `/api/scanner/validate` | Validasi token tanpa menulis data |
| `POST` | `/api/scanner/check-in` | Catat kehadiran (aman dari duplikasi) |

### Khusus admin

| Method | Endpoint | Keterangan |
|---|---|---|
| `GET` | `/api/dashboard` | Statistik dashboard |
| `GET/POST` | `/api/events` | Daftar / buat acara |
| `GET/PUT/DELETE` | `/api/events/{event}` | Detail / ubah / hapus acara |
| `POST` | `/api/events/{event}/banner` | Unggah banner |
| `GET/POST` | `/api/events/{event}/guests` | Daftar / tambah tamu |
| `POST` | `/api/events/{event}/guests/import` | Impor CSV atau array tamu |
| `PUT/DELETE` | `/api/guests/{guest}` | Ubah / hapus tamu |
| `GET` | `/api/invitations` | Daftar seluruh undangan |
| `GET` | `/api/events/{event}/invitations` | Undangan per acara |
| `GET` | `/api/guests/{guest}/invitation` | Buat/ambil undangan tamu |
| `POST` | `/api/guests/{guest}/generate-invitation` | Buat ulang tautan + QR |
| `POST` | `/api/guests/{guest}/send-whatsapp` | Kirim via `click` atau `api` |
| `POST` | `/api/invitations/{invitation}/mark-sent` | Konfirmasi manual mode click |
| `GET` | `/api/events/{event}/attendance` | Laporan hadir/belum hadir |
| `GET` | `/api/events/{event}/attendance/export` | Unduh CSV |
| `GET` | `/api/reports/summary` | Ringkasan kehadiran |
| `GET/POST` | `/api/users` | Daftar / buat akun petugas |
| `PUT/DELETE` | `/api/users/{user}` | Ubah / hapus akun petugas |

Catatan scanner: hasil `validate` dan `check-in` mengembalikan field seperti `valid`, `checked_in`, `already_checked_in`, dan `code` di level teratas JSON.

---

## 11. Skema Database

| Tabel | Fungsi |
|---|---|
| `users` | Admin dan petugas scanner (`role`: `admin`/`scanner`) |
| `categories` | Kategori desain undangan |
| `templates` | Desain undangan (`configuration` JSON, `preview_image`) |
| `events` | Acara yang dibuat admin |
| `guests` | Tamu per acara + `unique_token` 48 karakter |
| `invitations` | Tautan, QR, status pengiriman, pesan provider (satu per tamu) |
| `attendance` | Check-in tamu; unik pada `(guest_id, event_id)` |

---

## 12. Pengujian

### Backend

```bash
cd backend
php artisan test
```

Feature test berjalan di SQLite in-memory (lihat `phpunit.xml`) sehingga tidak mengubah database MySQL lokal. Cakupan:

- login, token, logout, dan pembatasan role admin/scanner;
- CRUD acara, validasi, cascade delete;
- CRUD tamu, normalisasi nomor WhatsApp, impor batch;
- token unik 48 karakter dan QR SVG;
- halaman undangan publik dan token tidak valid;
- Click-to-Chat (`queued`), tandai terkirim (`sent`), API gagal jujur (`failed`);
- pengiriman API dengan provider palsu (`Http::fake`) dan webhook status;
- validasi scanner, check-in, dan pencegahan check-in ganda;
- CRUD akun petugas dan perlindungan akun admin.

### Frontend

```bash
cd frontend
npm run check
npm run build
```

### Uji manual yang disarankan

1. Login admin → buat acara → pilih desain → tambah tamu.
2. Buka tautan tamu di ponsel/incognito, cek tampilan dan QR.
3. Login scanner di perangkat lain → pilih acara → pindai QR → konfirmasi.
4. Scan ulang QR yang sama → harus muncul "sudah tercatat hadir" dan jumlah kehadiran tidak bertambah.
5. Coba token acak → harus muncul halaman undangan tidak valid.
6. Kirim Click-to-Chat → cek `queued` → tandai terkirim → cek `sent`.
7. Ekspor CSV laporan dan buka di Excel.
8. Uji kamera di `http://localhost:5173` (bukan IP LAN biasa) atau gunakan HTTPS.

---

## 13. Panduan Deployment (dilakukan sendiri oleh Anda)

> Proyek ini **tidak di-deploy, tidak di-push, dan tidak terhubung ke hosting/GitHub** oleh pembuatnya. Langkah berikut adalah panduan agar Anda dapat melakukannya secara mandiri.

### 13.0 Stack Produksi yang Disarankan (Netlify + Supabase)

Frontend SvelteKit dan backend Laravel punya kebutuhan hosting berbeda:

| Bagian | Platform yang cocok | Catatan |
|---|---|---|
| Frontend | **Netlify** | `frontend/` sudah memakai `@sveltejs/adapter-netlify` |
| Database | **Supabase** (PostgreSQL) | Laravel connect via driver `pgsql` |
| Backend | Hosting PHP **atau** platform Docker gratis (Railway, Render) | Netlify **tidak bisa** menjalankan PHP/Laravel |

File pendukung yang sudah disiapkan:

- `frontend/vite.config.ts` → `adapter-netlify`
- `backend/.env.production.example` → template env produksi untuk Supabase
- `backend/Dockerfile` + `backend/docker-entrypoint.sh` → deploy backend ke platform berbasis Docker (Railway/Render/dkk). Set env `RUN_MIGRATIONS=true` untuk menjalankan `migrate --force` saat container start.
- `backend/config/database.php` → `sslmode` pgsql membaca `DB_SSLMODE` (set `require` untuk Supabase)

**Supabase:** ambil connection string di *Project Settings → Database*. Gunakan port **5432** (session mode), bukan 6543 (transaction pooler) — check-in memakai transaksi + `lockForUpdate` yang tidak aman di transaction mode.

### 13.1 Backend Laravel

1. Siapkan VPS/hosting PHP 8.0+ dan MySQL.
2. Unggah folder `backend/`, arahkan document root web server ke `backend/public`.
3. Buat database produksi dan user database.
4. Salin `.env.example` → `.env`, lalu isi:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.domainanda.com
APP_TIMEZONE=Asia/Jakarta

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=nama_database
DB_USERNAME=user_database
DB_PASSWORD=password_kuat

FRONTEND_URL=https://domainanda.com
CORS_ALLOWED_ORIGINS=https://domainanda.com

FILESYSTEM_DISK=public
```

5. Jalankan:

```bash
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
```

6. **Jangan** menjalankan `db:seed` di produksi karena akan membuat akun demo. Buat admin pertama secara manual, misalnya:

```bash
php artisan tinker
```

```php
\App\Models\User::create([
    'name' => 'Admin',
    'email' => 'admin@domainanda.com',
    'password' => \Illuminate\Support\Facades\Hash::make('password-yang-kuat'),
    'role' => 'admin',
]);
```

7. Pastikan folder `storage/` dan `bootstrap/cache/` dapat ditulis oleh web server.
8. Aktifkan HTTPS. Kamera scanner memerlukan HTTPS pada domain produksi.

### 13.2 Frontend SvelteKit

1. Isi URL API produksi:

```env
PUBLIC_API_URL=https://api.domainanda.com/api
```

2. Untuk server Node.js, pasang adapter Node:

```bash
npm install @sveltejs/adapter-node
```

Lalu ganti baris adapter di `frontend/vite.config.ts`:

```ts
import adapter from '@sveltejs/adapter-node';
```

3. Build dan jalankan:

```bash
npm run build
node build
```

4. Jalankan sebagai service (misalnya PM2) di belakang Nginx. Set `ORIGIN=https://domainanda.com` pada environment service agar SvelteKit menghasilkan URL yang benar.
5. Alternatif: gunakan adapter lain sesuai hosting Anda. Untuk hosting statis murni, gunakan `@sveltejs/adapter-static` dan pastikan seluruh halaman memakai mode SPA/fallback yang sesuai.

### 13.3 WhatsApp di Produksi

- Isi variabel WhatsApp hanya di environment backend.
- Daftarkan callback webhook HTTPS yang dapat diakses publik.
- Gunakan template WhatsApp yang sudah disetujui Meta untuk pesan di luar jendela 24 jam.
- Uji pengiriman ke nomor Anda sendiri sebelum dipakai acara sungguhan.
- Pantau kolom `delivery_error` dan status `failed` di menu **Undangan**.

### 13.4 Checklist Go-Live

- [ ] `APP_DEBUG=false` dan `APP_ENV=production`
- [ ] HTTPS aktif di frontend dan API
- [ ] `CORS_ALLOWED_ORIGINS` hanya berisi domain frontend
- [ ] `FRONTEND_URL` mengarah ke domain frontend
- [ ] Storage link dan permission benar
- [ ] Admin pertama dibuat, akun demo dihapus
- [ ] Backup database terjadwal
- [ ] WhatsApp API/webhook diuji dengan nomor nyata
- [ ] Scanner diuji di ponsel dengan kamera belakang

---

## 14. Catatan Teknis

- Token tamu dibuat dengan `Str::random(48)` dan dicek unik sebelum disimpan.
- QR Code server berisi URL undangan, bukan data pribadi tamu.
- Check-in memakai `DB::transaction`, `lockForUpdate`, dan unique index `(guest_id, event_id)`. Jika dua petugas memindai bersamaan, hanya satu baris kehadiran yang dibuat.
- Server tidak memakai GD/Imagick; QR server dikirim sebagai SVG. PNG dibuat di browser.
- Path gambar contoh desain berada di `frontend/static/templates/`; backend hanya menyimpan path-nya.
- Tailwind CSS 4 tidak mendukung `@apply` untuk class komponen kustom, sehingga komponen di `frontend/src/routes/layout.css` memakai utility standar.
