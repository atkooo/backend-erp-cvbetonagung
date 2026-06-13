# Database Schema Documentation
## Sistem ERP CV. Betonagung

> **Versi:** 1.0  
> **Tanggal:** Juni 2026  
> **Database Engine:** SQLite (dev) / MySQL 8+ (prod)  
> **ORM:** Laravel Eloquent  
> **Semua Primary Key:** UUID

---

## Diagram Relasi Entitas (ERD) — Ringkasan

```
[roles] ──< [role_permissions] >── [permissions]
  │
  ↓
[users] ──< [employees]
  │             │
  │             ├──< [employee_details]
  │             ├──< [employee_documents]
  │             ├──< [employee_salaries]
  │             ├──< [attendances]
  │             ├──< [leaves] >── [leave_types]
  │             ├──< [payrolls] ──< [payroll_details]
  │             └──< [employee_loans]
  │
[customers] ──< [quotations] ──< [quotation_items]
                    │
                    ↓
              [sales_orders] ──< [sales_order_items]
                    │                   │
                    ↓                   ↓
              [delivery_orders] ──< [delivery_order_items]
              [invoices] ──< [invoice_items]
              [projects] ──< [project_timelines]
                         ──< [project_documents]
                         ──< [project_budget_items]
                         ──< [project_termins]

[suppliers] ──< [purchase_requests] ──< [purchase_request_items]
                      │
                      ↓
                    [rfqs] ──< [rfq_items]
                      │
                      ↓
               [purchase_orders] ──< [purchase_order_items]
                      │
                      ↓
               [goods_receipt_notes] ──< [goods_receipt_note_items]
               [supplier_payables]

[products] ──< [product_stocks] (>── [storage_locations])
           ──< [stock_movements]
           ──< [boms] ──< [bom_items]
           ──< [production_work_orders] ──< [production_work_logs]
                                       ──< [production_work_order_items]

[warehouses] ──< [storage_locations]
[accounts] ──< [cash_transactions]
[approval_requests]
[audit_logs]
[reminders]
[document_exports]
```

---

## Detail Skema Per Tabel

---

### Modul: Identity & Auth

#### `roles`
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | UUID PK | |
| `code` | VARCHAR UNIQUE | Kode role (contoh: `admin`, `sales`) |
| `name` | VARCHAR UNIQUE | Nama tampilan |
| `description` | TEXT NULL | Deskripsi |
| `created_at` | TIMESTAMP NULL | |
| `updated_at` | TIMESTAMP NULL | |

#### `users`
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | UUID PK | |
| `role_id` | UUID FK → roles NULL | |
| `name` | VARCHAR | Nama lengkap |
| `email` | VARCHAR UNIQUE | Email login |
| `email_verified_at` | TIMESTAMP NULL | |
| `password` | VARCHAR | Hash bcrypt |
| `status` | VARCHAR DEFAULT `active` | `active` / `inactive` |
| `last_login_at` | TIMESTAMP NULL | |
| `remember_token` | VARCHAR NULL | |
| `created_at` | TIMESTAMP | |
| `updated_at` | TIMESTAMP | |

#### `permissions`
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | UUID PK | |
| `module` | VARCHAR | Nama modul (contoh: `sales`) |
| `action` | VARCHAR | Aksi (contoh: `create`, `view`) |
| `label` | VARCHAR NULL | Label tampilan |
| `created_at` | TIMESTAMP NULL | |
| **Unique:** `(module, action)` | | |

#### `role_permissions`
| Kolom | Tipe | Keterangan |
|---|---|---|
| `role_id` | UUID FK → roles CASCADE | Composite PK |
| `permission_id` | UUID FK → permissions CASCADE | Composite PK |
| `access_level` | VARCHAR | `read` / `write` / `admin` |

---

### Modul: Karyawan (HRD)

#### `employees`
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | UUID PK | |
| `employee_number` | VARCHAR UNIQUE | Nomor karyawan |
| `user_id` | UUID FK → users NULL | Akun login (opsional) |
| `name` | VARCHAR | |
| `gender` | VARCHAR NULL | Jenis kelamin |
| `place_of_birth` | VARCHAR NULL | |
| `date_of_birth` | DATE NULL | |
| `marital_status` | VARCHAR NULL | |
| `religion` | VARCHAR NULL | |
| `blood_type` | VARCHAR NULL | |
| `id_card_number` | VARCHAR NULL | NIK KTP |
| `tax_id_number` | VARCHAR NULL | NPWP |
| `bank_name` | VARCHAR NULL | |
| `bank_account` | VARCHAR NULL | |
| `role_name` | VARCHAR | Jabatan/posisi |
| `department` | VARCHAR | Divisi |
| `phone` | VARCHAR NULL | |
| `address` | TEXT NULL | |
| `join_date` | DATE NULL | |
| `employee_type` | VARCHAR DEFAULT `permanent` | `Tetap`/`Kontrak`/`Borongan`/`Harian` |
| `daily_rate` | DECIMAL(18,2) | Upah harian |
| `piece_rate` | DECIMAL(18,2) | Upah borongan |
| `status` | VARCHAR DEFAULT `active` | |
| `created_at` | TIMESTAMP | |
| `updated_at` | TIMESTAMP | |

