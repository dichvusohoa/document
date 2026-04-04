Về vấn đề header và echo trong PHP để trả về HMML. Ví dụ trang HTML như sau

!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>503 - service unavailable</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style></style>
</head>
<body></body>
</head>

thì toàn bộ nội dung đó chỉ là do lệnh echo gửi về thôi - nó gọi là phần BODY 
(không phải là thẻ body trong thẻ HTML đâu nhé). thực ra trước khi gửi phần BODY
thì còn một phần HEADER do các lệnh header gửi về. Ví dụ như nội dung sau

HTTP/1.1 404 Not Found
Content-Type: application/json; charset=UTF-8
Cache-Control: no-cache, private
Date: Tue, 20 Aug 2025 09:25:40 GMT
Server: Apache/2.4.57 (Ubuntu)
Content-Length: 123

phần nội dung này khi dùng trình duyệt thì dùng ví dụ: Chrome DevTools (F12 → tab Network → click vào request → mục Headers).
mới nhìn ra được.

Các loại header thông dụng nhất:

- header HTTP Status Code 200, 404, 503, 500
Riêng phần header này có 2 cách gửi

header("HTTP/1.1 404 Not Found") và http_response_code(404) (cách này tiện hơn)
- header báo content type: Content-Type: application/json; charset=UTF-8.
Ví dụ dùng PHP: header('Content-Type: application/json; charset=UTF-8')



