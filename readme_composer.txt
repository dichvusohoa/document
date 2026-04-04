1. Install composer
2. đầu tiên tại root của dự án tạo file composer.json
{
    "name": "tuanpt/document",
    "description": "quản lý tài liệu",
    "type": "project",
    "license": "proprietary",
    "authors": [
        {
            "name": "tuanpt"
        }
    ],
    "require": {
        
    },
    "autoload": {
        "psr-4": {
            "Core\\": "Core/",
            "App\\": "App/"
        }
    }
}
name": "tuanpt/document"

Ý nghĩa: Tên gói (package) của dự án.

Cấu trúc chuẩn: "vendor/package", ở đây:

tuanpt → vendor (tác giả hoặc công ty)

document → tên dự án/gói

Composer dùng để nhận diện package nếu bạn muốn publish hoặc dùng nội bộ.

2️⃣ "description": "quản lý tài liệu"

Ý nghĩa: Mô tả ngắn gọn về dự án.

Không bắt buộc, nhưng hữu ích khi publish lên Packagist hoặc quản lý nội bộ.

3️⃣ "type": "project"

Ý nghĩa: Loại package.

"project" → dự án hoàn chỉnh (khác với "library" là thư viện).

Composer dùng để biết cách xử lý khi cài đặt, ví dụ template dự án.

4️⃣ "license": "proprietary"

Ý nghĩa: Giấy phép (license) của dự án.

"proprietary" → bản quyền riêng, không phải open-source.

Có thể đổi thành các chuẩn như "MIT", "GPL-3.0" nếu dự án mở.

Quan trọng nhất là phần này 
"autoload": {
        "psr-4": {
            "Core\\": "Core/",
            "App\\": "App/"
        }
    }

Là ánh xạ namespace class => folder

Key là prefix của  namespace class ( ví dụ Core\Models\Request thì lấy Core\
Value là root của path to folder chứa class
3.chạy composer install (lần đầu) hoặc composer dump-autoload (sau khi thay đổi rule autoload).
Ở đây đã có file composer.json nên chạy composer dump-autoload. Sẽ sinh ra thư mục /vendor


4. Cài Mobile Detect qua Composer
Trong thư mục gốc dự án (nơi có composer.json), chạy lệnh:

composer require mobiledetect/mobiledetectlib
Composer sẽ:

Tải thư viện Mobile Detect về trong thư mục vendor/.

Ghi thêm vào phần "require" của composer.json.

Cập nhật lại vendor/autoload.php.

5. Trong index.php viết

    require '../vendor/autoload.php';
    require '../config/deploy.php';
    require '../config/config.php';

6. Nâng cấp
composer update mobiledetect/mobiledetectlib
nếu muốn update tất cả dùng
composer update
7. composer install và  composer update. composer update sẽ update lại source
của thư viện mới nhất. Còn . composer install thì keep lại version trong composer.lock

===================
Giải thích nguyên lý PSR-4

PSR-4 định nghĩa rằng:

“Autoloader sẽ loại bỏ namespace prefix khỏi FQCN, rồi nối phần còn lại vào base directory, thay \ bằng /, rồi thêm .php.”

Trong ví dụ này : 

Fully Qualified Class Name 	\Acme\Log\Writer\File_Writer
Namespace Prefix			Acme\Log\Writer
Base Directory				./acme-log-writer/lib/
Resulting File Path 		./acme-log-writer/lib/File_Writer.php

thì "autoload": {
        "psr-4":  viết thế nào
		
Viết là

{
    "autoload": {
        "psr-4": {
            "Acme\\Log\\Writer\\": "acme-log-writer/lib/"
        }
    }
}
===========================
lưu ý là khi viết các file model mới, thay đổi cấu trúc thư mục thì đều phải chạy
composer dump-autoload  -d D:\Projects\PHP\document

Khi triển khai ứng  dụng thật trên Linux chạy

composer dump-autoload  -o -d D:\Projects\PHP\document

thêm tham số -o để autoload sẽ sinh file classmap /vendor/composer/autoload_classmap.php chi tiết tới mức độ file

tức là maping Full class name trực tiếp vào 1 file php mà không cần nhiều cơ chế trung gian
nên đạt được tốc độ cao

