<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('system')]
class DatabaseSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_contains_the_nineteen_planned_domain_tables(): void
    {
        $tables = [
            'users',
            'addresses',
            'roles',
            'brands',
            'categories',
            'products',
            'product_images',
            'product_variants',
            'discounts',
            'suppliers',
            'goods_receipts',
            'goods_receipt_items',
            'carts',
            'cart_items',
            'orders',
            'order_items',
            'reviews',
            'return_requests',
            'audit_logs',
        ];

        $this->assertCount(19, $tables);

        foreach ($tables as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing domain table: {$table}");
        }
    }

    public function test_the_unused_wishlists_table_is_gone_and_the_migration_can_be_reversed(): void
    {
        $this->assertFalse(Schema::hasTable('wishlists'));

        $migration = require database_path('migrations/2026_11_03_000100_drop_unused_wishlists_table.php');
        $migration->down();
        $this->assertTrue(Schema::hasTable('wishlists'));
        $this->assertTrue(Schema::hasColumns('wishlists', ['id', 'user_id', 'product_id', 'created_at']));

        $migration->up();
        $this->assertFalse(Schema::hasTable('wishlists'));
    }

    public function test_shipping_uses_configuration_instead_of_a_table(): void
    {
        $this->assertFalse(Schema::hasTable('shipping'));
        $this->assertFalse(Schema::hasTable('shipments'));
        $this->assertSame(30000, config('store.shipping_fee'));
    }

    public function test_admin_managed_catalog_tables_track_creator_and_editor(): void
    {
        $tables = [
            'roles', 'brands', 'categories', 'products', 'product_variants',
            'product_images', 'discounts', 'suppliers',
        ];

        foreach ($tables as $table) {
            $this->assertTrue(Schema::hasColumn($table, 'created_by'), "Missing created_by on {$table}");
            $this->assertTrue(Schema::hasColumn($table, 'updated_by'), "Missing updated_by on {$table}");
        }
    }

    public function test_customer_originated_tables_only_track_the_staff_editor(): void
    {
        foreach (['goods_receipts', 'orders', 'reviews'] as $table) {
            $this->assertTrue(Schema::hasColumn($table, 'updated_by'), "Missing updated_by on {$table}");
        }
    }

    public function test_permissions_are_a_relational_catalog_instead_of_json(): void
    {
        // permissions/permission_role are auth infrastructure (like roles was
        // before it grew its own migration) — not counted in the 20 domain
        // tables above, same as sessions/cache/jobs.
        $this->assertTrue(Schema::hasTable('permissions'));
        $this->assertTrue(Schema::hasTable('permission_role'));
        $this->assertFalse(Schema::hasColumn('roles', 'permissions'), 'roles.permissions JSON column should be dropped');
    }
}
