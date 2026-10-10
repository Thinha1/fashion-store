<?php

namespace Database\Seeders;

use App\Models\Discount;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * A few checkout coupons (scope=order) to try the checkout with until the
 * admin coupon screen exists. Codes are stored upper-case, which is how
 * PriceCalculator looks them up.
 */
class CouponDemoSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();

        $coupons = [
            'GIAM50K' => ['discount_type' => 'fixed', 'discount_value' => 50000, 'min_order_amount' => 300000],
            'SALE10' => ['discount_type' => 'percent', 'discount_value' => 10, 'max_discount_amount' => 100000],
            'MOTLAN' => ['discount_type' => 'fixed', 'discount_value' => 30000, 'usage_limit_per_customer' => 1],
        ];

        foreach ($coupons as $code => $rules) {
            Discount::query()->updateOrCreate(['code' => $code], array_merge([
                'product_variant_id' => null,
                'scope' => 'order',
                'max_discount_amount' => null,
                'min_order_amount' => null,
                'usage_limit' => null,
                'usage_limit_per_customer' => null,
                'starts_at' => now()->startOfDay(),
                'ends_at' => now()->addYear(),
                'is_active' => true,
                'created_by' => $admin->id,
            ], $rules));
        }
    }
}
