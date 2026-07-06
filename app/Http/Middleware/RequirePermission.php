<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePermission
{
    /**
     * @var array<string, string>
     */
    private const RESOURCE_MODULES = [
        'roles' => 'roles',
        'users' => 'users',
        'employees' => 'employees',
        'permissions' => 'roles',
        'role-permissions' => 'roles',
        'customers' => 'customers',
        'suppliers' => 'suppliers',
        'product-categories' => 'products',
        'units' => 'products',
        'warehouses' => 'inventory',
        'storage-locations' => 'inventory',
        'products' => 'products',
        'discounts' => 'products',
        'company-settings' => 'settings',
        'product-stocks' => 'inventory',
        'stock-movements' => 'inventory',
        'stock-opname-sessions' => 'inventory',
        'stock-opname-items' => 'inventory',
        'approval-requests' => 'approvals',
        'quotations' => 'sales',
        'quotation-items' => 'sales',
        'sales-orders' => 'sales',
        'sales-order-items' => 'sales',
        'delivery-orders' => 'sales',
        'delivery-order-items' => 'sales',
        'purchase-orders' => 'purchasing',
        'purchase-order-items' => 'purchasing',
        'goods-receipts' => 'purchasing',
        'goods-receipt-items' => 'purchasing',
        'goods-receipt-notes' => 'purchasing',
        'goods-receipt-note-items' => 'purchasing',
        'supplier-payables' => 'purchasing',
        'returns' => 'purchasing',
        'return-items' => 'purchasing',
        'projects' => 'projects',
        'project-timelines' => 'projects',
        'project-documents' => 'projects',
        'project-budget-items' => 'projects',
        'invoices' => 'finance',
        'invoice-items' => 'finance',
        'payments' => 'finance',
        'project-termins' => 'finance',
        'work-orders' => 'production',
        'work-order-items' => 'production',
        'work-logs' => 'production',
        'boms' => 'production',
        'bom-items' => 'production',
        'audit-logs' => 'reports',
        'reminders' => 'reports',
        'document-exports' => 'reports',
    ];

    /**
     * @var array<string, array<int, string>>
     */
    private const ALLOWED_ACCESS_LEVELS = [
        'view' => ['read', 'edit', 'full'],
        'create' => ['edit', 'full'],
        'update' => ['edit', 'full'],
        'delete' => ['full'],
        'approve' => ['full'],
    ];

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('api/auth/me') || $request->is('api/auth/logout') || $request->is('api/hrd/attendances/scan')) {
            return $next($request);
        }

        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            return $this->forbidden();
        }

        $module = $this->moduleFor($request);
        $action = $this->actionFor($request);

        if ($module === null || ! $this->userCan($user, $module, $action)) {
            return $this->forbidden();
        }

        return $next($request);
    }

    private function moduleFor(Request $request): ?string
    {
        $resource = $request->route('resource');

        if (is_string($resource) && array_key_exists($resource, self::RESOURCE_MODULES)) {
            return self::RESOURCE_MODULES[$resource];
        }

        $segments = $request->segments();
        $apiModule = $segments[1] ?? null;
        $apiResource = $segments[2] ?? null;

        // Handle product image endpoint: api/master-data/products/{id}/image
        if ($apiModule === 'master-data' && $apiResource === 'products') {
            return 'products';
        }

        if ($apiModule === 'master') {
            return match ($apiResource) {
                'customers' => 'customers',
                'suppliers' => 'suppliers',
                'products' => 'products',
                default => null,
            };
        }

        return match ($apiModule) {
            'dashboard' => 'reports',
            'identity' => 'roles',
            'inventory' => str_contains($request->path(), 'stock-opname-items') ? 'inventory' : 'inventory',
            'sales' => 'sales',
            'purchasing' => 'purchasing',
            'projects' => 'projects',
            'finance' => 'finance',
            'production' => 'production',
            'reports' => 'reports',
            'support' => 'reports',
            'returns' => 'purchasing',
            default => null,
        };
    }

    private function actionFor(Request $request): string
    {
        if ($request->isMethod('post') && preg_match('/\/(approve|verify|receive|adjust|deliver|ship|refund)$/', $request->path()) === 1) {
            return 'approve';
        }

        return match ($request->method()) {
            'GET' => 'view',
            'POST' => 'create',
            'PUT', 'PATCH' => 'update',
            'DELETE' => 'delete',
            default => 'view',
        };
    }

    private function userCan(User $user, string $module, string $action): bool
    {
        if ($user->role_id === null) {
            return false;
        }

        $allowedAccessLevels = self::ALLOWED_ACCESS_LEVELS[$action] ?? ['full'];

        return $user->role()
            ->whereHas('permissions', function ($query) use ($module, $action, $allowedAccessLevels): void {
                $query
                    ->where('module', $module)
                    ->where('action', $action)
                    ->whereIn('role_permissions.access_level', $allowedAccessLevels);
            })
            ->exists();
    }

    private function forbidden(): JsonResponse
    {
        return response()->json(['message' => 'Forbidden.'], 403);
    }
}
