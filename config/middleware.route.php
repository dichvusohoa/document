<?php
return[
    '[module:*]' => \Core\Middleware\ClientInfoMiddleware::class,
    '[module:pbt-framework|bud-project]' => \Core\Middleware\AuthMiddleware::class
];
            
        
  

