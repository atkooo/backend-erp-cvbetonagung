# Engineering Documentation — Backend
## Sistem ERP CV. Betonagung

> **Versi:** 1.0  
> **Tanggal:** Juni 2026  
> **Framework:** Laravel 13 (PHP 8.3)  
> **Database:** SQLite (dev) / MySQL (prod)  
> **Auth:** Laravel Sanctum (Bearer Token)

---

## 1. Gambaran Umum

Backend ERP CV. Betonagung adalah REST API berbasis Laravel yang melayani seluruh operasi bisnis perusahaan, mencakup:

| Modul | Deskripsi |
|---|---|
| **Identity** | Manajemen user, role, permission (RBAC) |
| **Master Data** | Pelanggan, pemasok, produk, gudang, satuan |
| **Penjualan (Sales)** | Quotation → Sales Order → Delivery Order |
| **Pembelian (Purchasing)** | PR → RFQ → PO → GRN |
| **Inventori** | Stok multi-lokasi, mutasi stok, stock opname |
| **Keuangan (Finance)** | Invoice, pembayaran, piutang/hutang, kas |
| **Produksi** | Work Order, Work Log, BOM & Costing |
| **Proyek** | Manajemen proyek, termin, timeline, dokumen |
| **HRD** | Data karyawan, absensi, cuti, penggajian |
| **Support** | Audit log, pengingat, ekspor dokumen |

---

## 2. Stack Teknologi

| Komponen | Teknologi | Versi |
|---|---|---|
| Framework | Laravel | ^13.8 |
| Bahasa | PHP | ^8.3 |
| Auth | Laravel Sanctum | ^4.3 |
| 2FA | pragmarx/google2fa | ^9.0 |
| Database (Dev) | SQLite | — |
| Database (Prod) | MySQL | ≥8.0 |
| Queue | Laravel Queue (sync/database) | — |
| Testing | PHPUnit | ^12.5 |
| Code Style | Laravel Pint | ^1.27 |

---

## 3. Struktur Direktori

```
backend/
├── app/
│   ├── Console/               # Artisan commands
│   ├── Helpers/               # Helper functions global
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── Api/
│   │   │       ├── AuthController.php
│   │   │       ├── IdentityController.php
│   │   │       ├── MasterDataController.php
│   │   │       ├── SalesController.php
│   │   │       ├── PurchasingController.php
│   │   │       ├── InventoryController.php
│   │   │       ├── FinanceController.php
│   │   │       ├── ProductionController.php
│   │   │       ├── ProjectController.php
│   │   │       ├── HrdController.php
│   │   │       ├── PayrollController.php
│   │   │       ├── SupportController.php
│   │   │       ├── SystemController.php
│   │   │       ├── Dashboard/
│   │   │       │   └── DashboardSummaryController.php
│   │   │       ├── Finance/
│   │   │       │   └── FinanceQueryController.php
│   │   │       ├── Inventory/
│   │   │       │   └── InventoryQueryController.php
│   │   │       ├── Master/
│   │   │       │   └── MasterQueryController.php
│   │   │       ├── Purchasing/
│   │   │       │   └── PurchasingQueryController.php
│   │   │       ├── Reports/
│   │   │       │   └── ReportsController.php
│   │   │       └── Sales/
│   │   │           └── SalesQueryController.php
│   │   ├── Middleware/        # Permission middleware
│   │   └── Requests/          # Form Request Validation
│   ├── Models/                # 64 Eloquent Models
│   ├── Providers/             # Service Providers
│   ├── Services/              # Business Logic Layer
│   │   ├── SalesWorkflowService.php
│   │   ├── PurchasingWorkflowService.php
│   │   ├── InventoryWorkflowService.php
│   │   ├── FinanceWorkflowService.php
│   │   └── ProductionWorkflowService.php
│   └── Traits/                # Reusable traits
├── database/
│   ├── migrations/            # 23 migration files
│   ├── seeders/
│   └── factories/
├── routes/
│   ├── api.php                # Semua endpoint API
│   ├── web.php
│   └── console.php
├── config/
├── tests/
└── docs/
    └── engineering.md         # File ini
```

