<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * ~120 days of believable order history so the admin dashboard has
 * something to show in a local demo. Run it explicitly:
 *   php artisan db:seed --class=OrderDemoSeeder
 * Local/testing only, safe to re-run (it stops if demo orders exist), and it
 * never touches stock — these are history, not live orders. Demo order
 * numbers start with "DM" so they're easy to tell apart (and SePay never
 * matches them).
 */
class OrderDemoSeeder extends Seeder
{
    private const DAYS = 120;

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('OrderDemoSeeder chỉ chạy ở môi trường local.');

            return;
        }

        if (Order::query()->where('order_number', 'like', 'DM%')->exists()) {
            $this->command?->info('Đã có đơn demo (mã DM...), bỏ qua.');

            return;
        }

        $variants = ProductVariant::query()
            ->where('is_active', true)
            ->whereHas('product', fn ($product) => $product->where('status', 'active'))
            ->with('product:id,name,base_price')
            ->get();

        if ($variants->isEmpty()) {
            $this->command?->warn('Chưa có sản phẩm nào để tạo đơn demo — chạy CatalogDemoSeeder trước.');

            return;
        }

        $customers = $this->customers();
        $today = CarbonImmutable::now()->startOfDay();

        DB::transaction(function () use ($variants, $customers, $today): void {
            for ($daysAgo = self::DAYS - 1; $daysAgo >= 0; $daysAgo--) {
                $day = $today->subDays($daysAgo);
                // Busier weekends and a gentle upward trend towards today.
                $base = 2 + (int) round((self::DAYS - $daysAgo) / 40) + ($day->isWeekend() ? 2 : 0);

                for ($count = random_int(max(0, $base - 2), $base + 2); $count > 0; $count--) {
                    $placedAt = $day->setTime(random_int(8, 22), random_int(0, 59));

                    if ($placedAt->isFuture()) {
                        continue;
                    }

                    $this->createOrder($customers[array_rand($customers)], $variants, $placedAt, $daysAgo);
                }
            }
        });
    }

    /**
     * @return list<User>
     */
    private function customers(): array
    {
        $roleId = Role::query()->where('code', 'customer')->value('id');
        $names = ['Nguyễn Minh Anh', 'Trần Quốc Bảo', 'Lê Thu Hà', 'Phạm Gia Huy', 'Võ Ngọc Lan', 'Đặng Khánh Linh', 'Bùi Đức Long', 'Hoàng Mai Phương'];

        return collect($names)->map(fn (string $name, int $index): User => User::query()->firstOrCreate(
            ['email' => 'khachdemo'.($index + 1).'@example.com'],
            ['role_id' => $roleId, 'name' => $name, 'password' => Hash::make('password'), 'email_verified_at' => now()],
        ))->all();
    }

    /**
     * @param  Collection<int, ProductVariant>  $variants
     */
    private function createOrder(User $customer, $variants, CarbonImmutable $placedAt, int $daysAgo): void
    {
        [$status, $paymentMethod, $paymentStatus] = $this->outcome($daysAgo);
        $lines = $variants->random(min($variants->count(), random_int(1, 3)))->map(function (ProductVariant $variant): array {
            $unitPrice = (int) round((float) ($variant->price ?? $variant->product->base_price));
            $quantity = random_int(1, 10) > 8 ? 2 : 1;

            return ['variant' => $variant, 'unit' => $unitPrice, 'quantity' => $quantity, 'total' => $unitPrice * $quantity];
        });
        $subtotal = $lines->sum('total');
        $shippingFee = (int) config('store.shipping_fee');

        $order = Order::query()->create([
            'order_number' => 'DM'.$placedAt->format('ymd').Str::upper(Str::random(6)),
            'user_id' => $customer->id,
            'status' => $status,
            'status_history' => array_values(array_filter([
                ['from' => null, 'to' => 'pending', 'actor_id' => $customer->id, 'note' => 'Khách đặt hàng', 'at' => $placedAt->toIso8601String()],
                $status === 'pending' ? null : ['from' => 'pending', 'to' => $status, 'actor_id' => null, 'note' => 'Dữ liệu demo', 'at' => $placedAt->addHours(6)->toIso8601String()],
            ])),
            'payment_method' => $paymentMethod,
            'payment_status' => $paymentStatus,
            'customer_name' => $customer->name,
            'customer_email' => $customer->email,
            'customer_phone' => '09'.random_int(10000000, 99999999),
            'province_name' => 'TP. Hồ Chí Minh',
            'district_name' => ['Quận 1', 'Quận 3', 'Quận 7', 'Quận Bình Thạnh', 'TP. Thủ Đức'][random_int(0, 4)],
            'ward_name' => 'Phường '.random_int(1, 15),
            'shipping_address' => random_int(1, 250).' Đường demo',
            'subtotal' => $subtotal,
            'discount_amount' => 0,
            'shipping_fee' => $shippingFee,
            'grand_total' => $subtotal + $shippingFee,
            'placed_at' => $placedAt,
            'payment_reviewed_at' => $paymentStatus === 'paid' ? $placedAt->addHours(2) : null,
        ]);

        foreach ($lines as $line) {
            $order->items()->create([
                'product_variant_id' => $line['variant']->id,
                'product_name' => $line['variant']->product->name,
                'sku' => $line['variant']->sku,
                'size_name' => $line['variant']->size,
                'color_name' => $line['variant']->color,
                'original_unit_price' => $line['unit'],
                'discount_amount' => 0,
                'unit_price' => $line['unit'],
                'quantity' => $line['quantity'],
                'line_total' => $line['total'],
            ]);
        }
    }

    /**
     * Older orders have mostly finished; recent ones are still moving.
     *
     * @return array{0: string, 1: string, 2: string}
     */
    private function outcome(int $daysAgo): array
    {
        $roll = random_int(1, 100);
        $status = match (true) {
            $daysAgo > 10 => $roll <= 86 ? 'delivered' : ($roll <= 95 ? 'cancelled' : 'returned'),
            $daysAgo > 3 => $roll <= 55 ? 'delivered' : ($roll <= 80 ? 'shipping' : ($roll <= 92 ? 'preparing' : 'cancelled')),
            default => $roll <= 45 ? 'pending' : ($roll <= 75 ? 'confirmed' : ($roll <= 92 ? 'preparing' : 'cancelled')),
        };
        $paymentMethod = random_int(1, 100) <= 60 ? 'cod' : 'bank_transfer';
        $paymentStatus = match (true) {
            $status === 'returned' => 'refunded',
            $status === 'cancelled' => 'unpaid',
            $status === 'delivered' => 'paid',
            $paymentMethod === 'bank_transfer' => 'paid',
            default => 'unpaid',
        };

        return [$status, $paymentMethod, $paymentStatus];
    }
}
