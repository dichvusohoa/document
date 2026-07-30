<?php
return [
    'login' => [
        'required_roles' => null,
        'max_fail_count'       => 5,
        'turnstile' => 3, //các giá trị never|always|0|1|2... 
        'remember_cookie'      => true,
        'remember_expire'      => 86400 * 7,
        'default_redirect'     => '/',
    ],    
    'cacquak' => [
        'required_roles' => ['admin'],
        'max_fail_count'       => 3,
        'turnstile' => 'always',
        'remember_cookie'      => false,
        'default_redirect'     => '/'
    ],
];
        
  

