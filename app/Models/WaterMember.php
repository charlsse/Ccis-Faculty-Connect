<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WaterMember extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['waiver_reason'];

    protected function casts(): array
    {
        return ['assigned_amount' => 'decimal:2', 'assigned_at' => 'datetime', 'paid_at' => 'datetime', 'waived_at' => 'datetime', 'waiver_reason' => 'encrypted'];
    }

    public function collection()
    {
        return $this->belongsTo(WaterCollection::class, 'water_collection_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function submissions()
    {
        return $this->hasMany(WaterSubmission::class)->orderByDesc('attempt_number');
    }

    public function receipt()
    {
        return $this->hasOne(WaterReceipt::class);
    }

    public function latestSubmission()
    {
        return $this->hasOne(WaterSubmission::class)->latestOfMany('attempt_number');
    }

    public function getStatusLabelAttribute(): string
    {
        if (in_array($this->status, ['unpaid', 'rejected'], true) && ($attempt = \App\Services\Payments\WaterGatewayService::active($this))) {
            return $attempt->status === 'attention' ? 'Online payment needs attention' : 'Online payment processing';
        }
        if ($this->status === 'unpaid' && $this->collection->due_date->endOfDay()->isPast()) {
            return 'Overdue';
        }

        return ['unpaid' => 'Unpaid', 'pending' => 'Pending Verification', 'paid' => 'Paid / Verified', 'rejected' => 'Needs Attention', 'waived' => 'Waived'][$this->status];
    }
}
