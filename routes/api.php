<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Dashboard\DashboardSummaryController;
use App\Http\Controllers\Api\Finance\FinanceQueryController;
use App\Http\Controllers\Api\FinanceController;
use App\Http\Controllers\Api\HrdController;
use App\Http\Controllers\Api\IdentityController;
use App\Http\Controllers\Api\Inventory\InventoryQueryController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\Master\MasterQueryController;
use App\Http\Controllers\Api\MasterDataController;
use App\Http\Controllers\Api\ProductImageController;
use App\Http\Controllers\Api\ProductionController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\Purchasing\PurchasingQueryController;
use App\Http\Controllers\Api\PurchasingController;
use App\Http\Controllers\Api\Reports\ExecSalesReportController;
use App\Http\Controllers\Api\Reports\FinanceReportController;
use App\Http\Controllers\Api\Reports\InventoryReportController;
use App\Http\Controllers\Api\Reports\ProductMasterStockReportController;
use App\Http\Controllers\Api\Reports\PurchasingReportController;
use App\Http\Controllers\Api\Reports\ReportsController;
use App\Http\Controllers\Api\ReturnController;
use App\Http\Controllers\Api\SalesController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\SupportController;
use App\Http\Controllers\Api\SystemController;
use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'service' => config('app.name'),
    ]);
});

Route::post('auth/login', [AuthController::class, 'login']);

Route::middleware(['auth:sanctum'])->group(function () {
    Route::prefix('auth')->controller(AuthController::class)->group(function () {
        Route::get('me', 'me');
        Route::post('logout', 'logout');
        Route::put('profile', 'updateProfile');
    });

    Route::prefix('system')->controller(SystemController::class)->group(function () {
        Route::get('backup', 'exportBackup');
    });

    Route::prefix('settings')->controller(SettingsController::class)->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
    });

    Route::prefix('notifications')->controller(NotificationController::class)->group(function () {
        Route::get('/', 'index');
        Route::post('read-all', 'markAllAsRead');
        Route::post('test', 'testNotification');
        Route::post('{id}/read', 'markAsRead')->whereUuid('id');
    });
});

