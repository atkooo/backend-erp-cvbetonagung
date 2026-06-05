# Backend Implementation Plan

Target: implement `docs/erp_dbdiagram.dbml` into this Laravel backend and keep the project focused as a backend/API service.

## Current State

- Framework: Laravel 13, PHP 8.3.
- Current database config: SQLite in `.env` and `.env.example`.
- DBML target database: PostgreSQL.
- Backend foundation has started:
  - `routes/api.php` is enabled.
  - `/api/health` returns JSON status.
  - root `/` returns JSON backend status.
- ERP foundation has started:
  - DBML tables have been implemented as Laravel migrations.
  - ERP domain models and relationships exist.
  - baseline ERP seeders and relationship tests exist.
  - identity/RBAC CRUD API has started under `/api/identity/{resource}`.
  - master data CRUD API has started under `/api/master-data/{resource}`.
  - inventory CRUD API has started under `/api/inventory/{resource}`.
  - sales CRUD API has started under `/api/sales/{resource}`.
  - purchasing/returns CRUD API has started under `/api/purchasing/{resource}`.
  - projects CRUD API has started under `/api/projects/{resource}`.
  - finance CRUD API has started under `/api/finance/{resource}`.
  - production CRUD API has started under `/api/production/{resource}`.
  - support/reporting CRUD API has started under `/api/support/{resource}`.
- Vite/frontend skeleton still exists in `resources/`, `package.json`, and `vite.config.js`.

## Backend-Only Direction

The project should be treated as an API backend. Frontend assets can stay temporarily, but they should not drive implementation.

Recommended backend-only cleanup:

- Keep:
  - `app/`, `bootstrap/`, `config/`, `database/`, `routes/`, `storage/`, `tests/`, `docs/`, `artisan`, `composer.json`.
- Add:
  - `routes/api.php` if Laravel 13 skeleton has not enabled it yet.
  - API controllers, form requests, resources, services, policies, seeders, and tests.
- Deprioritize/remove later:
  - `resources/js`, `resources/css`, `vite.config.js`, `package.json`, and the default welcome page after API routing is established.
- Root `/` should become a simple health/status response, or the app can expose `/api/health`.

## Technical Decisions

These are the recommended implementation decisions for the DBML.

- Database: use PostgreSQL for real implementation because DBML explicitly declares PostgreSQL.
- IDs: use UUID primary keys for ERP domain tables.
- Existing `users` table:
  - convert `id` from auto-increment integer to UUID.
  - align DBML field names while preserving Laravel auth compatibility:
    - DBML has `password_hash`.
    - Laravel expects `password`.
    - Recommendation: keep column name `password` in database unless there is a strong reason to diverge, and document it as the Laravel equivalent of DBML `password_hash`.
- Enums:
  - Prefer string columns with constrained allowed values at application/request validation level.
  - PostgreSQL enum types are possible, but string columns are easier to migrate and refactor during early ERP development.
- Money/quantity:
  - use `decimal(18, 2)` exactly as DBML.
- Relationship behavior:
  - nullable foreign keys for optional links.
  - restrict deletes for transactional records.
  - cascade deletes only for child line items and pivots where parent deletion is intentionally allowed.
- Audit:
  - do not over-automate audit logging in phase 1.
  - add audit trail after core CRUD and auth flows are stable.

## Module Dependency Order

Implement tables in this dependency order to avoid broken foreign keys.

1. Identity and access
   - `roles`
   - `users`
   - `employees`
   - `permissions`
   - `role_permissions`

2. Master data
   - `customers`
   - `suppliers`
   - `product_categories`
   - `units`
   - `warehouses`
   - `storage_locations`
   - `products`
   - `company_settings`

3. Approval foundation
   - `approval_requests`
   - needed before `stock_opname_items` because the DBML links stock opname items to approvals.

4. Inventory
   - `product_stocks`
   - `stock_movements`
   - `stock_opname_sessions`
   - `stock_opname_items`

5. Sales
   - `quotations`
   - `quotation_items`
   - `sales_orders`
   - `sales_order_items`
   - `delivery_orders`
   - `delivery_order_items`

6. Project
   - `projects`
   - `project_timelines`
   - `project_documents`
   - `project_budget_items`

7. Finance
   - `invoices`
   - `invoice_items`
   - `payments`
   - `project_termins`
   - `supplier_payables`

8. Purchasing and returns
   - `purchase_orders`
   - `purchase_order_items`
   - `returns`
   - `return_items`

9. Production
   - `production_work_orders`
   - `production_work_order_items`
   - `production_work_logs`
   - `boms`
   - `bom_items`

10. Support and reporting
   - `audit_logs`
   - `reminders`
   - `document_exports`

Note: there are circular business references between sales, projects, invoices, and termins. The clean migration strategy is to create base tables first, then add late nullable foreign keys in follow-up migration steps where needed.

## Implementation Phases

### Phase 0 - Backend Foundation

Goal: make the Laravel app clearly backend/API focused.

Tasks:

- Enable API routing.
- Add `/api/health`.
- Decide PostgreSQL environment variables in `.env.example`.
- Keep or remove frontend skeleton after confirming deployment expectations.
- Add base API response conventions.
- Add UUID model trait or base model convention.

Verification:

- `php artisan route:list`
- `php artisan test`
- health endpoint returns JSON.

### Phase 1 - Schema Foundation

Goal: implement DBML as Laravel migrations with stable table order.