#### `employee_details`
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | UUID PK | |
| `employee_id` | UUID FK → employees CASCADE | |
| `type` | VARCHAR | `family` / `education` / `emergency_contact` |
| `name` | VARCHAR | |
| `relation` | VARCHAR NULL | Untuk family/emergency |
| `phone` | VARCHAR NULL | |
| `institution` | VARCHAR NULL | Untuk pendidikan |
| `degree` | VARCHAR NULL | Untuk pendidikan |
| `year_start` | VARCHAR NULL | |
| `year_end` | VARCHAR NULL | |
| `notes` | TEXT NULL | |
| `created_at` | TIMESTAMP | |
| `updated_at` | TIMESTAMP | |

#### `employee_documents`
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | UUID PK | |
| `employee_id` | UUID FK → employees CASCADE | |
| `document_type` | VARCHAR | `KTP` / `KK` / `Ijazah` / `Kontrak` |
| `file_path` | VARCHAR | Path file di storage |
| `file_name` | VARCHAR | Nama file asli |
| `expiry_date` | DATE NULL | Tanggal kedaluwarsa |
| `created_at` | TIMESTAMP | |
| `updated_at` | TIMESTAMP | |

#### `leave_types`
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | UUID PK | |
| `code` | VARCHAR UNIQUE | |
| `name` | VARCHAR | |
| `is_paid` | BOOLEAN DEFAULT true | |
| `max_days` | INTEGER DEFAULT 0 | 0 = tidak terbatas |
| `description` | TEXT NULL | |
| `created_at` | TIMESTAMP | |
| `updated_at` | TIMESTAMP | |

#### `leaves`
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | UUID PK | |
| `employee_id` | UUID FK → employees CASCADE | |
| `leave_type_id` | UUID FK → leave_types RESTRICT | |
| `start_date` | DATE | |
| `end_date` | DATE | |
| `total_days` | INTEGER | |
| `reason` | TEXT NULL | |
| `attachment_path` | VARCHAR NULL | |
| `status` | VARCHAR DEFAULT `pending` | `pending`/`approved`/`rejected` |
| `approved_by` | UUID FK → users NULL | |
| `approved_at` | TIMESTAMP NULL | |
| `created_at` | TIMESTAMP | |
| `updated_at` | TIMESTAMP | |

#### `attendances`
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | UUID PK | |
| `employee_id` | UUID FK → employees CASCADE | |
| `date` | DATE | |
| `clock_in` | TIME NULL | |
| `clock_out` | TIME NULL | |
| `status` | VARCHAR DEFAULT `present` | `present`/`late`/`absent`/`half_day`/`leave` |
| `late_minutes` | DECIMAL(8,2) DEFAULT 0 | |
| `notes` | TEXT NULL | |
| `created_at` | TIMESTAMP | |
| `updated_at` | TIMESTAMP | |
| **Unique:** `(employee_id, date)` | | |

---

### Modul: Penggajian (Payroll)

#### `salary_components`
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | UUID PK | |
| `code` | VARCHAR UNIQUE | |
| `name` | VARCHAR | |
| `type` | VARCHAR | `allowance` (tunjangan) / `deduction` (potongan) |
| `is_taxable` | BOOLEAN DEFAULT true | Kena pajak? |
| `is_fixed` | BOOLEAN DEFAULT true | Tetap atau variabel |
| `default_amount` | DECIMAL(18,2) DEFAULT 0 | |
| `created_at` | TIMESTAMP | |
| `updated_at` | TIMESTAMP | |

#### `employee_salaries`
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | UUID PK | |
| `employee_id` | UUID FK → employees CASCADE | |
| `salary_component_id` | UUID FK → salary_components CASCADE | |
| `amount` | DECIMAL(18,2) | |
| `created_at` | TIMESTAMP | |
| `updated_at` | TIMESTAMP | |
| **Unique:** `(employee_id, salary_component_id)` | | |

#### `payrolls`
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | UUID PK | |
| `employee_id` | UUID FK → employees RESTRICT | |
| `payroll_number` | VARCHAR UNIQUE | |
| `period_month` | INTEGER | |
| `period_year` | INTEGER | |
| `start_date` | DATE | |
| `end_date` | DATE | |
| `total_attendance` | INTEGER DEFAULT 0 | |
| `total_late_minutes` | INTEGER DEFAULT 0 | |
| `basic_salary` | DECIMAL(18,2) DEFAULT 0 | |
| `total_allowance` | DECIMAL(18,2) DEFAULT 0 | |
| `total_deduction` | DECIMAL(18,2) DEFAULT 0 | |
| `net_salary` | DECIMAL(18,2) DEFAULT 0 | |
| `status` | VARCHAR DEFAULT `draft` | `draft`/`approved`/`paid` |
| `payment_date` | DATE NULL | |
| `notes` | TEXT NULL | |
| `created_at` | TIMESTAMP | |
| `updated_at` | TIMESTAMP | |

#### `payroll_details`
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | UUID PK | |
| `payroll_id` | UUID FK → payrolls CASCADE | |
| `salary_component_id` | UUID FK → salary_components RESTRICT | |
| `type` | VARCHAR | Salinan tipe dari komponen (historis) |
| `amount` | DECIMAL(18,2) | |
| `notes` | TEXT NULL | |
| `created_at` | TIMESTAMP | |
| `updated_at` | TIMESTAMP | |

