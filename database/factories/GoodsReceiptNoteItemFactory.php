<?php

namespace Database\Factories;

use App\Models\GoodsReceiptNote;
use App\Models\PurchaseOrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class GoodsReceiptNoteItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'goods_receipt_note_id' => GoodsReceiptNote::factory(),
            'purchase_order_item_id' => PurchaseOrderItem::factory(),
            'product_id' => null, // Must be provided explicitly in tests
            'received_qty' => $this->faker->randomFloat(2, 1, 100),
            'rejected_qty' => 0,
            'notes' => null,
        ];
    }
}
