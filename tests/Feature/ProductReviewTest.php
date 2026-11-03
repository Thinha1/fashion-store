<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Review;
use App\Models\Role;
use App\Models\User;
use App\Support\ReviewerName;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Customers rate what they received; staff hide reviews that break the rules.
 */
#[Group('reviews')]
class ProductReviewTest extends TestCase
{
    use RefreshDatabase;

    private function orderItemFor(User $customer, string $orderStatus = 'delivered', ?Product $product = null): OrderItem
    {
        static $variantNumber = 0;
        $variant = ProductVariant::factory()->create(['color' => 'Màu '.++$variantNumber, 'product_id' => ($product ?? Product::factory()->create(['status' => 'active']))->id]);
        $order = Order::factory()->create(['user_id' => $customer->id, 'status' => $orderStatus]);

        return OrderItem::factory()->create(['order_id' => $order->id, 'product_variant_id' => $variant->id]);
    }

    private function staffWith(string ...$codes): User
    {
        $role = Role::factory()->create();
        $role->permissions()->attach(Permission::query()->whereIn('code', $codes)->pluck('id'));

        return User::factory()->create(['role_id' => $role->id]);
    }

    public function test_a_customer_reviews_a_delivered_order_line(): void
    {
        $customer = User::factory()->create();
        $item = $this->orderItemFor($customer);

        $this->actingAs($customer)
            ->post(route('reviews.store', [$item->order, $item]), ['rating' => 4, 'comment' => '  Vải mát, đúng size.  '])
            ->assertRedirect(route('orders.show', $item->order))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('reviews', [
            'order_item_id' => $item->id,
            'user_id' => $customer->id,
            'product_id' => $item->productVariant->product_id,
            'rating' => 4,
            'comment' => 'Vải mát, đúng size.',
            'status' => Review::STATUS_PENDING,
        ]);
    }

    public function test_the_comment_is_optional(): void
    {
        $customer = User::factory()->create();
        $item = $this->orderItemFor($customer);

        $this->actingAs($customer)->post(route('reviews.store', [$item->order, $item]), ['rating' => 5, 'comment' => '   '])
            ->assertSessionHasNoErrors();

        $this->assertNull(Review::query()->firstOrFail()->comment);
    }