Tasks:

- Replace/adjust default `users` migration for UUID-compatible auth.
- Create migrations for all DBML tables.
- Use explicit indexes and unique constraints from DBML.
- Add late foreign keys for circular references.
- Add migration tests or at least run full migrate/fresh cycle.

Verification:

- `php artisan migrate:fresh`
- `php artisan schema:dump` if needed
- `php artisan test`

### Phase 2 - Models and Relationships

Goal: expose schema as Eloquent models.

Tasks:

- Create models for each ERP table.
- Add fillable/casts for UUID, datetime, date, decimal, and enum-like strings.
- Add relationships matching DBML refs.
- Add factories for core master/transaction tables.

Verification:

- model relationship tests for representative modules.
- factory creation tests.

### Phase 3 - Seeders and Initial Master Data

Goal: make a fresh backend usable after migration.

Tasks:

- Seed roles, permissions, admin user.
- Seed units, product categories, warehouses, default storage locations.
- Seed company settings.
- Add sample data only if it is clearly marked as demo/dev.

Verification:

- `php artisan migrate:fresh --seed`
- login/auth seed user exists.

### Phase 4 - API Layer

Goal: provide CRUD APIs by module.

Status:

- Started identity/RBAC API:
  - `roles`
  - `users`
  - `employees`
  - `permissions`
  - `role-permissions`
- Started master data API:
  - `customers`
  - `suppliers`
  - `product-categories`
  - `units`
  - `warehouses`
  - `storage-locations`
  - `products`
  - `company-settings`
- Started inventory API:
  - `product-stocks`
  - `stock-movements`
  - `stock-opname-sessions`
  - `stock-opname-items`
  - `approval-requests`
- Started sales API:
  - `quotations`
  - `quotation-items`
  - `sales-orders`
  - `sales-order-items`
  - `delivery-orders`
  - `delivery-order-items`
- Started purchasing/returns API:
  - `purchase-orders`
  - `purchase-order-items`
  - `supplier-payables`
  - `returns`
  - `return-items`
- Started projects API:
  - `projects`
  - `project-timelines`
  - `project-documents`
  - `project-budget-items`
- Started finance API:
  - `invoices`
  - `invoice-items`
  - `payments`
  - `project-termins`
- Started production API:
  - `work-orders`
  - `work-order-items`
  - `work-logs`
  - `boms`
  - `bom-items`
- Started support/reporting API:
  - `audit-logs`
  - `reminders`
  - `document-exports`

Recommended order:

1. Auth and users.
2. Master data.
3. Inventory.
4. Sales.
5. Purchasing.
6. Projects.
7. Finance.
8. Production.
9. Approval, reminders, audit, exports.

Tasks per module:

- API routes.
- Controller.
- Form request validation.
- Resource/transformer.
- Service class for business rules when CRUD is not enough.
- Feature tests.

Verification:

- route list by module.
- feature tests for list/create/update/delete or state transition endpoints.

### Phase 5 - Business Workflows

Goal: implement ERP behavior, not just CRUD.

Workflow candidates:

- Quotation approval to sales order.
- Sales order to delivery order.
- Delivery to stock movement.
- Invoice and payment status calculation.
- Purchase order receiving to stock movement.
- Stock opname approval and adjustment.
- Project progress and termin billing.
- Production work order progress from work logs.
- BOM cost calculation.

Verification:

- workflow feature tests.
- transaction rollback tests for stock and finance updates.

### Phase 6 - Security, Audit, and Operations

Goal: harden the backend.

Tasks:

- Auth middleware.
- Role/permission checks.
- Policies per module.
- Audit log hooks for important actions.
- Pagination/filtering/sorting conventions.
- Error response standardization.
- Export job structure for PDF/XLSX/email/print.

Verification:

- authorization tests.
- audit creation tests.
- queue/job tests where applicable.

## Recommended First Implementation Batch

Start with a small, verifiable batch instead of all DBML tables at once.

Batch 1:

- Backend-only API foundation:
  - `/api/health`
  - API route bootstrap
  - root route returns JSON or redirects to API health
- UUID/auth foundation:
  - `roles`
  - UUID-compatible `users`
  - `permissions`
  - `role_permissions`
  - `employees`
- Core seed:
  - admin role
  - admin user
  - baseline permissions

Why this first:

- every later module depends on `users` and role/permission structure.
- it validates UUID strategy before creating dozens of migrations.
- it gives a usable backend identity foundation.

## Risks and Open Decisions

- PostgreSQL vs SQLite:
  - DBML says PostgreSQL, but current `.env` uses SQLite.
  - SQLite can be used for quick local tests, but PostgreSQL should be the target for migration correctness.
- `password_hash` vs `password`:
  - strict DBML naming conflicts with Laravel auth defaults.
  - recommended choice is `password`.
- Full schema size:
  - DBML contains many modules, so implementing everything in one pass increases migration and relationship error risk.
  - phased implementation is safer.
- Enum strategy:
  - PostgreSQL enums are stricter but harder to evolve.
  - string columns plus validation are more practical early on.

## Definition of Done

The DBML implementation is done when:

- all DBML tables exist as Laravel migrations.
- all DBML refs exist as foreign keys or intentionally documented nullable/manual references.
- all unique and composite indexes are implemented.
- models and relationships exist for all ERP tables.
- `migrate:fresh --seed` works.
- API health route works.
- tests cover schema creation, seed data, and representative module workflows.