#### `employee_loans`
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | UUID PK | |
| `employee_id` | UUID FK → employees CASCADE | |
| `loan_number` | VARCHAR UNIQUE | |
| `amount` | DECIMAL(18,2) | Jumlah pinjaman |
| `reason` | VARCHAR NULL | |
| `date` | DATE | |
| `status` | VARCHAR DEFAULT `pending` | `pending`/`approved`/`rejected`/`paid` |
| `remaining_amount` | DECIMAL(18,2) | Sisa hutang |
| `installment_amount` | DECIMAL(18,2) | Cicilan per bulan |
| `notes` | TEXT NULL | |
| `created_at` | TIMESTAMP | |
| `updated_at` | TIMESTAMP | |

#### `overtime_rules`
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | UUID PK | |
| `name` | VARCHAR | |
| `rate_per_hour` | DECIMAL(18,2) | Tarif per jam |
| `type` | VARCHAR DEFAULT `weekday` | `weekday`/`weekend`/`holiday` |
| `created_at` | TIMESTAMP | |
| `updated_at` | TIMESTAMP | |

#### `payroll_settings`
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | UUID PK | |
| `key` | VARCHAR UNIQUE | |
| `value` | JSON | |
| `description` | VARCHAR NULL | |
| `created_at` | TIMESTAMP | |
| `updated_at` | TIMESTAMP | |

---

### Modul: Master Data

#### `customers`
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | UUID PK | |
| `code` | VARCHAR UNIQUE | Kode pelanggan |
| `name` | VARCHAR | |
| `phone` | VARCHAR NULL | |
| `email` | VARCHAR NULL | |
| `city` | VARCHAR NULL | |
| `address` | TEXT NULL | |
| `status` | VARCHAR DEFAULT `active` | |
| `created_at` | TIMESTAMP | |
| `updated_at` | TIMESTAMP | |

#### `suppliers`
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | UUID PK | |
| `code` | VARCHAR UNIQUE | |
| `name` | VARCHAR | |
| `contact_name` | VARCHAR NULL | Nama kontak PIC |
| `phone` | VARCHAR NULL | |
| `city` | VARCHAR NULL | |
| `address` | TEXT NULL | |
| `status` | VARCHAR DEFAULT `active` | |
| `created_at` | TIMESTAMP | |
| `updated_at` | TIMESTAMP | |

#### `product_categories`
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | UUID PK | |
| `name` | VARCHAR UNIQUE | |
| `description` | TEXT NULL | |
| `status` | VARCHAR DEFAULT `active` | |
| `created_at` | TIMESTAMP | |
| `updated_at` | TIMESTAMP | |

#### `units`
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | UUID PK | |
| `code` | VARCHAR UNIQUE | Contoh: `pcs`, `kg`, `m3` |
| `name` | VARCHAR | |
| `created_at` | TIMESTAMP NULL | |

#### `warehouses`
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | UUID PK | |
| `code` | VARCHAR UNIQUE | |
| `name` | VARCHAR | |
| `type` | VARCHAR NULL | Jenis gudang |
| `address` | TEXT NULL | |
| `status` | VARCHAR DEFAULT `active` | |
| `created_at` | TIMESTAMP | |
| `updated_at` | TIMESTAMP | |

#### `storage_locations`
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | UUID PK | |
| `warehouse_id` | UUID FK → warehouses RESTRICT | |
| `code` | VARCHAR | Kode lokasi rak/slot |
| `name` | VARCHAR NULL | |
| `description` | TEXT NULL | |
| `created_at` | TIMESTAMP | |
| `updated_at` | TIMESTAMP | |
| **Unique:** `(warehouse_id, code)` | | |

#### `products`
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | UUID PK | |
| `category_id` | UUID FK → product_categories NULL | |
| `unit_id` | UUID FK → units NULL | |
| `sku` | VARCHAR UNIQUE | Kode produk |
| `name` | VARCHAR | |
| `type` | VARCHAR | `raw_material`/`finished_good`/`service` |
| `cost_price` | DECIMAL(18,2) DEFAULT 0 | Harga pokok |
| `selling_price` | DECIMAL(18,2) DEFAULT 0 | Harga jual |
| `min_stock` | DECIMAL(18,2) DEFAULT 0 | Stok minimum |
| `stock_status` | VARCHAR DEFAULT `safe` | `safe`/`low`/`empty` |
| `qr_value` | VARCHAR NULL UNIQUE | Nilai QR code |
| `status` | VARCHAR DEFAULT `active` | |
| `created_at` | TIMESTAMP | |
| `updated_at` | TIMESTAMP | |

#### `company_settings`
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | UUID PK | |
| `company_name` | VARCHAR | |
| `company_address` | TEXT NULL | |
| `contact_phone` | VARCHAR NULL | |
| `operational_email` | VARCHAR NULL | |
| `tax_rate` | DECIMAL(5,2) DEFAULT 0 | Persentase PPN |
| `backup_schedule` | VARCHAR NULL | |
| `updated_by` | UUID FK → users NULL | |
| `updated_at` | TIMESTAMP NULL | |

---

### Modul: Inventori

