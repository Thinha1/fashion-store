<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

#[Fillable([
    'actor_id', 'action', 'subject_type', 'subject_id', 'old_values',
    'new_values', 'ip_address', 'user_agent',
])]
class AuditLog extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * Subject model (class basename) => Vietnamese name.
     *
     * @var array<string, string>
     */
    public const SUBJECT_LABELS = [
        'Product' => 'Sản phẩm',
        'ProductVariant' => 'Biến thể',
        'Brand' => 'Thương hiệu',
        'Category' => 'Danh mục',
        'Supplier' => 'Nhà cung cấp',
        'Discount' => 'Giảm giá',
        'Order' => 'Đơn hàng',
        'GoodsReceipt' => 'Phiếu nhập',
        'AiSetting' => 'Cấu hình AI',
        'PaymentSetting' => 'Cấu hình thanh toán',
        'SizeChart' => 'Bảng size',
        'User' => 'Tài khoản',
        'Role' => 'Vai trò',
    ];

    /**
     * Business actions with their own wording; "<model>.created/updated/
     * deleted" entries are worded from SUBJECT_LABELS instead.
     *
     * @var array<string, string>
     */
    public const ACTION_LABELS = [
        'auth.login' => 'Đăng nhập quản trị',
        'ai.action' => 'Trợ lý AI thực hiện thao tác',
        'order.placed' => 'Đặt đơn hàng',
        'order.cancelled' => 'Hủy đơn hàng',
        'order.status_changed' => 'Đổi trạng thái đơn hàng',
        'payment.confirmed_manually' => 'Xác nhận chuyển khoản thủ công',
        'payment.sepay_matched' => 'SePay: khớp chuyển khoản',
        'payment.sepay_unmatched' => 'SePay: chuyển khoản không khớp',
        'goods_receipt.confirmed' => 'Xác nhận phiếu nhập',
        'ai_settings.created' => 'Thêm cấu hình AI',
        'ai_settings.updated' => 'Sửa cấu hình AI',
        'ai_settings.primary_changed' => 'Đổi cấu hình AI chính',
        'ai_settings.deleted' => 'Xóa cấu hình AI',
        'payment_settings.updated' => 'Sửa cấu hình thanh toán',
        'role.created' => 'Tạo vai trò',
        'role.permissions_changed' => 'Đổi quyền của vai trò',
        'role.deleted' => 'Xóa vai trò',
        'staff.created' => 'Tạo tài khoản nhân viên',
        'staff.updated' => 'Sửa tài khoản nhân viên',
        'staff.locked' => 'Khóa tài khoản nhân viên',
        'staff.unlocked' => 'Mở khóa tài khoản nhân viên',
    ];

    private const VERBS = ['created' => 'Tạo', 'updated' => 'Sửa', 'deleted' => 'Xóa'];

    public function actionLabel(): string
    {
        if (isset(self::ACTION_LABELS[$this->action])) {
            return self::ACTION_LABELS[$this->action];
        }

        [$model, $event] = array_pad(explode('.', $this->action, 2), 2, '');
        $subject = self::SUBJECT_LABELS[Str::studly($model)] ?? null;

        return isset(self::VERBS[$event]) && $subject !== null
            ? self::VERBS[$event].' '.mb_strtolower($subject)
            : $this->action;
    }

    public function subjectLabel(): string
    {
        return self::SUBJECT_LABELS[class_basename($this->subject_type)] ?? class_basename($this->subject_type);
    }

    /**
     * Admin page of the record this entry is about, when it still exists.
     */
    public function subjectUrl(): ?string
    {
        $subject = $this->subject_id === null ? null : $this->subject;

        return match (true) {
            $subject instanceof Product => route('admin.products.edit', $subject),
            $subject instanceof ProductVariant => $subject->product ? route('admin.products.edit', $subject->product) : null,
            $subject instanceof Brand => route('admin.brands.edit', $subject),
            $subject instanceof Category => route('admin.categories.edit', $subject),
            $subject instanceof Supplier => route('admin.suppliers.edit', $subject),
            $subject instanceof Discount => route('admin.discounts.edit', $subject),
            $subject instanceof Order => route('admin.orders.show', $subject),
            $subject instanceof GoodsReceipt => route('admin.goods-receipts.show', $subject),
            $subject instanceof PaymentSetting => route('admin.settings.payment.edit'),
            $subject instanceof SizeChart => route('admin.size-charts.edit', $subject),
            $subject instanceof Role => route('admin.roles.edit', $subject),
            $subject instanceof User && $subject->role?->code !== 'customer' => route('admin.staff.edit', $subject),
            default => null,
        };
    }

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
