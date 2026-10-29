@php($route = $route ?? route('admin.discounts.store'))
@php($method = $method ?? 'POST')
@php($scope = old('scope', $discount->scope ?? 'variant'))
@php($isVoucher = in_array($scope, \App\Models\Discount::VOUCHER_SCOPES, true))
@php($discountType = old('discount_type', $discount->discount_type ?? 'percent'))
@php($discountUnits = ['percent' => '%', 'fixed' => '₫'])
@php($labelClass = 'block text-sm font-medium text-gray-700')
@php($selectClass = 'block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-gray-500 focus:outline-none focus:ring-1 focus:ring-gray-500')

<x-admin.form-errors :messages="$errors->all()" />

@if (! $discount->exists)
    {{-- The kind decides which fields the form has; it is fixed once saved. --}}
    <nav class="mb-4 flex flex-wrap gap-2" aria-label="Loại giảm giá">
        @foreach (\App\Models\Discount::SCOPE_LABELS as $value => $label)
            <a href="{{ route('admin.discounts.create', ['loai' => $value]) }}" @class(['btn', 'btn-primary' => $scope === $value, 'btn-secondary' => $scope !== $value])
               @if ($scope === $value) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>
@endif

<form method="POST" action="{{ $route }}" id="discount-form" class="admin-form admin-form-simple space-y-5"
      x-data="{ discountType: {{ Js::from($discountType) }}, discountUnits: {{ Js::from($discountUnits) }}, appliesTo: {{ Js::from(old('applies_to', $discount->hasTargets() ? 'selected' : 'all')) }} }">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif
    <input type="hidden" name="scope" value="{{ $scope }}">

    <div class="admin-form-heading"><x-icon name="percent" /><div><h2>{{ \App\Models\Discount::SCOPE_LABELS[$scope] }}</h2>
        <p>{{ match ($scope) {
            'order' => 'Giảm vào tiền hàng của cả đơn. Mỗi đơn dùng tối đa một voucher giảm đơn.',
            'shipping' => 'Giảm vào phí vận chuyển, không vượt quá phí. Mỗi đơn dùng tối đa một voucher giảm phí ship.',
            default => $discount->exists ? 'Giảm giá bán của một biến thể sản phẩm.' : 'Giảm giá bán của nhiều biến thể cùng lúc: chọn cả thương hiệu, từng sản phẩm hoặc từng biến thể.',
        } }}</p></div></div>

    @if ($isVoucher)
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label for="name" class="{{ $labelClass }}">Tên ưu đãi</label>
                <x-input id="name" name="name" :value="old('name', $discount->name)" maxlength="120" required class="mt-1"
                         placeholder="{{ $scope === 'shipping' ? 'Freeship đơn từ 500.000 ₫' : 'Giảm 50.000 ₫ đơn từ 300.000 ₫' }}" />
                <x-input-error :messages="$errors->get('name')" />
            </div>
            <div>
                <label for="code" class="{{ $labelClass }}">Mã voucher</label>
                <x-input id="code" name="code" :value="old('code', $discount->code)" maxlength="40" class="mt-1 uppercase"
                         placeholder="{{ $scope === 'shipping' ? 'FREESHIP' : 'GIAM50K' }}" aria-describedby="code-hint" />
                <p id="code-hint" class="mt-1 text-xs text-gray-500">Khách nhập mã này ở trang thanh toán. Có thể để trống nếu voucher tự động áp dụng.</p>
                <x-input-error :messages="$errors->get('code')" />
            </div>
        </div>

        <div>
            <label class="flex items-start gap-2 text-sm text-gray-700">
                <input type="checkbox" name="is_auto_apply" value="1" @checked(old('is_auto_apply', $discount->is_auto_apply))
                       class="mt-0.5 rounded border-gray-300 text-gray-900 focus:ring-gray-500">
                <span><span class="font-medium">Tự động áp dụng</span><br>
                    <span class="text-gray-500">Đơn đủ điều kiện được giảm ngay, khách không cần nhập mã. Nếu khách nhập một mã cùng loại, đơn dùng cái giảm nhiều hơn.</span></span>
            </label>
        </div>

        @if ($scope === 'order' && $targetOptions)
            <fieldset class="space-y-3">
                <legend class="{{ $labelClass }}">Áp dụng cho</legend>
                <div class="flex flex-wrap gap-x-6 gap-y-2 text-sm text-gray-700">
                    <label class="flex items-center gap-2"><input type="radio" name="applies_to" value="all" x-model="appliesTo" class="border-gray-300 text-gray-900 focus:ring-gray-500"> Toàn bộ đơn hàng</label>
                    <label class="flex items-center gap-2"><input type="radio" name="applies_to" value="selected" x-model="appliesTo" class="border-gray-300 text-gray-900 focus:ring-gray-500"> Chỉ một số thương hiệu, sản phẩm hoặc biến thể</label>
                </div>
                <div x-show="appliesTo === 'selected'" x-cloak class="space-y-2">
                    @include('admin.discounts._target-picker', ['options' => $targetOptions, 'always' => false, 'bulk' => false])
                    <p class="text-xs text-gray-500">Mức giảm, "Giảm tối đa" và "Áp dụng cho đơn từ" chỉ tính trên phần hàng thuộc các mục đã chọn.</p>
                </div>
                <x-input-error :messages="$errors->get('targets')" />
            </fieldset>
        @endif
    @elseif ($discount->exists)
        <div>
            <label for="product_variant_id" class="{{ $labelClass }}">Biến thể sản phẩm</label>
            <select id="product_variant_id" name="product_variant_id" required class="{{ $selectClass }}">
                <option value="">— Chọn biến thể —</option>
                @foreach ($variants as $variant)
                    <option value="{{ $variant->id }}" @selected((int) old('product_variant_id', $discount->product_variant_id) === $variant->id)>
                        {{ $variant->product->name }} — {{ $variant->size }} / {{ $variant->color }} ({{ $variant->sku }})
                    </option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('product_variant_id')" />
        </div>
    @else
        <div class="space-y-2">
            <span class="{{ $labelClass }}">Giảm giá cho</span>
            @include('admin.discounts._target-picker', ['options' => $targetOptions, 'always' => true, 'bulk' => true])
            <x-input-error :messages="$errors->get('targets')" />
        </div>
    @endif

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <label for="discount_type" class="{{ $labelClass }}">Kiểu giảm</label>
            <select id="discount_type" name="discount_type" required x-model="discountType" class="{{ $selectClass }}">
                <option value="percent" @selected($discountType === 'percent')>Phần trăm</option>
                <option value="fixed" @selected($discountType === 'fixed')>Số tiền cố định</option>
            </select>
            <x-input-error :messages="$errors->get('discount_type')" />
        </div>

        <div>
            <label for="discount_value" class="{{ $labelClass }}">{{ $scope === 'shipping' ? 'Giảm phí vận chuyển' : 'Giá trị giảm' }}</label>
            <x-currency-input id="discount_value" name="discount_value" :value="old('discount_value', $discount->discount_value)"
                :unit="$discountUnits[$discountType] ?? ''"
                unit-expression="discountUnits[discountType] ?? ''" required class="mt-1" />
            @if ($scope === 'shipping')
                <p class="mt-1 text-xs text-gray-500">100% là miễn phí vận chuyển.</p>
            @endif
            <x-input-error :messages="$errors->get('discount_value')" />
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <label for="max_discount_amount" class="{{ $labelClass }}">Giảm tối đa (tùy chọn)</label>
            <x-currency-input id="max_discount_amount" name="max_discount_amount" :value="old('max_discount_amount', $discount->max_discount_amount)" class="mt-1" />
            <x-input-error :messages="$errors->get('max_discount_amount')" />
        </div>
        @if ($isVoucher)
            <div>
                <label for="min_order_amount" class="{{ $labelClass }}">Áp dụng cho đơn từ (tùy chọn)</label>
                <x-currency-input id="min_order_amount" name="min_order_amount" :value="old('min_order_amount', $discount->min_order_amount)" class="mt-1" />
                <p class="mt-1 text-xs text-gray-500">{{ $scope === 'shipping' ? 'So với tiền hàng sau khi đã trừ voucher giảm đơn.' : 'So với tiền hàng của đơn.' }}</p>
                <x-input-error :messages="$errors->get('min_order_amount')" />
            </div>
        @endif
    </div>

    @if ($isVoucher)
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label for="usage_limit" class="{{ $labelClass }}">Tổng lượt dùng (tùy chọn)</label>
                <x-input id="usage_limit" type="number" min="1" name="usage_limit" :value="old('usage_limit', $discount->usage_limit)" class="mt-1" />
                @if ($discount->exists)
                    <p class="mt-1 text-xs text-gray-500">Đã dùng {{ $discount->used_count }} lượt.</p>
                @endif
                <x-input-error :messages="$errors->get('usage_limit')" />
            </div>
            <div>
                <label for="usage_limit_per_customer" class="{{ $labelClass }}">Lượt dùng mỗi khách (tùy chọn)</label>
                <x-input id="usage_limit_per_customer" type="number" min="1" name="usage_limit_per_customer" :value="old('usage_limit_per_customer', $discount->usage_limit_per_customer)" class="mt-1" />
                <x-input-error :messages="$errors->get('usage_limit_per_customer')" />
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <label for="starts_at" class="{{ $labelClass }}">Bắt đầu</label>
            <x-input id="starts_at" type="datetime-local" name="starts_at" value="{{ old('starts_at', optional($discount->starts_at)->format('Y-m-d\TH:i')) }}" required class="mt-1" />
            <x-input-error :messages="$errors->get('starts_at')" />
        </div>
        <div>
            <label for="ends_at" class="{{ $labelClass }}">Kết thúc</label>
            <x-input id="ends_at" type="datetime-local" name="ends_at" value="{{ old('ends_at', optional($discount->ends_at)->format('Y-m-d\TH:i')) }}" required class="mt-1" />
            <x-input-error :messages="$errors->get('ends_at')" />
        </div>
    </div>

    <div>
        <label class="flex items-center gap-2 text-sm text-gray-700">
            <input type="checkbox" name="is_active" value="1"
                   @checked(old('is_active', $discount->is_active ?? true))
                   class="rounded border-gray-300 text-gray-900 focus:ring-gray-500">
            Đang hoạt động
        </label>
    </div>

    <x-admin.form-actions :cancel="route('admin.discounts.index')" label="Lưu" />
</form>
