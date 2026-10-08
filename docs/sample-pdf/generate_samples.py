"""Builds the sample PDFs for trying PDF input in the product chat widget.

    python docs/sample-pdf/generate_samples.py      (needs: pip install pymupdf)

Uses Arial from the system fonts so the Vietnamese text keeps a real text layer.
The category/brand names match the demo seed data. File 3 is a pure image on
purpose: it has no text layer, so the widget must say it cannot read it.
"""
import os

import pymupdf as fitz  # PyMuPDF

HERE = os.path.dirname(os.path.abspath(__file__))
FONT = 'C:/Windows/Fonts/arial.ttf'
BOLD = 'C:/Windows/Fonts/arialbd.ttf'


def save(doc, name):
    # Arial is large; subsetting keeps each sample to a few KB (needs fontTools, optional).
    try:
        doc.subset_fonts()
    except Exception:
        pass
    doc.save(os.path.join(HERE, name), garbage=4, deflate=True)
    doc.close()


def new_page(doc):
    page = doc.new_page(width=595, height=842)
    page.insert_font(fontname='f', fontfile=FONT)
    page.insert_font(fontname='fb', fontfile=BOLD)
    return page


def write(page, y, text, size=11, bold=False, x=56):
    page.insert_text((x, y), text, fontname='fb' if bold else 'f', fontsize=size)
    return y + size * 1.7


def lines(page, y, rows, size=11, x=56):
    for row in rows:
        y = write(page, y, row, size, x=x)
    return y


# ---------------------------------------------------------------------------
# 1) One product spec sheet
# ---------------------------------------------------------------------------
doc = fitz.open()
page = new_page(doc)
y = write(page, 70, 'PHIẾU THÔNG SỐ SẢN PHẨM', 18, True)
y = write(page, y + 4, 'Mã mẫu: SML-2410 · Ngày cập nhật: 08/10/2026', 10)
y += 14
y = lines(page, y, [
    'Tên sản phẩm: Áo sơ mi linen tay dài',
    'Nhóm hàng: Áo nam',
    'Thương hiệu: Lặng Studio',
    'Chất liệu: 70% linen, 30% cotton',
    'Xuất xứ: Việt Nam',
], 12)
y += 10
y = write(page, y, 'Mô tả', 13, True)
y = lines(page, y, [
    'Áo sơ mi linen dáng suông, tay dài, cổ đứng nhẹ. Vải linen pha cotton mềm,',
    'thoáng và ít nhăn hơn linen thường, phù hợp đi làm lẫn đi chơi.',
])
y += 10
y = write(page, y, 'Điểm nổi bật', 13, True)
y = lines(page, y, [
    '• Vải linen pha cotton thoáng mát, thấm hút tốt',
    '• Form suông thoải mái, dễ phối quần tây hoặc jeans',
    '• Cúc gỗ, đường may tỉ mỉ',
])
y += 10
y = write(page, y, 'Size và màu', 13, True)
y = lines(page, y, [
    'Size có sẵn: S, M, L, XL',
    'Màu: Be, Xanh navy, Trắng',
])
y += 10
y = write(page, y, 'Giá', 13, True)
y = lines(page, y, [
    'Giá bán lẻ đề xuất: 349.000đ',
    'Giá sỉ (từ 10 cái): 280.000đ',
])
y += 10
y = write(page, y, 'Bảo quản', 13, True)
lines(page, y, ['Giặt máy chế độ nhẹ dưới 30°C, không dùng chất tẩy, ủi ở nhiệt độ thấp.'])
save(doc, '01-phieu-thong-so-ao-so-mi.pdf')

# ---------------------------------------------------------------------------
# 2) A supplier catalog with three products (the user picks one in chat)
# ---------------------------------------------------------------------------
doc = fitz.open()
page = new_page(doc)
y = write(page, 90, 'CÔNG TY TNHH THỜI TRANG ĐẠI PHÁT', 20, True)
y = write(page, y + 6, 'CATALOG HÀNG MỚI — THÁNG 10/2026', 14)
y += 20
y = lines(page, y, [
    'Liên hệ đặt hàng: 0901 234 567 · sales@daiphat.example',
    'Giá đã gồm VAT. Giao hàng trong 3–5 ngày làm việc. Đổi trả trong 7 ngày.',
    '',
    'Trong catalog này:',
    '  1. Áo thun cổ tròn Basic',
    '  2. Váy liền dáng suông',
    '  3. Quần tây ống đứng',
], 12)

entries = [
    ('1. Áo thun cổ tròn Basic', [
        'Nhóm hàng: Áo nam · Thương hiệu: Mộc Daily',
        'Chất liệu: cotton 100%, định lượng 220gsm, co giãn nhẹ.',
        'Mô tả: áo thun cổ tròn form regular, vải dày dặn không xù lông, bền màu sau nhiều lần giặt.',
        'Size: S, M, L, XL, 2XL',
        'Màu: Trắng, Đen, Xám',
        'Giá bán lẻ: 199.000đ',
    ]),
    ('2. Váy liền dáng suông', [
        'Nhóm hàng: Váy liền · Thương hiệu: Dải Nắng',
        'Chất liệu: vải thô mềm, có lót.',
        'Mô tả: váy suông cổ tròn, dài qua gối, che khuyết điểm tốt, dễ mặc đi làm hoặc đi chơi.',
        'Size: S, M, L',
        'Màu: Be, Đen',
        'Giá bán lẻ: 690.000đ',
    ]),
    ('3. Quần tây ống đứng', [
        'Nhóm hàng: Quần nam · Thương hiệu: Northline',
        'Chất liệu: vải tuyết mưa, đứng form, ít nhăn.',
        'Mô tả: quần tây ống đứng lưng cao, có ly, phù hợp công sở.',
        'Size: L, XL',
        'Màu: Xám, Đen',
        'Giá bán lẻ: 1.250.000đ',
    ]),
]
for title, rows in entries:
    page = new_page(doc)
    y = write(page, 90, title, 18, True)
    lines(page, y + 14, rows, 12)
save(doc, '02-catalog-nha-cung-cap.pdf')

# ---------------------------------------------------------------------------
# 3) A scan: the same spec sheet rendered to a picture, no text layer at all
# ---------------------------------------------------------------------------
source = fitz.open(os.path.join(HERE, '01-phieu-thong-so-ao-so-mi.pdf'))
picture = source[0].get_pixmap(dpi=100).tobytes('jpeg', jpg_quality=55)
source.close()
doc = fitz.open()
page = doc.new_page(width=595, height=842)
page.insert_image(page.rect, stream=picture)
save(doc, '03-ban-scan-khong-co-chu.pdf')

print('Đã tạo 3 file PDF mẫu trong docs/sample-pdf/')
