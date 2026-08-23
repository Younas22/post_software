<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * Deliberately excludes `is_super_admin`, `client_id`, `business_id`,
     * and `branch_id` — these decide platform-wide privilege and tenant
     * identity, and must never be settable via mass assignment from request
     * input (no form should ever be able to set them, now or in the
     * future). Trusted server-side code (seeders, admin services) sets them
     * with forceFill() instead. See SECURITY.md / the Phase 9 audit.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user');
    }

    /**
     * Whether the user has been assigned the given role (by slug).
     */
    public function hasRole(string $slug): bool
    {
        return $this->roles->contains('slug', $slug);
    }

    /**
     * Whether the user may perform the given permission (by slug), across all
     * assigned roles. Super Admins implicitly hold every permission.
     */
    public function hasPermission(string $slug): bool
    {
        if ($this->is_super_admin) {
            return true;
        }

        return $this->roles
            ->loadMissing('permissions')
            ->pluck('permissions')
            ->flatten()
            ->contains('slug', $slug);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
