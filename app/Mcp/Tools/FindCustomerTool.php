<?php

namespace App\Mcp\Tools;

use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Reports\CustomerStats;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class FindCustomerTool extends CustomerTool
{
    private const DEFAULT_LIMIT = 5;

    private const MAX_LIMIT = 10;

    protected string $name = 'find_customer';

    protected string $title = 'Tìm khách hàng';

    protected string $description = 'Tìm khách hàng theo số điện thoại, email, tên hoặc mã đơn mà nhân viên đưa vào (ít nhất 3 ký tự): id, tên viết tắt, 3 số cuối điện thoại để đối chiếu, số đơn (kể cả đơn huỷ), đã chi (VND, chỉ đơn đã giao và đã thanh toán), đơn gần nhất, ngày đăng ký và admin_url để mở đầy đủ trong trang quản trị. Không trả email, số điện thoại đầy đủ hay địa chỉ. Dùng get_customer với id để xem chi tiết một khách.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->min(3)->max(100)->required()->description('Số điện thoại, email, tên hoặc mã đơn của khách, ví dụ "0901234567", "an@example.com", "Nguyễn An" hoặc "DH261024YZEBRU".'),
            'limit' => $this->limitSchema($schema, self::DEFAULT_LIMIT, self::MAX_LIMIT),
        ];
    }

    public function handle(Request $request, AuditLogger $audit): ResponseFactory|Response
    {
        $request->validate(['query' => ['required', 'string', 'min:3', 'max:100']]);
        $limit = $this->limit($request, self::DEFAULT_LIMIT, self::MAX_LIMIT);
        $text = trim((string) $request->get('query'));
        $like = '%'.addcslashes($text, '%_\\').'%';

        $matching = fn (): Builder => CustomerStats::customers()->where(fn (Builder $query) => $query
            ->where('name', 'like', $like)
            ->orWhere('email', 'like', $like)
            ->orWhere('phone', 'like', $like)
            ->orWhereHas('addresses', fn (Builder $addresses) => $addresses->where('phone', 'like', $like))
            ->orWhereHas('orders', fn (Builder $orders) => $orders->where('order_number', mb_strtoupper($text))));

        $customers = $matching()->orderByDesc('last_order_at')->orderByDesc('id')->limit($limit)->get();

        $this->audit($audit, $request);

        return Response::structured([
            'currency' => 'VND',
            'total_matches' => $matching()->count(),
            'customers' => $customers->map(fn (User $customer): array => [
                ...$this->identify($customer),
                'orders' => (int) $customer->orders_count,
                'spent' => $this->money($customer->spent),
                'last_order_at' => $customer->last_order_at ? $this->time(Carbon::parse($customer->last_order_at)) : null,
                'registered_at' => $this->time($customer->created_at),
            ])->all(),
        ]);
    }
}
