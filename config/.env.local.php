<?php
    /*Mục đích: Cung cấp các tham số kết nối datatabase chỉ dùng cho môi trường development. 
     * Trên môi trường production dùng biến môi trường để đảm bảo security
     * Có thể định nghĩa ra nhiều bộ tham số cho nhiều connection: DEFAULT, etc
     * Dùng chữ in hoa để đảm bảo tương thích với các hằng số trong file config.php
     */
    return [
        /*các tham số của kết nối default*/    
        'DEFAULT_CONNECT' => [
            'DB_SERVER'     => 'localhost',
            'DB_NAME'       => 'dichvuqu_document',
            'DB_USER'       => 'dichvuqu_common',
            'DB_PASSWORD'   => 'que2keckd9;_',
            'DB_CHARSET'    => 'utf8mb4'
        ]
        /*Begin: các tham số của kết nối khác. Ví dụ 
         *, 'OTHER_CONNECT' => [
            'OTHER_DB_SERVER'     => 'localhost',
            'OTHER_DB_NAME'       => 'dichvuqu_document',
            'OTHER_DB_USER'       => 'dichvuqu_common',
            'OTHER_DB_CHARSET'    => 'dichvuqu_common',
            'OTHER_DB_PASSWORD'   => 'que2keckd9;_'
        ]
         */
        /*End: các tham số của kết nối khác*/    
    ];

