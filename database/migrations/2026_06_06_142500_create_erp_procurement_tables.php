<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('purchase_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('pr_number')->unique();
            $table->foreignUuid('requester_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('request_date');
            $table->date('required_date')->nullable();
            $table->string('department')->nullable();
            $table->string('status')->default('pending_approval'); // pending_approval, approved, rejected, processed
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_request_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('purchase_request_id')->constrained('purchase_requests')->cascadeOnDelete();
            $table->foreignUuid('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('description')->nullable();
            $table->decimal('quantity', 18, 2);
            $table->string('status')->default('open'); // open, in_rfq, in_po
        });

        Schema::create('rfqs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('rfq_number')->unique();
            $table->foreignUuid('purchase_request_id')->nullable()->constrained('purchase_requests')->nullOnDelete();
            $table->foreignUuid('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->date('rfq_date');
            $table->date('valid_until')->nullable();
            $table->string('status')->default('sent'); // sent, quoted, accepted, rejected
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('rfq_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('rfq_id')->constrained('rfqs')->cascadeOnDelete();
            $table->foreignUuid('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('description')->nullable();
            $table->decimal('quantity', 18, 2);
            $table->decimal('quoted_unit_price', 18, 2)->default(0);
            $table->decimal('subtotal', 18, 2)->default(0);
        });

        // Add foreign keys to existing purchase_orders table
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->foreignUuid('purchase_request_id')->nullable()->after('supplier_id')->constrained('purchase_requests')->nullOnDelete();
            $table->foreignUuid('rfq_id')->nullable()->after('purchase_request_id')->constrained('rfqs')->nullOnDelete();
        });

        Schema::create('goods_receipt_notes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('grn_number')->unique();
            $table->foreignUuid('purchase_order_id')->nullable()->constrained('purchase_orders')->restrictOnDelete();
            $table->foreignUuid('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->foreignUuid('to_location_id')->nullable()->constrained('storage_locations')->nullOnDelete();
            $table->foreignUuid('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('receipt_date');
            $table->string('delivery_order_number')->nullable(); // Nomor Surat Jalan dari Supplier
            $table->string('status')->default('received'); // received, partially_returned, fully_returned
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('goods_receipt_note_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('goods_receipt_note_id')->constrained('goods_receipt_notes')->cascadeOnDelete();
            $table->foreignUuid('purchase_order_item_id')->nullable()->constrained('purchase_order_items')->nullOnDelete();
            $table->foreignUuid('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->decimal('received_qty', 18, 2);
            $table->decimal('rejected_qty', 18, 2)->default(0);
            $table->string('notes')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('goods_receipt_note_items');
        Schema::dropIfExists('goods_receipt_notes');

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropForeign(['rfq_id']);
            $table->dropForeign(['purchase_request_id']);
            $table->dropColumn(['rfq_id', 'purchase_request_id']);
        });

        Schema::dropIfExists('rfq_items');
        Schema::dropIfExists('rfqs');
        Schema::dropIfExists('purchase_request_items');
        Schema::dropIfExists('purchase_requests');
    }
};
