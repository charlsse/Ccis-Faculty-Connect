<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WaterGatewayAttempt extends Model
{
    protected $guarded = ['id'];
    protected $hidden = ['checkout_url', 'provider_session_id', 'provider_payment_id'];

    protected function casts(): array
    {
        return ['amount_minor' => 'integer', 'checkout_url' => 'encrypted', 'last_checked_at' => 'datetime', 'confirmed_at' => 'datetime'];
    }

    public function member() { return $this->belongsTo(WaterMember::class, 'water_member_id'); }
    public function submission() { return $this->belongsTo(WaterSubmission::class, 'water_submission_id'); }
    public function getRouteKeyName(): string { return 'reference'; }
    public function getDisplayReferenceAttribute(): string { return 'FC-WATER-'.$this->reference; }
}
