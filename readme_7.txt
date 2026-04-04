spUpdate là phức tạp hơn nhiều spDelete và spAdd

Lý do về cơ bản là spUpdate là xác định 1 tập hợp COL bị thay đổi giá trị trên 1 tập hợp ROW EFFECTED

Về cơ bản có hai loại câu lệnh UPDATE

1. UPDATE truyền thống là kiểu mà 1 tập hợp COL ( nhiều field) nhưng mỗi field chỉ có một giá trị đơn
( ví dụ status = active, name = 'xxxx'). Khí đó câu lệnh UPDATE có dạng
 UPDATE bảng X set status = active , name = 'xxxx' WHERE mô tả tập hợp ROW EFFECTED

đây là kiểu update tất cả các field cùng lúc bởi cùng một tập hợp  ROW EFFECTED

2. UPDATE  theo field, hễ có một field bất kỳ mà có nhiều hơn 2 giá trị thay đổi thì phải dùng
kiểu UPDATE từng field một


UPDATE bảng X
SET 
    field1 = CASE 
        WHEN tập hợp 1 THEN giá trị 1
        WHEN tập hợp 2 THEN giá trị 2
        ELSE field1
    END,
    field2 = CASE 
        WHEN tập hợp 1 THEN giá trị 1
        WHEN tập hợp 2 THEN giá trị 2
        ELSE field2
    END,

    .....
WHERE tập hợp các row ảnh hưởng




