<?php
return[
    '[module:*]' => \Core\Middlewares\ClientInfoMiddleware::class,
    '[module:pbt-framework|bud-project]' => \Core\Middlewares\AuthMiddleware::class
];
            
        
  

