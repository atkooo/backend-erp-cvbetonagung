<?php

namespace App\Traits;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tambahkan trait ini ke model yang mendukung pembatalan (cancel).
 *
 * Pastikan tabel memiliki kolom:
 *   - cancelled_by (uuid, FK ke users, nullable)
 *   - cancelled_at (timestamp, nullable)
 *   - cancel_reason (text, nullable)
 */
trait Cancellable
{
    // ── Scope ────────────────────────────────────────────────

    /**
     * Hanya tampilkan dokumen yang BUKAN cancelled.
     * Gunakan sebagai default di semua query index.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', '!=', 'cancelled');
    }

    /**
     * Hanya tampilkan dokumen yang sudah cancelled.
     */
    public function scopeCancelled(Builder $query): Builder
    {
        return $query->where('status', 'cancelled');
    }

    // ── State helpers ────────────────────────────────────────

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    // ── Relationship ─────────────────────────────────────────

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    // ── Fillable helper ──────────────────────────────────────

    /**
     * Daftar kolom yang perlu ditambahkan ke #[Fillable] model.
     * Cukup sebagai referensi — fillable diatur via #[Fillable] attribute di model.
     */
    public static function cancellableFields(): array
    {
        return ['cancelled_by', 'cancelled_at', 'cancel_reason'];
    }
}
