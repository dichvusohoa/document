Về include, include_once, require, require_one .

require nghiêm khắc hơn, không có file thì gây lỗi và dừng chương trình, include thì chỉ
warning và cho chạy tiếp

về hiệu năng thì  include, require nhanh hơn include_once và require_one vì nó mất thời 
gian kiểm tra xem đã load file chưa.

nếu file "x.php" có chứa function, class, define  thì phải dùng  require_one "x.php", 
include_one "x.php" để tránh lỗi khai báo class , function, const nhiều lần