<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WaterReceipt extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['snapshot', 'payment_reference'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'payment_date' => 'datetime', 'verified_at' => 'datetime', 'generated_at' => 'datetime', 'snapshot' => 'encrypted:array', 'payment_reference' => 'encrypted'];
    }

    public function member()
    {
        return $this->belongsTo(WaterMember::class, 'water_member_id');
    }

    public function submission()
    {
        return $this->belongsTo(WaterSubmission::class, 'water_submission_id');
    }
}
