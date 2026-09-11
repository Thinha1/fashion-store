<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabaseSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_contains_the_twenty_planned_domain_tables(): void
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
            'wishlists',
            'return_requests',
            'audit_logs',
        ];

        $this->assertCount(20, $tables);

        foreach ($tables as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing domain table: {$table}");
        }
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
}
