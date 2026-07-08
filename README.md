# Backend ERP CV. Beton Agung

Backend API untuk sistem Enterprise Resource Planning (ERP) yang dibangun khusus untuk **CV. Beton Agung**. Project ini memakai framework modern Laravel 13, PHP 8.3, Laravel Sanctum untuk autentikasi, dan PostgreSQL sebagai basis data. Sistem ini dirancang secara scalable dan menyediakan antarmuka JSON API RESTful untuk dikonsumsi oleh aplikasi frontend React yang berada di direktori `../frontend`.

## Tentang Sistem ERP

Sistem ERP CV. Beton Agung dikembangkan untuk mendigitalisasi, mengotomatisasi, dan mengintegrasikan seluruh alur kerja operasional perusahaan. Sistem ini sangat krusial untuk:
- **Sentralisasi Data:** Menyatukan data dari berbagai divisi (Gudang, Penjualan, Pembelian, Keuangan, HRD, dan Produksi) ke dalam satu sumber kebenaran (Single Source of Truth).
- **Efisiensi Operasional:** Mengelola stok material dan produk jadi secara presisi, memantau pergerakan barang (stock movement), dan mempercepat proses approval transaksi.
- **Transparansi & Pelaporan:** Memudahkan manajemen dalam mengambil keputusan melalui dashboard real-time, laporan keuangan, dan pencatatan jejak audit (audit log) yang komprehensif.

## Fitur Utama

- Autentikasi API memakai Bearer token Laravel Sanctum.
- RBAC role dan permission untuk proteksi endpoint.
- Master data customer, supplier, produk, kategori, satuan, gudang, lokasi stok, dan company settings.
- Inventory, stock movement, product stock, stock opname, dan approval request.
- Sales: quotation, sales order, delivery order, dan workflow approval/shipping.
- Purchasing: purchase request, RFQ, purchase order, goods receipt, payable, dan return.
- Finance: invoice, payment, account, cash transaction, billing, cashier, payable, dan cash bank.
- Project, production, HRD, payroll, audit log, reminder, document export, dan dashboard summary.

## Prasyarat

- PHP 8.3 atau lebih baru.
- Composer.
- PostgreSQL.
- Node.js dan npm, hanya diperlukan jika menjalankan asset/Vite Laravel.

## Setup Lokal

1. Install dependency PHP:

   ```bash
   composer install
   ```

2. Buat file environment:

   ```bash
   copy .env.example .env
   ```

3. Generate application key:

   ```bash
   php artisan key:generate
   ```

4. Buat database PostgreSQL, lalu sesuaikan `.env`:

   ```env
   DB_CONNECTION=pgsql
   DB_HOST=127.0.0.1
   DB_PORT=5432
   DB_DATABASE=cv_beton_agung_erp
   DB_USERNAME=postgres
   DB_PASSWORD=
   ```

5. Jalankan migration dan seeder:

   ```bash
   php artisan migrate --seed
   ```

6. Jalankan API server:

   ```bash
   php artisan serve
   ```

7. Cek health endpoint:

   ```text
   http://localhost:8000/api/health
   ```

## Akun Awal

Seeder membuat super admin berikut:

```text
Email: admin@betonagung.co.id
Password: password
Role: admin
```

Seeder juga membuat user untuk data employee dengan password default `password`.

## Environment Penting

| Nama | Keterangan | Contoh |
| --- | --- | --- |
| `APP_NAME` | Nama service backend. | `CV Beton Agung ERP Backend` |
| `APP_URL` | URL aplikasi backend. | `http://localhost` |
| `DB_CONNECTION` | Driver database. | `pgsql` |
| `DB_DATABASE` | Nama database ERP. | `cv_beton_agung_erp` |
| `SESSION_DRIVER` | Driver session Laravel. | `database` |
| `QUEUE_CONNECTION` | Driver queue. | `database` |
| `CACHE_STORE` | Driver cache. | `database` |
| `ALLOWED_ORIGINS` | Origin frontend yang boleh akses API. | `http://localhost:3000,http://localhost:5173` |

Untuk frontend lokal, pastikan `.env` backend memuat origin berikut:

```env
ALLOWED_ORIGINS=http://localhost:3000,http://localhost:5173,http://127.0.0.1:5173
```

## Script Composer

| Command | Fungsi |
| --- | --- |
| `composer run setup` | Install dependency, buat `.env`, generate key, migrate, install npm, dan build asset. |
| `composer run dev` | Menjalankan server Laravel, queue listener, log pail, dan Vite Laravel secara bersamaan. |
| `composer run test` | Clear config lalu menjalankan test Laravel. |

Untuk API-only development dengan frontend terpisah, biasanya cukup jalankan:

```bash
php artisan serve
```

Jika fitur queue dipakai, jalankan worker terpisah:

```bash
php artisan queue:listen --tries=1 --timeout=0
```

## Endpoint Dasar

Endpoint publik:

| Method | Endpoint | Fungsi |
| --- | --- | --- |
| `GET` | `/api/health` | Health check service. |
| `POST` | `/api/auth/login` | Login dan membuat Sanctum token. |

Endpoint setelah login memakai header `Authorization: Bearer <token>`:

| Method | Endpoint | Fungsi |
| --- | --- | --- |
| `GET` | `/api/auth/me` | Data user aktif. |
| `POST` | `/api/auth/logout` | Logout dan revoke token aktif. |
| `GET` | `/api/dashboard/summary` | Ringkasan dashboard. |
| `GET` | `/api/reports` | Data laporan. |

Prefix modul utama:

```text
/api/identity/*
/api/master/*
/api/master-data/*
/api/inventory/*
/api/sales/*
/api/purchasing/*
/api/projects/*
/api/finance/*
/api/production/*
/api/support/*
/api/hrd/*
```

## Integrasi Frontend

Frontend berada di `../frontend` dan default mengakses backend melalui:

```env
VITE_API_BASE_URL="http://localhost:8000/api"
```

Alur lokal yang disarankan:

1. Jalankan PostgreSQL.
2. Jalankan migration dan seeder backend.
3. Jalankan backend dengan `php artisan serve`.
4. Pastikan `/api/health` mengembalikan `status: ok`.
5. Jalankan frontend dari `../frontend` dengan `npm run dev`.
6. Login memakai akun seeder.

## Struktur Penting

```text
app/Http/Controllers/Api/  Controller JSON API per modul
database/migrations/       Skema database ERP
database/seeders/          Seed role, user, customer, supplier, produk, material
routes/api.php             Definisi endpoint API utama
app/Models/                 Model Eloquent domain ERP
```

## Troubleshooting

- `401 Unauthorized`: token tidak ada, token invalid, atau user belum login.
- `403 Forbidden`: user tidak punya permission untuk route tersebut.
- Error CORS dari browser: tambahkan origin frontend ke `ALLOWED_ORIGINS` lalu restart backend.
- Error koneksi database: pastikan database PostgreSQL sudah dibuat dan kredensial `.env` benar.
- Setelah mengubah `.env`, jalankan `php artisan config:clear` jika konfigurasi masih terbaca lama.
