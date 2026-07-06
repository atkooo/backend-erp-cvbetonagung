<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\StockOpnameSession;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;

class MasterDataTestSeeder extends Seeder
{
    public function run(): void
    {
        Model::unguard();

        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin User',
                'password' => bcrypt('password'),
                'status' => 'active',
            ]
        );

        $customer = Customer::firstOrCreate(
            ['code' => 'CUST-UMUM'],
            [
                'name' => 'Pelanggan Umum',
                'email' => 'umum@example.com',
                'phone' => '081234567890',
                'status' => 'active',
            ]
        );

        $supplier = Supplier::firstOrCreate(
            ['code' => 'SUP-UMUM'],
            [
                'name' => 'Supplier Umum',
                'contact_name' => 'Bapak Supplier',
                'phone' => '089876543210',
                'status' => 'active',
            ]
        );

        $productFg = Product::firstOrCreate(
            ['sku' => 'PRC-0001'],
            [
                'name' => 'Product FG Test',
                'cost_price' => 10000,
                'selling_price' => 15000,
                'status' => 'active',
            ]
        );

        $productRm = Product::firstOrCreate(
            ['sku' => 'MTL-0001'],
            [
                'name' => 'Material RM Test',
                'cost_price' => 5000,
                'selling_price' => 7000,
                'status' => 'active',
            ]
        );

        $unitKg = Unit::firstOrCreate(
            ['code' => 'KG'],
            ['name' => 'Kilogram']
        );

        $employee = Employee::firstOrCreate(
            ['employee_number' => 'EMP-PRD-INIT'],
            [
                'name' => 'Employee Prod Init',
                'department' => 'Production',
                'role_name' => 'Staff',
                'status' => 'active',
            ]
        );

        $warehouse = Warehouse::firstOrCreate(
            ['code' => 'GDG-UTM'],
            [
                'name' => 'Gudang Utama Test',
                'status' => 'active',
            ]
        );

        $location = StorageLocation::firstOrCreate(
            ['code' => 'DEFAULT'],
            [
                'warehouse_id' => $warehouse->id,
                'name' => 'Lokasi Default',
            ]
        );

        $account = Account::firstOrCreate(
            ['code' => 'ACC-01'],
            [
                'name' => 'Kas Test',
                'type' => 'cash',
                'currency' => 'IDR',
                'is_active' => true,
            ]
        );

        ProductStock::firstOrCreate(
            ['product_id' => $productFg->id, 'location_id' => $location->id],
            ['quantity' => 100]
        );

        // Initial Documents for testing referencing
        $quotation = Quotation::firstOrCreate(
            ['quotation_number' => 'QUO-INIT'],
            [
                'customer_id' => $customer->id,
                'created_by' => $admin->id,
                'quotation_date' => date('Y-m-d'),
                'valid_until' => date('Y-m-d', strtotime('+30 days')),
                'total' => 100000,
                'status' => 'draft',
            ]
        );

        $salesOrder = SalesOrder::firstOrCreate(
            ['order_number' => 'SO-INIT'],
            [
                'quotation_id' => $quotation->id,
                'customer_id' => $customer->id,
                'order_date' => date('Y-m-d'),
                'total' => 100000,
                'status' => 'draft',
            ]
        );

        $salesOrder->items()->firstOrCreate(
            ['product_id' => $productFg->id],
            [
                'description' => 'Product FG Test',
                'quantity' => 10,
                'unit_price' => 10000,
                'subtotal' => 100000,
                'discount_amount' => 0,
            ]
        );

        $invoice = Invoice::firstOrCreate(
            ['invoice_number' => 'INV-INIT'],
            [
                'customer_id' => $customer->id,
                'sales_order_id' => $salesOrder->id,
                'invoice_date' => date('Y-m-d'),
                'due_date' => date('Y-m-d', strtotime('+30 days')),
                'total' => 100000,
                'paid_amount' => 0,
                'status' => 'unpaid',
            ]
        );

        $project = Project::firstOrCreate(
            ['code' => 'PRJ-INIT'],
            [
                'customer_id' => $customer->id,
                'project_name' => 'Project Init',
                'contract_value' => 1000000,
                'deadline' => date('Y-m-d', strtotime('+30 days')),
                'status' => 'survey',
            ]
        );

        $stockOpnameSession = StockOpnameSession::firstOrCreate(
            ['opname_number' => 'OPN-INIT'],
            [
                'warehouse_id' => $warehouse->id,
                'started_at' => date('Y-m-d H:i:s'),
                'status' => 'draft',
                'started_by' => $admin->id,
            ]
        );
    }
}
