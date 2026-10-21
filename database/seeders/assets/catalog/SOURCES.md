# Nguồn dữ liệu catalog demo

Catalog demo lấy từ sản phẩm quần áo thật đang bán trên trang Việt Nam của Coolmate, Yame, Nike và Puma (tháng 10/2026): tên, giá niêm yết và giá khuyến mãi, màu, size, mô tả và ảnh. Dữ liệu được lấy theo `robots.txt` của từng trang (Coolmate: trang sản phẩm; Yame: `products.json` của Shopify; Nike: trang danh sách `/w/`, không vào trang chi tiết bị chặn; Puma: JSON-LD trên trang sản phẩm). Adidas Việt Nam chặn truy cập tự động nên không có trong catalog.

Ảnh đã cắt về tỉ lệ 4:5 (640×800). Mỗi màu có một ảnh riêng, đặt tên `<slug>-<màu>.jpg`; ảnh phụ là `<slug>-2.jpg`, `<slug>-3.jpg`. Mô tả sản phẩm Nike do nhóm tự viết vì trang danh sách không có mô tả. Size số của quần (29, 30…) được quy về S–XL cho khớp danh sách size của hệ thống. Tồn kho là số giả lập.

**Ảnh và nội dung thuộc bản quyền của các thương hiệu, chỉ dùng cho đồ án môn học, không dùng cho mục đích thương mại hay triển khai công khai.**

