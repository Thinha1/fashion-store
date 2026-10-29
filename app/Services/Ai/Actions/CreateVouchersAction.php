<?php

namespace App\Services\Ai\Actions;

use App\Http\Requests\Admin\StoreDiscountRequest;
use App\Models\Discount;
use App\Models\User;
use App\Services\Ai\Actions\Concerns\DescribesAmounts;
use Illuminate\Support\Facades\DB;

/**
 * "Freeship cho đơn từ 500k cuối tuần này, tự động áp dụng" → one or more
 * order or shipping vouchers, each checked by the discount form's own rules.
 */
class CreateVouchersAction implements AgentAction
{
    use DescribesAmounts;

    private const MAX_VOUCHERS = 10;

    public function __construct(private readonly FormRequestRunner $forms) {}

    public function type(): string
    {
        return 'create_vouchers';
    }

    public function permission(): string
    {
        return 'discounts.manage';
    }

    public function promptSpec(): string
    {
        return <<<'SPEC'
            - "create_vouchers": tạo voucher (tối đa 10 cái một lần), ví dụ "tạo freeship cho đơn từ 500k cuối tuần này",
              "làm 3 mã giảm 10%, 20%, 30% cho 11/11". Dạng:
              {"type": "create_vouchers", "vouchers": [{
                "scope": "order" (giảm tiền hàng) | "shipping" (giảm phí vận chuyển; "freeship" là shipping 100%),
                "name": "tên ngắn mô tả ưu đãi, ví dụ \"Freeship đơn từ 500.000 ₫\"",
                "code": "MÃ VIẾT HOA không dấu, chỉ chữ số - _; để \"\" nếu nhân viên muốn tự động áp dụng và không nói mã",
                "is_auto_apply": true nếu nhân viên nói tự động áp dụng / không cần nhập mã, ngược lại false,
                "discount_type": "percent" | "fixed",
                "discount_value": số (phần trăm hoặc số tiền VND),
                "max_discount_amount": số tiền giảm tối đa hoặc null,
                "min_order_amount": giá trị đơn tối thiểu hoặc null,
                "usage_limit": tổng số lượt dùng hoặc null,
                "usage_limit_per_customer": số lượt mỗi khách hoặc null,
                "starts_at": "YYYY-MM-DD HH:MM" (mặc định bây giờ nếu nhân viên không nói),
                "ends_at": "YYYY-MM-DD HH:MM" (bắt buộc; hỏi lại bằng cách vẫn đề xuất, hệ thống sẽ báo thiếu)
              }]}
            SPEC;
    }

    public function preview(array $params, User $actor): ActionPreview
    {
        $vouchers = is_array($params['vouchers'] ?? null) ? array_values($params['vouchers']) : [];

        if ($vouchers === []) {
            throw new InvalidActionException('Chưa có voucher nào để tạo.');
        }
        if (count($vouchers) > self::MAX_VOUCHERS) {
            throw new InvalidActionException('Mỗi lần chỉ tạo tối đa '.self::MAX_VOUCHERS.' voucher.');
        }

        $rows = [];
        $codes = [];
        foreach ($vouchers as $index => $voucher) {
            $label = count($vouchers) > 1 ? 'Voucher '.($index + 1).': ' : '';
            try {
                $data = $this->formData(is_array($voucher) ? $voucher : []);
                $this->forms->validate(StoreDiscountRequest::class, $data, $actor);
            } catch (InvalidActionException $exception) {
                throw new InvalidActionException($label.$exception->getMessage());
            }

            $code = $data['code'] !== '' ? mb_strtoupper($data['code']) : null;
            if ($code !== null && isset($codes[$code])) {
                throw new InvalidActionException("{$label}mã {$code} bị trùng với một voucher khác trong cùng lần tạo.");
            }
            $codes[$code ?? ''] = true;
            $rows[] = $data;
        }

        return new ActionPreview(
            title: count($rows) > 1 ? 'Tạo '.count($rows).' voucher' : 'Tạo voucher',
            lines: array_map($this->describe(...), $rows),
            confirmLabel: count($rows) > 1 ? 'Tạo '.count($rows).' voucher' : 'Tạo voucher',
            params: ['vouchers' => $rows],
        );
    }

    public function execute(array $params, User $actor): ActionResult
    {
        // Validated again: a code may have been taken since the preview.
        $requests = array_map(
            fn (array $data) => $this->forms->validate(StoreDiscountRequest::class, $data, $actor),
            $params['vouchers'] ?? [],
        );

        $created = DB::transaction(fn () => array_map(
            fn (StoreDiscountRequest $request) => Discount::query()->create($request->discountData()),
            $requests,
        ));

        return new ActionResult(
            message: 'Đã tạo '.count($created).' voucher.',
            links: array_map(fn (Discount $voucher) => [
                'label' => $voucher->voucherLabel(),
                'url' => route('admin.discounts.edit', $voucher),
            ], $created),
        );
    }

    /**
     * The discount form's fields from the model's loose values.
     *
     * @param  array<string, mixed>  $voucher
     * @return array<string, mixed>
     */
    private function formData(array $voucher): array
    {
        $scope = ($voucher['scope'] ?? '') === 'shipping' ? 'shipping' : 'order';
        $type = ($voucher['discount_type'] ?? '') === 'fixed' ? 'fixed' : 'percent';
        $whole = fn (string $key) => ($number = $this->number($voucher[$key] ?? null)) === null ? null : (int) round($number);

        return [
            'scope' => $scope,
            'name' => is_string($voucher['name'] ?? null) ? trim($voucher['name']) : '',
            'code' => is_string($voucher['code'] ?? null) ? trim($voucher['code']) : '',
            'is_auto_apply' => filter_var($voucher['is_auto_apply'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 1 : 0,
            'discount_type' => $type,
            'discount_value' => $this->number($voucher['discount_value'] ?? null) ?? '',
            'max_discount_amount' => $whole('max_discount_amount'),
            'min_order_amount' => $whole('min_order_amount'),
            'usage_limit' => $whole('usage_limit'),
            'usage_limit_per_customer' => $whole('usage_limit_per_customer'),
            'starts_at' => $this->moment(($voucher['starts_at'] ?? '') ?: now()->format('Y-m-d H:i'), 'ngày bắt đầu'),
            'ends_at' => $this->moment($voucher['ends_at'] ?? null, 'ngày kết thúc', endOfDay: true),
            'is_active' => 1,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function describe(array $data): string
    {
        $parts = ['giảm '.$this->amount($data['discount_type'], $data['discount_value'])];
        if ($data['max_discount_amount']) {
            $parts[] = 'tối đa '.$this->money($data['max_discount_amount']);
        }
        if ($data['min_order_amount']) {
            $parts[] = 'đơn từ '.$this->money($data['min_order_amount']);
        }
        if ($data['usage_limit']) {
            $parts[] = $data['usage_limit'].' lượt';
        }
        if ($data['usage_limit_per_customer']) {
            $parts[] = 'mỗi khách '.$data['usage_limit_per_customer'].' lượt';
        }

        $how = $data['is_auto_apply'] ? 'tự động áp dụng' : 'mã '.mb_strtoupper($data['code']);
        if ($data['is_auto_apply'] && $data['code'] !== '') {
            $how .= ', mã '.mb_strtoupper($data['code']);
        }

        return Discount::SCOPE_LABELS[$data['scope']].' "'.$data['name'].'" ('.$how.'): '.implode(', ', $parts)
            .'; từ '.$this->dateTime($data['starts_at']).' đến '.$this->dateTime($data['ends_at']).'.';
    }
}
