@php($route = $route ?? route('admin.discounts.store'))
@php($method = $method ?? 'POST')

<x-admin.form-errors :messages="$errors->all()" />

<form method="POST" action="{{ $route }}" class="admin-form admin-form-simple space-y-5"
      x-data="{ discountType: {{ Js::from(old('discount_type', $discount->discount_type ?? 'percent')) }} }">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <div class="admin-form-heading"><x-icon name="percent" /><div><h2>Thông tin giảm giá</h2><p>Điền thông tin và chọn trạng thái hiển thị.</p></div></div>

    <div>
        <x-label for="product_variant_id">Biến thể sản phẩm</x-label>
        <select id="product_variant_id" name="product_variant_id" required
                class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-gray-500 focus:outline-none focus:ring-1 focus:ring-gray-500">
            <option value="">— Chọn biến thể —</option>
            @foreach ($variants as $variant)
                <option value="{{ $variant->id }}" @selected((int) old('product_variant_id', $discount->product_variant_id) === $variant->id)>
                    {{ $variant->product->name }} — {{ $variant->size }} / {{ $variant->color }} ({{ $variant->sku }})
                </option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('product_variant_id')" />
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <label for="discount_type" class="block text-sm font-medium text-gray-700">Loại giảm giá</label>
            <select id="discount_type" name="discount_type" required x-model="discountType"
                    class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-gray-500 focus:outline-none focus:ring-1 focus:ring-gray-500">
                <option value="percent" @selected(old('discount_type', $discount->discount_type) === 'percent')>Phần trăm</option>
                <option value="fixed" @selected(old('discount_type', $discount->discount_type) === 'fixed')>Cố định (VNĐ)</option>
            </select>
            <x-input-error :messages="$errors->get('discount_type')" />
        </div>

        <div>
            <x-label for="discount_value">Giá trị</x-label>
            <x-currency-input id="discount_value" name="discount_value" :value="old('discount_value', $discount->discount_value)"
                :unit="old('discount_type', $discount->discount_type ?? 'percent') === 'percent' ? '%' : '₫'"
                unit-expression="discountType === 'percent' ? '%' : '₫'" required class="mt-1" />
            <x-input-error :messages="$errors->get('discount_value')" />
        </div>
    </div>

    <div>
        <x-label for="max_discount_amount">Giới hạn giá trị giảm (VNĐ, tùy chọn)</x-label>
        <x-currency-input id="max_discount_amount" name="max_discount_amount" :value="old('max_discount_amount', $discount->max_discount_amount)" class="mt-1" />
        <x-input-error :messages="$errors->get('max_discount_amount')" />
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <x-label for="starts_at">Bắt đầu</x-label>
            <x-input id="starts_at" type="datetime-local" name="starts_at" value="{{ old('starts_at', optional($discount->starts_at)->format('Y-m-d\TH:i')) }}" required class="mt-1" />
            <x-input-error :messages="$errors->get('starts_at')" />
        </div>
        <div>
            <x-label for="ends_at">Kết thúc</x-label>
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

    <x-admin.form-actions :cancel="route('admin.discounts.index')" label="Lưu giảm giá" />
</form>
