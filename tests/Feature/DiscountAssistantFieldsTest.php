<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\Ai\DiscountFormFields;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The assistant on the discount form: the same `set_fields` reply as on the
 * product form, read against the discount form's fields (screen "discount").
 */
class DiscountAssistantFieldsTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, mixed> What the pretend model answers next. */
    private array $reply = [];

    private bool $streaming = false;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        Http::fake(['internal-ai.example.test/*' => fn () => $this->streaming ? $this->streamedAnswer() : Http::response(['choices' => [['message' => ['content' => json_encode($this->reply)]]]])]);
        config([
            'services.ai.openai_compatible.endpoint' => 'https://internal-ai.example.test/v1',
            'services.ai.openai_compatible.key' => 'test-key',
        ]);
        $this->travelTo('2026-10-29 10:00:00');
    }

    /** @param  list<array<string, mixed>>  $setFields */
    private function ask(array $setFields, array $context = [], ?User $user = null, string $screen = 'discount'): TestResponse
    {
        $this->reply = $this->replyWith($setFields);

        return $this->actingAs($user ?? User::factory()->admin()->create())->postJson(route('admin.products.ai-assist'), [
            'messages' => [['role' => 'user', 'content' => [['type' => 'text', 'text' => 'freeship đơn từ 500k']]]],
            'screen' => $screen,
            'form_context' => array_merge(['mode' => 'create', 'kind' => 'order'], $context),
        ]);
    }

    /** The pretend model's answer as the server-sent events an OpenAI-compatible provider streams. */
    private function streamedAnswer(): PromiseInterface|Response
    {
        $json = json_encode($this->reply);
        $frames = array_map(
            fn (string $chunk) => 'data: '.json_encode(['choices' => [['delta' => ['content' => $chunk]]]]).'

',
            [substr($json, 0, 20), substr($json, 20)],
        );

        return Http::response(implode('', $frames).'data: [DONE]

', 200, ['Content-Type' => 'text/event-stream']);
    }

    /** @return array<string, mixed> */
    private function replyWith(array $setFields): array
    {
        return ['name' => '', 'description' => '', 'bullets' => [], 'seo_title' => '', 'price' => '', 'sizes' => [], 'colors' => [],
            'category' => '', 'brand' => '', 'variant_images' => [], 'set_fields' => $setFields];
    }

    private function sentPrompt(): string
    {
        $prompt = '';
        Http::assertSent(function (Request $request) use (&$prompt): bool {
            $prompt = json_encode($request->data(), JSON_UNESCAPED_UNICODE);

            return true;
        });

        return $prompt;
    }

    public function test_fields_come_back_in_the_forms_own_formats(): void
    {
        $response = $this->ask([
            ['field' => 'name', 'value' => 'Freeship đơn từ 500k'],
            ['field' => 'code', 'value' => ' freeship '],
            ['field' => 'is_auto_apply', 'value' => 'true'],
            ['field' => 'discount_type', 'value' => 'percent'],
            ['field' => 'discount_value', 'value' => 100],
            ['field' => 'min_order_amount', 'value' => '500k'],
            ['field' => 'max_discount_amount', 'value' => ''],
            ['field' => 'usage_limit', 'value' => '100'],
            ['field' => 'starts_at', 'value' => '2026-10-29 10:00'],
            ['field' => 'ends_at', 'value' => '2026-10-31'],
            ['field' => 'is_active', 'value' => true],
        ])->assertOk();

        $this->assertSame([
            ['field' => 'name', 'value' => 'Freeship đơn từ 500k'],
            ['field' => 'code', 'value' => 'FREESHIP'],
            ['field' => 'is_auto_apply', 'value' => true],
            ['field' => 'discount_type', 'value' => 'percent'],
            ['field' => 'discount_value', 'value' => 100],
            ['field' => 'min_order_amount', 'value' => 500000],
            ['field' => 'max_discount_amount', 'value' => ''],
            ['field' => 'usage_limit', 'value' => 100],
            ['field' => 'starts_at', 'value' => '2026-10-29T10:00'],
            ['field' => 'ends_at', 'value' => '2026-10-31T23:59'],
            ['field' => 'is_active', 'value' => true],
        ], $response->json('data.set_fields'));
        $response->assertJsonPath('notice', null);
    }

    public function test_anything_that_makes_no_sense_for_its_field_is_dropped(): void
    {
        $response = $this->ask([
            ['field' => 'discount_type', 'value' => 'free'],
            ['field' => 'discount_value', 'value' => '-5'],
            ['field' => 'discount_value', 'value' => 'nhiều'],
            ['field' => 'code', 'value' => 'GIẢM 50K'],
            ['field' => 'ends_at', 'value' => 'khi nào hết thì thôi'],
            ['field' => 'usage_limit', 'value' => '0'],
            ['field' => 'is_active', 'value' => 'có thể'],
            ['field' => 'price', 'value' => '350000'],
            ['field' => 'status', 'value' => 'archived'],
            'không phải mảng',
            ['field' => 'discount_value', 'value' => '20'],
        ])->assertOk();

        $this->assertSame([['field' => 'discount_value', 'value' => 20]], $response->json('data.set_fields'));
    }

    public function test_brand_and_product_names_become_ids_and_nothing_is_guessed(): void
    {
        $coolmate = Brand::factory()->create(['name' => 'Coolmate']);
        Brand::factory()->create(['name' => 'Levents']);
        $hoodie = Product::factory()->create(['name' => 'Áo hoodie basic']);
        Product::factory()->create(['name' => 'Áo thun basic']);
        Product::factory()->create(['name' => 'Áo thun cổ tròn']);

        $response = $this->ask([
            ['field' => 'brands', 'add' => ['coolmate', 'Không có hiệu này']],
            ['field' => 'products', 'value' => ['áo hoodie basic', 'áo thun', 'Quần không tồn tại']],
        ])->assertOk();

        $this->assertSame([
            ['field' => 'brands', 'add' => [['id' => $coolmate->id, 'name' => 'Coolmate']]],
            ['field' => 'products', 'value' => [['id' => $hoodie->id, 'name' => 'Áo hoodie basic']]],
        ], $response->json('data.set_fields'));
        $notice = $response->json('notice');
        $this->assertStringContainsString('Không có thương hiệu “Không có hiệu này”', $notice);
        $this->assertStringContainsString('Có nhiều sản phẩm khớp “áo thun” (Áo thun basic, Áo thun cổ tròn', $notice);
        $this->assertStringContainsString('Không tìm thấy sản phẩm “Quần không tồn tại”', $notice);
    }

    public function test_an_empty_list_still_clears_the_picks(): void
    {
        $response = $this->ask([['field' => 'brands', 'value' => []]])->assertOk();

        $this->assertSame([['field' => 'brands', 'value' => []]], $response->json('data.set_fields'));
    }

    public function test_fields_the_open_form_does_not_have_are_dropped_with_a_pointer_to_the_right_kind(): void
    {
        Brand::factory()->create(['name' => 'Coolmate']);

        $variant = $this->ask([
            ['field' => 'code', 'value' => 'GIAM20'],
            ['field' => 'discount_value', 'value' => 20],
            ['field' => 'brands', 'add' => ['Coolmate']],
        ], ['kind' => 'variant'])->assertOk();
        $this->assertSame(['discount_value', 'brands'], array_column($variant->json('data.set_fields'), 'field'));
        $this->assertStringContainsString('Form “Giảm theo biến thể” không có mục: mã voucher', $variant->json('notice'));

        $shipping = $this->ask([
            ['field' => 'applies_to', 'value' => 'selected'],
            ['field' => 'brands', 'add' => ['Coolmate']],
            ['field' => 'min_order_amount', 'value' => 500000],
        ], ['kind' => 'shipping'])->assertOk();
        $this->assertSame(['min_order_amount'], array_column($shipping->json('data.set_fields'), 'field'));
        $this->assertStringContainsString('phạm vi áp dụng, thương hiệu', $shipping->json('notice'));

        $editing = $this->ask([['field' => 'products', 'add' => ['x']]], ['kind' => 'variant', 'mode' => 'edit'])->assertOk();
        $this->assertSame([], $editing->json('data.set_fields'));
    }

    public function test_the_prompt_describes_the_discount_form_and_what_is_on_it(): void
    {
        $this->ask([], ['kind' => 'order', 'name' => 'Giảm Coolmate', 'discount_value' => '10', 'applies_to' => 'selected', 'brands' => ['Coolmate']])->assertOk();

        $prompt = $this->sentPrompt();
        $this->assertStringContainsString('FORM GIẢM GIÁ, KHÔNG PHẢI FORM SẢN PHẨM', $prompt);
        $this->assertStringContainsString('Bây giờ là 2026-10-29 10:00', $prompt);
        $this->assertStringContainsString('Loại: Voucher giảm đơn', $prompt);
        $this->assertStringContainsString('Tên ưu đãi: Giảm Coolmate', $prompt);
        $this->assertStringContainsString('Thương hiệu đã chọn: Coolmate', $prompt);
        $this->assertStringNotContainsString('Trạng thái HIỆN TẠI của form sản phẩm', $prompt);
    }

    public function test_the_product_screen_is_unchanged(): void
    {
        $this->ask([['field' => 'price', 'value' => '350000'], ['field' => 'discount_value', 'value' => 20]], ['kind' => 'order'], screen: 'product')->assertOk()
            ->assertJsonPath('data.set_fields', [['field' => 'price', 'value' => '350000']]);

        $this->assertStringNotContainsString('FORM GIẢM GIÁ', $this->sentPrompt());
    }

    public function test_the_discount_screen_needs_the_permission_to_manage_discounts(): void
    {
        $role = Role::factory()->create();
        $role->permissions()->attach(Permission::query()->whereIn('code', ['ai.assistant', 'admin.access'])->pluck('id'));
        $user = User::factory()->create(['role_id' => $role->id]);

        $this->ask([['field' => 'discount_value', 'value' => 20]], user: $user)->assertOk()->assertJsonPath('data.set_fields', []);

        $this->assertStringNotContainsString('FORM GIẢM GIÁ', $this->sentPrompt());
    }

    public function test_the_stream_endpoint_resolves_the_same_way(): void
    {
        $brand = Brand::factory()->create(['name' => 'Coolmate']);
        $this->reply = $this->replyWith([['field' => 'brands', 'add' => ['Coolmate']]]);
        $this->streaming = true;

        $body = $this->actingAs(User::factory()->admin()->create())->postJson(route('admin.products.ai-assist-stream'), [
            'messages' => [['role' => 'user', 'content' => [['type' => 'text', 'text' => 'thêm Coolmate']]]],
            'screen' => 'discount',
            'form_context' => ['mode' => 'create', 'kind' => 'variant'],
        ])->assertOk()->streamedContent();

        $this->assertStringContainsString('"id":'.$brand->id, $body);
    }

    public function test_a_malformed_discount_context_is_rejected(): void
    {
        $this->ask([], ['kind' => 'refund'])->assertUnprocessable();
        $this->ask([], ['applies_to' => 'some'])->assertUnprocessable();
        $this->ask([], ['brands' => array_fill(0, 101, 'x')])->assertUnprocessable();
    }

    public function test_the_whitelist_and_the_form_agree_on_what_each_kind_has(): void
    {
        $this->assertContains('brands', DiscountFormFields::fieldsFor('variant', false));
        $this->assertNotContains('brands', DiscountFormFields::fieldsFor('variant', true));
        $this->assertNotContains('code', DiscountFormFields::fieldsFor('variant', false));
        $this->assertContains('applies_to', DiscountFormFields::fieldsFor('order', false));
        $this->assertNotContains('applies_to', DiscountFormFields::fieldsFor('shipping', false));
        $this->assertEqualsCanonicalizing(array_keys(DiscountFormFields::LABELS), DiscountFormFields::fieldsFor('order', false), 'every field the assistant knows is on the order voucher form');
    }
}
