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
}
