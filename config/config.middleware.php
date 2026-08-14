<?php
return[
 
    /*biểu thức viết dưới dạng a/b/c/d/e => array 
     * trong đó a, b, c có thể là các strModuleExpr, strControlerExpr,strActionExpr,
     * strRoleExpr, strMethodExpr 
     */
    '[controller:*]' => [\Core\Middleware\AuthMiddleware::class,\Core\Middleware\ClientInfoMiddleware::class]
];
            
        
  