#### `product_stocks`
| Kolom | Tipe | Keterangan |
|---|---|---|
| `product_id` | UUID FK → products RESTRICT | Composite PK |
| `location_id` | UUID FK → storage_locations RESTRICT | Composite PK |
| `quantity` | DECIMAL(18,2) DEFAULT 0 | Stok saat ini |
| `updated_at` | TIMESTAMP NULL | |

#### `stock_movements`
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | UUID PK | |
| `product_id` | UUID FK → products RESTRICT | |
| `from_location_id` | UUID FK → storage_locations NULL | Lokasi asal |
| `to_location_id` | UUID FK → storage_locations NULL | Lokasi tujuan |
| `type` | VARCHAR | `in`/`out`/`transfer`/`adjustment` |
| `quantity` | DECIMAL(18,2) | |
| `reference_type` | VARCHAR NULL | Tipe dokumen referensi |
| `reference_id` | UUID NULL | ID dokumen referensi |
| `reference_number` | VARCHAR NULL | Nomor dokumen |
| `handled_by` | UUID FK → users NULL | |
| `notes` | TEXT NULL | |
| `movement_at` | TIMESTAMP | Waktu mutasi |
| `created_at` | TIMESTAMP NULL | |
| **Index:** `(reference_type, reference_id)` | | |
| **Index:** `movement_at` | | |

#### `approval_requests`
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | UUID PK | |
| `approval_number` | VARCHAR UNIQUE | |
| `request_type` | VARCHAR | Jenis permintaan persetujuan |
| `requester_id` | UUID FK → users NULL | |
| `approver_id` | UUID FK → users NULL | |
| `reference_type` | VARCHAR NULL | |
| `reference_id` | UUID NULL | |
| `reference_number` | VARCHAR NULL | |
| `change_summary` | TEXT NULL | Ringkasan perubahan |
| `amount` | DECIMAL(18,2) NULL | |
| `status` | VARCHAR DEFAULT `pending` | `pending`/`approved`/`rejected` |
| `requested_at` | TIMESTAMP NULL | |
| `decided_at` | TIMESTAMP NULL | |
| `decision_notes` | TEXT NULL | |

#### `stock_opname_sessions`
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | UUID PK | |
| `opname_number` | VARCHAR UNIQUE | |
| `warehouse_id` | UUID FK → warehouses RESTRICT | |
| `started_by` | UUID FK → users NULL | |
| `status` | VARCHAR | `open`/`closed` |
| `started_at` | TIMESTAMP NULL | |
| `closed_at` | TIMESTAMP NULL | |
| `notes` | TEXT NULL | |

#### `stock_opname_items`
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | UUID PK | |
| `session_id` | UUID FK → stock_opname_sessions CASCADE | |
| `product_id` | UUID FK → products RESTRICT | |
| `location_id` | UUID FK → storage_locations RESTRICT | |
| `system_qty` | DECIMAL(18,2) | Stok sistem |
| `physical_qty` | DECIMAL(18,2) | Stok fisik hasil hitung |
| `difference_qty` | DECIMAL(18,2) | Selisih |
| `notes` | TEXT NULL | |
| `approval_request_id` | UUID FK → approval_requests NULL | |
| `created_at` | TIMESTAMP | |
| `updated_at` | TIMESTAMP | |
| **Unique:** `(session_id, product_id, location_id)` | | |

---

### Modul: Penjualan (Sales)

#### `quotations`
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | UUID PK | |
| `quotation_number` | VARCHAR UNIQUE | |
| `customer_id` | UUID FK → customers RESTRICT | |
| `created_by` | UUID FK → users NULL | |
| `quotation_date` | DATE | |
| `valid_until` | DATE NULL | |
| `subtotal` | DECIMAL(18,2) DEFAULT 0 | |
| `tax_amount` | DECIMAL(18,2) DEFAULT 0 | |
| `total` | DECIMAL(18,2) DEFAULT 0 | |
| `status` | VARCHAR DEFAULT `draft` | `draft`/`sent`/`approved`/`rejected` |
| `notes` | TEXT NULL | |
| `created_at` | TIMESTAMP | |
| `updated_at` | TIMESTAMP | |

#### `quotation_items`
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | UUID PK | |
| `quotation_id` | UUID FK → quotations CASCADE | |
| `product_id` | UUID FK → products RESTRICT | |
| `description` | VARCHAR NULL | |
| `quantity` | DECIMAL(18,2) | |
| `unit_price` | DECIMAL(18,2) | |
| `subtotal` | DECIMAL(18,2) | |

#### `sales_orders`
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | UUID PK | |
| `quotation_id` | UUID FK → quotations NULL | |
| `order_number` | VARCHAR UNIQUE | |
| `customer_id` | UUID FK → customers RESTRICT | |
| `order_date` | DATE | |
| `total` | DECIMAL(18,2) DEFAULT 0 | |
| `status` | VARCHAR DEFAULT `draft` | `draft`/`processed`/`approved`/`completed`/`cancelled` |
| `notes` | TEXT NULL | |
| `created_at` | TIMESTAMP | |
| `updated_at` | TIMESTAMP | |