---

## 4. Autentikasi & Otorisasi

### 4.1 Autentikasi (Sanctum)

- Semua endpoint dilindungi middleware `auth:sanctum`
- Login mengembalikan `access_token` (Bearer Token)
- Token disimpan di database tabel `personal_access_tokens`

```
POST /api/auth/login
Body: { email, password, otp? }
Response: { data: { token_type, access_token, user } }
```

### 4.2 Otorisasi (RBAC)

Sistem menggunakan Role-Based Access Control dengan middleware custom `permission`:

```
roles → role_permissions ← permissions
  ↓
users
```

- Setiap `permission` memiliki `module` + `action` yang unik
- `role_permissions` memiliki `access_level` (read / write / admin)
- Middleware `permission` memvalidasi hak akses per endpoint

### 4.3 Endpoint Auth

| Method | Path | Aksi |
|---|---|---|
| POST | `/api/auth/login` | Login (publik) |
| GET | `/api/auth/me` | Data user saat ini |
| POST | `/api/auth/logout` | Logout |
| PUT | `/api/auth/profile` | Update profil |

---

## 5. Pola API & Routing

### 5.1 Konvensi Routing

Hampir seluruh resource menggunakan pola CRUD generik:

```php
Route::prefix('{resource}')->whereIn('resource', [...])->group(function () {
    Route::get('/', 'index');       // List dengan pagination
    Route::post('/', 'store');      // Create
    Route::get('/{id}', 'show');    // Detail by UUID
    Route::put('/{id}', 'update'); // Update
    Route::delete('/{id}', 'destroy'); // Hapus
});
```

Semua ID menggunakan **UUID** dan divalidasi dengan `->whereUuid('id')`.

### 5.2 Format Response

**Sukses (list):**
```json
{
  "data": [...],
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 15,
    "total": 75
  }
}
```

**Sukses (single):**
```json
{ "data": { ...resource } }
```

**Error:**
```json
{
  "message": "Pesan error.",
  "errors": { "field": ["detail error"] }
}
```

### 5.3 Daftar Endpoint API

#### Auth & System
| Method | Path | Deskripsi |
|---|---|---|
| `POST` | `/api/auth/login` | Login |
| `GET` | `/api/auth/me` | Profil user |
| `POST` | `/api/auth/logout` | Logout |
| `PUT` | `/api/auth/profile` | Update profil |
| `GET` | `/api/system/backup` | Download backup SQL |
| `GET` | `/api/health` | Health check |

#### Dashboard
| Method | Path | Deskripsi |
|---|---|---|
| `GET` | `/api/dashboard/summary` | Ringkasan statistik dashboard |

#### Master Data
| Method | Path | Deskripsi |
|---|---|---|
| `GET/POST` | `/api/master-data/customers` | CRUD pelanggan |
| `GET/POST` | `/api/master-data/suppliers` | CRUD pemasok |
| `GET/POST` | `/api/master-data/products` | CRUD produk |
| `GET/POST` | `/api/master-data/product-categories` | CRUD kategori |
| `GET/POST` | `/api/master-data/units` | CRUD satuan |
| `GET/POST` | `/api/master-data/warehouses` | CRUD gudang |
| `GET/POST` | `/api/master-data/storage-locations` | CRUD lokasi penyimpanan |
| `GET/POST` | `/api/master-data/company-settings` | Pengaturan perusahaan |

