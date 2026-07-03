Giờ tôi muốn viết file .htacces dạng giả ngôn ngữ như sau

If (file thật có tồn tại, php, html, css,....){

	phục vụ file chạy trực tiếp
}
else if( nếu là file tài nguyên .css, .svg, ...){
// KHÔNG LÀM GÌ CẢ, vì nếu không có nhánh này thì việc miss các file tài nguyên sẽ làm
//cho chạy file index.php và gọi Router rất nhiều lần
}
else{
	chạy file index.php
}

, hãy chuyển thành code chính xác cho tôi