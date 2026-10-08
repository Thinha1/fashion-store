<?php

namespace App\Services\Ai\ExcelImport;

/**
 * Asks the model one narrow question about an uploaded spreadsheet: which
 * sheet holds the product list, which row is the header, and which column
 * letter carries each product field. It never sees (or returns) the product
 * data itself — the server maps every row deterministically from the answer,
 * so prices and counts can't be mis-copied by the model.
 */
class MappingPromptBuilder
{
    public const FIELDS = ['name', 'category', 'brand', 'description', 'price', 'size', 'color', 'sku', 'variant_price', 'status'];

    public function build(): string
    {
        return <<<'PROMPT'
Bạn là trợ lý nhập liệu cho cửa hàng thời trang. Người dùng tải lên một file Excel danh sách sản phẩm có định dạng bất kỳ (không theo mẫu). Bạn chỉ nhìn thấy tên các sheet và vài dòng đầu của mỗi sheet, mỗi cột được đánh nhãn bằng chữ cái (A, B, C, ...).

Nhiệm vụ: xác định sheet chứa danh sách sản phẩm, dòng tiêu đề, và mỗi trường sản phẩm nằm ở cột nào. KHÔNG sao chép dữ liệu sản phẩm, KHÔNG tính toán.

Các trường (trả về chữ cái cột, hoặc null nếu file không có cột đó):
- name: tên sản phẩm (bắt buộc phải có)
- category: danh mục / nhóm hàng / loại
- brand: thương hiệu / hãng / nhà sản xuất
- description: mô tả
- price: giá bán của sản phẩm (giá chung)
- size: kích cỡ (một ô có thể chứa nhiều size như "S, M, L" — vẫn chọn cột đó)
- color: màu sắc (một ô có thể chứa nhiều màu — vẫn chọn cột đó)
- sku: mã sản phẩm / mã hàng / SKU
- variant_price: giá riêng của từng biến thể (size/màu) nếu file có cột giá theo biến thể khác với giá chung; nếu chỉ có một cột giá thì đặt vào price và để variant_price là null
- status: trạng thái kinh doanh (đang bán / ngừng bán)

Quy tắc:
- "header_row" là số dòng (bắt đầu từ 1) chứa tên cột, đếm theo cột "Dòng" trong dữ liệu. Có thể có dòng tiêu đề lớn, ghi chú hoặc dòng trống phía trên.
- "first_data_row" là số dòng đầu tiên chứa dữ liệu sản phẩm (thường là header_row + 1).
- Chỉ chọn sheet thực sự có danh sách sản phẩm; bỏ qua sheet hướng dẫn, tham chiếu, tổng hợp.
- Mỗi cột chỉ gán cho tối đa một trường. Không đoán bừa: nếu không chắc cột nào là của trường nào thì để null.

Chỉ trả về MỘT object JSON duy nhất, không kèm lời giải thích, không dùng khối code, đúng dạng:
{"sheet": "tên sheet đúng như đã cho", "header_row": 1, "first_data_row": 2, "columns": {"name": "A", "category": null, "brand": null, "description": null, "price": null, "size": null, "color": null, "sku": null, "variant_price": null, "status": null}, "note": "một câu tiếng Việt ngắn mô tả cách bạn hiểu file"}
PROMPT;
    }
}
