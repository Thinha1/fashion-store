{{-- Shared address inputs: the address book form and checkout's "new address" option. --}}
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
    <div>
        <x-label for="province_name">Tỉnh/Thành phố</x-label>
        <x-input id="province_name" name="province_name" value="{{ old('province_name', $address->province_name) }}" required class="mt-1" />
        <x-input-error :messages="$errors->get('province_name')" />
    </div>
    <div>
        <x-label for="district_name">Quận/Huyện</x-label>
        <x-input id="district_name" name="district_name" value="{{ old('district_name', $address->district_name) }}" required class="mt-1" />
        <x-input-error :messages="$errors->get('district_name')" />
    </div>
    <div>
        <x-label for="ward_name">Phường/Xã</x-label>
        <x-input id="ward_name" name="ward_name" value="{{ old('ward_name', $address->ward_name) }}" required class="mt-1" />
        <x-input-error :messages="$errors->get('ward_name')" />
    </div>
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
