<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Announcement extends Model
{
    use HasFactory;

    protected $fillable = [
        'created_by',
        'title',
        'category',
        'audience',
        'priority',
        'body',
        'image_path',
        'is_pinned',
        'published_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'is_pinned' => 'boolean',
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->where(function (Builder $query): void {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>=', now());
            });
    }

    public function scopeOrderedForPortal(Builder $query): Builder
    {
        return $query
            ->orderByDesc('is_pinned')
            ->orderByDesc('published_at')
            ->orderByDesc('created_at');
    }

    public static function audiences(): array
    {
        return ['All Faculty', 'All Members', 'Faculty', 'Administrators', 'Secretaries', 'Treasurers', 'Staff'];
    }

    public function scopeForAudience(Builder $query, User $user): Builder
    {
        $role = ['admin' => 'Administrators', 'secretary' => 'Secretaries', 'treasurer' => 'Treasurers', 'faculty' => 'Faculty', 'staff' => 'Staff'][$user->role] ?? '';
        // All Faculty is the existing portal-wide default. Unknown legacy labels
        // are visible to editors only until an editor selects a defined audience.
        return $query->whereIn('audience', ['All Faculty', 'All Members', $role]);
    }

    public function getPriorityLabelAttribute(): string
    {
        return ucfirst($this->priority);
    }
}