Route::middleware(['auth:sanctum', 'permission'])->group(function () {
    Route::get('dashboard/summary', DashboardSummaryController::class);

    Route::prefix('master')->controller(MasterQueryController::class)->group(function () {
        Route::get('customers', 'customers');
        Route::get('suppliers', 'suppliers');
        Route::get('products', 'products');
    });

    Route::prefix('purchasing')->controller(PurchasingQueryController::class)->group(function () {
        Route::get('receivings', 'receivings');
    });

    Route::prefix('inventory')->controller(InventoryQueryController::class)->group(function () {
        Route::get('stocks', 'stocks');
        Route::get('stock-ins', 'stockIns');
        Route::get('stock-outs', 'stockOuts');
    });

    Route::prefix('finance')->controller(FinanceQueryController::class)->group(function () {
        Route::get('billing', 'billing');
        Route::get('cashier', 'cashier');
        Route::get('account-payable', 'accountPayable');
        Route::get('cash-bank', 'cashBank');
    });

    Route::get('reports', ReportsController::class);
    Route::get('reports/product-master-stock', ProductMasterStockReportController::class);

    Route::prefix('reports/exec')->controller(ExecSalesReportController::class)->group(function () {
        Route::get('daily-sales', 'dailySales');
        Route::get('gross-profit', 'grossProfit');
        Route::get('ar-aging', 'arAging');
        Route::get('top-products', 'topProducts');
    });

    Route::prefix('reports/inventory')->controller(InventoryReportController::class)->group(function () {
        Route::get('mutation', 'mutation');
        Route::get('low-stock', 'lowStock');
        Route::get('valuation', 'valuation');
        Route::get('dead-stock', 'deadStock');
    });

    Route::prefix('reports/purchasing')->controller(PurchasingReportController::class)->group(function () {
        Route::get('supplier', 'supplier');
        Route::get('ap-aging', 'apAging');
        Route::get('price-analysis', 'priceAnalysis');
    });

    Route::prefix('reports/finance')->controller(FinanceReportController::class)->group(function () {
        Route::get('cashflow', 'cashflow');
        Route::get('expenses', 'expenses');
        Route::get('profit-loss', 'profitLoss');
    });

    Route::prefix('identity')->controller(IdentityController::class)->group(function () {
        Route::get('role-permissions/{roleId}/{permissionId}', 'showRolePermission')
            ->whereUuid(['roleId', 'permissionId']);
        Route::match(['put', 'patch'], 'role-permissions/{roleId}/{permissionId}', 'updateRolePermission')
            ->whereUuid(['roleId', 'permissionId']);
        Route::delete('role-permissions/{roleId}/{permissionId}', 'destroyRolePermission')
            ->whereUuid(['roleId', 'permissionId']);

        Route::post('employees/{id}/generate-account', 'generateAccount')
            ->whereUuid('id');

        Route::prefix('{resource}')
            ->whereIn('resource', [
                'roles',
                'users',
                'employees',
                'permissions',
                'role-permissions',
            ])
            ->group(function () {
                Route::get('/', 'index');
                Route::post('/', 'store');
                Route::get('/{id}', 'show')->whereUuid('id');
                Route::match(['put', 'patch'], '/{id}', 'update')->whereUuid('id');
                Route::delete('/{id}', 'destroy')->whereUuid('id');
            });
    });

    // Product image upload (multipart, separate from generic resource controller)
    Route::post('master-data/products/{id}/image', [ProductImageController::class, 'upload'])->whereUuid('id');
    Route::delete('master-data/products/{id}/image', [ProductImageController::class, 'destroy'])->whereUuid('id');

    Route::prefix('master-data/{resource}')
        ->whereIn('resource', [
            'customers',
            'suppliers',
            'product-categories',
            'units',
            'warehouses',
            'storage-locations',
            'products',
            'company-settings',
            'discounts',
        ])
        ->controller(MasterDataController::class)
        ->group(function () {
            Route::get('/', 'index');
            Route::post('/', 'store');
            Route::get('/{id}', 'show')->whereUuid('id');
            Route::match(['put', 'patch'], '/{id}', 'update')->whereUuid('id');
            Route::delete('/{id}', 'destroy')->whereUuid('id');
        });

    Route::prefix('inventory')->controller(InventoryController::class)->group(function () {
        Route::post('stock-opname-items/{id}/adjust', 'adjustStockOpnameItem')
            ->whereUuid('id');

        Route::get('product-stocks/{productId}/{locationId}', 'showProductStock')
            ->whereUuid(['productId', 'locationId']);
        Route::match(['put', 'patch'], 'product-stocks/{productId}/{locationId}', 'updateProductStock')
            ->whereUuid(['productId', 'locationId']);
        Route::delete('product-stocks/{productId}/{locationId}', 'destroyProductStock')
            ->whereUuid(['productId', 'locationId']);

        Route::prefix('{resource}')
            ->whereIn('resource', [
                'product-stocks',
                'stock-movements',
                'stock-opname-sessions',
                'stock-opname-items',
                'approval-requests',
                'bags',
                'bag-items',
            ])
            ->group(function () {
                Route::get('/', 'index');
                Route::post('/', 'store');
                Route::get('/{id}', 'show')->whereUuid('id');
                Route::match(['put', 'patch'], '/{id}', 'update')->whereUuid('id');
                Route::delete('/{id}', 'destroy')->whereUuid('id');
            });
    });

    Route::post('sales/pos', [SalesController::class, 'processPos']);
    Route::post('sales/quotations/{id}/approve', [SalesController::class, 'approveQuotation'])
        ->whereUuid('id');
    Route::post('sales/quotations/{id}/cancel', [SalesController::class, 'cancelQuotation'])
        ->whereUuid('id');
    Route::post('sales/sales-orders/{id}/approve', [SalesController::class, 'approveSalesOrder'])
        ->whereUuid('id');
    Route::post('sales/sales-orders/{id}/deliver', [SalesController::class, 'createDeliveryOrder'])
        ->whereUuid('id');
    Route::post('sales/sales-orders/{id}/cancel', [SalesController::class, 'cancelSalesOrder'])
        ->whereUuid('id');
    Route::post('sales/delivery-orders/{id}/ship', [SalesController::class, 'shipDeliveryOrder'])
        ->whereUuid('id');
    Route::post('sales/delivery-orders/{id}/cancel', [SalesController::class, 'cancelDeliveryOrder'])
        ->whereUuid('id');
    Route::post('sales/pos/{id}/cancel', [SalesController::class, 'cancelPosTransaction'])
        ->whereUuid('id');

    Route::prefix('sales/{resource}')
        ->whereIn('resource', [
            'quotations',
            'quotation-items',
            'sales-orders',
            'sales-order-items',
            'delivery-orders',
            'delivery-order-items',
        ])
        ->controller(SalesController::class)
        ->group(function () {
            Route::get('/', 'index');
            Route::post('/', 'store');
            Route::get('/{id}', 'show')->whereUuid('id');
            Route::match(['put', 'patch'], '/{id}', 'update')->whereUuid('id');
            Route::delete('/{id}', 'destroy')->whereUuid('id');
        });

    Route::post('purchasing/purchase-orders/{id}/receive', [PurchasingController::class, 'receivePurchaseOrder'])
        ->whereUuid('id');
    Route::post('purchasing/purchase-orders/{id}/cancel', [PurchasingController::class, 'cancelPurchaseOrder'])
        ->whereUuid('id');
    Route::post('purchasing/purchase-requests/{id}/cancel', [PurchasingController::class, 'cancelPurchaseRequest'])
        ->whereUuid('id');
    Route::post('purchasing/rfqs/{id}/cancel', [PurchasingController::class, 'cancelRfq'])
        ->whereUuid('id');
    Route::post('purchasing/goods-receipt-notes/{id}/cancel', [PurchasingController::class, 'cancelGoodsReceiptNote'])
        ->whereUuid('id');
    Route::post('purchasing/supplier-payables/{id}/cancel', [PurchasingController::class, 'cancelSupplierPayable'])
        ->whereUuid('id');

    Route::prefix('purchasing/{resource}')
        ->whereIn('resource', [
            'purchase-requests',
            'purchase-request-items',
            'rfqs',
            'rfq-items',
            'purchase-orders',
            'purchase-order-items',
            'goods-receipts',
            'goods-receipt-items',
            'goods-receipt-notes',
            'goods-receipt-note-items',
            'supplier-payables',
        ])
        ->controller(PurchasingController::class)
        ->group(function () {
            Route::get('/', 'index');
            Route::post('/', 'store');
            Route::get('/{id}', 'show')->whereUuid('id');
            Route::match(['put', 'patch'], '/{id}', 'update')->whereUuid('id');
            Route::delete('/{id}', 'destroy')->whereUuid('id');
        });
    Route::post('returns/{id}/refund', [ReturnController::class, 'manualRefund'])
        ->whereUuid('id');

    Route::prefix('{resource}')
        ->whereIn('resource', [
            'returns',
            'return-items',
        ])
        ->controller(ReturnController::class)
        ->group(function () {
            Route::get('/', 'index');
            Route::post('/', 'store');
            Route::get('/{id}', 'show')->whereUuid('id');
            Route::match(['put', 'patch'], '/{id}', 'update')->whereUuid('id');
            Route::delete('/{id}', 'destroy')->whereUuid('id');
        });

    Route::prefix('projects/{resource}')
        ->whereIn('resource', [
            'projects',
            'project-timelines',
            'project-documents',
            'project-budget-items',
        ])
        ->controller(ProjectController::class)
        ->group(function () {
            Route::post('{id}/cancel', 'cancelProject')->whereUuid('id')->where('resource', 'projects');
            Route::get('/', 'index');
            Route::post('/', 'store');
            Route::get('/{id}', 'show')->whereUuid('id');
            Route::match(['put', 'patch'], '/{id}', 'update')->whereUuid('id');
            Route::delete('/{id}', 'destroy')->whereUuid('id');
        });

    Route::post('finance/payments/{id}/verify', [FinanceController::class, 'verifyPayment'])
        ->whereUuid('id');
    Route::post('finance/supplier-payables/{id}/pay', [FinanceController::class, 'paySupplierPayable'])
        ->whereUuid('id');
    Route::post('finance/invoices/{id}/cancel', [FinanceController::class, 'cancelInvoice'])
        ->whereUuid('id');
    Route::post('finance/payments/{id}/cancel', [FinanceController::class, 'cancelPayment'])
        ->whereUuid('id');

    Route::prefix('finance/{resource}')
        ->whereIn('resource', [
            'invoices',
            'invoice-items',
            'payments',
            'project-termins',
            'accounts',
            'cash-transactions',
        ])
        ->controller(FinanceController::class)
        ->group(function () {
            Route::get('/', 'index');
            Route::post('/', 'store');
            Route::get('/{id}', 'show')->whereUuid('id');
            Route::match(['put', 'patch'], '/{id}', 'update')->whereUuid('id');
            Route::delete('/{id}', 'destroy')->whereUuid('id');
        });

    Route::post('production/work-orders/{id}/receive', [ProductionController::class, 'receive'])
        ->whereUuid('id');
    Route::post('production/work-orders/{id}/cancel', [ProductionController::class, 'cancelWorkOrder'])
        ->whereUuid('id');

    Route::prefix('production/{resource}')
        ->whereIn('resource', [
            'work-orders',
            'work-order-items',
            'work-logs',
            'boms',
            'bom-items',
        ])
        ->controller(ProductionController::class)
        ->group(function () {
            Route::get('/', 'index');
            Route::post('/', 'store');
            Route::get('/{id}', 'show')->whereUuid('id');
            Route::match(['put', 'patch'], '/{id}', 'update')->whereUuid('id');
            Route::delete('/{id}', 'destroy')->whereUuid('id');
        });

    Route::prefix('support/{resource}')
        ->whereIn('resource', [
            'audit-logs',
            'reminders',
            'document-exports',
        ])
        ->controller(SupportController::class)
        ->group(function () {
            Route::get('/', 'index');
            Route::post('/', 'store');
            Route::get('/{id}', 'show')->whereUuid('id');
            Route::match(['put', 'patch'], '/{id}', 'update')->whereUuid('id');
            Route::delete('/{id}', 'destroy')->whereUuid('id');
        });

    Route::post('hrd/attendances/scan', [HrdController::class, 'scanAttendance']);

    Route::prefix('hrd/{resource}')
        ->whereIn('resource', [
            'employee-details',
            'employee-documents',
            'leave-types',
            'leaves',
            'attendances',
        ])
        ->controller(HrdController::class)
        ->group(function () {
            Route::get('/', 'index');
            Route::post('/', 'store');
            Route::get('/{id}', 'show')->whereUuid('id');
            Route::match(['put', 'patch'], '/{id}', 'update')->whereUuid('id');
            Route::delete('/{id}', 'destroy')->whereUuid('id');
        });
});
