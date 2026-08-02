<?php
return [
    'login' => [
        'accepted_roles_pattern' => '[role:!guest|admin]',
        'max_fail_count'       => 5,
        'turnstile' => 3, //các giá trị never|always|0|1|2... 
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
        
  

