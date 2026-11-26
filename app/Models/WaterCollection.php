<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WaterCollection extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'due_date' => 'date', 'published_at' => 'datetime', 'closed_at' => 'datetime'];
    }

    public function account()
    {
        return $this->belongsTo(WaterPaymentAccount::class, 'payment_account_id');
    }

    public function members()
    {
        return $this->hasMany(WaterMember::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeVisibleTo($query, User $user)
    {
        if (! $user->hasPermission('payments.view')) {
            $query->where('status', '!=', 'draft')->whereHas('members', fn ($q) => $q->where('user_id', $user->id));
        }

        return $query;
    }
}
