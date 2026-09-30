<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSupplierRequest;
use App\Models\Supplier;
use App\Support\AdminPagination;
use App\Support\AdminSorting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;

class SupplierController extends Controller
{
    private const TAX_LOOKUP_UNAVAILABLE = 'Chưa thể tra cứu mã số thuế. Vui lòng thử lại sau hoặc nhập thông tin thủ công.';

    public function index(Request $request): View
    {
        $sorting = new AdminSorting($request, [
            'code' => 'code', 'name' => 'name', 'phone' => 'phone', 'email' => 'email', 'tax_code' => 'tax_code',
            'goods_receipts_count' => 'goods_receipts_count', 'is_active' => 'is_active',
        ]);
        $suppliers = $sorting->apply(Supplier::query()->withCount('goodsReceipts')->orderBy('name')->orderBy('id'))
            ->paginate(AdminPagination::perPage($request))->withQueryString();

        return view('admin.suppliers.index', ['suppliers' => $suppliers, 'sorting' => $sorting]);
    }

    public function create(): View
    {
        return view('admin.suppliers.create', ['supplier' => new Supplier(['is_active' => true])]);
    }

    public function store(StoreSupplierRequest $request): RedirectResponse
    {
        $supplier = Supplier::query()->create($request->validated() + ['is_active' => $request->boolean('is_active', true)]);

        return redirect()->route('admin.suppliers.index')
            ->with('status', "Nhà cung cấp \"{$supplier->name}\" đã được tạo.");
    }

    public function edit(Supplier $supplier): View
    {
        return view('admin.suppliers.edit', ['supplier' => $supplier]);
    }

    public function update(StoreSupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($request->validated() + ['is_active' => $request->boolean('is_active', true)]);

        return redirect()->route('admin.suppliers.index')
            ->with('status', "Nhà cung cấp \"{$supplier->name}\" đã được cập nhật.");
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        abort_unless(auth()->user()->can('suppliers.manage'), 403);

        if ($supplier->goodsReceipts()->exists()) {
            return back()->with('error', 'Không thể xóa nhà cung cấp đang có phiếu nhập.');
        }

        $supplier->delete();

        return redirect()->route('admin.suppliers.index')->with('status', 'Nhà cung cấp đã được xóa.');
    }

    public function taxLookup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tax_code' => ['required', 'string', 'regex:/\A[0-9]{10}(?:-?[0-9]{3})?\z/'],
        ], [
            'tax_code.required' => 'Vui lòng nhập mã số thuế cần tra cứu.',
            'tax_code.regex' => 'Mã số thuế doanh nghiệp gồm 10 số hoặc 13 số (có thể có dấu gạch ngang trước 3 số cuối).',
        ]);
        $digits = str_replace('-', '', $validated['tax_code']);
        $taxCode = strlen($digits) === 13 ? substr($digits, 0, 10).'-'.substr($digits, 10) : $digits;
        $cacheSeconds = max(0, (int) config('services.vietqr.business_cache_seconds'));
        $company = $cacheSeconds > 0
            ? Cache::remember('supplier-tax:'.$taxCode, $cacheSeconds, fn () => $this->fetchCompany($taxCode))
            : $this->fetchCompany($taxCode);

        return response()->json(['data' => $company]);
    }

    private function fetchCompany(string $taxCode): array
    {
        try {
            $response = Http::acceptJson()->connectTimeout(3)->timeout(8)
                ->withOptions(['allow_redirects' => false])
                ->get('https://api.vietqr.io/v2/business/'.$taxCode);
        } catch (ConnectionException) {
            abort(503, self::TAX_LOOKUP_UNAVAILABLE);
        }

        if ($response->status() === 429) {
            $retryAfter = $response->header('Retry-After');
            abort(503, 'Dịch vụ tra cứu đang quá tải. Vui lòng thử lại sau.', [
                'Retry-After' => ctype_digit($retryAfter) ? (string) min(3600, max(1, (int) $retryAfter)) : '60',
            ]);
        }
        abort_if($response->serverError(), 503, self::TAX_LOOKUP_UNAVAILABLE);
        abort_if($response->json('code') === '51', 404, 'Không tìm thấy doanh nghiệp với mã số thuế này. Bạn vẫn có thể nhập thông tin thủ công.');
        abort_unless($response->successful() && $response->json('code') === '00', 502, self::TAX_LOOKUP_UNAVAILABLE);

        $data = $response->json('data');
        abort_unless(is_array($data), 502, self::TAX_LOOKUP_UNAVAILABLE);
        foreach (['id', 'name', 'address'] as $field) {
            abort_unless(isset($data[$field]) && is_string($data[$field]) && trim($data[$field]) !== '', 502,
                'Dữ liệu doanh nghiệp chưa đầy đủ. Vui lòng nhập tên và địa chỉ thủ công.');
        }
        abort_unless(str_replace('-', '', $data['id']) === str_replace('-', '', $taxCode), 502, self::TAX_LOOKUP_UNAVAILABLE);

        return ['tax_code' => $taxCode, 'name' => trim($data['name']), 'address' => trim($data['address'])];
    }
}
