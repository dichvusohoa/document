<?php
/*config.role.php liệt kê tất các các role. Trong code ta sử dụng phần key của array
ứng dụng khi dựng dữ liệu định tuyến thì dùng nó để valid các giá trị ra ngoài phạm vi
 * role đã định nghĩa trước
  */
return 
[
    'guest' => ['display_name' => 'khách', 'default_url' => '/document', 'weight' =>0 ], 
    'cm_admin' => ['display_name' => 'admin tài liệu sưu tập', 'default_url' => '/document', 'weight' =>2], 
    'it_admin' => ['display_name' => 'admin tài liệu tin học', 'default_url' => '/document', 'weight' =>2], 
    'pbt_fwk_user' => ['display_name' => 'người dùng PBT framework', 'default_url' => '/document', 'weight' =>1], 
    'pbt_fwk_admin' => ['display_name' => 'admin PBT framework', 'default_url' => '/document', 'weight' =>2], 
    'bud_prj_user'=> ['display_name' => 'người dùng BUD project', 'default_url' => '/document', 'weight' =>1], 
    'bud_prj_admin'=> ['display_name' => 'admin BUD project', 'default_url' => '/document', 'weight' =>2] ,
    'admin'=> ['display_name' => 'quản trị hệ thống', 'default_url' => '/document', 'weight' =>3]
];
            
        
  

