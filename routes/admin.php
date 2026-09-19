<?php

use App\Actions\AdminExcel;
use App\Http\Controllers\Admin\BrandController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DiscountController;
use App\Http\Controllers\Admin\ExcelController;
use App\Http\Controllers\Admin\GoodsReceiptController;
use App\Http\Controllers\Admin\ProductAiAssistController;
use App\Http\Controllers\Admin\ProductAiAssistStreamController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\SupplierController;
use App\Http\Controllers\Admin\SupplierTaxLookupController;
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

        // Dashboard — needs the admin.access permission.
        Route::middleware('permission:admin.access')
            ->get('dashboard', DashboardController::class)
            ->name('dashboard');

        // Brand
        Route::middleware('permission:products.manage')
            ->get('thuong-hieu', [BrandController::class, 'index'])->name('brands.index');
        Route::middleware('permission:products.manage')
            ->get('thuong-hieu/tao-moi', [BrandController::class, 'create'])->name('brands.create');
        Route::middleware('permission:products.manage')
            ->post('thuong-hieu', [BrandController::class, 'store'])->name('brands.store');
        Route::middleware('permission:products.manage')
            ->get('thuong-hieu/{brand}', [BrandController::class, 'show'])->name('brands.show');
        Route::middleware('permission:products.manage')
            ->get('thuong-hieu/{brand}/sua', [BrandController::class, 'edit'])->name('brands.edit');
        Route::middleware('permission:products.manage')
            ->put('thuong-hieu/{brand}', [BrandController::class, 'update'])->name('brands.update');
        Route::middleware('permission:products.manage')
            ->delete('thuong-hieu/{brand}', [BrandController::class, 'destroy'])->name('brands.destroy');

        // Category
        Route::middleware('permission:products.manage')
            ->get('danh-muc', [CategoryController::class, 'index'])->name('categories.index');
        Route::middleware('permission:products.manage')
            ->get('danh-muc/tao-moi', [CategoryController::class, 'create'])->name('categories.create');
        Route::middleware('permission:products.manage')
            ->post('danh-muc', [CategoryController::class, 'store'])->name('categories.store');
        Route::middleware('permission:products.manage')
            ->get('danh-muc/{category}/sua', [CategoryController::class, 'edit'])->name('categories.edit');
        Route::middleware('permission:products.manage')
            ->put('danh-muc/{category}', [CategoryController::class, 'update'])->name('categories.update');
        Route::middleware('permission:products.manage')
            ->delete('danh-muc/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');

        // Supplier
        Route::prefix('nha-cong-cap')->name('suppliers.')
            ->middleware('permission:suppliers.manage')
            ->group(function (): void {
                Route::get('tra-cuu-ma-so-thue', SupplierTaxLookupController::class)
                    ->middleware('throttle:supplier-tax-lookup')->name('tax-lookup');
                Route::get('/', [SupplierController::class, 'index'])->name('index');
                Route::get('tao-moi', [SupplierController::class, 'create'])->name('create');
                Route::post('/', [SupplierController::class, 'store'])->name('store');
                Route::get('{supplier}/sua', [SupplierController::class, 'edit'])->name('edit');
                Route::put('{supplier}', [SupplierController::class, 'update'])->name('update');
                Route::delete('{supplier}', [SupplierController::class, 'destroy'])->name('destroy');
            });

        // Product (with variants + images)
        Route::middleware(['permission:products.manage', 'throttle:product-ai-assist'])
            ->post('san-pham/ai-goi-y', ProductAiAssistController::class)->name('products.ai-assist');
        // SSE variant of the same call — same rate limiter/permission, kept
        // as a separate route+controller so the JSON one above (and its
        // tests) never has to change shape; see ProductDraftResolver for the
        // parsing/validation logic both share.
        Route::middleware(['permission:products.manage', 'throttle:product-ai-assist'])
            ->post('san-pham/ai-goi-y/stream', ProductAiAssistStreamController::class)->name('products.ai-assist-stream');
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
        Route::middleware('permission:inventory.manage')
            ->post('nhap-hang/{goodsReceipt}/xac-nhan', [GoodsReceiptController::class, 'confirm'])->name('goods-receipts.confirm');

        // Discount (scope=variant only in Giai đoạn 2)
        Route::middleware('permission:products.manage')
            ->get('giam-gia', [DiscountController::class, 'index'])->name('discounts.index');
        Route::middleware('permission:products.manage')
            ->get('giam-gia/tao-moi', [DiscountController::class, 'create'])->name('discounts.create');
        Route::middleware('permission:products.manage')
            ->post('giam-gia', [DiscountController::class, 'store'])->name('discounts.store');
        Route::middleware('permission:products.manage')
            ->get('giam-gia/{discount}/sua', [DiscountController::class, 'edit'])->name('discounts.edit');
        Route::middleware('permission:products.manage')
            ->put('giam-gia/{discount}', [DiscountController::class, 'update'])->name('discounts.update');
        Route::middleware('permission:products.manage')
            ->delete('giam-gia/{discount}', [DiscountController::class, 'destroy'])->name('discounts.destroy');
    });
