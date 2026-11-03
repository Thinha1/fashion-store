<?php

namespace Tests\Feature;

use App\Models\OrderItem;
use App\Models\ProductVariant;
use App\Models\Review;
use App\Models\Role;
use Database\Seeders\OrderDemoSeeder;
use Database\Seeders\ReviewDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    /** Demo order history, as `db:seed --class=OrderDemoSeeder` builds it. */
    private function demoOrders(): void
    {
        Role::factory()->create(['code' => 'customer']);
        ProductVariant::factory()->count(6)->create(['stock_quantity' => 10]);

        $this->seed(OrderDemoSeeder::class);
    }

    public function test_it_reviews_delivered_demo_order_lines_once_each_as_their_own_customer(): void
    {
        $this->demoOrders();

        $this->seed(ReviewDemoSeeder::class);

        $reviews = Review::query()->with('orderItem.order', 'orderItem.productVariant')->get();
        $this->assertGreaterThan(20, $reviews->count());
        $this->assertSame($reviews->count(), $reviews->pluck('order_item_id')->unique()->count(), 'one review per order line');
        foreach ($reviews as $review) {
            $item = $review->orderItem;
            $this->assertSame('delivered', $item->order->status);
            $this->assertStringStartsWith('DM', $item->order->order_number);
            $this->assertSame($item->order->user_id, $review->user_id);
            $this->assertSame($item->productVariant->product_id, $review->product_id);
            $this->assertGreaterThanOrEqual(Review::MIN_RATING, $review->rating);
            $this->assertLessThanOrEqual(Review::MAX_RATING, $review->rating);
            $this->assertGreaterThanOrEqual($item->order->placed_at->timestamp, $review->created_at->timestamp, 'a review comes after the order');
            $this->assertFalse($review->created_at->isFuture());
        }
    }

    public function test_it_leaves_reviews_in_every_status_and_most_of_them_on_show(): void
    {
        $this->demoOrders();

        $this->seed(ReviewDemoSeeder::class);

        $byStatus = Review::query()->get()->countBy('status');
        foreach ([Review::STATUS_PUBLISHED, Review::STATUS_PENDING, Review::STATUS_HIDDEN] as $status) {
            $this->assertGreaterThan(0, $byStatus[$status] ?? 0, "no {$status} review to try the screens with");
        }
        $this->assertGreaterThan($byStatus->sum() / 2, $byStatus[Review::STATUS_PUBLISHED]);
    }

    public function test_it_only_reviews_a_share_of_the_delivered_lines_and_some_without_a_comment(): void
    {
        $this->demoOrders();
        $delivered = OrderItem::query()->whereHas('order', fn ($order) => $order->where('status', 'delivered'))->count();

        $this->seed(ReviewDemoSeeder::class);

        $this->assertLessThan($delivered, Review::query()->count());
        $this->assertGreaterThan(0, Review::query()->whereNull('comment')->count());
        $this->assertGreaterThan(0, Review::query()->whereNotNull('comment')->count());
    }

    public function test_it_runs_only_once_and_leaves_real_reviews_alone(): void
    {
        $this->demoOrders();
        $real = Review::factory()->create(['comment' => 'Đánh giá thật của khách']);

        $this->seed(ReviewDemoSeeder::class);
        $count = Review::query()->count();
        $this->seed(ReviewDemoSeeder::class);

        $this->assertSame($count, Review::query()->count());
        $this->assertSame('Đánh giá thật của khách', $real->fresh()->comment);
    }

    public function test_it_does_nothing_without_delivered_demo_orders(): void
    {
        $this->seed(ReviewDemoSeeder::class);

        $this->assertSame(0, Review::query()->count());
    }

    public function test_it_refuses_to_run_outside_local_and_testing(): void
    {
        $this->demoOrders();
        $this->app->detectEnvironment(fn () => 'production');

        // Straight to the seeder: `db:seed` itself would ask to confirm in production.
        $this->app->make(ReviewDemoSeeder::class)->run();

        $this->assertSame(0, Review::query()->count());
    }
}
