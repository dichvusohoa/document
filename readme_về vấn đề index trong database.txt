Về vấn đề index trong table DB, các nguyên tắc
1. Index chủ yếu được tạo để tối ưu việc truy xuất dữ liệu (đặc biệt là SELECT), nhưng cũng được DBMS sử dụng khi thực thi UPDATE và DELETE để nhanh chóng xác định các dòng cần thay đổi. Vì vậy việc thiết kế index phải xuất phát từ các câu lệnh SELECT thực tế của hệ thống.
2. Index phục vụ cho việc làm nhanh SELECT nhưng thường có tác dụng làm chậm UPDATE/INDSERT/DELETE và làm tăng dung lượng lưu trữ của database
3. PRIMARY KEY và UNIQUE KEY đều tự động sinh index, vì vậy không cần tạo thêm index riêng cho các cột này
4. Từ các câu lệnh SELECT  tìm các thành phần như WHERE, JOIN ON, ORDER BY, GROUP BY, DISTINCT, LIMIT (đặc biệt khi đi cùng ORDER BY) từ đó tìm ra các column ứng viên. Từ danh sách các column ứng viên, thiết kế các composite index theo đúng mẫu truy vấn để hạn chế số lượng index dư thừa và tận dụng tối đa khả năng của optimizer
Ví dụ
SELECT *
FROM category
WHERE parent_id = ?
ORDER BY sort_order;
 thì idx là (parent_id, sort_order)

5.  composite index tuân theo nguyên tắc left most ví dụ INDEX(col_A, col_B, col_C) thì không cần INDEX(col_A), INDEX(col_A, col_B), vì INDEX(col_A, col_B, col_C) có thể thay thế hầu hết các trường hợp sử dụng INDEX(col_A), INDEX(col_A, col_B). Nhưng nếu bạn cần INDEX (col_C, col_A) thì vẫn phải tạo thêm vì INDEX(col_A, col_B, col_C) không thay thế được. Cách diễn dải khác là: khi đã có INDEX(col_A, col_B, col_C)
dùng được

WHERE col_A = ?

WHERE col_A = ? AND col_B = ?

WHERE col_A = ? AND col_B = = ? AND col_C = = ?

Nhưng không dùng hiệu quả cho

WHERE col_B = ?

WHERE col_C = ?

WHERE col_B =? AND col_C =?

còn WHERE col_A =? AND col_C =? thì có hiệu quả được 1 phần, chính là được phần col_A = ?
Nên uu tiên thiết kế một composite index phục vụ nhiều truy vấn hơn là nhiều index đơn lẻ chỉ phục vụ từng truy vấn riêng biệt.

6. Về Foreign Key
Đối với 1 FK(A) DB luôn cần 1 index mà cột đầu tiên là A. Tất nhiên đó là minh họa đơn giản, nếu
với dạng 2 column như FK(A,B) thì tất nhiên hệ thống cần 1 index mà 2 cột đầu tiên là A,B.
Ví dụ

Index	FK(A) dùng được?
(A)	✅
(A,B)	✅
(A,B,C)	✅
(B,A)	❌
(C,A)	❌

Nếu như không sẵn có Index phù hợp thì sau khi tạo FK, MySQL/MariaDB sẽ tự tạo ra index phù hợp (các DB khác thì chưa chắc)
Vậy nên 
- Kiểm tra các index mà hệ thống tự sinh phục vụ cho FK
- Nếu cần chủ động tạo các index đó trước khi tạo FK để đặt tên Index (không dùng tên do hệ thống tự sinh) và chỉnh lại thiết kế của index cho phù hợp
(ví dụ thay đổi các column trong index )

7. Sau khi thiết kế index, cần dùng EXPLAIN (hoặc EXPLAIN ANALYZE nếu DB hỗ trợ) để kiểm tra optimizer có thực sự sử dụng index đó hay không. Không nên chỉ dựa vào suy đoán
8. Qui trình tạo index 
- Tạo bảng 
- Tạo Primary Key. 
- Tạo các index đủ để phục vụ các FK ( xem mục 6). Nhiều khi database cũng sẽ tự tạo ra các index này.
- Nếu tới đây đã có thể nắm chắc các lệnh SELECT nào sau này thường xuyên sử dụng thì theo mục 4, mục 5 để tạo/chỉnh sửa index. Chú ý rằng đôi khi 
có thể phải điều chỉnh cả Primary Key dạng composite index, tức là điều chỉnh thứ tự các column trong đó sao cho tối ưu
- Theo mục 7 để kiểm tra lại và tiếp tục chỉnh sửa index
