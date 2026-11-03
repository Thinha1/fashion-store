<?php

namespace Database\Seeders;

use App\Models\OrderItem;
use App\Models\Review;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Reviews on the demo order history, so the product pages and /admin/danh-gia
 * have something to show. Run it after OrderDemoSeeder:
 *   php artisan db:seed --class=ReviewDemoSeeder
 * Local/testing only and safe to re-run (it stops once demo reviews exist).
 * One delivered demo order line in four gets a review, written by the
 * customer who ordered it. Most are published, some wait for approval and
 * some are hidden, so every screen has a case to try.
 */
class ReviewDemoSeeder extends Seeder
{
    /** Review one delivered line in this many. */
    private const EVERY = 4;

    /** Statuses in turn: 7 in 10 published, 2 pending, 1 hidden. */
    private const STATUS_CYCLE = [
        Review::STATUS_PUBLISHED, Review::STATUS_PUBLISHED, Review::STATUS_PUBLISHED, Review::STATUS_PUBLISHED,
        Review::STATUS_PUBLISHED, Review::STATUS_PUBLISHED, Review::STATUS_PUBLISHED,
        Review::STATUS_PENDING, Review::STATUS_PENDING,
        Review::STATUS_HIDDEN,
    ];

    private const COMMENTS = [
        'good' => [
            'Chất vải mát, mặc rất thoải mái.',
            'Form chuẩn, đúng size như bảng size của shop.',
            'Giao nhanh, đóng gói cẩn thận. Sẽ ủng hộ tiếp.',
            'Màu ngoài đẹp hơn trong ảnh, rất ưng.',
            'Đường may chắc chắn, mặc đi làm hay đi chơi đều hợp.',
            'Mua tặng bạn, bạn rất thích.',
        ],
        'ok' => [
            'Sản phẩm tạm ổn, đúng mô tả.',
            'Mặc vừa, vải hơi mỏng hơn mình nghĩ.',
            'Giao hơi chậm nhưng hàng không có vấn đề.',
        ],
        'bad' => [
            'Size hơi chật so với bảng size, mong shop xem lại.',
            'Màu không giống ảnh lắm, hơi thất vọng.',
            'Vải nhăn nhiều sau lần giặt đầu tiên.',
        ],
    ];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('ReviewDemoSeeder chỉ chạy ở môi trường local.');

            return;
        }

        $demoOrder = fn ($order) => $order->where('order_number', 'like', 'DM%');

        if (Review::query()->whereHas('orderItem.order', $demoOrder)->exists()) {
            $this->command?->info('Đã có đánh giá demo, bỏ qua.');

            return;
        }

        $items = OrderItem::query()
            ->whereNotNull('product_variant_id')
            ->whereHas('order', fn ($order) => $demoOrder($order)->where('status', 'delivered'))
            ->with(['order:id,user_id,placed_at', 'productVariant:id,product_id'])
            ->orderBy('id')
            ->get()
            ->filter(fn (OrderItem $item, int $index): bool => $index % self::EVERY === 0)
            ->values();

        if ($items->isEmpty()) {
            $this->command?->warn('Chưa có đơn demo đã giao — chạy OrderDemoSeeder trước.');

            return;
        }

        DB::transaction(function () use ($items): void {
            foreach ($items as $position => $item) {
                $rating = $this->rating();
                $at = $this->reviewedAt($item->order->placed_at);

                Review::query()->forceCreate([
                    'product_id' => $item->productVariant->product_id,
                    'user_id' => $item->order->user_id,
                    'order_item_id' => $item->id,
                    'rating' => $rating,
                    // Every fifth review is just stars.
                    'comment' => $position % 5 === 4 ? null : $this->comment($rating),
                    'status' => self::STATUS_CYCLE[$position % count(self::STATUS_CYCLE)],
                    'created_at' => $at,
                    'updated_at' => $at,
                ]);
            }
        });

        $this->command?->info("Đã tạo {$items->count()} đánh giá demo.");
    }

    /** Mostly 4 and 5 stars, like real shops. */
    private function rating(): int
    {
        $roll = random_int(1, 100);

        return match (true) {
            $roll <= 45 => 5,
            $roll <= 75 => 4,
            $roll <= 89 => 3,
            $roll <= 96 => 2,
            default => 1,
        };
    }

    private function comment(int $rating): string
    {
        $pool = self::COMMENTS[match (true) {
            $rating >= 4 => 'good',
            $rating === 3 => 'ok',
            default => 'bad',
        }];

        return $pool[array_rand($pool)];
    }

    /** A few days after the order was placed, never in the future. */
    private function reviewedAt(\DateTimeInterface $placedAt): CarbonImmutable
    {
        $at = CarbonImmutable::instance($placedAt)->addDays(random_int(2, 10))->addMinutes(random_int(0, 600));

        return $at->isFuture() ? CarbonImmutable::now() : $at;
    }
}
