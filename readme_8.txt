Về vấn đề session của PHP
1)
session của PHP hoạt động được thực chất vẫn cần cookie ở client.
- Phía client trình duyệt lưu một cookie có name thường là PHPSESSID , value thì
là id của session đó. Ví dụ PHPSESSID=abc123xyz
- Trên server thường lưu các session bằng các file *.sess (ví dụ thê)
- khi request từ client lên server thì client sẽ gửi PHPSESSID lên server để tìm ra
file *.sess chính xác 

2) session time out
Khi đặt time out của session bằng lệnh

ini_set('session.gc_maxlifetime', 1800);

thì không có nghĩa là chính xác sau 30 phút thì file session (*.sess)  trên server sẽ bị hủy. việc
soát và xóa file session trên server là do trình GC (Garbage Collector) chạy. vấn đề
là trình này không chạy khi session_start(); được gọi. Nó chạy theo xác suất.
nên không thể "dựa" hoàn toàn vào cơ chế tự động của PHP được. Nếu chỉ dựa vào ini_set('session.gc_maxlifetime', 1800);
thì có khi sau 30 phút idle, chưa chắc file session đã bị xóa và người dùng có thể là sau,  120, 150phút ...
vẫn không bị timeout session và tiếp tục chạy được => bảo mật

nên cần cơ chế 

if(time() - $_SESSION['auth']['last_activity'] > SESSION_TIMEOUT){
    //hành vi hủy session (nói ở phần sau) 
}
ở đây giả thiết là dữ liệu xác thực được lưu ở $_SESSION['auth']. last_activity là
key được bổ sung vào $_SESSION['auth'] ghi lại thời điểm cuối cùng $_SESSION['auth']
được truy xuất (đọc, ghi)

Tóm lại ta cần làm gì

a) trước khi ta gọi session_start(); cần có đoạn code tựa như sau


require_one (config.php);
$lifetime = 2 * SESSION_TIMEOUT; //hệ số 2 hoặc 1 con số nào đó > 1. SESSION_TIMEOUT thì có thể định nghĩa trong file config.php
ini_set('session.gc_maxlifetime', $lifetime);
session_set_cookie_params($lifetime);

điều này để đảm bảo không xảy ra việc xóa file session trên server và file cookie
ở client trước thời điểm SESSION_TIMEOUT

b)
session_start();
if(time() - $_SESSION['auth']['last_activity'] > SESSION_TIMEOUT){
    //hành vi hủy session (nói ở phần sau) 
}
else{
    $_SESSION['auth']['last_activity'] = time();
}

3) Hành vi hủy session

Trong $_SESSION có thể có nhiều key thí dụ $_SESSION["auth"] chứa thông tin authenticate
$_SESSION["something"]  chứa dữ liệu gì đó phục vụ cho nghiệp vụ chương trình

nhưng khi session hết hạn thì toàn bộ thông tin trong $_SESSION phải bị xóa . Hành vi này gồm
3 bước

$_SESSION = []; //xóa dữ liệu của  $_SESSION trong bộ nhớ. dùng session_unset();cũng được

session_destroy();// xóa các file *.sess trên server. Chú ý là vẫn phải có $_SESSION = []
vì session_destroy() không xóa $_SESSION , nếu script PHP chạy tiếp và truy xuất vào\
 $_SESSION thì có thể gây lầm

// Xoá cookie PHPSESSID ở trình duyệt
setcookie(session_name(), '', time() - 3600, '/'); //session_name() thường trả về PHPSESSID 
lệnh này để xóa cookie PHPSESSID ở trình duyệt, nếu không có lệnh này thì sau đó client request
lên server có thể nó vẫn request với Id cũ, server sẽ không tìm thấy file session phù hợp
và tạo file *.sess mới với ID cũ. quá trình này thì có thể không vấn đề gì, hoặc có
thể có rủi ro nào đó. Để đảm bảo an toàn thì vẫn nên xóa cookie đi

//xóa thế nào. lệnh setcookie ngoài việc đặt lùi thời gian để xóa: time() - 3600 còn nên
đặt các tham số khác (domain, path, httponly...) cho khớp vói lúc tạo cookie. Nên khuyến cáo như sau

$params = session_get_cookie_params();

setcookie(
    session_name(),
    '',
    time() - 3600,
    $params['path'],
    $params['domain'],
    $params['secure'],
    $params['httponly']
);





