<?php
/*config.fca2f.php
fca2f meaning: fully qualified class name - FQCN + action To function
 * fc = fully qualified class name - FQCN
 * a = Action
 * f = Function
 
  */
return[   
    \App\Controller\CategoryController::class => [   
        'index' =>['function'=>'index', 'method'=>'get'],
        'update' =>['function'=>'update', 'method'=>'post']
    ],
    \App\Controller\DocumentController::class =>[   
        'index' =>['function'=>'index', 'method'=>'get'],
        'update' =>['function'=>'update', 'method'=>'post']
    ],
    //\Core\Controller\Login\LoginControllerFactory::class =>[   
    //    'index' =>  ['function'=>'renderPage', 'method'=>'get'] /*Tạm thời*/
    //],
    \Core\Controller\Login\LoginPageController::class =>[   
        'index' =>  ['function'=>'renderPage', 'method'=>'get'], /*Tạm thời*/
        'login' =>  ['function'=>'login', 'method'=>'post']
    ],
   /* \Core\Controllers\AdminLoginController::class =>[   
        'index' =>  ['function'=>'index', 'method'=>'get'],//show login form
        'login' =>  ['function'=>'login', 'method'=>'post'],
        'logout' => ['function'=>'logout','method'=>'post']
    ],*/
    \Core\Controller\ClientInfoController::class =>[
        'index' =>  ['function'=>'index', 'method'=>'json']
    ]
];
        
  

