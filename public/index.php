<?php
    require '../vendor/autoload.php';
    require '../config/deploy.php';
    require '../config/config.php';
   // require '../config/main.php';
    use Core\Foundation\App;
    use Core\Database\Connection\Connection;
    use Core\Http\Request;
    use Core\Auth\AuthContext;
    
    use Core\View\Layout\BaseLayout;
    use Core\View\Layout\BaseMobileDetectFactory;
    use Core\View\Layout\BaseDeviceScreenFactory;
    use App\View\Layout\Layout;
    use App\View\Layout\MobileDetectFactory;
    use App\View\Layout\DeviceScreenFactory;
    
    use Core\Http\RequestAuthContext;
    use Core\Routing\StaticRouter;
    use Core\Routing\ContextRouter;
    use Core\Routing\RouterFactory;
    use Core\Middleware\MiddlewareFactory;
   
    
    use Core\Cache\StaticRouterCache;
    use Core\Foundation\ErrorHandler;
    
   // use App\Controller\HtmlPage\LoginPageController;
    //use Core\Controller\ControllerFactory;
    use Core\Controller\ControllerResolver;
    use Core\Foundation\HttpKernel;
    //use Detection\MobileDetect;
    
    ErrorHandler::register(true);
    $container = new App();
    //1. set các class bị overriden 
    $container->set(BaseMobileDetectFactory::class, function($c){
        return new (MobileDetectFactory::class)(
            $c->get(RequestAuthContext::class),
            $c  
        );
    });
    
    $container->set(BaseDeviceScreenFactory::class, function($c){
        return new (DeviceScreenFactory::class)(
            $c->get(RequestAuthContext::class)
        );
    });
    $container->set(BaseLayout::class, function($c){
        return new (Layout::class)(
            $c->get(RequestAuthContext::class),
            $c->get(BaseMobileDetectFactory::class),    
            $c->get(BaseDeviceScreenFactory::class)    
        );
    });
    
    //2.Begin chỉ set các class có contructor đặc biệt không tạo tự động được 
    $container->set(Connection::class, function ($c) {
        $cfg = loadCfgConnection();
        return new(Connection::class)($cfg);
    });
    $container->set(RequestAuthContext::class, function ($c) {
        return new (RequestAuthContext::class)(
            $c->get(Request::class),
            $c->get(AuthContext::class)->getAuthInfo()
        );
    });
    $container->set(RouterFactory::class, function($c){
        return new (RouterFactory::class)(
            StaticRouter::class, 
            ContextRouter::class, 
            $c->get(AuthContext::class)->getAuthInfo(),
            $c->get(StaticRouterCache::class)    
        );
    });
    $container->set(MiddlewareFactory::class, function($c){
        return new (MiddlewareFactory::class)($c); 
    });
    /*$container->set(ControllerFactory::class, function($c){
        return new (ControllerFactory::class)(
            $c    
        );
    });*/
    //$container->get(LoginPageController::class);
    //3.End chỉ set các class có contructor đặc biệt không tạo tự động được 
    $kernel = new HttpKernel(
        $container->get(RequestAuthContext::class),
        $container->get(RouterFactory::class),
        $container->get(MiddlewareFactory::class),    
        $container->get(ControllerResolver::class)
    );
    $kernel->dispatch();
    
 