<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_without_the_permission_attached_is_denied(): void
    {
        $role = Role::factory()->create();

        $this->assertFalse($role->hasPermission('products.manage'));
    }

    public function test_role_with_the_permission_attached_is_granted(): void
    {
        $role = Role::factory()->create();
        $permission = Permission::query()->where('code', 'products.manage')->firstOrFail();

        $role->permissions()->attach($permission);

        $this->assertTrue($role->hasPermission('products.manage'));
        $this->assertFalse($role->hasPermission('inventory.manage'));
    }

    public function test_admin_factory_role_has_every_catalog_permission(): void
    {
        $role = Role::factory()->admin()->create();

        foreach (Permission::query()->pluck('code') as $code) {
            $this->assertTrue($role->hasPermission($code), "Admin role should have {$code}");
        }
    }
}
