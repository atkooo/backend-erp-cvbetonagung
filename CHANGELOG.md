# Changelog

All notable changes to the Backend ERP CV. Beton Agung will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]
### Added
- **Penambahan Role `Karyawan` & `HR Manager` ke RbacSeeder**:
  - Role `employee` (Karyawan) ditambahkan ke `RbacSeeder` dengan akses `read` terbatas pada modul `reports`.
  - Role `hrd` (HR Manager) ditambahkan ke `RbacSeeder` dengan akses `full` pada modul `employees`, `payroll`, serta `read` pada modul `reports` dan `settings`.
  - Kedua role sudah ada di database namun belum terdaftar di seeder, sehingga permissions-nya tidak terkonfigurasi. Seeder kini mencakup semua 12 role yang ada: `admin`, `finance`, `sales`, `inventory`, `purchasing`, `billing`, `cashier`, `project`, `production`, `viewer`, `employee`, `hrd`.
  - Sample users `employee@example.com` dan `hrd@example.com` ditambahkan ke constant `USERS` di seeder.
- **Penambahan Modul `payroll` ke RBAC Permission Matrix**:
  - Modul `payroll` ditambahkan ke constant `MODULES` di `RbacSeeder`, menghasilkan 5 permission baru (`view`, `create`, `update`, `delete`, `approve` payroll) — total 80 permissions (16 modul × 5 aksi).
  - Role `hrd` (HR Manager) dikonfigurasi dengan akses `full` ke modul `payroll`.
  - Role `admin` dan `viewer` otomatis mendapat akses ke modul `payroll` karena menggunakan wildcard `*`.
  - Frontend `RolePermissionView.tsx`: `MODULE_LABELS` ditambahkan entri `payroll: 'Penggajian (Payroll)'` agar nama modul tampil dengan benar di RBAC matrix.


- **Batasan Generate Work Order dari Sales Order (Mencegah Duplikasi Data WO Aktif)**:
  - `SalesWorkflowService::autoGenerateWorkOrdersForSalesOrder`: Menambahkan pengecekan status Work Order aktif (`->active()`). Jika Sales Order sudah memiliki Work Order yang masih aktif (belum dibatalkan), sistem tidak akan membuat record Work Order baru (mencegah duplikasi data ganda), kecuali jika seluruh Work Order yang pernah dibuat sebelumnya telah dibatalkan (`stage: cancelled`).
  - `SalesController::generateWorkOrders`: Endpoint manual trigger `POST /api/sales/sales-orders/{id}/generate-work-orders` mengembalikan status HTTP 422 jika masih terdapat Work Order aktif untuk Sales Order tersebut, dan hanya mengizinkan generate ulang apabila seluruh Work Order terkait telah dibatalkan.
  - `SalesController`: Menambahkan relasi `productionWorkOrders` pada konfigurasi resource `sales-orders` agar status keterkaitan WO dapat diakses langsung oleh client frontend.
