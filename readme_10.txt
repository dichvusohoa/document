Vấn đề lỗi và xử lý lỗi trong một hệ thống PHP chạy trên một web server (apache
lite speed, nginx

1. Lỗi ở cấp độ web server.
(Xảy ra ở tầng dưới – Apache, Nginx – khi PHP chưa được chạy hoặc không chịu trách nhiệm xử lý)

Mã lỗi HTTP	Ý nghĩa	Khi nào xảy ra	Do ai xử lý
404	Not Found	URL không map vào file hay route nào	Web server hoặc framework
403	Forbidden	Không đủ quyền truy cập file/thư mục	Web server
500	Internal Server Error	PHP bị lỗi nghiêm trọng	PHP sinh lỗi, Web server gửi lại mã
502	Bad Gateway	Lỗi giữa proxy & PHP backend	Web server hoặc Nginx
503	Service Unavailable	Server đang bảo trì	Web server hoặc bạn tự set

về lỗi 404 nêú bạn dùng .htaccess để cấu hình hệ thống chỉ còn 1 entry point duy nhất index.php
( không còn các file như 404.php, login.php...) nữa. Đây là xu hướng đa số các framework hiện nay đi theo

Việc dùng nhiều file làm điểm entry point có nhiều bất lợi :

- gây trùng lặp code. Ví dụ trang index.php và login.php thì đều phải include các file như config.php, deploy.php ở đầu
- khó truyền thông tin khi redirect từ trang nọ sang trang kia. 
- khó khăn trong việc code các model. ví dụ như việc kiểm tra authenticate xem là việc thường luôn làm ở đầu page.
 ví dụ Auth.class.php  khi kiểm tra xem user đã authenticate chưa. Nếu chưa thì hành vi tiếp theo lại tùy thuộc bạn đang ở page nào. Nếu đang ở index.php rồi thì mới redirect sang login.php
càng có nhiều trang như error.php, logoff.php thì logic lập trình càng rắc rối. 

nếu chỉ dùng index.php làm entry point duy nhất thì bạn có thể xử lý lỗi 404 tại index.php



2. Lỗi ở cấp độ PHP
hệ thống lỗi trong PHP vừa cũ vừa mới, khiến việc hiểu rõ khá rắc rối. 
Dưới đây là bản tổng hợp hoàn chỉnh, có phân nhóm, phân biệt rõ loại lỗi (error type) 
và hệ thống phân cấp class (Throwable), kèm sơ đồ cây.

Đại thể nó có 2 nhánh lớn

+--------------------------+
                 |     PHP Lỗi (Error)      |
                 +--------------------------+
                     /                   \
        +-------------------+     +------------------+
        | Hằng số lỗi (E_*) |     |   Throwable Tree |
        +-------------------+     +------------------+
        | - E_ERROR          |     | Throwable        |
        | - E_PARSE          |     | ├─ Exception     |
        | - E_WARNING        |     | │   ├─ Runtime.. |
        | - E_NOTICE         |     | │   └─ PDO...    |
        | - E_STRICT         |     | └─ Error         |
        | - E_DEPRECATED     |     |     ├─ TypeError |
        | - E_COMPILE_ERROR  |     |     ├─ ParseError|
        | - E_USER_WARNING   |     |     ├─ Arith...  |
        | - E_USER_NOTICE    |     |     └─ ...       |
        +-------------------+     +------------------+

A.Lỗi kiểu cũ (error types - E_*) lỗi này không có class
B. Lỗi kiểu mới (class kế thừa Throwable)

Sơ đồ cây Throwable
php
Copy
Edit
Throwable
├── Exception                (logic exception - do lập trình viên)
│   ├── RuntimeException
│   ├── InvalidArgumentException
│   └── PDOException
│       ...
└── Error                    (lỗi hệ thống - engine, code)
    ├── TypeError
    ├── ParseError
    ├── ArithmeticError
    │   └── DivisionByZeroError
    ├── AssertionError
    └── CompileError (PHP 8.3+) 

các lỗi implement interface Throwable này là bắt trong khối try/catch được

3. Phương pháp xử lý

Lỗi ở cấp độ web server. Có thể custom việc xử lý lỗi 404
- Cấu hình .htaccess để chỉ có 1 entry point  là index.php
-khi xảy ra lỗi 404 thì trên file index.php sẽ xử lý thủ công,(phần này nói sau)

Lỗi ở cấp độ PHP. Qui trình 3 bước. Ba bước này hầu hết đặt ở đầu file index.php

set_error_handler() – chuyển Warning, Notice thành ErrorException. Nó có thể xử lý các lỗi
E_xxx như E_WARNING, E_NOTICE, E_USER_WARNING, E_DEPRECATED, nhưng không xử lý được các lỗi
nặng như E_ERROR, E_PARSE, ....

set_exception_handler() – xử lý mọi Throwable chưa bắt bao gồm cả các Throwable do set_error_handler(
tạo ra và chuyển sang

register_shutdown_function() – xử lý lỗi nghiêm trọng (fatal error, parse error…)

4. Cấu hình
error_reporting(E_ALL);//báo cáo tất cả lỗi
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('log_errors', 1);   


error_reporting chỉ ảnh hưởng đến các lỗi kiểu cũ ( dạng E_*) không ảnh hưởng đến
các lỗi dạng Throwable 
vì error_reporting() chỉ áp dụng cho các lỗi truyền thống kiểu E_WARNING, E_NOTICE, E_DEPRECATED, v.v.
chú ý rằng các lỗi Fatal error, Parse error thì  error_reporting cũng không tác động vào được ( các lỗi
dạng E_ERROR, E_PARSE). Tức là nếu  error_reporting(0) nhưng nếu có lỗi dạng E_ERROR, E_PARSE
thì nó không ảnh hưởng được.

error_reporting cũng ảnh hưởng tới hàm set_error_handler(). Ví dụ nếu error_reporting(0) thì sẽ
làm một số lỗi như E_WARNING, E_NOTICE sẽ không được chuyển tới set_error_handler();

error_reporting ảnh hưởng tới việc ghi log. Ví dụ error_reporting(0), ini_set('log_errors', 1); thì
sẽ làm một số lỗi như E_WARNING, E_NOTICE không được ghi lỗi.

ini_set('display_startup_errors', 1);  hiển thị các lỗi trước khi script PHP chạy, gồm
    PHP load các extension (ví dụ: pdo, mbstring, gd...)
    Lỗi trong php.ini (sai cú pháp, thiếu cấu hình cần thiết)
    Lỗi khi PHP khởi tạo môi trường thực thi (memory limit, syntax config,...)

5 Cách đọc hiển thị lỗi - custom lại hiển thị và ghi log lỗi
Trước tiên ta hiểu cách hiển thị và log mặc định của PHP để sau đó custom lại
mặc định hiển thị

PHP Fatal error:  Uncaught ErrorException: [message] in [file]:[line]
Stack trace:
#0 [file(line)]: [function]
#1 ...
thrown in [file] on line [line]

đây là cấu trúc báo lỗi theo thứ tự từ lõi sâu nhất hướng ra ngoài
- Dòng đầu tiên [message] in [file]:[line] là dòng báo lỗi ở lõi sâu nhất
- Stack trace: cấu trúc truy vết hướng từ sâu nhất ra ngoài cùng.

Chú ý rằng Stack trace không phải là độ sâu lồng do require hay include file gây ra
mà nó là do lời gọi function gây ra  ( funct1 gọi funct2 gọi funct 3.....)
Stact trace hiển thị bằng ký hiệu #0,#1, ..... #main.  #0 tương ứng với function
trực tiếp gây ra lỗi.

Nếu muốn custom lại việc hiển thị lỗi có thể cân nhắc như sau

try {
    // ...
} catch (ErrorException $e) {
    error_log("❌ ErrorException: " . $e->getMessage());
    error_log("📄 File: " . $e->getFile() . " (line " . $e->getLine() . ")");
    error_log("🔢 Severity: " . $e->getSeverity());
    error_log("🧵 Stack trace:\n" . $e->getTraceAsString());
}

custom lại việc ghi lỗi có thể cân nhắc sau
function logException(Throwable $e): void {
    $log = sprintf(
        "[%s] %s: %s in %s:%d\nStack trace:\n%s\n",
        date("Y-m-d H:i:s"),
        get_class($e),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        $e->getTraceAsString()
    );
    error_log($log); // hoặc ghi vào file riêng nếu muốn
}