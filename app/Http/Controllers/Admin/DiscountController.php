<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreDiscountRequest;
use App\Models\Discount;
use App\Models\ProductVariant;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DiscountController extends Controller
{
    public function index(): View
    {
        $discounts = Discount::query()
            ->where('scope', 'variant')
            ->with('productVariant.product:id,name')
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('admin.discounts.index', ['discounts' => $discounts]);
    }

    public function create(): View
    {
        return view('admin.discounts.create', [
            'discount' => new Discount([
                'scope' => 'variant',
                'discount_type' => 'percent',
                'discount_value' => 10,
                'is_active' => true,
            ]),
            'variants' => ProductVariant::query()->where('is_active', true)->with('product:id,name')->get(),
        ]);
    }

    public function store(StoreDiscountRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $discount = Discount::query()->create($data + [
            'scope' => 'variant',
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('admin.discounts.index')
            ->with('status', 'Giảm giá biến thể đã được tạo.');
    }

    public function edit(Discount $discount): View
    {
        abort_unless($discount->scope === 'variant', 404, 'Chỉ giảm giá theo biến thể mới có thể sửa ở đây.');

        return view('admin.discounts.edit', [
            'discount' => $discount,
            'variants' => ProductVariant::query()->where('is_active', true)->with('product:id,name')->get(),
        ]);
    }

    public function update(StoreDiscountRequest $request, Discount $discount): RedirectResponse
    {
        abort_unless($discount->scope === 'variant', 404, 'Chỉ giảm giá theo biến thể mới có thể sửa ở đây.');

        $data = $request->validated();

        $discount->update($data + [
            'scope' => 'variant',
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('admin.discounts.index')
            ->with('status', 'Giảm giá biến thể đã được cập nhật.');
    }

    public function destroy(Discount $discount): RedirectResponse
    {
        abort_unless(auth()->user()->can('products.manage'), 403);

        $discount->delete();

        return redirect()->route('admin.discounts.index')->with('status', 'Giảm giá đã được xóa.');
    }
}
