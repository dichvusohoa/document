<?php
    require '../vendor/autoload.php';
    require '../config/deploy.php';
    require '../config/config.php';
   // require '../config/main.php';
    use Core\Models\App;
    use Core\Models\RequestAuthContext;
    use Core\Models\Route\StaticRouter;
    use Core\Models\Route\ContextRouter;
    use Core\Models\Cache\StaticRouterCache;
    use Core\Models\ErrorHandler;
    use Core\Models\HtmlKernel;
    use Detection\MobileDetect;
    use App\Models\Layout;
    ErrorHandler::register(true);
    $container = new App();
    //1. set các class bị overriden 
    
    //2. chỉ set các class có contructor đặc biệt không tạo tự động được 
    /*$requestAuthContext = $container::get(RequestAuthContext::class);
    $routerFactory = new RouterFactory(
        StaticRouter::class, 
        ContextRouter::class, 
        $requestAuthContext,
        $container::get(StaticRouterCache::class)
    );
    $kernel = new HtmlKernel(
        $requestAuth,
        $routerFactory,
        $controllerFactory
    );
    $kernel->dispatch();*/
    
 