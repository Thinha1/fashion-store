<?php

use App\Actions\AdminExcel;
use App\Http\Controllers\Admin\AiSettingController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\BrandController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DiscountController;
use App\Http\Controllers\Admin\ExcelController;
use App\Http\Controllers\Admin\GoodsReceiptController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\PaymentSettingController;
use App\Http\Controllers\Admin\ProductAiAssistController;
use App\Http\Controllers\Admin\ProductAiAssistStreamController;
use App\Http\Controllers\Admin\ProductAiPdfController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SizeChartController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Admin\SupplierController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')
    ->middleware(['auth', 'verified'])
    ->name('admin.')
    ->group(function (): void {
        Route::prefix('excel/{resource}')
            ->whereIn('resource', array_keys(AdminExcel::resources()))
            ->name('excel.')
            ->group(function (): void {
                Route::get('xuat', [ExcelController::class, 'export'])->name('export');
                Route::get('file-mau', [ExcelController::class, 'template'])->name('template');
                Route::post('nhap', [ExcelController::class, 'import'])->middleware('throttle:10,1')->name('import');
            });

        // Audit trail — read-only, needs audit.view.
        Route::middleware('permission:audit.view')
            ->get('nhat-ky', [AuditLogController::class, 'index'])->name('audit-logs.index');
        Route::middleware('permission:audit.view')
            ->get('nhat-ky/{auditLog}', [AuditLogController::class, 'show'])->name('audit-logs.show');
        // Dashboard — needs the admin.access permission.
        Route::middleware('permission:admin.access')
            ->get('dashboard', DashboardController::class)
            ->name('dashboard');

        // Brand
        Route::middleware('permission:catalog.manage')
            ->get('thuong-hieu', [BrandController::class, 'index'])->name('brands.index');
        Route::middleware('permission:catalog.manage')
            ->get('thuong-hieu/tao-moi', [BrandController::class, 'create'])->name('brands.create');
        Route::middleware('permission:catalog.manage')
            ->post('thuong-hieu', [BrandController::class, 'store'])->name('brands.store');
        Route::middleware('permission:catalog.manage')
            ->get('thuong-hieu/{brand}', [BrandController::class, 'show'])->name('brands.show');
        Route::middleware('permission:catalog.manage')
            ->get('thuong-hieu/{brand}/sua', [BrandController::class, 'edit'])->name('brands.edit');
        Route::middleware('permission:catalog.manage')
            ->put('thuong-hieu/{brand}', [BrandController::class, 'update'])->name('brands.update');
        Route::middleware('permission:catalog.manage')
            ->delete('thuong-hieu/{brand}', [BrandController::class, 'destroy'])->name('brands.destroy');

        // Size charts — shown on product pages, matched by brand/category.
        Route::middleware('permission:catalog.manage')->prefix('bang-size')->name('size-charts.')
            ->group(function (): void {
                Route::get('/', [SizeChartController::class, 'index'])->name('index');
                Route::get('tao-moi', [SizeChartController::class, 'create'])->name('create');
                Route::post('/', [SizeChartController::class, 'store'])->name('store');
                Route::get('{sizeChart}/sua', [SizeChartController::class, 'edit'])->name('edit');
                Route::put('{sizeChart}', [SizeChartController::class, 'update'])->name('update');
                Route::delete('{sizeChart}', [SizeChartController::class, 'destroy'])->name('destroy');
            });

        // Roles and the permissions they grant; staff accounts and their role.
        Route::middleware('permission:roles.manage')->prefix('vai-tro')->name('roles.')
            ->group(function (): void {
                Route::get('/', [RoleController::class, 'index'])->name('index');
                Route::get('tao-moi', [RoleController::class, 'create'])->name('create');
                Route::post('/', [RoleController::class, 'store'])->name('store');
                Route::get('{role}/sua', [RoleController::class, 'edit'])->name('edit');
                Route::put('{role}', [RoleController::class, 'update'])->name('update');
                Route::delete('{role}', [RoleController::class, 'destroy'])->name('destroy');
            });
        Route::middleware('permission:staff.manage')->prefix('nhan-vien')->name('staff.')
            ->group(function (): void {
                Route::get('/', [StaffController::class, 'index'])->name('index');
                Route::get('tao-moi', [StaffController::class, 'create'])->name('create');
                Route::post('/', [StaffController::class, 'store'])->name('store');
                Route::get('{staff}/sua', [StaffController::class, 'edit'])->name('edit');
                Route::put('{staff}', [StaffController::class, 'update'])->name('update');
            });

        // Category
        Route::middleware('permission:catalog.manage')
            ->get('danh-muc', [CategoryController::class, 'index'])->name('categories.index');
        Route::middleware('permission:catalog.manage')
            ->get('danh-muc/tao-moi', [CategoryController::class, 'create'])->name('categories.create');
        Route::middleware('permission:catalog.manage')
            ->post('danh-muc', [CategoryController::class, 'store'])->name('categories.store');
        Route::middleware('permission:catalog.manage')
            ->get('danh-muc/{category}/sua', [CategoryController::class, 'edit'])->name('categories.edit');
        Route::middleware('permission:catalog.manage')
            ->put('danh-muc/{category}', [CategoryController::class, 'update'])->name('categories.update');
        Route::middleware('permission:catalog.manage')
            ->delete('danh-muc/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');

        // Supplier
        Route::prefix('nha-cong-cap')->name('suppliers.')
            ->middleware('permission:suppliers.manage')
            ->group(function (): void {
                Route::get('tra-cuu-ma-so-thue', [SupplierController::class, 'taxLookup'])
                    ->middleware('throttle:supplier-tax-lookup')->name('tax-lookup');
                Route::get('/', [SupplierController::class, 'index'])->name('index');
                Route::get('tao-moi', [SupplierController::class, 'create'])->name('create');
                Route::post('/', [SupplierController::class, 'store'])->name('store');
                Route::get('{supplier}/sua', [SupplierController::class, 'edit'])->name('edit');
                Route::put('{supplier}', [SupplierController::class, 'update'])->name('update');
                Route::delete('{supplier}', [SupplierController::class, 'destroy'])->name('destroy');
            });

        // Product (with variants + images)
        Route::middleware(['permission:ai.assistant', 'throttle:product-ai-assist'])
            ->post('san-pham/ai-goi-y', ProductAiAssistController::class)->name('products.ai-assist');
        // SSE variant of the same call — same rate limiter/permission, kept
        // as a separate route+controller so the JSON one above (and its
        // tests) never has to change shape; see ProductDraftResolver for the
        // parsing/validation logic both share.
        Route::middleware(['permission:ai.assistant', 'throttle:product-ai-assist'])
            ->post('san-pham/ai-goi-y/stream', ProductAiAssistStreamController::class)->name('products.ai-assist-stream');
        // Extracts the text of an uploaded PDF so the chat can attach it to the
        // next message. Calls no model and saves nothing.
        Route::middleware(['permission:ai.assistant', 'throttle:product-ai-assist'])
            ->post('san-pham/ai-pdf/doc-file', ProductAiPdfController::class)->name('products.ai-pdf.read');
        Route::middleware('permission:products.manage')
            ->get('san-pham', [ProductController::class, 'index'])->name('products.index');
        Route::middleware('permission:products.manage')
            ->get('san-pham/tao-moi', [ProductController::class, 'create'])->name('products.create');
        Route::middleware('permission:products.manage')
            ->post('san-pham', [ProductController::class, 'store'])->name('products.store');
        Route::middleware('permission:products.manage')
            ->get('san-pham/{product}', [ProductController::class, 'show'])->name('products.show');
        Route::middleware('permission:products.manage')
            ->get('san-pham/{product}/sua', [ProductController::class, 'edit'])->name('products.edit');
        Route::middleware('permission:products.manage')
            ->put('san-pham/{product}', [ProductController::class, 'update'])->name('products.update');
        Route::middleware('permission:products.manage')
            ->patch('san-pham/{product}/noi-bat', [ProductController::class, 'updateFeatured'])->name('products.featured');
        Route::middleware('permission:products.manage')
            ->delete('san-pham/{product}', [ProductController::class, 'destroy'])->name('products.destroy');

        // Goods receipt
        Route::middleware('permission:inventory.manage')
            ->get('nhap-hang', [GoodsReceiptController::class, 'index'])->name('goods-receipts.index');
        Route::middleware('permission:inventory.manage')
            ->get('nhap-hang/tao-moi', [GoodsReceiptController::class, 'create'])->name('goods-receipts.create');
        Route::middleware('permission:inventory.manage')
            ->post('nhap-hang', [GoodsReceiptController::class, 'store'])->name('goods-receipts.store');
        Route::middleware('permission:inventory.manage')
            ->get('nhap-hang/{goodsReceipt}', [GoodsReceiptController::class, 'show'])->name('goods-receipts.show');
        Route::middleware('permission:inventory.manage')
            ->get('nhap-hang/{goodsReceipt}/sua', [GoodsReceiptController::class, 'edit'])->name('goods-receipts.edit');
        Route::middleware('permission:inventory.manage')
            ->put('nhap-hang/{goodsReceipt}', [GoodsReceiptController::class, 'update'])->name('goods-receipts.update');
        Route::middleware('permission:inventory.manage')
            ->delete('nhap-hang/{goodsReceipt}', [GoodsReceiptController::class, 'destroy'])->name('goods-receipts.destroy');
        Route::middleware('permission:inventory.confirm')
            ->post('nhap-hang/{goodsReceipt}/xac-nhan', [GoodsReceiptController::class, 'confirm'])->name('goods-receipts.confirm');

        // Discount (scope=variant only in Giai đoạn 2)
        Route::middleware('permission:discounts.manage')
            ->get('giam-gia', [DiscountController::class, 'index'])->name('discounts.index');
        Route::middleware('permission:discounts.manage')
            ->get('giam-gia/tao-moi', [DiscountController::class, 'create'])->name('discounts.create');
        Route::middleware('permission:discounts.manage')
            ->post('giam-gia', [DiscountController::class, 'store'])->name('discounts.store');
        Route::middleware('permission:discounts.manage')
            ->get('giam-gia/{discount}/sua', [DiscountController::class, 'edit'])->name('discounts.edit');
        Route::middleware('permission:discounts.manage')
            ->put('giam-gia/{discount}', [DiscountController::class, 'update'])->name('discounts.update');
        Route::middleware('permission:discounts.manage')
            ->delete('giam-gia/{discount}', [DiscountController::class, 'destroy'])->name('discounts.destroy');

        // Orders — staff move them one step at a time (see ChangeOrderStatus).
        Route::prefix('don-hang')->name('orders.')
            ->middleware('permission:orders.view')
            ->group(function (): void {
                Route::get('/', [OrderController::class, 'index'])->name('index');
                Route::get('{order}', [OrderController::class, 'show'])->name('show');
                Route::patch('{order}/trang-thai', [OrderController::class, 'updateStatus'])->middleware('permission:orders.manage')->name('status');
                // A transfer the SePay webhook couldn't match, confirmed by hand.
                Route::patch('{order}/thanh-toan', [OrderController::class, 'confirmPayment'])->middleware('permission:payments.confirm')->name('confirm-payment');
            });

        // Settings — the AI backend and the payment account. Each has its own
        // permission (settings.ai / settings.payment): an API key or a bank
        // account is a different trust tier than catalog editing.
        Route::prefix('cai-dat')->name('settings.')
            ->group(function (): void {
                Route::get('ai', [AiSettingController::class, 'edit'])->middleware('permission:settings.ai')->name('ai.edit');
                Route::post('ai', [AiSettingController::class, 'store'])->middleware('permission:settings.ai')->name('ai.store');
                Route::post('ai/kiem-tra', [AiSettingController::class, 'test'])
                    ->middleware('throttle:6,1')->middleware('permission:settings.ai')->name('ai.test');
                Route::put('ai/{aiSetting}', [AiSettingController::class, 'update'])->middleware('permission:settings.ai')->name('ai.update');
                Route::post('ai/{aiSetting}/chinh', [AiSettingController::class, 'makePrimary'])->middleware('permission:settings.ai')->name('ai.primary');
                Route::delete('ai/{aiSetting}', [AiSettingController::class, 'destroy'])->middleware('permission:settings.ai')->name('ai.destroy');
                // Bank account for the VietQR code + the SePay webhook key.
                Route::get('thanh-toan', [PaymentSettingController::class, 'edit'])->middleware('permission:settings.payment')->name('payment.edit');
                Route::put('thanh-toan', [PaymentSettingController::class, 'update'])->middleware('permission:settings.payment')->name('payment.update');
                Route::post('thanh-toan/kiem-tra', [PaymentSettingController::class, 'test'])
                    ->middleware('throttle:6,1')->middleware('permission:settings.payment')->name('payment.test');
            });
    });
