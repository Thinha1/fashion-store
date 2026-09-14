@props(['resource'])

<div class="mb-4 flex flex-wrap gap-2" x-data="excelImport(@js($errors->has('file')))">
    <x-button type="button" variant="secondary" x-on:click="openDialog()" aria-haspopup="dialog" aria-controls="excel-import-{{ $resource }}"><i class="fa-solid fa-file-import" aria-hidden="true"></i> Nhập từ Excel</x-button>
    <a href="{{ route('admin.excel.export', $resource) }}" class="btn btn-secondary"><i class="fa-solid fa-file-export" aria-hidden="true"></i> Xuất Excel</a>

    <dialog id="excel-import-{{ $resource }}" x-ref="importDialog" class="excel-dialog" aria-labelledby="excel-import-title-{{ $resource }}" aria-describedby="excel-import-help-{{ $resource }}" x-on:click="if ($event.target === $refs.importDialog) closeDialog()" x-on:cancel="if (busy) $event.preventDefault()" x-on:keydown.tab="trapFocus($event)">
        <div class="p-5 sm:p-7">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 id="excel-import-title-{{ $resource }}" class="text-xl font-semibold text-gray-900">Nhập từ Excel</h2>
                    <p class="mt-1 text-sm text-gray-500">{{ \App\Actions\AdminExcel::resources()[$resource]['label'] }}</p>
                </div>
                <x-button type="button" variant="secondary" class="shrink-0 px-3" x-on:click="closeDialog()" x-bind:disabled="busy" aria-label="Đóng dialog nhập Excel"><x-icon name="close" /></x-button>
            </div>

            <p id="excel-import-help-{{ $resource }}" class="mt-5 text-sm leading-6 text-gray-600">Tải file mẫu, điền dữ liệu rồi chọn file để nhập. Để trống ID để thêm mới, hoặc điền ID để cập nhật.</p>
            <a href="{{ route('admin.excel.template', $resource) }}" class="btn btn-secondary mt-3"><i class="fa-solid fa-download" aria-hidden="true"></i> Tải file mẫu</a>

            <form method="POST" action="{{ route('admin.excel.import', $resource) }}" enctype="multipart/form-data" class="mt-5" x-on:submit="busy = true">
                @csrf
                <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 p-4 sm:p-5">
                    <label for="excel-file-{{ $resource }}" class="flex items-center gap-2 text-sm font-semibold text-gray-700"><i class="fa-solid fa-file-excel text-brand" aria-hidden="true"></i> Chọn file Excel (.xlsx)</label>
                    <input id="excel-file-{{ $resource }}" type="file" name="file" required autofocus accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" class="mt-3 block min-h-11 w-full min-w-0 text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-white file:px-3 file:py-2.5 file:font-medium file:text-brand" aria-describedby="excel-file-help-{{ $resource }}" @if($errors->has('file')) aria-invalid="true" @endif />
                    <p id="excel-file-help-{{ $resource }}" class="mt-3 text-xs leading-5 text-gray-500">Tối đa 5 MB, 5.000 dòng. Xem sheet Hướng dẫn và Tham chiếu trong file mẫu.</p>
                </div>

                @if ($resource === 'goods-receipts')
                    <p class="mt-4 text-xs leading-6 text-gray-500">Sheet Dòng hàng chứa toàn bộ dòng muốn giữ trong phiếu. Phiếu mới luôn là nháp; chỉ cập nhật phiếu nháp, sau đó xác nhận trên màn hình chi tiết.</p>
                @endif
                <div role="alert"><x-input-error :messages="$errors->get('file')" /></div>

                <div class="mt-6 flex flex-wrap justify-end gap-2 border-t border-gray-100 pt-5">
                    <x-button type="button" variant="secondary" x-on:click="closeDialog()" x-bind:disabled="busy">Hủy</x-button>
                    <x-button type="submit" x-bind:disabled="busy"><i class="fa-solid fa-file-import" aria-hidden="true"></i><span x-text="busy ? 'Đang nhập…' : 'Nhập dữ liệu'">Nhập dữ liệu</span></x-button>
                </div>
            </form>
        </div>
    </dialog>
</div>
