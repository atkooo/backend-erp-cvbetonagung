<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['role_id', 'name', 'email', 'password', 'status', 'last_login_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasUuids, Notifiable;

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }

    public function handledStockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'handled_by');
    }

    public function startedStockOpnameSessions(): HasMany
    {
        return $this->hasMany(StockOpnameSession::class, 'started_by');
    }

    public function requestedApprovals(): HasMany
    {
        return $this->hasMany(ApprovalRequest::class, 'requester_id');
    }

    public function assignedApprovals(): HasMany
    {
        return $this->hasMany(ApprovalRequest::class, 'approver_id');
    }

    public function createdQuotations(): HasMany
    {
        return $this->hasMany(Quotation::class, 'created_by');
    }

    public function createdProjectTimelines(): HasMany
    {
        return $this->hasMany(ProjectTimeline::class, 'created_by');
    }

    public function uploadedProjectDocuments(): HasMany
    {
        return $this->hasMany(ProjectDocument::class, 'uploaded_by');
    }

    public function verifiedPayments(): HasMany
    {
        return $this->hasMany(Payment::class, 'verified_by');
    }

    public function createdProductReturns(): HasMany
    {
        return $this->hasMany(ProductReturn::class, 'created_by');
    }

    public function verifiedProductionWorkLogs(): HasMany
    {
        return $this->hasMany(ProductionWorkLog::class, 'verified_by');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class, 'assigned_to');
    }

    public function documentExports(): HasMany
    {
        return $this->hasMany(DocumentExport::class, 'exported_by');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