- **Penyesuaian Batas Pagination Global & Work Order (Menghilangkan Batasan 15 Data)**:
  - `ApiResourceController`, `StockProductionRequestController`, `IdentityController`: Nilai fallback default `per_page` diubah dari 15/20 menjadi **250 records** (dengan batas maksimal 500) pada seluruh endpoint resource ERP (Work Orders, Purchase Orders, Projects, Invoices, Delivery Orders, Stock Requests, BOMs, Roles, dsb) sehingga data tidak lagi terpotong 15 item jika client tidak mengirimkan parameter pagination.
  - `productionApi` (`api.ts`): Parameter `per_page=250` dan `-created_at` ditambahkan pada `getWorkOrders`, `getBoms`, dan `getStockProductionRequests`.
  - `purchasingApi`, `projectsApi`, `financeApi`, `supportApi`: Menambahkan query parameter default `per_page=250` dan sort `-created_at` agar seluruh modul transaksi dan master data konsisten mengambil hingga 250 records.
  - Frontend [ProductionWorkOrderView.tsx](file:///d:/Okta/Project/2025-ALURIN-system/2026-ALURIN-System-ERP/frontend/src/components/ProductionWorkOrderView.tsx): Menambahkan kontrol navigasi pagination pada panel daftar grup referensi Work Order di sidebar kiri dengan pemilih baris per halaman (5, 10, 20, 50 grup) dan tombol Previous / Next.
- **Pagination Lengkap pada Daftar Sales Order & Delivery Order**:
  - `ApiResourceController`: Batas maksimal `per_page` dinaikkan hingga 500 records agar pengambilan data koleksi skala besar tidak terpotong.
  - `salesApi` (`api.ts`): Parameter `per_page=250` dan sorting default `-created_at` diterapkan pada `getSalesOrders`, `getDeliveryOrders`, dan `getQuotations`.
  - Frontend [SalesView.tsx](file:///d:/Okta/Project/2025-ALURIN-system/2026-ALURIN-System-ERP/frontend/src/components/SalesView.tsx): Navigasi pagination kini selalu tampil saat dokumen tersedia, dilengkapi pemilih jumlah data per halaman (10, 15, 25, 50, 100), tombol First, Prev, Next, Last, serta indikator nomor halaman dan total dokumen.
  - Frontend [DeliveryOrdersView.tsx](file:///d:/Okta/Project/2025-ALURIN-system/2026-ALURIN-System-ERP/frontend/src/components/DeliveryOrdersView.tsx): Menambahkan kontrol pagination tabel surat jalan dengan pemotong list `paginatedOrders`, pemilih jumlah data per halaman (10, 15, 25, 50, 100), informasi rentang baris, dan tombol navigasi halaman.
- **Proteksi Pengiriman Antrian Work Order (WO) dari Sales Order Berstatus Draft**:
  - `SalesWorkflowService::createSalesOrder`: Otomasi pembuatan Work Order tidak lagi dieksekusi saat Sales Order masih berstatus `draft`. Work order hanya dibuat otomatis setelah Sales Order disetujui (`approved`).
  - `SalesWorkflowService::autoGenerateWorkOrdersForSalesOrder`: Ditambahkan guard klausul yang menolak pembuatan Work Order jika status Sales Order adalah `draft` atau `cancelled`.
  - `SalesController::generateWorkOrders`: Endpoint manual trigger `POST /api/sales/sales-orders/{id}/generate-work-orders` kini mengembalikan status HTTP 422 jika Sales Order masih berstatus `draft`, mewajibkan persetujuan (approval) terlebih dahulu sebelum dapat dikirim ke antrian produksi pabrik.
  - Frontend `SalesView.tsx`: Tombol aksi "Kirim ke Antrian Produksi (WO)" kini digantikan dengan pesan informasi bahwa Sales Order berstatus Draft dan harus disetujui (Approve) terlebih dahulu. Aksi approval pada Sales Order diarahkan langsung ke endpoint approve resmi (`/approve`).
  - Feature test: `test_sales_order_in_draft_cannot_generate_work_orders_until_approved` pada `SalesOrderAutoWorkOrderTest.php`.
- **Metode Pengadaan Produk (Sourcing Type: Produksi Pabrik vs Beli Jadi Supplier)**:
  - Migrasi `2026_10_09_153500_add_procurement_type_to_products_table.php` menambahkan field `procurement_type` enum string (`manufacture` / `purchase`) dengan default `'manufacture'`, serta menginisialisasi `'purchase'` untuk tipe `raw_material`.
  - Atribut `$fillable` pada `Product.php` dan validasi `MasterDataRequest.php` diperbarui untuk menerima `procurement_type`.
  - Logika otomasi Work Order pada `SalesWorkflowService::isSelfProducedProduct()` kini membedakan metode pengadaan secara tegas:
    - `procurement_type === 'purchase'`: Barang beli jadi dari supplier / trading / resell TIDAK AKAN dibuatkan Work Order (SPK Pabrik) saat Sales Order dibuat atau disetujui.
    - `procurement_type === 'manufacture'`: Barang produksi pabrik sendiri tetap dibuatkan Work Order (SPK Pabrik) secara otomatis.
  - Automated feature test: `test_sales_order_distinguishes_manufactured_vs_purchased_products` pada `SalesOrderAutoWorkOrderTest.php` (lolos 100%).
- **Fitur Ongkos Kirim (Gratis & Berbayar) pada Delivery Order (Surat Jalan)**:
  - Migrasi `2026_10_09_150000_add_shipping_fields_to_delivery_orders_and_invoices_tables.php` menambahkan kolom `shipping_type`, `shipping_cost`, `shipping_payment_type`, `driver_name`, dan `vehicle_number` pada tabel `delivery_orders`, serta `shipping_cost` pada tabel `invoices`.
  - Dukungan jenis ongkos kirim: `Gratis Ongkir` (Rp 0 / ditanggung perusahaan) dan `Berbayar` (dengan nominal rupiah).
  - Pilihan metode beban ongkir: `customer_invoice` (otomatis ditagihkan ke invoice pelanggan), `cod` (bayar di tempat / tunai ke supir), dan `company` (ditanggung perusahaan).
  - `SalesWorkflowService::createDeliveryOrder`: Jika DO berbayar dan ditagihkan ke invoice pelanggan, otomatis mengakumulasikan biaya ongkir ke `shipping_cost` invoice terkait, memperbarui `total` tagihan invoice, dan menyesuaikan status invoice jika diperlukan.
  - `CancellationService::cancelDeliveryOrder`: Jika DO dibatalkan, ongkos kirim yang sebelumnya dibebankan ke invoice pelanggan otomatis direverse/dikurangkan kembali dan status invoice diseimbangkan.
  - FormRequest `CreateDeliveryOrderRequest` diperbarui dengan validasi field ongkir, supir, dan nomor kendaraan.
  - Feature test: `test_delivery_order_can_be_created_with_shipping_fee_and_updates_invoice` dan `test_cancelling_delivery_order_reverses_invoice_shipping_fee` pada `SalesApiTest.php`.
- **Fitur Edit Penawaran (Quotation) Berstatus Draft**:
  - `SalesWorkflowService::updateQuotation`: Menambahkan validasi guard agar dokumen dan item hanya dapat diubah ketika quotation berstatus `draft` / `sent`, serta mencegah modifikasi jika quotation sudah terikat ke Sales Order.
  - Penambahan form editing pada antarmuka frontend (`SalesView.tsx`) dengan dukungan penuh untuk menambah baris item, menghapus baris item, mengganti produk, mengubah kuantitas, harga, diskon, catatan, relasi pelanggan, serta tanggal penawaran.
  - Automated test: `test_it_can_update_draft_quotation_items` dan `test_it_cannot_update_approved_quotation_items` pada `SalesWorkflowServiceTest.php`.
- **Sinkronisasi Zona Waktu Dinamis Sistem (WIB/WITA/WIT) dari Settings**:
  - Konfigurasi `config/app.php` dan `.env` disesuaikan agar mendukung `APP_TIMEZONE=Asia/Jakarta` secara dinamis.
  - Penambahan bootstrap dinamis pada `AppServiceProvider::boot()` dan `SettingsController::store()` untuk membaca preferensi `company.timezone` langsung dari tabel `settings` (misal `Asia/Jakarta`, `Asia/Makassar`, `Asia/Jayapura`).
  - Pencatatan timestamp MySQL (`created_at`, `updated_at`, dan `now()`) kini otomatis mengikuti zona waktu aktif yang dipilih pengguna pada menu Pengaturan (misal 15:xx:xx WIB).
- **Penyembunyian Dokumen Dibatalkan dari Frontend (Backend & Database Only)**:
  - Pembatasan default pada seluruh model transaksi & operasional (`Quotation`, `SalesOrder`, `DeliveryOrder`, `Invoice`, `Payment`, `PurchaseRequest`, `Rfq`, `PurchaseOrder`, `GoodsReceiptNote`, `SupplierPayable`, `StockProductionRequest`, `ProductionWorkOrder`, `Project`, `StockOpnameSession`) menggunakan scope `active()` agar data dengan status `cancelled` / `dibatalkan` tidak lagi dikirimkan ke endpoint API umum ataupun muncul di frontend.
  - Parameter `include_cancelled=true` disediakan khusus jika endpoint internal/audit backend membutuhkan pengambilan data yang dibatalkan.
  - Filter analitik, dashboard, dan laporan eksekutif (sales, AR/AP aging, supplier purchases, purchase price analysis) diperbarui agar otomatis mengabaikan dokumen yang dibatalkan.
  - Frontend (Sales, Purchase, RFQ, PR, WO, DO, Invoice, Payment, AR/AP Ledger, Project, dan POS) diperbarui untuk memfilter transaksi berstatus dibatalkan dari daftar tabel, perhitungan ringkasan saldo/outstanding, dan opsi status aktif.
  - Sesi Stock Opname (STO) berstatus `draft` kini dapat dibatalkan secara langsung melalui tombol "Batalkan Sesi", dengan konfirmasi dialog dan pembersihan otomatis dari daftar aktif frontend.
- **Workflow Work Order Berbasis Dokumen Referensi & Permintaan Produksi Stok (SPR)**:
  - Penambahan model & migrasi `StockProductionRequest` dan `StockProductionRequestItem` sebagai dokumen induk Make to Stock (MTS).
  - Penambahan kolom relasi `stock_production_request_id` pada `production_work_orders`.
  - Service `StockProductionRequestService` untuk pembuatan draft SPR dan approval otomatis yang menerbitkan WO terkait.
  - Controller `StockProductionRequestController` beserta form validation `StockProductionRequestStoreRequest`.
  - Endpoint baru:
    - `GET/POST /api/production/stock-production-requests`
    - `POST /api/production/stock-production-requests/{id}/approve`
    - `POST /api/production/stock-production-requests/{id}/cancel`
- **Aturan Pembatalan Runtut Hybrid (Cancellation Chain)**:
  - Penambahan logika guard dan cascade pembatalan pada `CancellationService::cancelSalesOrder`, `cancelProject`, dan `cancelStockProductionRequest`.
  - Jika WO turunan masih berstatus `Draft`/`Pending` (belum dikerjakan), WO otomatis ikut dibatalkan bersama dokumen induknya.
  - Jika WO turunan sudah berjalan (`In Progress`, memiliki work logs tukang, atau `completed_qty > 0`), pembatalan dokumen induk ditolak dengan error 422 yang menyertakan nomor WO yang sedang berjalan.
  - Feature test: `WorkOrderWorkflowAndCancellationTest`.
- **Otomasi Pembuatan Work Order (WO) dari Sales Order**:
  - Penambahan logika otomatisasi pada `SalesWorkflowService` (`autoGenerateWorkOrdersForSalesOrder` & `isSelfProducedProduct`).
  - Pembuatan SPK / Work Order otomatis ke antrian produksi (`stage: 'Draft'`, `progress: 0`) saat SO dibuat/disetujui, khusus untuk produk hasil produksi sendiri (`type === 'finished_good'` atau memiliki BOM) dan melewatkan produk bahan baku / barang beli.
  - Endpoint manual trigger: `POST /api/sales/sales-orders/{id}/generate-work-orders`.
  - Feature test: `SalesOrderAutoWorkOrderTest`.
- **Fitur Restok Barang Kosong via Purchase Order (PO)**:
  - `RestockSuggestionService`: kalkulasi defisit stok barang kosong/menipis dan pembuatan Purchase Order otomatis.
  - FormRequest: `GenerateRestockPoRequest`.
  - Endpoint: `GET /api/purchasing/restock-suggestions` dan `POST /api/purchasing/restock-generate-po`.
  - Feature test: `RestockPoGenerationTest`.

### Fixed
- Fixed error `totalPages is not defined` pada `ProductionWorkOrderView.tsx` dengan menghapus blok pagination tanpa state yang menyebabkan crash saat membuka modul Work Order.
- Fixed auto-generate Work Order yang melewatkan baris item kedua jika menggunakan master produk yang sama dengan ukuran berbeda (seperti pada SO2026090013):
  - Penambahan kolom relasi `sales_order_item_id` pada tabel `production_work_orders`.
  - Pengecekan idempotensi kini berbasis baris item pesanan (`sales_order_item_id`) dengan fallback untuk data lama.
  - Penambahan detail spesifikasi ukuran (panjang potongan & jumlah fisik, misal `P: 3.3m, 2 btg`) pada `source_label` Work Order.
  - Penambahan relasi `salesOrderItem` pada `ProductionWorkOrder` dan `productionWorkOrders` pada `SalesOrderItem`.
- Fixed `Unknown API resource` (404) error on penerimaan barang / Goods Receipt Note (GRN) by adding `goods-receipts` and `goods-receipt-items` resource mapping and handling in `PurchasingController`.

## [1.0.0] - 2026-07-31
### Added
- Initial project setup.
- Endpoint `GET /api/reports/product-master-stock` dan `GetProductMasterStockReportAction` untuk Laporan Master Produk Stok (COGS, harga jual, margin, valuasi stok, status stok, SKU, & QR code).
- Endpoint `GET /api/reports/inventory/mutation`, `GET /api/reports/inventory/low-stock`, `GET /api/reports/inventory/valuation`, dan `GET /api/reports/inventory/dead-stock` beserta action services (`GetStockMutationReportAction`, `GetLowStockReportAction`, `GetInventoryValuationReportAction`, `GetDeadStockReportAction`) & feature test `InventoryReportsApiTest`.
- Endpoint `GET /api/reports/purchasing/supplier`, `GET /api/reports/purchasing/ap-aging`, dan `GET /api/reports/purchasing/price-analysis` beserta action services (`GetSupplierPurchasesReportAction`, `GetApAgingReportAction`, `GetPurchasePriceAnalysisReportAction`) & feature test `PurchasingReportsApiTest`.
- Endpoint `GET /api/reports/finance/cashflow`, `GET /api/reports/finance/expenses`, dan `GET /api/reports/finance/profit-loss` beserta action services (`GetCashflowReportAction`, `GetExpensesReportAction`, `GetProfitLossReportAction`) & feature test `FinanceReportsApiTest`.
- Workflow Enterprise **Berita Acara Gudang (BAG)**: skema dual control approval (`pending_approval`, `approved`, `rejected`), penambahan `reason_code` terstruktur, pemicu `ApprovalRequest`, endpoint `POST /api/inventory/bags/{id}/approve` dan `POST /api/inventory/bags/{id}/reject`, serta pengujian unit `InventoryWorkflowServiceTest`.
- Dokumentasi Artisan Custom Commands (`inventory:reconcile`, `inventory:reset-movements`, `app:check-product`) pada `docs/engineering.md`.

### Fixed
- Fixed SQL column error (`Unknown column 'order_date'`) on `purchase_orders` in `ExecSalesReportController@grossProfit` by correcting column name to `po_date`.
- Added `ExecSalesReportApiTest` feature tests for gross profit report endpoint.
- Updated `inventory:reconcile` artisan command (`ReconcileInventory.php`) to automatically detect missing `product_stocks` records from `stock_movements` history and initialize them before auditing/reconciling.

