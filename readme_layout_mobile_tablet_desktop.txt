ĐỘ PHÂN GIẢI LOGICAL của mobile

Độ phân giải	Thiết bị thường gặp	Ghi chú
360 × 800 px	Các thiết bị Android giá rẻ	Tỷ lệ màn hình 9:19, phổ biến trên các dòng smartphone giá rẻ.
375 × 812 px	iPhone X, XS, 11	Tỷ lệ màn hình 9:19.5, thường được sử dụng trên các dòng iPhone cũ.
390 × 844 px	iPhone 14 Pro, iPhone 15	Tỷ lệ màn hình 9:19.5, phổ biến trên các dòng iPhone mới.
412 × 915 px	Một số mẫu Android tầm trung	Tỷ lệ màn hình 9:19.5, thường thấy trên các dòng smartphone tầm trung.
414 × 896 px	iPhone 11, một số mẫu Android cũ	Tỷ lệ màn hình 9:19.5, phổ biến trên các dòng iPhone cũ và Android.
1080 × 2400 px	Android tầm trung và cao cấp	Tỷ lệ màn hình 9:20, phổ biến trên các dòng smartphone hiện đại.
1440 × 3200 px	Samsung Galaxy S series, điện thoại cao cấp	Tỷ lệ màn hình 9:20, mang lại trải nghiệm hình ảnh sắc nét.



=> phổ biến ước lượng thiết bị mobile là 360px 
========================
2. Cỡ chữ đề xuất (theo UX guideline của Google & Apple)
Thành phần	Mobile (min–max)	Desktop tương ứng
Body text	1.4rem – 1.6rem (≈14–16px)	1.6rem – 1.8rem
Subtitle	1.6rem – 1.8rem	1.8rem – 2.0rem
Heading h6	1.8rem – 2.0rem	2.0rem – 2.2rem
Heading h5	2.0rem – 2.4rem	2.4rem – 2.8rem
Heading h4	2.4rem – 2.8rem	2.8rem – 3.2rem
Heading h3	2.8rem – 3.2rem	3.2rem – 3.6rem
Heading h2	3.2rem – 3.6rem	3.6rem – 4.0rem
Heading h1	3.6rem – 4.0rem	4.0rem – 4.8rem

có thể thấy là mobile và desktop có font-size các thành phần tương đương nhau
=============================================
Tóm tắt gợi ý thiết kế baseline
Thiết bị	CSS Width	CSS Height (ước lượng)	Tỉ lệ
Mobile		360px		780px (± 40px)	~19.5:9
Tablet		768px		1024px	4:3
Desktop		1366px		768px	16:9

=============================
Gợi ý cho thiết kế responsive tablet:
Tình huống sử dụng	Kích thước gợi ý để thiết kế
Tablet portrait (dọc)	834 × 1112 hoặc 834 × 1194
Tablet landscape (ngang)	1112 × 834 hoặc 1194 × 834
Breakpoint CSS gợi ý	min-width: 768px đến 1024px