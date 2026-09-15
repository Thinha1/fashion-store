@props(['messages' => []])
@if ($messages)
    <div class="admin-form-errors" role="alert" tabindex="-1">
        <div class="flex items-center gap-2 font-semibold"><x-icon name="info" class="size-4" /> Chưa thể lưu, hãy kiểm tra thông tin</div>
        <ul class="mt-2 list-disc space-y-1 pl-5">
            @foreach ($messages as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
@endif