| Thương hiệu | Sản phẩm | Nguồn |
| --- | --- | --- |
| Coolmate | Áo thun nam Premium Cotton | [www.coolmate.me](https://www.coolmate.me/product/ao-thun-nam-premium-cotton) |
| Coolmate | Áo Polo nam Premium Pique | [www.coolmate.me](https://www.coolmate.me/product/ao-polo-nam-premium-pique) |
| Coolmate | Áo sơ mi dài tay Oxford Premium | [www.coolmate.me](https://www.coolmate.me/product/ao-so-mi-nam-dai-tay-oxford-premium) |
| Coolmate | Áo sơ mi Flannel 100% Cotton | [www.coolmate.me](https://www.coolmate.me/product/ao-so-mi-flannel-100-cotton) |
| Coolmate | Áo sơ mi Overshirt 100% Cotton | [www.coolmate.me](https://www.coolmate.me/product/ao-so-mi-overshirt-100-cotton) |
| Coolmate | Áo Hoodie Essential | [www.coolmate.me](https://www.coolmate.me/product/ao-hoodie-essential) |
| Coolmate | Áo khoác nam Track Jacket Windbreaker | [www.coolmate.me](https://www.coolmate.me/product/ao-khoac-nam-track-jacket-windbreaker) |
| Coolmate | Quần Jeans Nam Basics dáng Slim fit | [www.coolmate.me](https://www.coolmate.me/product/quan-jeans-nam-basics-dang-slim-fit) |
| Coolmate | Quần Dài Nam Kaki Excool dáng Straight | [www.coolmate.me](https://www.coolmate.me/product/quan-dai-nam-kaki-excool-dang-regular-straight) |
| Coolmate | Quần Shorts Daily Casual | [www.coolmate.me](https://www.coolmate.me/product/quan-shorts-daily-casual) |
| Coolmate | Quần Jogger Nỉ French Terry | [www.coolmate.me](https://www.coolmate.me/product/quan-jogger-ni-french-terry) |
| Coolmate | Áo thun nữ Baby Tee 100% Cotton Mèo lười | [www.coolmate.me](https://www.coolmate.me/product/ao-thun-nu-baby-tee-100-cotton-meo-luoi) |
| Coolmate | Quần wide leg nữ Flex Active | [www.coolmate.me](https://www.coolmate.me/product/quan-wide-leg-nu-flex-active) |
| Coolmate | Áo khoác thể thao nữ Studio | [www.coolmate.me](https://www.coolmate.me/product/ao-khoac-the-thao-nu-studio) |
| Yame | Áo thun cổ tròn Basic FlexFit | [yame.vn](https://yame.vn/products/ao-thun-flexfit-basic-cotton-mem-min-co-gian-non-branded-059-mau-xam-trang-phom-om-vua-f2-2625252) |
| Yame | Áo thun Raglan phối màu | [yame.vn](https://yame.vn/products/ao-thun-cool-comfort-vai-thun-hai-mat-phom-hop-rong-f5-the-no-style-251-mau-xanh-duong-dam-2625151) |
| Yame | Áo thun cổ Henley CloudTouch | [yame.vn](https://yame.vn/products/ao-thun-cloud-touch-mem-min-than-tron-phom-om-vua-f2-the-weekend-055-mau-xanh-duong-dam-2625162) |
| Yame | Áo polo cá sấu ColorLock | [yame.vn](https://yame.vn/products/ao-thun-polo-colorlock-co-phoi-mau-co-gian-thoang-khi-non-branded-058-mau-xanh-la-nhat-phom-vua-van-f3-2625090) |
| Yame | Sơ mi Oxford tay dài EasyCare | [yame.vn](https://yame.vn/products/ao-so-mi-tay-dai-easycare-oxford-thoang-mat-it-nhan-the-no-style-266-mau-den-99-dang-vua-van-f3-2625119) |
| Yame | Sơ mi tay ngắn AirDry hiệu ứng linen | [yame.vn](https://yame.vn/products/ao-so-mi-tay-ngan-vai-gia-dui-thoang-khi-phom-rong-f4-the-weekend-056-mau-beige-2625293) |
| Yame | Áo khoác dù Ripstop trượt nước | [yame.vn](https://yame.vn/products/ao-khoac-du-tron-mot-mau-co-non-phom-rong-f4-seventy-seven-055-mau-xam-nhat-2625230) |
| Yame | Áo khoác jean sọc Hickory | [yame.vn](https://yame.vn/products/ao-khoac-jean-hoa-tiet-soc-hickory-ton-dang-ca-tinh-day-dan-ben-bi-the-original-060-mau-xanh-nhat-phom-hop-f5-2625136) |
| Yame | Quần jeans ống suông DurableTex | [yame.vn](https://yame.vn/products/quan-jean-mai-nhe-vai-mem-ong-suong-phom-rong-f4-the-original-064-mau-xam-den-2625139) |
| Yame | Quần tây xếp ly EasyCare | [yame.vn](https://yame.vn/products/quan-tay-can-ban-ong-om-gon-phom-vua-f3-the-ceo-033-mau-xam-dam-2625235) |
| Yame | Quần short lưng thun vân nổi | [yame.vn](https://yame.vn/products/quan-dui-lung-thun-vai-det-van-noi-ong-rong-phom-rong-f4-the-no-style-272-mau-xam-trang-2625367) |
| Nike | Nike Sportswear T-Shirt | [www.nike.com](https://www.nike.com/vn/t/sportswear-t-shirt-p4XzEXq4/IM9340-323) |
| Nike | Nike Solo Fleece Full-Zip Hoodie | [www.nike.com](https://www.nike.com/vn/t/solo-fleece-mens-full-zip-hoodie-TM6OYDjX/IX1929-063) |
| Nike | Nike Tech Windrunner Hoodie | [www.nike.com](https://www.nike.com/vn/t/tech-mens-full-zip-windrunner-hoodie-5CxKH2/HV0950-010) |
| Nike | Nike Club Hooded Jacket | [www.nike.com](https://www.nike.com/vn/t/club-mens-hooded-jacket-2GLh03/HJ2013-010) |
| Nike | Nike Solo Fleece Cuffed Trousers | [www.nike.com](https://www.nike.com/vn/t/solo-fleece-mens-cuffed-trousers-PM3AUgqC/IX1931-063) |
| Nike | Nike Miler Dri-FIT Running Shorts | [www.nike.com](https://www.nike.com/vn/t/miler-mens-dri-fit-12-5cm-approx-brief-lined-running-shorts-sf4iTJOg/IF2061-010) |
| Nike | Nike Sportswear Women's T-Shirt | [www.nike.com](https://www.nike.com/vn/t/sportswear-womens-t-shirt-d4gKTP/DX7903-654) |
| Nike | Nike Studio Fleece Oversized Full-Zip Hoodie | [www.nike.com](https://www.nike.com/vn/t/studio-fleece-medium-weight-womens-oversized-full-zip-hoodie-n49qDwx5/IM8085-001) |
| Nike | Nike Sportswear Mid-Rise Pleated Skirt | [www.nike.com](https://www.nike.com/vn/t/sportswear-womens-mid-rise-pleated-skirt-ih9xxeOO/IF0590-010) |
| Nike | Nike Sportswear Chill Terry Dress | [www.nike.com](https://www.nike.com/vn/t/sportswear-chill-terry-womens-dress-coyQGsmF/II3977-461) |
| Nike | Nike One High-Waisted Flared Leggings | [www.nike.com](https://www.nike.com/vn/t/one-womens-high-waisted-flared-leggings-vcJvijDa/IB9136-010) |
| Puma | Áo Khoác Hoodie Nam Logo Lớn Essentials | [vn.puma.com](https://vn.puma.com/vn/vi/pd/%C3%A1o-kho%C3%A1c-hoodie-nam-logo-l%E1%BB%9Bn-essentials/586688.html) |
| Puma | Áo Thun Logo Essentials Nam | [vn.puma.com](https://vn.puma.com/vn/vi/pd/%C3%A1o-thun-logo-essentials-nam/586667.html) |
| Puma | Áo polo nam Essentials Pique | [vn.puma.com](https://vn.puma.com/vn/vi/pd/%C3%A1o-polo-nam-essentials-pique/586674.html) |
| Puma | Áo khoác thể thao nam Essentials | [vn.puma.com](https://vn.puma.com/vn/vi/pd/%C3%A1o-kho%C3%A1c-th%E1%BB%83-thao-nam-essentials/586696.html) |
| Puma | Quần Chino Nam ESS | [vn.puma.com](https://vn.puma.com/vn/vi/pd/qu%E1%BA%A7n-chino-nam-ess/680450.html) |
| Puma | Áo thun croptop logo Essentials nữ | [vn.puma.com](https://vn.puma.com/vn/vi/pd/%C3%A1o-thun-croptop-logo-essentials-n%E1%BB%AF/586866.html) |
| Puma | Đầm WARDROBE ESS Midi Cut-Out | [vn.puma.com](https://vn.puma.com/vn/vi/pd/%C4%91%E1%BA%A7m-wardrobe-ess-midi-cut-out/629769.html) |
| Puma | Quần nỉ nữ ống rộng Wardrobe Essentials Relaxed | [vn.puma.com](https://vn.puma.com/vn/vi/pd/qu%E1%BA%A7n-n%E1%BB%89-n%E1%BB%AF-%E1%BB%91ng-r%E1%BB%99ng-wardrobe-essentials-relaxed/629754.html) |
