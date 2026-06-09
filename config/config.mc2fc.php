<?php
/*config.mc2fc.php
/*config.mc2fc meaning: configuration route path to fully qualified class name - FQCN
 * route path có cấu trúc dạng module/controller (module có thể khuyết thiếu)
  *  Module,Controller viết theo qui tắc kebab-case
 1.  Controlller ở router so với controller name ở class thì bỏ đi chữ controller. 
   Ví dụ  class FoodPriceController  thì khi ánh xạ ra router ở url thì sẽ là food-price
 2  So sánh với CSDL quan hệ thì M,C là key, N là value
 3.  trong route-path thì module-path expr dùng mode RoutePattern::EXPR_ALL_MODES (do có hiện tượng share một FQCN cho nhiều module)
 4.  phần controller-path thì expr dùng mode RoutePattern::EXPR_SINGLE_VALUE | RoutePattern::EXPR_INCLUDE_VALUE
 * do cân nhắc do FQCN là tên đầy đủ của một class controller name
  nên gần như bắt buộc controller-path cần phải xác định chính xác thì tính ra được FQCN cụ thể
 5. module thiếu ứng với trường hợp nào? . Có nhiều controller dùng để hỗ trợ và không gắn với module thương mại nào cả
 ví dụ như LoginController chẳng hạn 
 
 6. Chú ý là tổ chức thư mục của các file class controller không cần mô phỏng nhóm theo module
 Module là 1 khái niệm mang tính thương mại. Nó có ảnh hưởng đến giao diện vào url chương trình
 * Khi user truy cập các module thương mại khác nhau thì url sẽ khác nhau
 * Nhưng trong thư mục thì các file class controller nên phân chia theo chức năng chứ không nhất thiết
 * phải phản ánh đúng các module thương mại này
 * 
 * Ví dụ File CategoryController.php không nhất thiết phải nằm ở application/controllers/bug-project
 * 
 
  
không sửa ra [response_type:xx][module:yy][controller:zz] vì lý do có html_schema, dùng thế này tiện hơn
  */
return [
    '[module:*]/category' =>  \App\Controller\CategoryController::class,
    '[module:*]/document' =>  \App\Controller\DocumentController::class,
    'login' => \Core\Controller\Login\LoginPageController::class,
    'admin-login' => \Core\Controller\Login\LoginPageController::class,
    'client-info' => \Core\Controller\ClientInfoController::class
];
            
        
  

