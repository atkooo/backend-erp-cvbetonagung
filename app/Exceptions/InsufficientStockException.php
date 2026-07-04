<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class InsufficientStockException extends Exception
{
    public function __construct(string $productName, float $qtyNeeded, float $qtyAvailable)
    {
        $message = "Stok tidak mencukupi untuk produk {$productName}. Dibutuhkan: {$qtyNeeded}, Tersedia: {$qtyAvailable}. Silakan buat Work Order/Purchase Order terlebih dahulu.";
        parent::__construct($message, 422);
    }

    public function render($request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'errors' => [
                'stock' => [$this->getMessage()]
            ]
        ], 422);
    }
}
