<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'code', 'description', 'created_by', 'updated_by'])]
class Role extends Model
{
    use HasFactory;

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Determine whether this role grants the given permission code.
     *
     * Full access ("Admin toàn quyền") is granted by attaching every row in
     * the permissions catalog to the role (see DatabaseSeeder /
     * RoleFactory::admin()) rather than a wildcard code, so a role's access
     * is always exactly what the `permission_role` pivot says.
     */
    public function hasPermission(string $code): bool
    {
        return $this->permissions->contains(fn (Permission $permission): bool => $permission->code === $code);
    }
}
