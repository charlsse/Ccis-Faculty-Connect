<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WaterPaymentAccount extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['qr_image_path'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
