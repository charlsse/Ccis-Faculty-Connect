<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WaterNotice extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime', 'context' => 'array', 'email_next_at' => 'datetime', 'email_delivered_at' => 'datetime'];
    }

    public function collection()
    {
        return $this->belongsTo(WaterCollection::class, 'water_collection_id');
    }
}
