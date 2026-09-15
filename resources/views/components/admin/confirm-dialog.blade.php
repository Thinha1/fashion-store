<div x-data="adminConfirm" x-on:admin-confirm.window="open($event.detail)">
    <dialog x-ref="dialog" class="admin-dialog" aria-labelledby="admin-confirm-title" aria-describedby="admin-confirm-message"
        x-on:cancel.prevent="close()" x-on:click="if ($event.target === $refs.dialog) close()">
        <div class="p-6">
            <div class="mb-4 flex size-11 items-center justify-center rounded-xl bg-brand-soft text-brand"><x-icon name="info" /></div>
            <h2 id="admin-confirm-title" class="text-lg font-semibold">Xác nhận thao tác</h2>
            <p id="admin-confirm-message" class="mt-2 text-sm leading-6 text-gray-600" x-text="message"></p>
            <div class="mt-6 flex justify-end gap-2">
                <x-admin.action type="button" variant="secondary" x-on:click="close()" x-bind:disabled="busy" autofocus>Hủy</x-admin.action>
                <x-admin.action type="button" x-on:click="confirm()" x-bind:disabled="busy"><span x-text="busy ? 'Đang xử lý…' : 'Xác nhận'">Xác nhận</span></x-admin.action>
            </div>
        </div>
    </dialog>
</div>
