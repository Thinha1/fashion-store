<?php

namespace Database\Seeders;

use App\Models\Discount;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Demo vouchers to try the checkout with: order vouchers the customer types,
 * a shipping-fee code and an automatic free-shipping offer. Codes are stored
 * upper-case, which is how PriceCalculator looks them up.
 */
class CouponDemoSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();

        $vouchers = [
            ['code' => 'GIAM50K', 'name' => 'Giảm 50.000 ₫ cho đơn từ 300.000 ₫', 'discount_type' => 'fixed', 'discount_value' => 50000, 'min_order_amount' => 300000],
            ['code' => 'SALE10', 'name' => 'Giảm 10%, tối đa 100.000 ₫', 'discount_type' => 'percent', 'discount_value' => 10, 'max_discount_amount' => 100000],
            ['code' => 'MOTLAN', 'name' => 'Giảm 30.000 ₫, mỗi khách một lần', 'discount_type' => 'fixed', 'discount_value' => 30000, 'usage_limit_per_customer' => 1],
            ['code' => 'SHIP50', 'name' => 'Giảm 50% phí vận chuyển', 'scope' => 'shipping', 'discount_type' => 'percent', 'discount_value' => 50],
            ['code' => null, 'name' => 'Miễn phí vận chuyển cho đơn từ 500.000 ₫', 'scope' => 'shipping', 'discount_type' => 'percent', 'discount_value' => 100, 'min_order_amount' => 500000, 'is_auto_apply' => true],
        ];

        foreach ($vouchers as $voucher) {
            // A code identifies a typed voucher; an automatic one without a code is found by its name.
            $key = $voucher['code'] !== null ? ['code' => $voucher['code']] : ['code' => null, 'name' => $voucher['name']];

            Discount::query()->updateOrCreate($key, array_merge([
                'product_variant_id' => null,
                'scope' => 'order',
                'max_discount_amount' => null,
                'min_order_amount' => null,
                'usage_limit' => null,
                'usage_limit_per_customer' => null,
                'starts_at' => now()->startOfDay(),
                'ends_at' => now()->addYear(),
                'is_active' => true,
                'is_auto_apply' => false,
                'created_by' => $admin->id,
            ], $voucher));
        }
    }
}
