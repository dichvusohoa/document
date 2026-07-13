<?php
return[
    //'[controller:*]' => [\Core\Middleware\AuthMiddleware::class,\Core\Middleware\ClientInfoMiddleware::class]
    //'[controller:*]' => [\Core\Middleware\AuthMiddleware::class]
    '[controller:*]' => [\Core\Middleware\AuthMiddleware::class,\Core\Middleware\ClientInfoMiddleware::class]
];
            
        
  