#### `sales_order_items`
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | UUID PK | |
| `sales_order_id` | UUID FK → sales_orders CASCADE | |
| `product_id` | UUID FK → products RESTRICT | |
| `description` | VARCHAR NULL | |
| `quantity` | DECIMAL(18,2) | |
| `unit_price` | DECIMAL(18,2) | |
| `subtotal` | DECIMAL(18,2) | |

#### `delivery_orders`
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | UUID PK | |
| `delivery_number` | VARCHAR UNIQUE | |
| `sales_order_id` | UUID FK → sales_orders RESTRICT | |
| `customer_id` | UUID FK → customers RESTRICT | |
| `delivery_date` | DATE NULL | |
| `received_at` | TIMESTAMP NULL | |
| `receiver_name` | VARCHAR NULL | |
| `status` | VARCHAR DEFAULT `ready_to_load` | `ready_to_load`/`shipped`/`received`/`cancelled` |
| `notes` | TEXT NULL | |
| `created_at` | TIMESTAMP | |
| `updated_at` | TIMESTAMP | |

#### `delivery_order_items`
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | UUID PK | |
| `delivery_order_id` | UUID FK → delivery_orders CASCADE | |
| `sales_order_item_id` | UUID FK → sales_order_items NULL | |
| `product_id` | UUID FK → products RESTRICT | |
| `quantity` | DECIMAL(18,2) | |

---

### Modul: Pembelian (Purchasing)

#### `purchase_requests`
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | UUID PK | |
| `pr_number` | VARCHAR UNIQUE | |
| `requester_id` | UUID FK → users NULL | |
| `request_date` | DATE | |
| `required_date` | DATE NULL | |
| `department` | VARCHAR NULL | |
| `status` | VARCHAR DEFAULT `pending_approval` | `pending_approval`/`approved`/`rejected`/`processed` |
| `notes` | TEXT NULL | |
| `created_at` | TIMESTAMP | |
| `updated_at` | TIMESTAMP | |

#### `purchase_request_items`
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | UUID PK | |
| `purchase_request_id` | UUID FK → purchase_requests CASCADE | |
| `product_id` | UUID FK → products NULL | |
| `description` | VARCHAR NULL | |
| `quantity` | DECIMAL(18,2) | |
| `status` | VARCHAR DEFAULT `open` | `open`/`in_rfq`/`in_po` |

#### `rfqs`
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | UUID PK | |
| `rfq_number` | VARCHAR UNIQUE | |
| `purchase_request_id` | UUID FK → purchase_requests NULL | |
| `supplier_id` | UUID FK → suppliers RESTRICT | |
| `rfq_date` | DATE | |
| `valid_until` | DATE NULL | |
| `status` | VARCHAR DEFAULT `sent` | `sent`/`quoted`/`accepted`/`rejected` |
| `notes` | TEXT NULL | |
| `created_at` | TIMESTAMP | |
| `updated_at` | TIMESTAMP | |

#### `rfq_items`
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | UUID PK | |
| `rfq_id` | UUID FK → rfqs CASCADE | |
| `product_id` | UUID FK → products NULL | |
| `description` | VARCHAR NULL | |
| `quantity` | DECIMAL(18,2) | |
| `quoted_unit_price` | DECIMAL(18,2) DEFAULT 0 | Harga penawaran supplier |
| `subtotal` | DECIMAL(18,2) DEFAULT 0 | |

#### `purchase_orders`
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | UUID PK | |
| `po_number` | VARCHAR UNIQUE | |
| `supplier_id` | UUID FK → suppliers RESTRICT | |
| `purchase_request_id` | UUID FK → purchase_requests NULL | |
| `rfq_id` | UUID FK → rfqs NULL UNIQUE | |
| `po_date` | DATE | |
| `total` | DECIMAL(18,2) DEFAULT 0 | |
| `status` | VARCHAR DEFAULT `draft` | `draft`/`ordered`/`partial`/`received`/`cancelled` |
| `notes` | TEXT NULL | |
| `created_at` | TIMESTAMP | |
| `updated_at` | TIMESTAMP | |

#### `purchase_order_items`
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | UUID PK | |
| `purchase_order_id` | UUID FK → purchase_orders CASCADE | |
| `product_id` | UUID FK → products RESTRICT | |
| `description` | VARCHAR NULL | |
| `quantity` | DECIMAL(18,2) | |
| `unit_price` | DECIMAL(18,2) | |
| `received_qty` | DECIMAL(18,2) DEFAULT 0 | Sudah diterima |
| `subtotal` | DECIMAL(18,2) | |

#### `goods_receipt_notes` (GRN)
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | UUID PK | |
| `grn_number` | VARCHAR UNIQUE | |
| `purchase_order_id` | UUID FK → purchase_orders RESTRICT NULL | |
| `warehouse_id` | UUID FK → warehouses NULL | |
| `to_location_id` | UUID FK → storage_locations NULL | |
| `received_by` | UUID FK → users NULL | |
| `receipt_date` | DATE | |
| `delivery_order_number` | VARCHAR NULL | Nomor surat jalan dari supplier |
| `status` | VARCHAR DEFAULT `received` | `received`/`partially_returned`/`fully_returned` |
| `notes` | TEXT NULL | |
| `created_at` | TIMESTAMP | |
| `updated_at` | TIMESTAMP | |

