# Changelog

All notable changes to the Backend ERP CV. Beton Agung will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]
### Added
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

