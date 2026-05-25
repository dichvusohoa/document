<?php
return[
    /*'[module:*]' => \Core\Middlewares\ClientInfoMiddleware::class,*/
    '[module:*]' => \Core\Middlewares\PathMiddleware::class,
    '[module:pbt-framework|bud-project]' => \Core\Middlewares\AuthMiddleware::class
];
            
        
  

