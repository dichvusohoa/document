Response là nấc cuối của chuỗi xử lý, chủ yếu nhận dữ liệu từ Controller rồi trả về
 Do đó nó chủ yếu là các hàm static với đầu vào hoặc là dữ liệu dạng array do controller
 tập hợp về hoặc là tên file
 
Nguyên tắc trong Framework này là mọi loại request lên đều phải có response_type

 response_type = "html"; (default) đó chính là kiểu request 1 trang html truyền thống. Nhưng ở
đây thì thường Response trả về một khung layout, sau đó các lệnh fetch của javascript sẽ request nốt
các phần của layout

= "json": trả về dữ liệu kiểu json
= filedownload