#### Penjualan (Sales)
| Method | Path | Deskripsi |
|---|---|---|
| `GET/POST` | `/api/sales/quotations` | CRUD quotation |
| `POST` | `/api/sales/quotations/{id}/approve` | Setujui quotation |
| `GET/POST` | `/api/sales/sales-orders` | CRUD sales order |
| `POST` | `/api/sales/sales-orders/{id}/approve` | Setujui SO |
| `POST` | `/api/sales/sales-orders/{id}/deliver` | Buat DO dari SO |
| `GET/POST` | `/api/sales/delivery-orders` | CRUD delivery order |
| `POST` | `/api/sales/delivery-orders/{id}/ship` | Kirim DO |

#### Pembelian (Purchasing)
| Method | Path | Deskripsi |
|---|---|---|
| `GET/POST` | `/api/purchasing/purchase-requests` | CRUD PR |
| `GET/POST` | `/api/purchasing/rfqs` | CRUD RFQ |
| `GET/POST` | `/api/purchasing/purchase-orders` | CRUD PO |
| `POST` | `/api/purchasing/purchase-orders/{id}/receive` | Terima PO |
| `GET/POST` | `/api/purchasing/goods-receipt-notes` | CRUD GRN |
| `GET/POST` | `/api/purchasing/supplier-payables` | Hutang supplier |
| `GET/POST` | `/api/purchasing/returns` | Retur pembelian |

#### Inventori
| Method | Path | Deskripsi |
|---|---|---|
| `GET/POST` | `/api/inventory/product-stocks` | Stok produk per lokasi |
| `GET/POST` | `/api/inventory/stock-movements` | Mutasi stok |
| `GET/POST` | `/api/inventory/stock-opname-sessions` | Sesi stock opname |
| `GET/POST` | `/api/inventory/stock-opname-items` | Item stock opname |
| `POST` | `/api/inventory/stock-opname-items/{id}/adjust` | Ajustmen stok |
| `GET/POST` | `/api/inventory/approval-requests` | Request persetujuan |

#### Keuangan (Finance)
| Method | Path | Deskripsi |
|---|---|---|
| `GET/POST` | `/api/finance/invoices` | CRUD invoice |
| `GET/POST` | `/api/finance/payments` | CRUD pembayaran |
| `POST` | `/api/finance/payments/{id}/verify` | Verifikasi pembayaran |
| `POST` | `/api/finance/supplier-payables/{id}/pay` | Bayar hutang supplier |
| `GET/POST` | `/api/finance/project-termins` | Termin proyek |
| `GET/POST` | `/api/finance/accounts` | Akun kas/bank |
| `GET/POST` | `/api/finance/cash-transactions` | Transaksi kas |

#### Produksi
| Method | Path | Deskripsi |
|---|---|---|
| `GET/POST` | `/api/production/work-orders` | CRUD work order |
| `POST` | `/api/production/work-orders/{id}/receive` | Selesaikan WO |
| `GET/POST` | `/api/production/work-logs` | Log kerja |
| `GET/POST` | `/api/production/boms` | Bill of Materials |
| `GET/POST` | `/api/production/bom-items` | Item BOM |

#### Proyek
| Method | Path | Deskripsi |
|---|---|---|
| `GET/POST` | `/api/projects/projects` | CRUD proyek |
| `GET/POST` | `/api/projects/project-timelines` | Timeline proyek |
| `GET/POST` | `/api/projects/project-documents` | Dokumen proyek |
| `GET/POST` | `/api/projects/project-budget-items` | Anggaran proyek |

#### HRD
| Method | Path | Deskripsi |
|---|---|---|
| `GET/POST` | `/api/hrd/employee-details` | Detail karyawan |
| `GET/POST` | `/api/hrd/employee-documents` | Dokumen karyawan |
| `GET/POST` | `/api/hrd/leave-types` | Jenis cuti |
| `GET/POST` | `/api/hrd/leaves` | Data cuti |
| `GET/POST` | `/api/hrd/attendances` | Data absensi |
| `POST` | `/api/hrd/attendances/scan` | Scan absensi QR |