#### `goods_receipt_note_items`
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | UUID PK | |
| `goods_receipt_note_id` | UUID FK → goods_receipt_notes CASCADE | |
| `purchase_order_item_id` | UUID FK → purchase_order_items NULL | |
| `product_id` | UUID FK → products NULL | |
| `received_qty` | DECIMAL(18,2) | Qty diterima |
| `rejected_qty` | DECIMAL(18,2) DEFAULT 0 | Qty ditolak (QC) |
| `notes` | VARCHAR NULL | |

#### `supplier_payables`
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | UUID PK | |
| `purchase_order_id` | UUID FK → purchase_orders NULL | |
| `supplier_id` | UUID FK → suppliers RESTRICT | |
| `payable_number` | VARCHAR UNIQUE | |
| `due_date` | DATE NULL | |
| `amount` | DECIMAL(18,2) | Total hutang |
| `paid_amount` | DECIMAL(18,2) DEFAULT 0 | Sudah dibayar |
| `status` | VARCHAR DEFAULT `open` | `open`/`partial`/`paid` |
| `created_at` | TIMESTAMP | |
| `updated_at` | TIMESTAMP | |

#### `returns`
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | UUID PK | |
| `return_number` | VARCHAR UNIQUE | |
| `type` | VARCHAR | `customer_return` / `supplier_return` |
| `customer_id` | UUID FK → customers NULL | |
| `supplier_id` | UUID FK → suppliers NULL | |
| `sales_order_id` | UUID FK → sales_orders NULL | |
| `purchase_order_id` | UUID FK → purchase_orders NULL | |
| `reason` | TEXT | |
| `qc_status` | VARCHAR | Status QC |
| `created_by` | UUID FK → users NULL | |
| `created_at` | TIMESTAMP | |
| `updated_at` | TIMESTAMP | |

#### `return_items`
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | UUID PK | |
| `return_id` | UUID FK → returns CASCADE | |
| `product_id` | UUID FK → products RESTRICT | |
| `quantity` | DECIMAL(18,2) | |
| `notes` | TEXT NULL | |

---

### Modul: Keuangan (Finance)

#### `invoices`
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | UUID PK | |
| `sales_order_id` | UUID FK → sales_orders NULL | |
| `project_id` | UUID FK → projects NULL | |
| `invoice_number` | VARCHAR UNIQUE | |
| `customer_id` | UUID FK → customers RESTRICT | |
| `invoice_date` | DATE | |
| `due_date` | DATE NULL | |
| `subtotal` | DECIMAL(18,2) DEFAULT 0 | |
| `tax_amount` | DECIMAL(18,2) DEFAULT 0 | |
| `total` | DECIMAL(18,2) DEFAULT 0 | |
| `paid_amount` | DECIMAL(18,2) DEFAULT 0 | |
| `status` | VARCHAR DEFAULT `unpaid` | `unpaid`/`partial`/`paid`/`overdue` |
| `created_at` | TIMESTAMP | |
| `updated_at` | TIMESTAMP | |

#### `invoice_items`
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | UUID PK | |
| `invoice_id` | UUID FK → invoices CASCADE | |
| `product_id` | UUID FK → products NULL | |
| `description` | VARCHAR NULL | |
| `quantity` | DECIMAL(18,2) | |
| `unit_price` | DECIMAL(18,2) | |
| `subtotal` | DECIMAL(18,2) | |

#### `payments`
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | UUID PK | |
| `invoice_id` | UUID FK → invoices CASCADE | |
| `payment_number` | VARCHAR UNIQUE | |
| `payment_date` | TIMESTAMP | |
| `method` | VARCHAR | `cash`/`transfer`/`qris` |
| `amount` | DECIMAL(18,2) | |
| `status` | VARCHAR DEFAULT `pending` | `pending`/`verified`/`failed` |
| `verified_by` | UUID FK → users NULL | |
| `verified_at` | TIMESTAMP NULL | |
| `notes` | TEXT NULL | |
| `created_at` | TIMESTAMP NULL | |

#### `accounts`
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | UUID PK | |
| `code` | VARCHAR UNIQUE | Kode akun (contoh: `111-01`) |
| `name` | VARCHAR | Nama akun (contoh: `Kas Besar`) |
| `type` | VARCHAR | `asset`/`liability`/`equity`/`revenue`/`expense` |
| `balance` | DECIMAL(15,2) DEFAULT 0 | |
| `currency` | VARCHAR DEFAULT `IDR` | |
| `description` | TEXT NULL | |
| `is_active` | BOOLEAN DEFAULT true | |
| `created_at` | TIMESTAMP | |
| `updated_at` | TIMESTAMP | |

#### `cash_transactions`
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | UUID PK | |
| `transaction_number` | VARCHAR UNIQUE | Contoh: `TR-20240101-001` |
| `account_id` | UUID FK → accounts CASCADE | |
| `transaction_date` | DATE | |
| `type` | VARCHAR | `in` (masuk) / `out` (keluar) |
| `amount` | DECIMAL(15,2) | |
| `category` | VARCHAR | `operational`/`payroll`/`utility`/`tax` dll |
| `description` | TEXT NULL | |
| `reference_type` | VARCHAR NULL | |
| `reference_id` | UUID NULL | |
| `recorded_by` | UUID NULL | |
| `created_at` | TIMESTAMP | |
| `updated_at` | TIMESTAMP | |

