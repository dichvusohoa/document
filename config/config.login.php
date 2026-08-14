<?php
return [
    'login' => [
        'input_actions' => ['index', 'login'],
        'default_input_action' => 'index',
        'accepted_roles_pattern' => '[role:!guest|admin]',
        'max_fail_count'       => 5,
        'turnstile' => 3, // Các giá trị: 'never', 'always' hoặc số nguyên dương 1, 2, 3...
        'remember_cookie'      => true,
        'remember_expire'      => 86400 * 7,
    ],    
    'cacquak' => [
        'input_actions' => ['index', 'login'],
        'default_input_action' => 'index',
        'accepted_roles_pattern' => 'admin',
        'max_fail_count'       => 3,
        'turnstile' => 'always',
        'remember_cookie'      => false,
    ],
];
        
  

