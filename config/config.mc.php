<?php
/*config.mc.php  Example
return [
    'module-controllers' => [
        'module1' => ['controller1', 'controller2'],
        'module2' => ['controller3', 'controller4'],
    ],
    'standalone-controllers' => [
        'controller5',
        'controller6',
    ],
]; 
    controller5, controller6 là các controller độc lập không gắn vào module
 */
//Trong bài toán Document Management  thì không có module, chỉ có các controller độc lập
return [
    'module-controllers' => [],    
    'standalone-controllers' => [
        'category',
        'document',
        'client-info'
    ],
];
        
  