#### Identity
| Method | Path | Deskripsi |
|---|---|---|
| `GET/POST` | `/api/identity/roles` | CRUD role |
| `GET/POST` | `/api/identity/users` | CRUD user |
| `GET/POST` | `/api/identity/employees` | CRUD karyawan |
| `GET/POST` | `/api/identity/permissions` | CRUD permission |
| `GET/PUT/DELETE` | `/api/identity/role-permissions/{roleId}/{permissionId}` | Kelola hak akses role |

#### Support
| Method | Path | Deskripsi |
|---|---|---|
| `GET/POST` | `/api/support/audit-logs` | Audit log |
| `GET/POST` | `/api/support/reminders` | Pengingat |
| `GET/POST` | `/api/support/document-exports` | Ekspor dokumen |

#### Query / Laporan
| Method | Path | Deskripsi |
|---|---|---|
| `GET` | `/api/master/customers` | Lookup pelanggan |
| `GET` | `/api/master/suppliers` | Lookup pemasok |
| `GET` | `/api/master/products` | Lookup produk |
| `GET` | `/api/sales/orders` | Query SO |
| `GET` | `/api/sales/invoices` | Query invoice |
| `GET` | `/api/purchasing/receivings` | Query penerimaan |
| `GET` | `/api/inventory/stocks` | Query stok |
| `GET` | `/api/inventory/stock-ins` | Query stok masuk |
| `GET` | `/api/inventory/stock-outs` | Query stok keluar |
| `GET` | `/api/finance/billing` | Query tagihan |
| `GET` | `/api/finance/cashier` | Query kasir |
| `GET` | `/api/finance/account-payable` | Query piutang/hutang |
| `GET` | `/api/finance/cash-bank` | Query kas/bank |
| `GET` | `/api/reports` | Laporan komprehensif |

---

## 6. Arsitektur Layanan (Service Layer)

Business logic kompleks dienkapsulasi di dalam `app/Services/`:

| Service | Tanggung Jawab |
|---|---|
| `SalesWorkflowService` | Approve quotation, buat SO dari quotation, buat DO dari SO, shipment |
| `PurchasingWorkflowService` | Approve PR, buat PO, proses penerimaan GRN |
| `InventoryWorkflowService` | Mutasi stok masuk/keluar, ajustmen opname |
| `FinanceWorkflowService` | Verifikasi pembayaran, bayar hutang, update saldo invoice |
| `ProductionWorkflowService` | Finalisasi WO, kalkulasi BOM cost |

---

## 7. Middleware

| Middleware | Fungsi |
|---|---|
| `auth:sanctum` | Validasi Bearer Token |
| `permission` | Cek RBAC permission berdasarkan route & role user |

---

## 8. Environment & Konfigurasi

File `.env` yang wajib dikonfigurasi:

```env
APP_NAME="ERP CV Betonagung"
APP_ENV=local
APP_KEY=base64:...
APP_URL=http://localhost:8000

DB_CONNECTION=sqlite        # atau mysql untuk produksi
DB_DATABASE=/path/to/db

SANCTUM_STATEFUL_DOMAINS=localhost:3000
SESSION_DRIVER=database
QUEUE_CONNECTION=sync
```

---

## 9. Cara Menjalankan (Development)

```bash
# Clone & install
composer install

# Setup .env
cp .env.example .env
php artisan key:generate

# Migrasi & seed
php artisan migrate --seed

# Jalankan server
php artisan serve
```

---

## 10. Pengujian

```bash
# Jalankan semua test
php artisan test

# Jalankan test tertentu
php artisan test --filter=SalesTest
```

---

## 11. Konvensi Kode

- Semua UUID digunakan sebagai primary key
- Semua nominal uang menggunakan `decimal(18, 2)`
- Semua tabel menggunakan `timestamps()` kecuali tabel lookup (hanya `created_at`)
- Soft delete tidak digunakan — hapus permanen dengan FK constraint
- Naming kolom: `snake_case`
- Naming model: `PascalCase` (singular)
- Naming tabel: `snake_case` (plural)
