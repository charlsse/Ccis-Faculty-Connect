<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use MustVerifyEmail;

    public const ROLE_SECRETARY = 'secretary';

    public const ROLE_TREASURER = 'treasurer';

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, config('roles.'.$this->role.'.permissions', []), true);
    }

    public function onboardingRoute(): ?string
    {
        if ($this->must_change_password || ($this->status === self::STATUS_PENDING && ! $this->password_replaced_at)) {
            return 'onboarding.password';
        }
        if (! $this->email_verified_at && ! $this->isAcademicRosterDemo()) {
            return 'verification.notice';
        }
        if (! $this->two_factor_confirmed_at) {
            return 'two-factor.setup';
        }

        return null;
    }

    public function isAcademicRosterDemo(): bool
    {
        // The marker is stored separately and cannot be supplied through user mass assignment.
        // Before the approved migration, existing authentication is unchanged.
        return $this->exists && $this->role === self::ROLE_FACULTY && $this->email === null
            && str_starts_with($this->faculty_id, 'CCIS-DEMO-2627-')
            && \Illuminate\Support\Facades\Schema::hasTable('academic_roster_accounts')
            && \Illuminate\Support\Facades\DB::table('academic_roster_accounts')->where('user_id', $this->id)->exists();
    }

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';

    public const ROLE_FACULTY = 'faculty';

    public const ROLE_STAFF = 'staff';

    public const STATUS_PENDING = 'pending_activation';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_DEACTIVATED = 'deactivated';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'faculty_id',
        'first_name',
        'middle_name',
        'last_name',
        'email',
        'department',
        'position',
        'contact_number',
        'bio',
        'role',
        'status',
        'profile_photo',
        'password',
        'pending_email',
        'pending_email_token_hash',
        'pending_email_expires_at',
        'invitation_token_hash',
        'invitation_expires_at',
        'activated_at',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
        'two_factor_last_used_step',
        'last_login_at',
        'security_version',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'contact_number', 'bio', 'pending_email',
        'password',
        'remember_token',
        'pending_email_token_hash',
        'invitation_token_hash',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'must_change_password' => 'boolean',
            'temporary_password_expires_at' => 'datetime',
            'password_replaced_at' => 'datetime',
            'invitation_sent_at' => 'datetime',
            'onboarding_completed_at' => 'datetime',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'pending_email_expires_at' => 'datetime',
            'invitation_expires_at' => 'datetime',
            'activated_at' => 'datetime',
            'contact_number' => 'encrypted',
            'bio' => 'encrypted',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'security_version' => 'integer',
        ];
    }

    public function getFullNameAttribute(): string
    {
        return trim(collect([
            $this->first_name,
            $this->middle_name,
            $this->last_name,
        ])->filter()->join(' '));
    }

    public function getInitialsAttribute(): string
    {
        return strtoupper(substr($this->first_name, 0, 1).substr($this->last_name, 0, 1));
    }

    public function getMaskedContactNumberAttribute(): string
    {
        $contact = (string) $this->contact_number;

        if ($contact === '') {
            return 'Not provided';
        }

        if (mb_strlen($contact) <= 7) {
            return str_repeat('•', max(0, mb_strlen($contact) - 2)).mb_substr($contact, -2);
        }

        return mb_substr($contact, 0, 4)
            .str_repeat('•', max(3, mb_strlen($contact) - 7))
            .mb_substr($contact, -3);
    }

    public function hasRole(string|array $roles): bool
    {
        return in_array($this->role, (array) $roles, true);
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(self::ROLE_ADMIN);
    }

    public function isFaculty(): bool
    {
        return $this->hasRole(self::ROLE_FACULTY);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function getRoleLabelAttribute(): string
    {
        return config('roles.'.$this->role.'.label', ucfirst($this->role));
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'subject_user_id');
    }

    public function trustedDevices(): HasMany
    {
        return $this->hasMany(TrustedDevice::class);
    }

    public function getProfilePhotoUrlAttribute(): string
    {
        return $this->profile_photo
            ? asset('storage/'.$this->profile_photo)
            : asset('images/default-profile.svg');
    }
}