---

### Modul: Proyek

#### `projects`
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | UUID PK | |
| `code` | VARCHAR UNIQUE | |
| `customer_id` | UUID FK → customers RESTRICT | |
| `quotation_id` | UUID FK → quotations NULL | |
| `sales_order_id` | UUID FK → sales_orders NULL | |
| `project_name` | VARCHAR | |
| `location` | VARCHAR NULL | |
| `project_type` | VARCHAR NULL | |
| `project_spec` | VARCHAR NULL | Spesifikasi teknis |
| `contract_value` | DECIMAL(18,2) DEFAULT 0 | |
| `deadline` | DATE NULL | |
| `progress` | INTEGER DEFAULT 0 | 0-100% |
| `status` | VARCHAR DEFAULT `survey` | `survey`/`offer`/`deal`/`production`/`delivery`/`installation`/`completed`/`cancelled` |
| `created_at` | TIMESTAMP | |
| `updated_at` | TIMESTAMP | |

#### `project_timelines`
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | UUID PK | |
| `project_id` | UUID FK → projects CASCADE | |
| `event_date` | DATE | |
| `stage` | VARCHAR | |
| `description` | TEXT NULL | |
| `icon` | VARCHAR NULL | |
| `created_by` | UUID FK → users NULL | |
| `created_at` | TIMESTAMP NULL | |

#### `project_documents`
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | UUID PK | |
| `project_id` | UUID FK → projects CASCADE | |
| `title` | VARCHAR | |
| `file_url` | TEXT NULL | |
| `document_date` | DATE NULL | |
| `uploaded_by` | UUID FK → users NULL | |
| `created_at` | TIMESTAMP NULL | |

#### `project_budget_items`
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | UUID PK | |
| `project_id` | UUID FK → projects CASCADE | |
| `component` | VARCHAR | Komponen anggaran |
| `budget_amount` | DECIMAL(18,2) DEFAULT 0 | Anggaran rencana |
| `actual_amount` | DECIMAL(18,2) DEFAULT 0 | Realisasi |
| `notes` | TEXT NULL | |
| `created_at` | TIMESTAMP | |
| `updated_at` | TIMESTAMP | |

#### `project_termins`
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | UUID PK | |
| `project_id` | UUID FK → projects CASCADE | |
| `phase` | VARCHAR | Fase termin (contoh: `DP`, `Termin 1`) |
| `amount` | DECIMAL(18,2) | |
| `due_date` | DATE NULL | |
| `status` | VARCHAR DEFAULT `unpaid` | `unpaid`/`paid` |
| `invoice_id` | UUID FK → invoices NULL | |
| `paid_at` | TIMESTAMP NULL | |

---

### Modul: Produksi

#### `production_work_orders`
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | UUID PK | |
| `work_order_number` | VARCHAR UNIQUE | |
| `product_id` | UUID FK → products RESTRICT | |
| `sales_order_id` | UUID FK → sales_orders NULL | |
| `project_id` | UUID FK → projects NULL | |
| `source_label` | VARCHAR NULL | Label asal order |
| `stage` | VARCHAR | Tahap produksi |
| `target_qty` | DECIMAL(18,2) | Target produksi |
| `completed_qty` | DECIMAL(18,2) DEFAULT 0 | Selesai diproduksi |
| `progress` | INTEGER DEFAULT 0 | 0-100% |
| `due_date` | DATE NULL | |
| `created_at` | TIMESTAMP | |
| `updated_at` | TIMESTAMP | |

#### `production_work_order_items`
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | UUID PK | |
| `work_order_id` | UUID FK → production_work_orders CASCADE | |
| `product_id` | UUID FK → products RESTRICT | Material yang dibutuhkan |
| `quantity` | DECIMAL(18,2) | |
| `notes` | TEXT NULL | |

#### `production_work_logs`
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | UUID PK | |
| `work_order_id` | UUID FK → production_work_orders CASCADE | |
| `employee_id` | UUID FK → employees NULL | |
| `work_date` | DATE | |
| `stage` | VARCHAR | |
| `made_qty` | DECIMAL(18,2) DEFAULT 0 | Total dibuat |
| `reject_qty` | DECIMAL(18,2) DEFAULT 0 | Ditolak QC |
| `ok_qty` | DECIMAL(18,2) DEFAULT 0 | Diterima |
| `piece_rate` | DECIMAL(18,2) DEFAULT 0 | Upah per unit |
| `notes` | TEXT NULL | |
| `verified_by` | UUID FK → users NULL | |
| `verified_at` | TIMESTAMP NULL | |
| `created_at` | TIMESTAMP | |
| `updated_at` | TIMESTAMP | |

#### `boms` (Bill of Materials)
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | UUID PK | |
| `product_id` | UUID FK → products RESTRICT | Produk jadi |
| `version` | VARCHAR | Versi BOM |
| `effective_from` | DATE NULL | |
| `status` | VARCHAR | `active`/`inactive` |
| `total_cost` | DECIMAL(18,2) DEFAULT 0 | Total biaya material |
| `created_at` | TIMESTAMP | |
| `updated_at` | TIMESTAMP | |
| **Unique:** `(product_id, version)` | | |

