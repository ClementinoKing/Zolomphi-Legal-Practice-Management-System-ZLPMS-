<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $department_id
 * @property int|null $position_id
 * @property bool $must_change_password
 */
#[Fillable(['name', 'email', 'password', 'phone', 'department', 'department_id', 'position_id', 'status', 'avatar_path', 'notification_settings', 'session_timeout', 'must_change_password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

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
            'two_factor_confirmed_at' => 'datetime',
            'notification_settings' => 'array',
            'password_changed_at' => 'datetime',
            'must_change_password' => 'boolean',
        ];
    }

    /**
     * Roles assigned to the user.
     *
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
    }

    /**
     * The normalized department assigned to the user.
     *
     * @return BelongsTo<Department, $this>
     */
    public function assignedDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    /**
     * The normalized position assigned to the user.
     *
     * @return BelongsTo<Position, $this>
     */
    public function assignedPosition(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'position_id');
    }

    /**
     * @return HasOne<TimeTracker, $this>
     */
    public function timeTracker(): HasOne
    {
        return $this->hasOne(TimeTracker::class);
    }

    /**
     * Limit a query to active users in an active attorney position.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeAttorneys(Builder $query): Builder
    {
        return $query
            ->where('status', 'active')
            ->whereHas('assignedPosition', fn (Builder $position): Builder => $position
                ->where('is_active', true)
                ->where('is_attorney', true));
    }

    /**
     * Determine whether the user has a permission through any assigned role.
     */
    public function hasPermission(string $permission): bool
    {
        if ($this->relationLoaded('roles')
            && $this->roles->every(fn (Role $role): bool => $role->relationLoaded('assignedPermissions'))) {
            return $this->roles->contains(
                fn (Role $role): bool => $role->relationLoaded('assignedPermissions')
                    && $role->assignedPermissions->contains('key', $permission),
            );
        }

        return $this->roles()
            ->whereHas('assignedPermissions', fn ($query) => $query->where('key', $permission))
            ->exists();
    }

    /**
     * Determine whether the user has at least one of the supplied permissions.
     *
     * @param  list<string>  $permissions
     */
    public function hasAnyPermission(array $permissions): bool
    {
        if ($permissions === []) {
            return false;
        }

        if ($this->relationLoaded('roles')
            && $this->roles->every(fn (Role $role): bool => $role->relationLoaded('assignedPermissions'))) {
            return $this->roles->contains(
                fn (Role $role): bool => $role->relationLoaded('assignedPermissions')
                    && $role->assignedPermissions->whereIn('key', $permissions)->isNotEmpty(),
            );
        }

        return $this->roles()
            ->whereHas('assignedPermissions', fn ($query) => $query->whereIn('key', $permissions))
            ->exists();
    }
}
