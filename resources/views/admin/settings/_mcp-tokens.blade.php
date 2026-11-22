{{-- Tokens of the read-only MCP server (/ai/shop): staff connect Claude with one to ask for sales, orders and stock. --}}
<section class="mt-12" aria-labelledby="mcp-tokens-title">
    @if ($issued = session('mcp_token'))
        <div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900" role="status">
            <p class="font-medium">Đã tạo token "{{ $issued['name'] }}" cho vai trò {{ $issued['role'] }}, hết hạn {{ $issued['expires_at'] }}.</p>
            <p class="mt-1">Token chỉ hiện một lần. Hãy chép và lưu lại ngay.</p>
            <div class="mt-3 flex flex-wrap items-center gap-2">
                <code class="break-all rounded bg-white px-2 py-1 text-gray-900">{{ $issued['plain'] }}</code>
                <button type="button" class="admin-row-action"
                        x-data="{ copied: false }"
                        x-on:click="navigator.clipboard.writeText({{ Js::from($issued['plain']) }}).then(() => { copied = true; setTimeout(() => copied = false, 2000) })"
                        x-text="copied ? 'Đã chép' : 'Chép token'">Chép token</button>
            </div>
            <p class="mt-3">Endpoint: <code>{{ url('/ai/shop') }}</code>. Đọc được: {{ implode(', ', $issued['permissions']) }}.</p>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.settings.mcp-tokens.store') }}" class="admin-form admin-form-simple mb-6 space-y-5">
        @csrf
        <div class="admin-form-heading"><x-icon name="settings" /><div>
            <h2 id="mcp-tokens-title">Token MCP</h2>
            <p>Token để kết nối Claude với MCP server chỉ đọc của cửa hàng. Token đọc được những gì vai trò của nó được xem, theo quyền tại thời điểm gọi.</p>
        </div></div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div>
                {{-- A plain <label> (not <x-label>) so static analysis sees it is tied to the select. --}}
                <label for="mcp_role_id" class="block text-sm font-medium text-gray-700">Vai trò</label>
                <select id="mcp_role_id" name="role_id" required class="field mt-1">
                    <option value="">Chọn vai trò</option>
                    @foreach ($mcpRoles as $role)
                        <option value="{{ $role->id }}" @selected((string) old('role_id') === (string) $role->id)>{{ $role->name }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('role_id')" />
            </div>
            <div>
                <x-label for="mcp_days">Số ngày hiệu lực</x-label>
                <x-input id="mcp_days" type="number" name="days" min="1" max="365" value="{{ old('days', 30) }}" required class="mt-1" />
                <x-input-error :messages="$errors->get('days')" />
            </div>
            <div>
                <x-label for="mcp_name">Tên gợi nhớ</x-label>
                <x-input id="mcp_name" name="name" value="{{ old('name') }}" maxlength="120" placeholder="VD: Claude của chủ shop" class="mt-1" />
                <x-input-error :messages="$errors->get('name')" />
            </div>
        </div>

        <x-admin.form-actions :cancel="route('admin.settings.ai.edit')" label="Tạo token" />
    </form>

    <x-admin-table :header="['Tên', 'Vai trò', 'Hết hạn', 'Dùng lần cuối', 'Trạng thái', 'Thao tác']">
        @forelse ($mcpTokens as $token)
            @php
                [$label, $tone] = match (true) {
                    $token->revoked_at !== null => ['Đã thu hồi', 'neutral'],
                    $token->expires_at->isPast() => ['Hết hạn', 'warning'],
                    default => ['Đang dùng', 'success'],
                };
            @endphp
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900">{{ $token->name }}</td>
                <td class="px-4 py-3 text-gray-600">{{ $token->role?->name ?? '—' }}</td>
                <td class="px-4 py-3 text-gray-600">{{ $token->expires_at->format('d/m/Y H:i') }}</td>
                <td class="px-4 py-3 text-gray-600">{{ $token->last_used_at?->format('d/m/Y H:i') ?? 'Chưa dùng' }}</td>
                <td class="px-4 py-3"><span class="admin-status admin-status-{{ $tone }}"><span aria-hidden="true"></span>{{ $label }}</span></td>
                <td class="px-4 py-3 text-right"><div class="admin-row-actions">
                    @if ($label === 'Đang dùng')
                        <form method="POST" action="{{ route('admin.settings.mcp-tokens.destroy', $token) }}" class="inline"
                              x-on:submit.prevent="$dispatch('admin-confirm', { form: $el, message: 'Thu hồi token &quot;{{ e($token->name) }}&quot;? Claude dùng token này sẽ mất kết nối ngay.' })">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="admin-row-action"><x-icon name="delete" class="size-3.5" /> Thu hồi</button>
                        </form>
                    @endif
                </div></td>
            </tr>
        @empty
            <tr>
                <td colspan="6" class="px-4 py-8 text-center text-gray-500">Chưa có token MCP nào.</td>
            </tr>
        @endforelse
    </x-admin-table>
</section>