#### `bom_items`
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | UUID PK | |
| `bom_id` | UUID FK → boms CASCADE | |
| `component_product_id` | UUID FK → products NULL | Material/komponen |
| `component_name` | VARCHAR NULL | Nama alternatif jika bukan dari master |
| `quantity` | DECIMAL(18,2) | |
| `unit_id` | UUID FK → units NULL | |
| `unit_cost` | DECIMAL(18,2) | |
| `subtotal` | DECIMAL(18,2) | |

---

### Modul: Support

#### `audit_logs`
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | UUID PK | |
| `user_id` | UUID FK → users NULL | |
| `role_id` | UUID FK → roles NULL | |
| `action` | VARCHAR | `create`/`update`/`delete`/`approve` dll |
| `object_type` | VARCHAR | Tipe objek yang diubah |
| `object_id` | UUID NULL | ID objek |
| `object_number` | VARCHAR NULL | Nomor dokumen |
| `summary` | TEXT NULL | Ringkasan perubahan |
| `ip_address` | VARCHAR NULL | |
| `created_at` | TIMESTAMP | |
| **Index:** `(object_type, object_id)` | | |
| **Index:** `created_at` | | |

#### `reminders`
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | UUID PK | |
| `type` | VARCHAR | Tipe pengingat |
| `reference_type` | VARCHAR NULL | |
| `reference_id` | UUID NULL | |
| `reference_number` | VARCHAR NULL | |
| `division` | VARCHAR NULL | |
| `schedule_at` | TIMESTAMP NULL | |
| `priority` | VARCHAR DEFAULT `medium` | `low`/`medium`/`high` |
| `status` | VARCHAR DEFAULT `open` | `open`/`done` |
| `assigned_to` | UUID FK → users NULL | |
| `created_at` | TIMESTAMP | |
| `updated_at` | TIMESTAMP | |

#### `document_exports`
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | UUID PK | |
| `document_type` | VARCHAR | Jenis dokumen |
| `reference_type` | VARCHAR NULL | |
| `reference_id` | UUID NULL | |
| `document_number` | VARCHAR NULL | |
| `export_format` | VARCHAR | `pdf`/`excel` dll |
| `division` | VARCHAR NULL | |
| `exported_by` | UUID FK → users NULL | |
| `exported_at` | TIMESTAMP NULL | |

---

## Urutan Migrasi

| No | File Migrasi | Tabel yang Dibuat |
|---|---|---|
| 1 | `0001_01_01_000000_create_users_table` | roles, users, employees, permissions, role_permissions, sessions |
| 2 | `0001_01_01_000001_create_cache_table` | cache, cache_locks |
| 3 | `0001_01_01_000002_create_jobs_table` | jobs, job_batches, failed_jobs |
| 4 | `2026_06_04_000001_create_erp_master_tables` | customers, suppliers, product_categories, units, warehouses, storage_locations, products, company_settings |
| 5 | `2026_06_04_000002_create_erp_inventory_tables` | approval_requests, product_stocks, stock_movements, stock_opname_sessions, stock_opname_items |
| 6 | `2026_06_04_000003_create_erp_sales_tables` | quotations, quotation_items, sales_orders, sales_order_items, delivery_orders, delivery_order_items |
| 7 | `2026_06_04_000004_create_erp_project_tables` | projects, project_timelines, project_documents, project_budget_items |
| 8 | `2026_06_04_000005_create_erp_finance_tables` | invoices, invoice_items, payments, purchase_orders, purchase_order_items, supplier_payables, project_termins |
| 9 | `2026_06_04_000006_create_erp_return_tables` | returns, return_items |
| 10 | `2026_06_04_000007_create_erp_production_tables` | production_work_orders, production_work_order_items, production_work_logs, boms, bom_items |
| 11 | `2026_06_04_000008_create_erp_support_tables` | audit_logs, reminders, document_exports |
| 12 | `2026_06_06_131521_create_erp_hrd_tables` | ALTER employees (HRD columns), employee_details, employee_documents, leave_types, leaves, attendances |
| 13 | `2026_06_06_132323_create_erp_payroll_tables` | salary_components, employee_salaries, payrolls, payroll_details |
| 14 | `2026_06_06_133031_create_erp_advanced_payroll_tables` | employee_loans, overtime_rules, payroll_settings |
| 15 | `2026_06_06_135340_create_personal_access_tokens_table` | personal_access_tokens |
| 16 | `2026_06_06_142500_create_erp_procurement_tables` | purchase_requests, purchase_request_items, rfqs, rfq_items, ALTER purchase_orders, goods_receipt_notes, goods_receipt_note_items |
| 17 | `2026_06_06_145750_create_accounts_table` | accounts |
| 18 | `2026_06_06_145751_create_cash_transactions_table` | cash_transactions |

---

## Ringkasan Tabel

| Kategori | Jumlah Tabel |
|---|---|
| Auth & Identity | 6 |
| Master Data | 8 |
| Inventori | 5 |
| Penjualan | 6 |
| Pembelian & Procurement | 8 |
| Keuangan | 6 |
| Proyek | 5 |
| Produksi | 5 |
| HRD | 5 |
| Payroll | 7 |
| Support | 3 |
| **Total** | **~64 tabel** |
