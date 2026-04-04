0. Lưu ý
Nói về controller ở đây là đang nói về 1 class controller trong namespace đầy đủ của nó
ví dụ  'App\\Controllers\\Nutrition\FoodController' là định danh đầy đủ của 
class FoodController.class.php chứ không phải chỉ là đang nói tới chuỗi 'FoodController'

Như vậy 'App\\Controllers\\UserAdmin\\LoginController' và 'App\\Controllers\\User\\LoginController'
là 2 controller hoàn toàn khác nhau

1. Module và controller có quan hệ 1 - nhiều hay nhiều nhiều

Khi phân chia module theo chức năng ví dụ như quản lý trường học: module dinh dưỡng, học sinh,
kế toán thì đúng là M-C có quan hệ 1 nhiều. Vì chức năng của các module là khác nhau nên controller
trong module này sẽ không có lý do gì để thiết kế xuất hiện trong module khác
Ví dụ module dinh dưỡng có controller về food nutrition, food price, menu,...
module pupil có controller về pupil, school, class,  học phí

Tuy nhiên trong thực tế lại có loại bài toán mà phân chia module không dựa vào chức năng mà lại là
dữ liệu. Tức là dữ liệu có cấu trúc đồng nhất trên cả hệ thống nhưng lại được chia làm nhiều
phần dựa theo quyền truy cập của người dùng. Ví dụ một hệ thống document trợ giúp sẽ chia làm nhiều phần
dữ liệu cho anonimous, dữ liệu cho school_admin, accountal, teacher , dữ liệu cho amin tổng.

Như vậy chỉ có một controller ví dụ là document_controller, nhưng có thể gắn với nhiều module 

2. Controller có thể không gắn với module nào. Điều này có thể vì khái niệm
module sinh ra thường bởi lý do thương mại nhiều hơn là kỹ thuật.
Tức là chia ra các module thương mại để bán cho các subscriber

Nhưng về kỹ thuật trong application thường có các controller quản lý các chức
năng mà mang ý nghĩa kỹ thuật phục vụ cho các module thương mại kia

Ví dụ điển hình hay gặp là việc quản lý các table dạng common như các bảng
province, scale_unit, ...

Vậy có thể tạo ra một module kiểu như "common" , "other", hoặc nếu để nhấn mạnh
tính chất "phi module thương mại" của nó thì dùng  module name là "" (không dùng null
được vì cấu trúc dữ liệu array trong PHP và JSON không cho phép)

Cách khác là bỏ hẳn module ("", "common") trong cấu trúc array phân cấp đi

3 2025-07-23. Sau khi cân nhắc kỹ vẫn quyết định giữ cấu trúc module /controller/ action
trong cả cây phân cấp và URL

Sau này chuyển sang Laravel hoàn toàn có thể cấu hình lại url tương đương

Để giảm mức độ cồng kềnh thi viết file router.mca.php, hôm nay 2025-07-23 quyết định dùng
biểu thức trong router.mca.php

Cú pháp
/[*]/ tất  cả
/[module1 module2]/  khớp một trong 2 module
/[!accountant]/ khớp tất cả trừ accountant



