<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\SaveAddressRequest;
use App\Models\Address;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * The signed-in customer's address book. At most one address is the default;
 * the first address saved becomes the default automatically.
 */
class AddressController extends Controller
{
    public function index(Request $request): View
    {
        return view('storefront.addresses.index', [
            'addresses' => $request->user()->addresses()->orderByDesc('is_default')->latest('id')->get(),
        ]);
    }

    public function create(): View
    {
        return view('storefront.addresses.form', ['address' => new Address]);
    }

    public function store(SaveAddressRequest $request): RedirectResponse
    {
        $user = $request->user();
        $address = $user->addresses()->create($request->safe()->except('is_default'));

        if ($request->boolean('is_default') || ! $user->addresses()->where('is_default', true)->exists()) {
            $address->markAsDefault();
        }

        return redirect()->route('addresses.index')->with('status', 'Đã thêm địa chỉ mới.');
    }

    public function edit(Address $address): View
    {
        Gate::authorize('update', $address);

        return view('storefront.addresses.form', ['address' => $address]);
    }

    public function update(SaveAddressRequest $request, Address $address): RedirectResponse
    {
        Gate::authorize('update', $address);

        $address->update($request->safe()->except('is_default'));

        if ($request->boolean('is_default')) {
            $address->markAsDefault();
        }

        return redirect()->route('addresses.index')->with('status', 'Đã cập nhật địa chỉ.');
    }

    public function makeDefault(Address $address): RedirectResponse
    {
        Gate::authorize('update', $address);

        $address->markAsDefault();

        return redirect()->route('addresses.index')->with('status', 'Đã đặt làm địa chỉ mặc định.');
    }

    /**
     * Deleting the default address hands the default to the newest remaining one.
     */
    public function destroy(Address $address): RedirectResponse
    {
        Gate::authorize('delete', $address);

        DB::transaction(function () use ($address): void {
            $address->delete();

            if ($address->is_default) {
                Address::query()->where('user_id', $address->user_id)->latest('id')->first()?->markAsDefault();
            }
        });

        return redirect()->route('addresses.index')->with('status', 'Đã xóa địa chỉ.');
    }
}
