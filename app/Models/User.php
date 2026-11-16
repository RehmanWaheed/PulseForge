<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\TenantRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $password
 * @property string|null $google_id
 * @property string|null $avatar
 * @property bool $is_platform_admin
 * @property Carbon|null $email_verified_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'google_id', 'avatar', 'is_platform_admin'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

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
            'is_platform_admin' => 'boolean',
        ];
    }

    /**
     * Tenants that the user belongs to.
     *
     * @return BelongsToMany<Tenant, $this>
     */
    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'tenant_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Check if user is a member of the given tenant workspace.
     */
    public function belongsToTenant(Tenant $tenant): bool
    {
        return $this->tenants()->where('tenants.id', $tenant->id)->exists();
    }

    /**
     * Retrieve the user's role in the given tenant workspace.
     */
    public function roleForTenant(Tenant $tenant): ?TenantRole
    {
        /** @var Tenant|null $workspace */
        $workspace = $this->tenants()->where('tenants.id', $tenant->id)->first();

        if ($workspace === null || ! isset($workspace->pivot->role)) {
            return null;
        }

        return TenantRole::tryFrom((string) $workspace->pivot->role);
    }
}
