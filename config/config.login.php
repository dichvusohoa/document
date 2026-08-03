<?php
return [
    'login' => [
        'accepted_roles_pattern' => '[role:!guest|admin]',
        'max_fail_count'       => 5,
        'turnstile' => 3, // Các giá trị: 'never', 'always' hoặc số nguyên dương 1, 2, 3...
        'remember_cookie'      => true,
        'remember_expire'      => 86400 * 7,
        'default_business_path'     => '/',
    ],    
    'cacquak' => [
        'accepted_roles_pattern' => 'admin',
        'max_fail_count'       => 3,
        'turnstile' => 'always',
        'remember_cookie'      => false,
        'default_business_path'     => '/'
    ],
];
        
  

