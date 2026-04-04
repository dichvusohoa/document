PDO  Prepare - bindParam - bindValue - Stored Procedure
- prepare là phương pháp tốt để chống SQL injunction thi user input từ ngoài. Dù
bạn chỉ execute 1 lần cũng nên dùng phương pháp này để đảm bảo an toàn. Không nên
dùng phương pháp  exec($strSQL) tĩnh, với strSQL được tạo từ các biến ghép nối do người 
dùng nhập từ bên ngoài vào
- Khi execute nhiều lần ( INSERT chẳng hạn) thì bạn dùng prepare + cơ chế bindParam
có tiên lợi: hệ thống chỉ phân tích cú pháp 1 lần ở lệnh prepare sau đó mỗi lần execute
la thay đổi giá trị biến, không biên dịch lại câu lệnh SQL nữa

- khi execute 1 lần thì vẫn nên dùng prepare vì lý do an toàn (chống SQL injunction).
Còn bindParam thì không cần, execute(array) như là $stmt->execute([
    ':sku' => $_POST['sku'],
    ':title' => $_POST['title']
]) luôn cũng được. 

- binValue thì nó gắn value vào lúc bind không thay đổi giá trị lúc execute nữa như là bindParam( bin reference)

- stored procedure



$stmt = $dbh->prepare("INSERT INTO product SET sku = :sku, title = :title");
$stmt->execute([
    ':sku' => $_POST['sku'],
    ':title' => $_POST['title']
]);


 chuyển đoạn code sang dùng stored procedure kiểu như

$stmt = $dbh->prepare("CALL spAddProduct(:sku,:title");
$stmt->execute([
    ':sku' => $_POST['sku'],
    ':title' => $_POST['title']
])

DELIMITER //
CREATE PROCEDURE spAddProduct(IN p_sku VARCHAR(50), IN p_title VARCHAR(255))
BEGIN
    INSERT INTO product (sku, title) VALUES (p_sku, p_title);
END //
DELIMITER ;


thì hiệu suất có thể cao hơn nếu spAddProduct gọi nhiều lần vì việc phân tích cú pháp có thể đã
được biên dịch sẵn trong DB

====================

Thiết kế theo hướng tạo các sp tổng quát ( nhằm giảm số lượng sp ) hay theo hướng tạo sql động

Tôi làm việc với database DbX, có 02 database user là uX và uY. uX ở DbX chỉ có quyền execute  
(chỉ chạy được stored procedure) còn uY có full quyền. Tất cả các stored procedure của tôi đều run với uY (CREATE DEFINER=uY@localhost PROCEDURE ...) . ux là để tạo connection và execute các stored procedure.

Chính vì kiến trúc bảo mật này nên cố gắng theo hướng tạo sp tổng quát

spDelete đơn giản nhất

- dbName và tableName do code nên không cần kiểm soát
- listFiledKey do code nên không cần validate
- cần kiểm soát chặt listValue truyền lên
