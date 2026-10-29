# Nguồn dữ liệu catalog demo

Catalog demo lấy từ sản phẩm quần áo thật đang bán trên trang Việt Nam của Coolmate, Yame, Nike, Puma, Uniqlo, Teelab, Levents và Routine (tháng 10/2026), cùng Alo Yoga lấy từ trang web Mỹ của hãng (giá USD quy đổi theo 1 USD = 26.000 đ, làm tròn đến 10.000 đ): tên, giá niêm yết và giá khuyến mãi, màu, size, mô tả và ảnh. Dữ liệu được lấy theo `robots.txt` của từng trang (Coolmate: trang sản phẩm; Yame: `products.json` của Shopify; Nike: trang danh sách `/w/`, không vào trang chi tiết bị chặn; Puma: JSON-LD trên trang sản phẩm; Uniqlo: API sản phẩm của trang web; Teelab: `products.json` của Sapo; Levents: dữ liệu sản phẩm nhúng trong trang, lấy link từ các trang danh mục; Routine: JSON-LD `ProductGroup` trên trang sản phẩm, lấy link từ sitemap; Alo Yoga: `products.json` của Shopify, mỗi màu của một mẫu gộp về một sản phẩm; các hãng Stüssy, Represent, Fear of God và Allbirds trả lỗi 429 cho truy cập tự động nên không lấy). Adidas Việt Nam chặn truy cập tự động nên không có trong catalog.

