<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\FinanceController;
use App\Http\Controllers\Api\IdentityController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\MasterDataController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\ProductionController;
use App\Http\Controllers\Api\PurchasingController;
use App\Http\Controllers\Api\SalesController;
use App\Http\Controllers\Api\SupportController;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'service' => config('app.name'),
    ]);
});

Route::post('auth/login', [AuthController::class, 'login']);

Route::middleware(['api.token', 'permission'])->group(function () {
    Route::prefix('auth')->controller(AuthController::class)->group(function () {
        Route::get('me', 'me');
        Route::post('logout', 'logout');
    });

    Route::prefix('identity')->controller(IdentityController::class)->group(function () {
        Route::get('role-permissions/{roleId}/{permissionId}', 'showRolePermission')
            ->whereUuid(['roleId', 'permissionId']);
        Route::match(['put', 'patch'], 'role-permissions/{roleId}/{permissionId}', 'updateRolePermission')
            ->whereUuid(['roleId', 'permissionId']);
        Route::delete('role-permissions/{roleId}/{permissionId}', 'destroyRolePermission')
            ->whereUuid(['roleId', 'permissionId']);

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
            ])
            ->group(function () {
                Route::get('/', 'index');
                Route::post('/', 'store');
                Route::get('/{id}', 'show')->whereUuid('id');
                Route::match(['put', 'patch'], '/{id}', 'update')->whereUuid('id');
                Route::delete('/{id}', 'destroy')->whereUuid('id');
            });
    });

    Route::post('sales/quotations/{id}/approve', [SalesController::class, 'approveQuotation'])
        ->whereUuid('id');
    Route::post('sales/sales-orders/{id}/deliver', [SalesController::class, 'createDeliveryOrder'])
        ->whereUuid('id');
    Route::post('sales/delivery-orders/{id}/ship', [SalesController::class, 'shipDeliveryOrder'])
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

    Route::prefix('purchasing/{resource}')
        ->whereIn('resource', [
            'purchase-orders',
            'purchase-order-items',
            'supplier-payables',
            'returns',
            'return-items',
        ])
        ->controller(PurchasingController::class)
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
            Route::get('/', 'index');
            Route::post('/', 'store');
            Route::get('/{id}', 'show')->whereUuid('id');
            Route::match(['put', 'patch'], '/{id}', 'update')->whereUuid('id');
            Route::delete('/{id}', 'destroy')->whereUuid('id');
        });

    Route::post('finance/payments/{id}/verify', [FinanceController::class, 'verifyPayment'])
        ->whereUuid('id');

    Route::prefix('finance/{resource}')
        ->whereIn('resource', [
            'invoices',
            'invoice-items',
            'payments',
            'project-termins',
        ])
        ->controller(FinanceController::class)
        ->group(function () {
            Route::get('/', 'index');
            Route::post('/', 'store');
            Route::get('/{id}', 'show')->whereUuid('id');
            Route::match(['put', 'patch'], '/{id}', 'update')->whereUuid('id');
            Route::delete('/{id}', 'destroy')->whereUuid('id');
        });

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
});
