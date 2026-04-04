LOGIN
1. Sơ đồ thuật toán LOGIN vào hệ thống qua trang login
 giả sử cấu trúc dữ liệu login là $verify. 
 1.1 So sánh $_SESSION["imagesecurity"] và  $verify["imagesecurity"] xem có khớp không
 1.2  query DB bằng user_name  trả về các field sau, ví dụ chứa trong $arrQuery
    'user_id'  int
    'user_name' string ,
    'user_password' string  (đã mã hóa)
    'roles'     => array,
    'name' or "title"      => string,
    'permissions' => array

So sánh $verify["user_password"]  và  $user_password bằng password_verify (lệnh so sánh
một chuỗi chưa mã hóa $verify["user_password"] và một chuỗi đã mã hóa $user_password)
1.3 Nếu khớp password thì cập nhật lại  $_SESSION, các thông tin trong session đều lấy từ
arrQuery ra trừ filed 'last_login'
 $_SESSION["auth"] =  [
                'user_id'  int
                'user_name' string ,
                'user_password' string , (đã mã hóa)
                'roles'     => array,
                'name' or "title"      => string,
                'permissions' => array, // có thể tính toán nếu cần
                'last_login' = time(); 
            ];

1.4 Nếu khớp password thì kiểm tra xem có yêu cầu ghi $_COOKIE không, nếu có thì ghi $_COOKIE

1.4.a Thuật toán ghi cookie

LEFT_TOKEN_LENGTH = 30;
RIGHT_TOKEN_LENGTH =  66;
 $strLeftToken =  bin2hex(random_bytes(self::LEFT_TOKEN_LENGTH >> 1));//lengh 30
 sRightToken = bin2hex(random_bytes(self::RIGHT_TOKEN_LENGTH >> 1));//leng 66

sRightHashedToken = hash('sha256',sRightToken);//mã hóa sRightToken
$sToken =  sLeftToken . ':' . sRightToken;

$_COOKIE["auth"] = ["token" = $sToken ]; // khi dùng lệnh ghi cookie (setcookie) có đặt thêm tham số ngày hết hạn

1.4.b. Ghi token vào DB. Một row trong đó có các dữ liệu sau

auth_token_id KEY => autoincrement  : tự sinh ra
selector  => thuộc tính UNIQUE = $strLeftToken
hashed_validator = sRightHashedToken 
user_id 
exp_date = tính toán từ time() + Khoảng thời gian hết hạn

2. SƠ ĐỒ XÁC THỰC khi vào trang index.php
// ở đây không tóm tắt không nói về xác thực suspend chỉ nói về xác thực user

2.1 Trước tiên kiểm tra thông tin xác thực chứa trong  $_SESSION["auth"], yêu cầu là $_SESSION["auth"]
tồn tại và $_SESSION["auth"]['last_login'] chưa bị quá hạn. Nếu không được ( chưa có $_SESSION["auth"]
hoặc $_SESSION["auth"]['last_login'] đã bị quá hạn) thì phải chuyển sang xác thực bằng $_COOKIE

$_SESSION["auth"] =  [
                'user_id'  int
                'user_name' string ,
                'user_password' string , (đã mã hóa)
                'roles'     => array,
                'name' or "title"      => string,
                'permissions' => array, // có thể tính toán nếu cần
                'last_login' = time(); 
            ];


  query DB bằng user_name  trả về các field sau, ví dụ chứa trong $arrQuery
    'user_id'  int
    'user_name' string ,
    'user_password' string  (đã mã hóa)
    'roles'     => array,
    'name' or "title"      => string,
    'permissions' => array
So sánh $arrQuery["user_password"]  và  $_SESSION["auth"][$user_password] bằng hash_equals vì cả 2 chuỗi đã mã  hóa, hash_equals dùng để chống timing attack

Nếu khớp password thì cập nhật lại  $_SESSION, các thông tin trong session đều lấy từ
arrQuery ra trừ filed 'last_login'

Nếu không xác thực được bằng session thì xác thực bằng cookie

 2.2 Xác thực qua $_COOKIE

$sToken =  $_COOKIE["auth"]["token"]
Tính $sTokenLeft, $sTokenRight bằng split qua dấu ; ở giữa chuỗi $sToken ;
tính ra sRightHashedToken = hash('sha256', $sRightToken);

query vào cơ sở dữ liệu table auth_token by sTokenLeft, thêm điều kiện exp_date >= NOW(); ví dụ 
được $arrQuery có field selector, hashed_validator	

So sánh $sRightHashedToken và $arrQuery["hashed_validator"] bằng hash_equals


