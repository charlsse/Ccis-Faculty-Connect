<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WaterSubmission extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['reference_number', 'reference_lookup', 'proof_path', 'faculty_note', 'internal_verification_note', 'rejection_reason'];

    protected function casts(): array
    {
        return ['submitted_amount' => 'decimal:2', 'paid_at' => 'datetime', 'submitted_at' => 'datetime', 'verified_at' => 'datetime', 'reference_number' => 'encrypted', 'faculty_note' => 'encrypted', 'internal_verification_note' => 'encrypted', 'rejection_reason' => 'encrypted'];
    }

    public function member()
    {
        return $this->belongsTo(WaterMember::class, 'water_member_id');
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
