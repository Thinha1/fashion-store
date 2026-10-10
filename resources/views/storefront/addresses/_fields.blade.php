{{-- Shared address inputs: the address book form and checkout's "new address" option.
     Province -> ward (no district since the 2025 reorganisation). With the province list loaded,
     the province is picked from a list and the ward is suggested from that province's wards;
     without it ($provinces === null) both are plain text. --}}
<div class="grid gap-4 sm:grid-cols-2">
    <div>
        <x-label for="recipient_name">Họ tên người nhận</x-label>
        <x-input id="recipient_name" name="recipient_name" autocomplete="name" value="{{ old('recipient_name', $address->recipient_name) }}" required class="mt-1" />
        <x-input-error :messages="$errors->get('recipient_name')" />
    </div>
    <div>
        <x-label for="phone">Số điện thoại</x-label>
        <x-input id="phone" name="phone" type="tel" autocomplete="tel" value="{{ old('phone', $address->phone) }}" required class="mt-1" />
        <x-input-error :messages="$errors->get('phone')" />
    </div>
    @if ($provinces)
        @php($selectedProvince = old('province_name', $address->province_name))
        <div class="grid gap-4 sm:col-span-2 sm:grid-cols-2"
             x-data="addressPicker({ wardsUrl: {{ Js::from(route('addresses.wards', ['provinceCode' => '__CODE__'])) }}, provinces: {{ Js::from(collect($provinces)->map(fn ($province) => ['code' => $province['code'], 'name' => $province['name']])) }}, province: {{ Js::from($selectedProvince) }}, ward: {{ Js::from(old('ward_name', $address->ward_name)) }} })">
            <div>
                <label for="province_name" class="block text-sm font-medium text-gray-700">Tỉnh/Thành phố</label>
                <select id="province_name" name="province_name" required class="field mt-1" x-model="province">
                    <option value="">Chọn Tỉnh/Thành phố</option>
                    @foreach ($provinces as $province)
                        <option value="{{ $province['name'] }}" @selected($selectedProvince === $province['name'])>{{ $province['label'] }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('province_name')" />
            </div>
            <div>
                <label for="ward_name" class="block text-sm font-medium text-gray-700">Phường/Xã</label>
                <input id="ward_name" name="ward_name" list="ward-options" required autocomplete="off" class="field mt-1"
                       value="{{ old('ward_name', $address->ward_name) }}" x-model="ward"
                       :placeholder="province ? 'Gõ để tìm, ví dụ: Bến Thành' : 'Chọn Tỉnh/Thành phố trước'">
                <datalist id="ward-options">
                    <template x-for="name in wards" :key="name"><option :value="name"></option></template>
                </datalist>
                <p class="mt-1 text-xs text-gray-500" x-show="status === 'loading'" x-cloak>Đang tải danh sách phường/xã…</p>
                <p class="mt-1 text-xs text-amber-700" x-show="status === 'failed'" x-cloak>Chưa tải được danh sách phường/xã — bạn vẫn có thể tự nhập.</p>
                <x-input-error :messages="$errors->get('ward_name')" />
            </div>
        </div>
    @else
        <div>
            <x-label for="province_name">Tỉnh/Thành phố</x-label>
            <x-input id="province_name" name="province_name" value="{{ old('province_name', $address->province_name) }}" required class="mt-1" />
            <x-input-error :messages="$errors->get('province_name')" />
        </div>
        <div>
            <x-label for="ward_name">Phường/Xã</x-label>
            <x-input id="ward_name" name="ward_name" value="{{ old('ward_name', $address->ward_name) }}" required class="mt-1" />
            <x-input-error :messages="$errors->get('ward_name')" />
        </div>
    @endif
    <div>
        <x-label for="label">Tên gợi nhớ <span class="font-normal text-gray-400">(không bắt buộc)</span></x-label>
        <x-input id="label" name="label" placeholder="Nhà riêng, Công ty..." value="{{ old('label', $address->label) }}" class="mt-1" />
        <x-input-error :messages="$errors->get('label')" />
    </div>
    <div class="sm:col-span-2">
        <x-label for="address_line">Số nhà, tên đường</x-label>
        <x-input id="address_line" name="address_line" autocomplete="street-address" value="{{ old('address_line', $address->address_line) }}" required class="mt-1" />
        <x-input-error :messages="$errors->get('address_line')" />
    </div>
</div>
