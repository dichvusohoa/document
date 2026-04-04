<?php
/*config.api.mcr2a sử dụng trong việc phân quyền
mcr2a meaning module + controller + role  to action
1. route path có cấu trúc dạng module/controller (module có thể khuyết thiếu)
2. route path có thể chứa regex RoutePattern::EXPR_ALL_MODES tại vị trí module và RoutePattern::EXPR_SINGLE_VALUE | RoutePattern::EXPR_INCLUDE_VALUE tại vị trí controller
    nhằm tương thích với file config.mc2fc.php. Thực tế nếu không xác định rõ ràng
    cả module-path và controller-path (mà nhất là module-path) thì rất khó viết cấu hình phân quyền cho 
    rõ ràng trong sáng. Nên thực tế thì thường viết rõ rệt  cả module path và controller path
    trong route path. Ở đây ta phân tích vai trò của từng thành phần
        - module-path: thường nó giúp xác định ra bộ role hoạt động trên đó. Ví dụ ta có module là edutication thì
       có bộ role: school_admin, teacher_rle, pupil_role. Module nutrition thì có thể có role: ware_house, menu_maker   
        - controller-path: ví dụ trong một hệ thống cơ bản là phân cấp theo chức năng thì controller-path là một mức nhỏ hơn
       của module-path nó sẽ xác định được 1 bộ role nhỏ  hơn nữa, cụ thể hơn nữa
  
3. action cũng có thể chứa regex nhằm làm gọn file. Sau này khi dựng mô hình cây trong bộ nhớ sẽ được cụ thể hóa ra nhờ hàm allActions() của
   của một class controller cụ thể 
 
 2025-07-30 Thiết kế này là cân nhắc sau nhiều sự cân đối dung hòa của nhiều yếu tố
 * sự rõ ràng <> sự ngắn gọn của file
 * ví dụ route path tính đến 2 thành phần là cân đối giữa các thiết kế
 *  1 + 1 ( dạng array) vì nó khó đưa regex vào và làm array bị lồng sâu
 *  thiết kế dạng chuỗi module/controller/action vì nó tạo chuỗi lặp quá dài
 *  việc chỉ đưa regex vào 2 vị trí module và action cũng là cân nhắc
 
  */
return [
'compiled-materials/category' => ['guest'=>'index','cm_admin' => '[action:*]','admin' => '[action:*]'],
'compiled-materials/document' => ['guest'=>'index','cm_admin' => '[action:*]','admin' => '[action:*]'],
'it-documents/category' => ['guest'=>'index','it_admin' => '[action:*]','admin' => '[action:*]'],
'it-documents/document' => ['guest'=>'index','it_admin' => '[action:*]','admin' => '[action:*]'],
'pbt-framework/category' => ['pbt_fwk_user'=>'index','pbt_fwk_admin' => '[action:*]','admin' => '[action:*]'],
'pbt-framework/document' => ['pbt_fwk_user'=>'index','pbt_fwk_admin' => '[action:*]','admin' => '[action:*]'],
'bud-project/category' => ['bud_prj_user'=>'index','bud_prj_admin' => '[action:*]','admin' => '[action:*]'],
'bud-project/document' => ['bud_prj_user'=>'index','bud_prj_admin' => '[action:*]','admin' => '[action:*]'],
'login' => ['guest'=>'login'],
'admin-login' => ['guest'=>'login'],
'client-info' => ['[role:*]'=>'index']    
];

