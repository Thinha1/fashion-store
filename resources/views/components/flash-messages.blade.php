@if (session('status'))
    <x-alert type="success" class="mb-4">{{ session('status') }}</x-alert>
@endif

@if (session('error'))
    <x-alert type="error" class="mb-4">{{ session('error') }}</x-alert>
@endif
