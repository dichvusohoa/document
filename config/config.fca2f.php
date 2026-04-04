<?php
/*config.fca2f.php
fca2f meaning: fully qualified class name - FQCN + action To function
 * fc = fully qualified class name - FQCN
 * a = Action
 * f = Function
 
  */
return[   
    \App\Controllers\_Shared\CategoryController::class => [   
        'index' =>['function'=>'index', 'method'=>'get'],
        'update' =>['function'=>'update', 'method'=>'post']
    ],
    \App\Controllers\_Shared\DocumentController::class =>[   
        'index' =>['function'=>'index', 'method'=>'get'],
        'update' =>['function'=>'update', 'method'=>'post']
    ],
    \Core\Controllers\LoginController::class =>[   
        'login' =>  ['function'=>'login', 'method'=>'post'],
        'logout' => ['function'=>'logout','method'=>'post']
    ],
    \Core\Controllers\HtmlPageControllers\LoginPageController::class =>[   
        'renderPage' =>  ['function'=>'renderPage', 'method'=>'get']
    ],
    \Core\Controllers\AdminLoginController::class =>[   
        'index' =>  ['function'=>'index', 'method'=>'get'],//show login form
        'login' =>  ['function'=>'login', 'method'=>'post'],
        'logout' => ['function'=>'logout','method'=>'post']
    ],
    \Core\Controllers\ClientInfoController::class =>[
        'index' =>  ['function'=>'index', 'method'=>'json']
    ]
];
        
  

