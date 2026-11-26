<?php

namespace App\Models;

use App\Services\DataProtectionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'recorded_by',
        'reference_no',
        'description',
        'amount',
        'status',
        'due_date',
        'paid_at',
        'notes',
    ];

    protected $hidden = [
        'reference_no', 'notes',
        'reference_no_lookup',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'reference_no' => 'encrypted',
            'notes' => 'encrypted',
            'due_date' => 'date',
            'paid_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Payment $payment): void {
            if ($payment->reference_no !== null && $payment->reference_no !== '') {
                $payment->reference_no_lookup = app(DataProtectionService::class)
                    ->paymentReferenceLookup($payment->reference_no);
            }
        });
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function scopeOrderedForPortal(Builder $query): Builder
    {
        return $query
            ->orderByRaw("case when status = 'pending' then 0 when status = 'paid' then 1 else 2 end")
            ->orderByDesc('paid_at')
            ->orderByDesc('created_at');
    }

    public function scopeWhereReference(Builder $query, string $reference): Builder
    {
        return $query->where(
            'reference_no_lookup',
            app(DataProtectionService::class)->paymentReferenceLookup($reference),
        );
    }

    public function getStatusLabelAttribute(): string
    {
        return ucfirst($this->status);
    }
}
