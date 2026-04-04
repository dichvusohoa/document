1. Layout

- phân loại layout cho thiết bị theo tên đuôi.  *.phtml mặc định cho máy tính, *.m.phtml cho mobile
- Nếu ví dụ toàn bộ chương trình chỉ có một layout cho chương trình chính và một layout cho login
thì đặt tên đơn giản là layout.phtml và layout_login.phtml
- Nếu chương trình có vài layout cho chương trình chính thì nên đặt tên kiểu mã số dòng-cột cho layout
Nguyên tắc này này là : lọc bỏ các vùng trùng nhau giữa các layout (thường là các vùng phụ: header , footer , hmenu )

Còn lại vùng khác biệt giữa các layout thì biến thành 1 dạng table và mã hóa ra dạng dòng cột
Dòng mã 0,1,2, cột mã a,b,c
Ví dụ layout có lefmenu và right là data thì đặt tên là layout0ab.phtml
layout mà không có lefmenu, toàn bộ là data thì tên là layout0a.phtml hoặc đơn giản là layout.phtml

Chú ý là không đặt mã vào các vùng phụ trùng lặp như header, footer, ...

Ưu điểm của nguyên tắc này
- Hình dung ra ngay hình dạng layout
- Việc dùng code thay vì english word => nhanh. Trong lập trình đôi khi nghĩ các từ tiếng Anh cho sát nghĩa cũng rất mất thời gian
- Tổng quát hóa. Vì nếu dùng english word thì vừa dài, khó nghĩ, khó tổng quát. Ví dụ cái lefmenu
(code 0a) có thể là menu nhưng trường hợp khác có thể là tree view, cũng có thể là lelf panel control command
==============
Các thuật ngữ: 
- page HTML được phân thành các fragment
- 1 page HTML sẽ có một layout ( dàn trang trình bày)
- fragment sẽ có các type: css, script, title, embed_sub_layout (thiết kế nhúng trong layout), link_sub_layout ( thiết kế dàn trang link sang file khác)
 
==============
2. Loại Response. ???? Chưa chốt
Mọi Response đều có một root response là cái response sẽ trả về một layout html. Đó là cái khung HTML
thủy tổ lúc đầu, trên đó có gắn một số file CSS và Js cần thiết thì sau này mới chạy tiếp được

Sau khi có cái khung ấy rồi thì sẽ có 2 loại response nữa

Response chỉ trả về data dạng JSON
Response trả về data dạng JSON + view (hay sub_layout).  View hay layout thì bản chất là cái khung thẻ
HTML rỗng đã bị bóc gỡ hết data  


Tức là tạm thời ta hiểu có 3 loại response 



==============
3. Nguyên tắc dữ liệu + layout

01 Vùng dữ liệu chính + các vùng thông tin phụ trợ

Một layout có nhiều vùng dữ liệu, nhưng nguyên tắc là tại một ngữ cảnh nhất định thì chỉ có một vùng dữ liệu chính
Lý do là vì con người khi làm việc tại một thời điểm nhất định thì thường chỉ thao tác với
một loại dữ liệu : user, pupil, school,.... Ví dụ như 

- Một layout có header, footer, hmenu, lefmenu, data bên phải
- Khi người dùng nhập danh sách học sinh thì left menu là danh sách class, bên phải là thông tin về học sinh
Vậy vùng dữ liệu chính là học sinh, các vùng khác là thông tin phụ trợ

- Khi người dùng nhập danh sách các trường thì left menu là danh sách province, bên phải là thông tin về school
Vậy vùng dữ liệu chính là school, các vùng khác là thông tin phụ trợ

VÙNG DỮ LIỆU CHÍNH thường map với một model chính và vài model phụ trợ. Ví dụ vùng dữ liệu chính
về pupil thì model chính là pupil, nhưng mà dữ liệu về pupil thường cũng phải liên kết với dữ liệu
về class, province. Vậy model phụ trợ sẽ là class, province

ĐẶT TÊN controller thế nào ?. Thường nếu không có gì đặt biệt thì đặt tên controller theo model chính
Tuy nhiên nó cũng có thể có ngoại lệ. Ví dụ LoginController thì nó thao tác với model Auth, User, Capcha, Session, Cookie
trong đó Auth là chính, nhưng LoginController cũng là một thông lệ được chọn.

==============
4.Layout controller và service controller

NHư phân tích ở phần 2 có 3 loại response

Việc tạo khung layout ban đầu ( không có dữ liệu) thuộc về một loại controller là layout controller
nó chỉ để tạo khung

Sau khi có cái khung này thì các service controller mới chạy ( thí dụ SchoolController, ...)






