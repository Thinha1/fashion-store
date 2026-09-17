<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class SupplierTaxLookupController extends Controller
{
    private const UNAVAILABLE = 'Chưa thể tra cứu mã số thuế. Vui lòng thử lại sau hoặc nhập thông tin thủ công.';

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tax_code' => ['required', 'string', 'regex:/\A[0-9]{10}(?:-?[0-9]{3})?\z/'],
        ], [
            'tax_code.required' => 'Vui lòng nhập mã số thuế cần tra cứu.',
            'tax_code.regex' => 'Mã số thuế doanh nghiệp gồm 10 số hoặc 13 số (có thể có dấu gạch ngang trước 3 số cuối).',
        ]);
        $digits = str_replace('-', '', $validated['tax_code']);
        $taxCode = strlen($digits) === 13 ? substr($digits, 0, 10).'-'.substr($digits, 10) : $digits;
        $company = Cache::remember('supplier-tax:'.$taxCode, now()->addHour(), fn () => $this->fetchCompany($taxCode));

        return response()->json(['data' => $company]);
    }

    private function fetchCompany(string $taxCode): array
    {
        try {
            $response = Http::acceptJson()->connectTimeout(3)->timeout(8)
                ->withOptions(['allow_redirects' => false])
                ->get('https://api.vietqr.io/v2/business/'.$taxCode);
        } catch (ConnectionException) {
            abort(503, self::UNAVAILABLE);
        }

        if ($response->status() === 429) {
            $retryAfter = $response->header('Retry-After');
            abort(503, 'Dịch vụ tra cứu đang quá tải. Vui lòng thử lại sau.', [
                'Retry-After' => ctype_digit($retryAfter) ? (string) min(3600, max(1, (int) $retryAfter)) : '60',
            ]);
        }
        abort_if($response->json('code') === '51', 404, 'Không tìm thấy doanh nghiệp với mã số thuế này. Bạn vẫn có thể nhập thông tin thủ công.');
        abort_unless($response->successful() && $response->json('code') === '00', 503, self::UNAVAILABLE);

        $data = $response->json('data');
        abort_unless(is_array($data), 503, self::UNAVAILABLE);
        foreach (['id', 'name', 'address'] as $field) {
            abort_unless(isset($data[$field]) && is_string($data[$field]) && trim($data[$field]) !== '', 503,
                'Dữ liệu doanh nghiệp chưa đầy đủ. Vui lòng nhập tên và địa chỉ thủ công.');
        }
        abort_unless(str_replace('-', '', $data['id']) === str_replace('-', '', $taxCode), 503, self::UNAVAILABLE);

        return ['tax_code' => $taxCode, 'name' => trim($data['name']), 'address' => trim($data['address'])];
    }
}
