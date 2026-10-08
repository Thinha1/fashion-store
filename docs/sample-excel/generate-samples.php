<?php

/**
 * Builds the two sample spreadsheets for trying the AI Excel import in the
 * product chat widget. Both deliberately do NOT follow the shop's own Excel
 * template — that is the point of the feature.
 *
 *   docker compose exec app php docs/sample-excel/generate-samples.php
 *
 * The category/brand names match the demo seed data (CatalogDemoSeeder); a few
 * rows are deliberately unimportable so the preview shows its warnings.
 */

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

require __DIR__.'/../../vendor/autoload.php';

/** @param list<list<string|int|float|null>> $rows */
function writeSheet(Spreadsheet $book, string $title, array $rows, bool $first = false): void
{
    $sheet = $first ? $book->getActiveSheet() : $book->createSheet();
    $sheet->setTitle($title);
    foreach ($rows as $r => $row) {
        foreach ($row as $c => $value) {
            if ($value !== null) {
                $sheet->getCell([$c + 1, $r + 1])->setValueExplicit($value, is_string($value) ? DataType::TYPE_STRING : DataType::TYPE_NUMERIC);
            }
        }
    }
}

// ---------------------------------------------------------------------------
// 1) A supplier price list: banner rows above the header, one row per variant,
//    product name only on the first row of each product, prices as text,
//    a total row at the bottom, and a second sheet the AI must ignore.
// ---------------------------------------------------------------------------
$book = new Spreadsheet;
writeSheet($book, 'Bảng giá T10', [
    ['CÔNG TY TNHH THỜI TRANG ĐẠI PHÁT — BẢNG GIÁ HÀNG MỚI THÁNG 10', null, null, null, null, null, null, null, null],
    ['Liên hệ: 0901 234 567 · Giá đã gồm VAT', null, null, null, null, null, null, null, null],
    [],
    ['STT', 'Tên hàng', 'Nhóm hàng', 'Hãng', 'Size', 'Màu', 'Mã hàng', 'Giá bán lẻ', 'Ghi chú'],
    [1, 'Áo thun cổ tròn Basic', 'Áo nam', 'Mộc Daily', 'M', 'Trắng', 'ATB-M-TRANG', '199.000đ', 'Cotton 100%'],
    [null, null, null, null, 'L', 'Trắng', 'ATB-L-TRANG', '199.000đ', null],
    [null, null, null, null, 'M', 'Đen', null, '199.000đ', null],
    [null, null, null, null, 'L', 'Đen', null, '199.000đ', null],
    [null, null, null, null, 'XXL', 'Đen', null, '219.000đ', 'Size lớn +20k'],
    [2, 'Áo sơ mi linen tay dài', 'Áo nam', 'Lặng Studio', 'M', 'Be', 'SML-M-BE', '349k', null],
    [null, null, null, null, 'L', 'Be', 'SML-L-BE', '349k', null],
    [null, null, null, null, 'XL', 'Xanh navy', 'SML-XL-NAVY', '369k', null],
    [3, 'Quần tây ống đứng', 'Quần nam', 'Northline', 'L', 'Xám', 'QTD-L-XAM', '1,25tr', null],
    [null, null, null, null, 'XL', 'Xám', 'QTD-XL-XAM', '1,25tr', null],
    [4, 'Nón lưỡi trai thêu logo', 'Phụ kiện', 'Mộc Daily', 'M', 'Đen', 'NLT-M-DEN', '120.000đ', 'Danh mục chưa có trong cửa hàng'],
    [null, 'TỔNG CỘNG', null, null, null, null, null, null, null],
], true);
writeSheet($book, 'Điều khoản', [
    ['Điều khoản giao hàng'],
    ['Giao trong 3-5 ngày làm việc. Đổi trả trong 7 ngày.'],
]);
$book->getSheetByName('Bảng giá T10')->mergeCells('A1:I1');
$book->getSheetByName('Bảng giá T10')->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$book->setActiveSheetIndex(0);
(new Xlsx($book))->save(__DIR__.'/01-bang-gia-nha-cung-cap.xlsx');
$book->disconnectWorksheets();

// ---------------------------------------------------------------------------
// 2) A shop owner's own list: English headers, one row per product, sizes and
//    colors packed into single cells, mixed price styles and a status column.
// ---------------------------------------------------------------------------
$book = new Spreadsheet;
writeSheet($book, 'products', [
    ['Product name', 'Category', 'Brand', 'Price (VND)', 'Sizes', 'Colors', 'Short description', 'Status'],
    ['Váy liền dáng suông', 'Váy liền', 'Dải Nắng', '690k', 'S, M, L', 'Trắng / Đen', 'Váy suông chất vải thô mềm, dễ phối đồ.', 'Đang kinh doanh'],
    ['Áo len cổ lọ', 'Áo len & cardigan', 'Lặng Studio', '1.150.000', 'M, L, XL', 'Be, Nâu', 'Len mịn, giữ ấm tốt.', 'Đang kinh doanh'],
    ['Áo hoodie unisex form rộng', 'Áo unisex', 'Mộc Daily', '450000', 'M, L, XL, 2XL', 'Xám / Đen', 'Nỉ bông dày, form rộng.', 'Ngừng bán'],
    ['Áo khoác dù mỏng', 'Áo nam', 'Northline', '2tr', 'FREE SIZE', 'Đen', 'Size không thuộc danh sách của cửa hàng.', 'Đang kinh doanh'],
    ['Funny ChatGPT Shirt, AI Humor Tee, Tech Lover Gift', 'Áo unisex', 'Mộc Daily', '300k', 'M, L', 'Đen', 'Trùng tên với một sản phẩm đã có trong cửa hàng.', 'Đang kinh doanh'],
], true);
(new Xlsx($book))->save(__DIR__.'/02-san-pham-mot-dong.xlsx');
$book->disconnectWorksheets();

echo "Đã tạo 2 file mẫu trong docs/sample-excel/\n";
