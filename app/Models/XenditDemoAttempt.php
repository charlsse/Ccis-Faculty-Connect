<?php
namespace App\Models;

class XenditDemoAttempt extends WaterGatewayAttempt
{
    protected $connection = 'xendit_demo';
    protected $table = 'demo_attempts';
    protected function casts(): array
    {
        return parent::casts() + ['user_id' => 'integer', 'water_member_id' => 'integer', 'paid_at' => 'datetime'];
    }
    public function getReceiptNumberAttribute(): string { return 'DEMO-'.strtoupper($this->reference); }
}