Ảnh đã cắt về tỉ lệ 4:5 (640×800). Ảnh Levents, Routine và Alo Yoga là bản chất lượng cao (tối đa 1080×1350), không nằm trong repo: chúng được đưa một lần lên disk ảnh (R2) ở `demo/catalog/` và seeder chỉ kiểm tra ảnh đã có ở đó. Mỗi màu có một ảnh riêng, đặt tên `<slug>-<màu>.jpg`; ảnh phụ là `<slug>-2.jpg`, `<slug>-3.jpg`. Mô tả được rút gọn từ nội dung trang gốc; mô tả Nike đợt đầu do nhóm tự viết, đợt sau dịch từ trang sản phẩm. Mỗi sản phẩm lấy tối đa 4 màu. Size số của quần (29, 30…) được quy về S–XL cho khớp danh sách size của hệ thống. Tồn kho là số giả lập.

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
| Uniqlo | AIRism Cotton Áo Thun Dáng Rộng / Tay Lỡ | [www.uniqlo.com](https://www.uniqlo.com/vn/vi/products/E465185-000) |
| Uniqlo | SUPIMA COTTON Áo Thun | [www.uniqlo.com](https://www.uniqlo.com/vn/vi/products/E455365-000) |
| Uniqlo | AIRism Cotton Áo Polo Vải Pique | [www.uniqlo.com](https://www.uniqlo.com/vn/vi/products/E475367-000) |
| Uniqlo | DRY-EX Áo Polo | [www.uniqlo.com](https://www.uniqlo.com/vn/vi/products/E482305-000) |
| Uniqlo | Áo Sơ Mi Vải Linen Cao Cấp Dài Tay | [www.uniqlo.com](https://www.uniqlo.com/vn/vi/products/E455957-000) |
| Uniqlo | Áo Sơ Mi Vải Dạ | [www.uniqlo.com](https://www.uniqlo.com/vn/vi/products/E486600-000) |
| Uniqlo | Áo Hoodie Nỉ Chui Đầu Dáng Rộng | [www.uniqlo.com](https://www.uniqlo.com/vn/vi/products/E471808-000) |
| Uniqlo | Áo Nỉ | [www.uniqlo.com](https://www.uniqlo.com/vn/vi/products/E486117-000) |
| Uniqlo | Áo Khoác Giả Lông Cừu Kéo Khóa | [www.uniqlo.com](https://www.uniqlo.com/vn/vi/products/E450195-000) |
| Uniqlo | PUFFTECH Áo Khoác Chần Bông | [www.uniqlo.com](https://www.uniqlo.com/vn/vi/products/E486171-000) |
| Uniqlo | Quần Jeans EZY Siêu Co Giãn | [www.uniqlo.com](https://www.uniqlo.com/vn/vi/products/E455476-000) |
| Uniqlo | Quần Dài Vải Pha Linen Dáng Relax | [www.uniqlo.com](https://www.uniqlo.com/vn/vi/products/E482920-000) |
| Uniqlo | Quần Shorts Thể Thao Siêu Co Giãn | [www.uniqlo.com](https://www.uniqlo.com/vn/vi/products/E475386-000) |
| Uniqlo | AIRism Cotton Áo Thun | [www.uniqlo.com](https://www.uniqlo.com/vn/vi/products/E465755-000) |
| Uniqlo | Áo Thun Vải Gân Cổ Henley | [www.uniqlo.com](https://www.uniqlo.com/vn/vi/products/E487908-000) |
| Uniqlo | Áo Sơ Mi Vải Linen Cao Cấp | [www.uniqlo.com](https://www.uniqlo.com/vn/vi/products/E475647-000) |
| Uniqlo | AIRism Áo Khoác Chống Tia UV Siêu Co Giãn Kéo Khóa | [www.uniqlo.com](https://www.uniqlo.com/vn/vi/products/E483281-000) |
| Uniqlo | Áo Khoác Giả Lông Cừu Loại Dày Kéo Khóa | [www.uniqlo.com](https://www.uniqlo.com/vn/vi/products/E449753-000) |
| Uniqlo | Quần Jeans EZY Dáng Rộng Ống Suông | [www.uniqlo.com](https://www.uniqlo.com/vn/vi/products/E482281-000) |
| Uniqlo | Quần Cotton Dáng Relax Dài Đến Mắt Cá | [www.uniqlo.com](https://www.uniqlo.com/vn/vi/products/E482243-000) |
| Uniqlo | Chân Váy Xếp Ly Vải Nylon | [www.uniqlo.com](https://www.uniqlo.com/vn/vi/products/E487995-000) |
| Uniqlo | Đầm Kiểu Xếp Tầng | [www.uniqlo.com](https://www.uniqlo.com/vn/vi/products/E489239-000) |
| Teelab | Teelab Alter Regular Cotton Essentials FW26 Tee TS437 | [teelab.vn](https://teelab.vn/tee-ts437) |
| Teelab | Teelab Alter Oversized Cotton Essentials FW26 Tee TS438 | [teelab.vn](https://teelab.vn/tee-ts438) |
| Teelab | Teelab Alter Oversized Cotton Solid Tee TS382 | [teelab.vn](https://teelab.vn/ao-thun-tron-ts382) |
| Teelab | Teelab Alter Baby Slub-Cotton Tee TS427 | [teelab.vn](https://teelab.vn/tee-ts427) |
| Teelab | Teelab Alter Baby Cotton Tee TS426 | [teelab.vn](https://teelab.vn/tee-ts426) |
| Teelab | Teelab Alter Regular Pique Animal Polo AP079 | [teelab.vn](https://teelab.vn/polo-ap079) |
| Teelab | Teelab Alter Oversized Cotton Essentials Polo AP075 | [teelab.vn](https://teelab.vn/ao-polo-ap075) |
| Teelab | Teelab Alter Long Sleeve Oxford Essentials Shirt SS092 | [teelab.vn](https://teelab.vn/ao-so-mi-ss092) |
| Teelab | Teelab Alter Cuban Oxford Essentials Shirt SS093 | [teelab.vn](https://teelab.vn/ao-so-mi-ss093) |
| Teelab | Teelab Alter Semi Fleece World Tour Hoodie HD130 | [teelab.vn](https://teelab.vn/ao-hoodie-local-brand-unisex-teelab-world-tour-hd130) |
| Teelab | Teelab Alter Relaxed Fit Fleece Essentials FW26 Hoodie HD145 | [teelab.vn](https://teelab.vn/hoodie-hd145) |
| Teelab | Teelab Alter Loose-Fit Nylon Essentials FW26 Windbreaker AK152 | [teelab.vn](https://teelab.vn/windbreaker-ak152) |
| Teelab | Teelab Alter Oversized Nylon Essentials FW26 Bomber AK151 | [teelab.vn](https://teelab.vn/bomber-ak151) |
| Teelab | Teelab Alter Oversized Denim Tapered Pants PS142 | [teelab.vn](https://teelab.vn/quan-jeans-ps142) |
| Teelab | Teelab Alter Relaxed Fit Fleece Essentials FW26 Pants PS148 | [teelab.vn](https://teelab.vn/pants-ps148) |
| Teelab | Teelab Alter Jogger Khaki Essentials Shorts SH011 | [teelab.vn](https://teelab.vn/quan-short-sh011) |
| Yame | Áo polo Cool Comfort thun 2 mặt | [yame.vn](https://yame.vn/products/ao-polo-cool-comfort-vai-thun-hai-mat-phom-rong-f4-the-no-style-257-mau-xam-dam-2625154) |
| Yame | Áo polo IceVibes mát lạnh | [yame.vn](https://yame.vn/products/ao-polo-cool-comfort-vai-thun-hai-mat-phom-hop-rong-f5-the-no-style-250-mau-xanh-duong-2625212) |
| Yame | Sơ mi cổ Cuban nhung tăm EasyCare | [yame.vn](https://yame.vn/products/ao-so-mi-tay-ngan-vai-nhung-ke-phom-vua-f3-seventy-seven-066-mau-be-nhat-2625128) |
| Yame | Áo thun Henley tay dài vải waffle | [yame.vn](https://yame.vn/products/ao-thun-henley-tay-dai-giu-am-vai-to-ong-phom-vua-f3-the-weekend-052-mau-xanh-duong-dam-2625275) |
| Yame | Áo thun in chữ Typography | [yame.vn](https://yame.vn/products/ao-thun-in-chu-phom-rong-f4-the-no-style-265-mau-do-dam-2625312) |
| Yame | Quần jogger thể thao Double Face | [yame.vn](https://yame.vn/products/quan-jogger-the-thao-quick-dry-the-beginner-007-xanh-xam-0024501) |
| Yame | Quần Easy Pant ống siêu rộng | [yame.vn](https://yame.vn/products/quan-easy-pant-baggy-fit-day-thoang-dung-dang-the-no-style-246-mau-trang-phom-sieu-rong-f5-2625093) |
| Yame | Quần short jeans DurableTex | [yame.vn](https://yame.vn/products/quan-dui-jeans-durabletex-mem-lung-cong-chong-ho-the-original-059-mau-xanh-den-phom-vua-van-f3-2625094) |
| Yame | Quần short Easy Pant IceVibes | [yame.vn](https://yame.vn/products/quan-dui-easy-pant-icevibes-lung-thun-vai-tan-nhiet-mat-lanh-the-minimalist-033-mau-beige-phom-vua-van-f3-2625266) |
| Coolmate | Áo Sơ Mi Dài Tay Premium Poplin | [www.coolmate.me](https://www.coolmate.me/product/ao-so-mi-nam-dai-tay-premium-poplin-100-cotton-chong-nhan-truot-nuoc) |
| Coolmate | Áo thun cotton compact logo COOLMATE | [www.coolmate.me](https://www.coolmate.me/product/ao-thun-cotton-compact-logo-coolmate) |
| Coolmate | Áo Polo nam Premium Cotton Linen | [www.coolmate.me](https://www.coolmate.me/product/ao-polo-nam-premium-cotton-linen) |
| Coolmate | Áo Polo Nam Cafe Essentials | [www.coolmate.me](https://www.coolmate.me/product/ao-polo-nam-cafe-essentials-thoang-mat-chong-uv) |
| Coolmate | Áo khoác WindBreaker Nylon Taslan | [www.coolmate.me](https://www.coolmate.me/product/ao-khoac-windbreaker-nylon-taslan) |
| Coolmate | Quần Jeans Nam Relaxed dáng Straight | [www.coolmate.me](https://www.coolmate.me/product/quan-jeans-nam-relaxed-dang-straight) |
| Coolmate | Quần dài nam TechComfort | [www.coolmate.me](https://www.coolmate.me/product/quan-dai-nam-techcomfort) |
| Coolmate | Áo thun nữ Baby Tee 100% Cotton Chill like a Cat | [www.coolmate.me](https://www.coolmate.me/product/ao-thun-nu-baby-tee-100-cotton-chill-like-a-cat) |
| Coolmate | Áo Tshirt Regular fit | [www.coolmate.me](https://www.coolmate.me/product/ao-nu-t-shirt-regular-fit) |
| Coolmate | Áo khoác nữ chống nắng Slim Cooling Sun Protection UPF50 | [www.coolmate.me](https://www.coolmate.me/product/ao-khoac-nu-chong-nang-slim-cooling-sun-protection-upf50) |
| Coolmate | Quần dài thể thao nữ Studio Pants | [www.coolmate.me](https://www.coolmate.me/product/quan-dai-the-thao-nu-studio-pants) |
| Puma | Áo Thun Logo Nhỏ Essentials Nam | [vn.puma.com](https://vn.puma.com/vn/vi/pd/%C3%A1o-thun-logo-nh%E1%BB%8F-essentials-nam/586669.html) |
| Puma | Áo polo nam Classics | [vn.puma.com](https://vn.puma.com/vn/vi/pd/%C3%A1o-polo-nam-classics/686481.html) |
| Puma | Áo hoodie nam có khóa kéo đầy đủ OPEN ROAD | [vn.puma.com](https://vn.puma.com/vn/vi/pd/%C3%A1o-hoodie-nam-c%C3%B3-kh%C3%B3a-k%C3%A9o-%C4%91%E1%BA%A7y-%C4%91%E1%BB%A7-open-road/681654.html) |
| Puma | Áo Khoác Chạy Bộ Dệt RUN FAVOURITE Nam | [vn.puma.com](https://vn.puma.com/vn/vi/pd/%C3%A1o-kho%C3%A1c-ch%E1%BA%A1y-b%E1%BB%99-d%E1%BB%87t-run-favourite-nam/523155.html) |
| Puma | Quần short nam Classics | [vn.puma.com](https://vn.puma.com/vn/vi/pd/qu%E1%BA%A7n-short-nam-classics/686482.html) |
| Puma | Áo hoodie logo Essentials nữ | [vn.puma.com](https://vn.puma.com/vn/vi/pd/%C3%A1o-hoodie-logo-essentials-n%E1%BB%AF/586791.html) |
| Puma | Váy phối chất liệu nữ | [vn.puma.com](https://vn.puma.com/vn/vi/pd/v%C3%A1y-ph%E1%BB%91i-ch%E1%BA%A5t-li%E1%BB%87u-n%E1%BB%AF/628272.html) |
| Nike | Nike Stride Dri-FIT ADV Running Top | [www.nike.com](https://www.nike.com/vn/t/stride-mens-dri-fit-adv-short-sleeve-running-top-wBPHXUOp/IO1418-100) |
| Nike | Nike Solo Fleece Crew-Neck Sweatshirt | [www.nike.com](https://www.nike.com/vn/t/solo-fleece-mens-crew-neck-sweatshirt-UVynyCWb/IX1937-063) |
| Nike | Nike Sportswear Club Lightweight Full-Zip Jacket | [www.nike.com](https://www.nike.com/vn/t/sportswear-club-mens-lightweight-full-zip-jacket-q0ztJnfq/IF0767-010) |
| Nike | Nike Tech Helios Dri-FIT Trousers | [www.nike.com](https://www.nike.com/vn/t/tech-helios-mens-dri-fit-trousers-gd7HAsbf/IF1283-010) |
| Nike | NikeCourt Victory Dri-FIT Tennis Shorts | [www.nike.com](https://www.nike.com/vn/t/nikecourt-victory-mens-dri-fit-18cm-approx-tennis-shorts-SWCBQw/FD5381-010) |
| Nike | Nike One Relaxed Dri-FIT Long-Sleeve Top | [www.nike.com](https://www.nike.com/vn/t/one-relaxed-womens-dri-fit-long-sleeve-top-KgpSG12p/IU1423-580) |
| Nike | Nike Studio Fleece Oversized Crew-Neck Sweatshirt | [www.nike.com](https://www.nike.com/vn/t/studio-fleece-lightweight-womens-oversized-crew-neck-sweatshirt-r5Fh4PQC/IM8510-133) |
| Nike | NikeCourt Advantage Pleated Tennis Skirt | [www.nike.com](https://www.nike.com/vn/t/nikecourt-advantage-womens-dri-fit-pleated-tennis-skirt-bC6d18/FZ6513-010) |
| Nike | Nike Zenvy High-Waisted Flared Leggings | [www.nike.com](https://www.nike.com/vn/t/zenvy-womens-high-waisted-flared-leggings-with-no-front-seam-LbranU7F/II5224-630) |
| Levents | Levents Serif Logo Slim Fit Tee | [www.levents.asia](https://www.levents.asia/products/levents-serif-logo-slim-fit-tee) |
| Levents | Levents Forget Me Not Slim Fit Tee | [www.levents.asia](https://www.levents.asia/products/levents-forget-me-not-slim-fit-tee) |
| Levents | Levents Forget Me Not Long Sleeve Boxy Tee | [www.levents.asia](https://www.levents.asia/products/levents-forget-me-not-long-sleeve-boxy-tee) |
| Levents | Levents Forget Me Not Semi-Oversized Tee | [www.levents.asia](https://www.levents.asia/products/levents-forget-me-not-semi-oversized-tee) |
| Levents | Levents Forget Me Not Raglan Long Sleeve Boxy Tee | [www.levents.asia](https://www.levents.asia/products/levents-forget-me-not-raglan-long-sleeve-boxy-tee) |
| Levents | Levents XL Logo Zipper Hoodie Boxy | [www.levents.asia](https://www.levents.asia/products/levents-xl-logo-zipper-hoodie-boxy) |
| Levents | Levents Striped Long Sleeve Boxy Polo Shirt | [www.levents.asia](https://www.levents.asia/products/levents-striped-long-sleeve-boxy-polo-shirt) |
| Levents | Levents Seasonal Sporty Jacket | [www.levents.asia](https://www.levents.asia/products/levents-seasonal-sporty-jacket) |
| Levents | Levents Forget Me Not Track Jacket | [www.levents.asia](https://www.levents.asia/products/levents-forget-me-not-track-jacket) |
| Levents | Levents Classic Dashed Line Nylon Jacket | [www.levents.asia](https://www.levents.asia/products/levents-classic-dashed-line-nylon-jacket) |
| Levents | Levents Signature Logo Heavyweight Relaxed Hoodie Zip | [www.levents.asia](https://www.levents.asia/products/levents-signature-logo-heavyweight-relaxed-hoodie-zip) |
| Levents | Levents Forget Me Not Semi-Oversized Hoodie Zip | [www.levents.asia](https://www.levents.asia/products/levents-forget-me-not-semi-oversized-hoodie-zip) |
| Levents | Levents Sporty Nylon Cargo Pants | [www.levents.asia](https://www.levents.asia/products/levents-sporty-nylon-cargo-pants) |
| Levents | Levents x Hello Kitty Striped Chino Pants | [www.levents.asia](https://www.levents.asia/products/levents-x-hello-kitty-striped-chino-pants) |
| Levents | Levents Seasonal Sporty Shorts | [www.levents.asia](https://www.levents.asia/products/levents-seasonal-sporty-shorts) |
| Levents | Levents Serif Logo Striped Sporty Shorts | [www.levents.asia](https://www.levents.asia/products/levents-serif-logo-striped-sporty-shorts) |
| Routine | Áo Len Nam Dệt Kim Tay Dài Cổ Cao Form Fitted | [routine.vn](https://routine.vn/products/10f24kni011) |
| Routine | Áo Len Nam Tay Dài Cổ Tròn Form Regular | [routine.vn](https://routine.vn/products/10f24kni008) |
| Routine | Áo Thun Nữ Tay Ngắn Slimme Viền Cổ Mảnh | [routine.vn](https://routine.vn/products/10f24tssw025) |
| Routine | Áo sơ mi nam ngắn tay cotton form fitted | [routine.vn](https://routine.vn/products/10s24shs011) |
| Routine | Áo Polo Nam Ngắn Tay Cao Cấp Cotton Polyester Form Fitted | [routine.vn](https://routine.vn/products/10f24pol014) |
| Routine | Chân Váy Mini Nữ Ngắn Tweed Form Fitted | [routine.vn](https://routine.vn/products/10f22skiw005) |
| Routine | Áo sơ mi denim nam tay dài xanh đậm form loose | [routine.vn](https://routine.vn/products/10f24dsh002) |
| Routine | Áo sơ mi denim sọc nam tay ngắn form oversized | [routine.vn](https://routine.vn/products/ao-so-mi-jean-nam-ngan-tay-soc-form-oversize-006) |
| Routine | Áo Thun Nam Ngắn Tay S.cafe Form Fitted | [routine.vn](https://routine.vn/products/ao-thun-coffee-lovers-series-4-tron-fitted-002) |
| Routine | Áo Thun Nam Ngắn Tay Cotton Form Regular | [routine.vn](https://routine.vn/products/ao-thun-nam-tay-ngan-cotton-tron-form-regular-070) |
| Routine | Áo blazer nam linen form fitted | [routine.vn](https://routine.vn/products/ao-blazer-nam-linen-form-fitted-001) |
| Routine | Áo sơ mi nam tay dài S.Cafe form fitted | [routine.vn](https://routine.vn/products/ao-so-mi-nam-dai-tay-s-cafe-form-fitted-090) |
| Routine | Áo blazer dệt kim nam form regular | [routine.vn](https://routine.vn/products/ao-blazer-nam-knitting-form-regular-002) |
| Routine | Áo sweatshirt nam có hình thêu form fitted | [routine.vn](https://routine.vn/products/ao-ni-sweater-nam-theu-hinh-fitted-001) |
| Routine | Áo polo nam ngắn tay cotton poly form fitted | [routine.vn](https://routine.vn/products/ao-polo-nam-ngan-tay-cotton-poly-form-fitted-030) |
| Routine | Áo thun nam tay ngắn S.Cafe Coffee Lovers form fitted | [routine.vn](https://routine.vn/products/ao-thun-nam-tron-basic-soi-cafe-fitted-039) |
| Alo Yoga | Alo Yoga Cropped Accolade Crewneck | [www.aloyoga.com](https://www.aloyoga.com/products/w3924rg-cropped-accolade-crewneck-paradise-pink) |
| Alo Yoga | Alo Yoga Alosoft High-Waist Iconic 90's Capri | [www.aloyoga.com](https://www.aloyoga.com/products/w51024r-alosoft-high-waist-iconic-90s-capri-azure-blue) |
| Alo Yoga | Alo Yoga Street Essential Tee | [www.aloyoga.com](https://www.aloyoga.com/products/m1258r-street-essential-tee-gravel) |
| Alo Yoga | Alo Yoga 7" Linen Jersey Coastal Short | [www.aloyoga.com](https://www.aloyoga.com/products/m6201p-7-linen-jersey-coastal-short-white) |
| Alo Yoga | Alo Yoga Airbrush High-Waist Heart Throb Legging | [www.aloyoga.com](https://www.aloyoga.com/products/w51298r-airbrush-high-waist-heart-throb-legging-winter-frost-white) |
| Alo Yoga | Alo Yoga Kinetic Cargo Pant | [www.aloyoga.com](https://www.aloyoga.com/products/m5014r-kinetic-cargo-pant-anthracite) |
| Alo Yoga | Alo Yoga Elemental Full-Zip Jacket | [www.aloyoga.com](https://www.aloyoga.com/products/m4185r-elemental-full-zip-jacket-anthracite) |
| Alo Yoga | Alo Yoga Faux Fur Cropped Amore Jacket | [www.aloyoga.com](https://www.aloyoga.com/products/w4837r-faux-fur-cropped-amore-jacket-rich-bordeaux) |
| Alo Yoga | Alo Yoga Cotton Cashmere High-Waist Capri Dream Skirt | [www.aloyoga.com](https://www.aloyoga.com/products/w6640p-cotton-cashmere-high-waist-capri-dream-skirt-sundrenched-pink-white) |
| Alo Yoga | Alo Yoga Momentum Skirt | [www.aloyoga.com](https://www.aloyoga.com/products/w6632r-momentum-skirt-bamboo-green) |
| Alo Yoga | Alo Yoga Momentum Vest | [www.aloyoga.com](https://www.aloyoga.com/products/w4844r-momentum-vest-bamboo-green) |
| Alo Yoga | Alo Yoga Softsculpt Tank | [www.aloyoga.com](https://www.aloyoga.com/products/w2704r-alo-softsculpt-tank-white) |
