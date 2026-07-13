<?php
/*config.fc.action.php
fc.action.php meaning: fully qualified class name - FQCN 
 * fc = fully qualified class name - FQCN

 
Chú ý FQCN =>[
        'action' => ['function' => 'functionName', 'method' => 'methodName']
        ...
    ]  
    Nếu khuyết 'function' => 'functionName' thì mặc định functionName trùng với action
 
  */
return[   
    \App\Controller\Category\CategoryPageController::class => [   
        'index' =>['function'=>'renderPage','method'=>'get'],
        'update' =>['method'=>'post']
    ],
    \App\Controller\Document\DocumentPageController::class =>[   
        'index' =>['method'=>'get'],
        'update' =>['method'=>'post']
    ],
    //\Core\Controller\Login\LoginControllerFactory::class =>[   
    //    'index' =>  ['function'=>'renderPage', 'method'=>'get'] /*Tạm thời*/
    //],
    \Core\Controller\Login\LoginPageController::class =>[   
        'index' =>  ['function'=>'renderPage', 'method'=>'get'], /*Tạm thời*/
        'login' =>  ['method'=>'post']
    ],
   /* \Core\Controllers\AdminLoginController::class =>[   
        'index' =>  ['function'=>'index', 'method'=>'get'],//show login form
        'login' =>  ['function'=>'login', 'method'=>'post'],
        'logout' => ['function'=>'logout','method'=>'post']
    ],*/
    \Core\Controller\ClientInfoController::class =>[
        'index' =>  ['method'=>'json']
    ]
];
        
  

