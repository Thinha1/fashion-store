<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['role_id', 'name', 'email', 'phone', 'status', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Status of the accounts the system acts as (see App\Mcp\McpActor): they
     * hold their role's permissions but nobody logs in as them, so they are
     * not listed as staff and do not count as a remaining permission manager.
     */
    public const STATUS_SYSTEM = 'system';

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

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    public function carts(): HasMany
    {
        return $this->hasMany(Cart::class);
    }

    /**
     * The cart the customer is filling right now. Checked-out carts stay
     * behind as `converted` for history; the next add-to-cart starts a new one.
     */
    public function activeCart(): HasOne
    {
        return $this->hasOne(Cart::class)->ofMany(['id' => 'max'], fn (Builder $query) => $query->where('status', 'active'));
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function wishlists(): HasMany
    {
        return $this->hasMany(Wishlist::class);
    }

    public function returnRequests(): HasMany
    {
        return $this->hasMany(ReturnRequest::class);
    }

    /**
     * Whether the user's role grants the given permission code. A locked
     * account (status "locked", set at /admin/nhan-vien) keeps its role but
     * holds no permission until it is unlocked.
     */
    public function hasPermission(string $code): bool
    {
        return $this->isActive() && $this->role !== null && $this->role->hasPermission($code);
    }

    public function isActive(): bool
    {
        return $this->status !== 'locked';
    }
}
