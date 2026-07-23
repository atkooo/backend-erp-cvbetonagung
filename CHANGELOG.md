# Changelog

All notable changes to the Backend ERP CV. Beton Agung will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]
### Added
- Initial project setup.
- Endpoint `GET /api/reports/product-master-stock` dan `GetProductMasterStockReportAction` untuk Laporan Master Produk Stok (COGS, harga jual, margin, valuasi stok, status stok, SKU, & QR code).
- Endpoint `GET /api/reports/inventory/mutation`, `GET /api/reports/inventory/low-stock`, `GET /api/reports/inventory/valuation`, dan `GET /api/reports/inventory/dead-stock` beserta action services (`GetStockMutationReportAction`, `GetLowStockReportAction`, `GetInventoryValuationReportAction`, `GetDeadStockReportAction`) & feature test `InventoryReportsApiTest`.

### Fixed
- Fixed SQL column error (`Unknown column 'order_date'`) on `purchase_orders` in `ExecSalesReportController@grossProfit` by correcting column name to `po_date`.
- Added `ExecSalesReportApiTest` feature tests for gross profit report endpoint.
