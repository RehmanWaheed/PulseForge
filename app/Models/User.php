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
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password'])]
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