    public function test_the_rating_must_be_one_to_five(): void
    {
        $customer = User::factory()->create();
        $item = $this->orderItemFor($customer);

        foreach ([null, 0, 6, 'abc'] as $rating) {
            $this->actingAs($customer)->post(route('reviews.store', [$item->order, $item]), ['rating' => $rating])
                ->assertSessionHasErrors('rating');
        }

        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_only_a_delivered_order_can_be_reviewed(): void
    {
        $customer = User::factory()->create();

        foreach (['pending', 'shipping', 'cancelled'] as $status) {
            $item = $this->orderItemFor($customer, $status);

            $this->actingAs($customer)->post(route('reviews.store', [$item->order, $item]), ['rating' => 5])
                ->assertRedirect(route('orders.show', $item->order))
                ->assertSessionHas('error');
        }

        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_an_order_line_is_reviewed_only_once(): void
    {
        $customer = User::factory()->create();
        $item = $this->orderItemFor($customer);

        $this->actingAs($customer)->post(route('reviews.store', [$item->order, $item]), ['rating' => 5]);
        $this->actingAs($customer)->post(route('reviews.store', [$item->order, $item]), ['rating' => 1])
            ->assertSessionHas('error');

        $this->assertSame(1, Review::query()->count());
        $this->assertSame(5, Review::query()->firstOrFail()->rating);
    }

    public function test_someone_else_cannot_review_the_order(): void
    {
        $item = $this->orderItemFor(User::factory()->create());

        $this->actingAs(User::factory()->create())
            ->post(route('reviews.store', [$item->order, $item]), ['rating' => 5])
            ->assertForbidden();

        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_an_item_of_another_order_is_not_found(): void
    {
        $customer = User::factory()->create();
        $item = $this->orderItemFor($customer);
        $otherOrder = Order::factory()->create(['user_id' => $customer->id, 'status' => 'delivered']);

        $this->actingAs($customer)->post(route('reviews.store', [$otherOrder, $item]), ['rating' => 5])->assertNotFound();
    }

    public function test_guests_are_sent_to_sign_in(): void
    {
        $item = $this->orderItemFor(User::factory()->create());

        $this->post(route('reviews.store', [$item->order, $item]), ['rating' => 5])->assertRedirect(route('login'));
    }

    public function test_the_order_page_offers_the_form_only_once_delivered(): void
    {
        $customer = User::factory()->create();
        $delivered = $this->orderItemFor($customer);
        $shipping = $this->orderItemFor($customer, 'shipping');

        $this->withoutExceptionHandling();
        $this->actingAs($customer)->get(route('orders.show', $delivered->order))->assertSee(route('reviews.index'));
        $this->actingAs($customer)->get(route('orders.show', $shipping->order))->assertDontSee(route('reviews.index'));

        Review::factory()->create(['order_item_id' => $delivered->id, 'user_id' => $customer->id, 'product_id' => $delivered->productVariant->product_id, 'comment' => 'Rất đẹp']);

        $this->actingAs($customer)->get(route('orders.show', $delivered->order))
            ->assertDontSee(route('reviews.index'))->assertSee('Bạn đã đánh giá sản phẩm này')->assertSee('Rất đẹp');
    }

    public function test_the_product_page_shows_published_reviews_with_the_average(): void
    {
        $product = Product::factory()->create(['status' => 'active']);
        $this->reviewFor($product, 5, 'Áo rất đẹp', 'Nguyễn Văn An');
        $this->reviewFor($product, 4, 'Ổn trong tầm giá');
        $this->reviewFor($product, 1, 'Nội dung vi phạm', status: Review::STATUS_HIDDEN);

        $this->get(route('products.show', $product))
            ->assertOk()
            ->assertSee('Áo rất đẹp')
            ->assertSee('Ổn trong tầm giá')
            ->assertDontSee('Nội dung vi phạm')
            ->assertSee('4,5')
            ->assertSee('2 đánh giá')
            ->assertSee('N*** An')
            ->assertDontSee('Nguyễn Văn An');
    }

    public function test_the_product_page_offers_the_form_for_a_delivered_unreviewed_purchase(): void
    {
        $product = Product::factory()->create(['status' => 'active']);
        $customer = User::factory()->create();
        $item = $this->orderItemFor($customer, 'delivered', $product);
        $this->orderItemFor($customer, 'shipping', $product);

        $this->actingAs($customer)->get(route('products.show', $product))
            ->assertSee('Bạn đã nhận sản phẩm này')
            ->assertSee(route('reviews.index'))
            ->assertSee('Đánh giá sản phẩm đã mua');

        $this->actingAs($customer)->post(route('reviews.store', [$item->order, $item]), ['rating' => 5])
            ->assertSessionHas('status');

        $this->actingAs($customer)->get(route('products.show', $product))->assertDontSee('Bạn đã nhận sản phẩm này');
    }

    public function test_the_product_page_tells_guests_and_non_buyers_how_to_review(): void
    {
        $product = Product::factory()->create(['status' => 'active']);

        $this->get(route('products.show', $product))->assertSee('Đăng nhập')->assertDontSee('Bạn đã nhận sản phẩm này');
        $this->actingAs(User::factory()->create())->get(route('products.show', $product))
            ->assertSee('sau khi nhận được sản phẩm này')->assertDontSee('Bạn đã nhận sản phẩm này');
    }

    public function test_reviewing_returns_to_the_page_that_sent_the_form(): void
    {
        $customer = User::factory()->create();
        $product = Product::factory()->create(['status' => 'active']);
        $item = $this->orderItemFor($customer, 'delivered', $product);

        $this->actingAs($customer)->from(route('products.show', $product))
            ->post(route('reviews.store', [$item->order, $item]), ['rating' => 4])
            ->assertRedirect(route('products.show', $product));
    }

    public function test_the_purchased_page_lists_delivered_unreviewed_lines_of_the_customer_only(): void
    {
        $customer = User::factory()->create();
        $delivered = $this->orderItemFor($customer);
        $shipping = $this->orderItemFor($customer, 'shipping');
        $reviewed = $this->orderItemFor($customer);
        Review::factory()->create(['order_item_id' => $reviewed->id, 'user_id' => $customer->id, 'product_id' => $reviewed->productVariant->product_id, 'comment' => 'Đã khen rồi']);
        $strangers = $this->orderItemFor(User::factory()->create());

        $response = $this->actingAs($customer)->get(route('reviews.index'))->assertOk();

        $response->assertSee(route('reviews.store', [$delivered->order, $delivered]), false)
            ->assertDontSee(route('reviews.store', [$shipping->order, $shipping]), false)
            ->assertDontSee(route('reviews.store', [$reviewed->order, $reviewed]), false)
            ->assertDontSee(route('reviews.store', [$strangers->order, $strangers]), false)
            ->assertSee('Chờ đánh giá (1)')
            ->assertSee('Đã đánh giá (1)');

        $this->actingAs($customer)->get(route('reviews.index', ['tab' => 'da-danh-gia']))
            ->assertOk()->assertSee('Đã khen rồi')->assertDontSee(route('reviews.store', [$delivered->order, $delivered]), false);
    }

    public function test_the_purchased_page_is_for_signed_in_customers(): void
    {
        $this->get(route('reviews.index'))->assertRedirect(route('login'));
    }

    public function test_a_product_without_reviews_says_so(): void
    {
        $product = Product::factory()->create(['status' => 'active']);

        $this->get(route('products.show', $product))->assertOk()->assertSee('chưa có đánh giá nào');
    }

    public function test_reviewer_names_are_masked(): void
    {
        $this->assertSame('N*** An', ReviewerName::mask('Nguyễn Văn An'));
        $this->assertSame('A***', ReviewerName::mask('An'));
        $this->assertSame('Khách hàng', ReviewerName::mask(null));
    }

    public function test_the_moderation_screen_needs_the_review_permission(): void
    {
        $this->actingAs($this->staffWith('admin.access', 'orders.view'))->get(route('admin.reviews.index'))->assertForbidden();
        $this->actingAs($this->staffWith('admin.access', 'reviews.manage'))->get(route('admin.reviews.index'))->assertOk();
    }

    public function test_the_moderation_list_filters_by_status_and_stars(): void
    {
        $product = Product::factory()->create(['name' => 'Áo Polo Xanh']);
        $this->reviewFor($product, 5, 'Năm sao ok');
        $this->reviewFor($product, 2, 'Hai sao tệ', status: Review::STATUS_HIDDEN);

        $staff = $this->staffWith('admin.access', 'reviews.manage');

        $this->actingAs($staff)->get(route('admin.reviews.index', ['trang-thai' => 'hidden']))
            ->assertSee('Hai sao tệ')->assertDontSee('Năm sao ok');
        $this->actingAs($staff)->get(route('admin.reviews.index', ['sao' => 5]))
            ->assertSee('Năm sao ok')->assertDontSee('Hai sao tệ');
        $this->actingAs($staff)->get(route('admin.reviews.index', ['q' => 'Polo']))
            ->assertSee('Năm sao ok')->assertSee('Hai sao tệ');
    }

    public function test_a_new_review_waits_for_approval_before_it_shows(): void
    {
        $customer = User::factory()->create();
        $product = Product::factory()->create(['status' => 'active']);
        $item = $this->orderItemFor($customer, 'delivered', $product);

        $this->actingAs($customer)->post(route('reviews.store', [$item->order, $item]), ['rating' => 5, 'comment' => 'Chờ shop duyệt nhé']);

        $this->get(route('products.show', $product))->assertDontSee('Chờ shop duyệt nhé')->assertSee('chưa có đánh giá nào');
        $this->actingAs($customer)->get(route('reviews.index', ['tab' => 'da-danh-gia']))->assertSee('Chờ duyệt');
        $this->actingAs($customer)->get(route('orders.show', $item->order))->assertSee('Đánh giá đang chờ cửa hàng duyệt');
    }

    public function test_staff_approve_a_pending_review_and_it_is_logged(): void
    {
        $review = $this->reviewFor(Product::factory()->create(['status' => 'active']), 4, 'Hàng chuẩn', status: Review::STATUS_PENDING);
        $staff = $this->staffWith('admin.access', 'reviews.manage');

        $this->actingAs($staff)->get(route('admin.reviews.index'))->assertSee('1 đánh giá đang chờ duyệt')->assertSee('Duyệt');

        $this->actingAs($staff)->patch(route('admin.reviews.update', $review), ['status' => 'published'])->assertSessionHas('status');

        $this->assertSame(Review::STATUS_PUBLISHED, $review->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'review.approved', 'subject_id' => $review->id, 'actor_id' => $staff->id]);
        $this->get(route('products.show', $review->product))->assertSee('Hàng chuẩn');
    }

    public function test_staff_can_hide_a_pending_review_without_approving_it(): void
    {
        $review = $this->reviewFor(Product::factory()->create(['status' => 'active']), 1, 'Spam quảng cáo', status: Review::STATUS_PENDING);

        $this->actingAs($this->staffWith('admin.access', 'reviews.manage'))
            ->patch(route('admin.reviews.update', $review), ['status' => 'hidden']);

        $this->assertSame(Review::STATUS_HIDDEN, $review->fresh()->status);
        $this->get(route('products.show', $review->product))->assertDontSee('Spam quảng cáo');
    }

    public function test_staff_hide_and_show_a_review_and_it_is_logged(): void
    {
        $review = $this->reviewFor(Product::factory()->create(['status' => 'active']), 3, 'Bình thường');
        $staff = $this->staffWith('admin.access', 'reviews.manage');

        $this->actingAs($staff)->patch(route('admin.reviews.update', $review), ['status' => 'hidden'])->assertSessionHas('status');
        $this->assertSame('hidden', $review->fresh()->status);
        $this->assertSame($staff->id, $review->fresh()->updated_by);
        $this->assertDatabaseHas('audit_logs', ['action' => 'review.hidden', 'subject_id' => $review->id, 'actor_id' => $staff->id]);

        $this->get(route('products.show', $review->product))->assertDontSee('Bình thường');

        $this->actingAs($staff)->patch(route('admin.reviews.update', $review), ['status' => 'published']);
        $this->assertSame('published', $review->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'review.published', 'subject_id' => $review->id]);
    }

    public function test_moderation_rejects_unknown_statuses_and_unauthorised_staff(): void
    {
        $review = $this->reviewFor(Product::factory()->create(), 3, 'x');

        $this->actingAs($this->staffWith('admin.access', 'reviews.manage'))
            ->patch(route('admin.reviews.update', $review), ['status' => 'pending'])->assertSessionHasErrors('status');

        $this->actingAs($this->staffWith('admin.access', 'orders.view'))
            ->patch(route('admin.reviews.update', $review), ['status' => 'hidden'])->assertForbidden();

        $this->assertSame('published', $review->fresh()->status);
    }

    private function reviewFor(Product $product, int $rating, string $comment, ?string $customerName = null, string $status = Review::STATUS_PUBLISHED): Review
    {
        $customer = User::factory()->create($customerName ? ['name' => $customerName] : []);
        $item = $this->orderItemFor($customer, 'delivered', $product);

        return Review::factory()->create([
            'product_id' => $product->id, 'user_id' => $customer->id, 'order_item_id' => $item->id,
            'rating' => $rating, 'comment' => $comment, 'status' => $status,
        ]);
    }
}
