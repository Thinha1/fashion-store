@props(['cancel', 'label' => 'Lưu thay đổi'])
<div class="admin-form-actions">
    <p class="text-xs text-gray-500 hidden sm:block">Kiểm tra thông tin trước khi lưu.</p>
    <div class="flex items-center justify-end gap-2">
        <x-admin.action :href="$cancel" variant="secondary">Hủy</x-admin.action>
        <x-admin.action icon="save">{{ $label }}</x-admin.action>
    </div>
</div>
