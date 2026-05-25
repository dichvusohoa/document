<?php
    require '../vendor/autoload.php';
    require '../config/deploy.php';
    require '../config/config.php';
   // require '../config/main.php';
    use Core\Models\App;
    use Core\Models\Connection\Connection;
    use Core\Models\Request;
    use Core\Models\Auth\AuthContext;
    
    use Core\Models\Layout\BaseLayout;
    use Core\Models\Layout\BaseMobileDetectFactory;
    use Core\Models\Layout\BaseDeviceScreenFactory;
    use App\Models\Layout\Layout;
    use App\Models\Layout\MobileDetectFactory;
    use App\Models\Layout\DeviceScreenFactory;
    
    use Core\Models\RequestAuthContext;
    use Core\Models\Route\StaticRouter;
    use Core\Models\Route\ContextRouter;
    use Core\Models\Route\RouterFactory;
    
    use Core\Models\Cache\StaticRouterCache;
    use Core\Models\ErrorHandler;
    
   // use App\Controllers\HtmlPageControllers\LoginPageController;
    use Core\Controllers\ControllerFactory;
    use Core\Models\HtmlKernel;
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
    $container->set(ControllerFactory::class, function($c){
        return new (ControllerFactory::class)(
            $c    
        );
    });
    //$container->get(LoginPageController::class);
    //3.End chỉ set các class có contructor đặc biệt không tạo tự động được 
    $kernel = new HtmlKernel(
        $container->get(RequestAuthContext::class),
        $container->get(RouterFactory::class),
        $container->get(ControllerFactory::class)
    );
    $kernel->dispatch();
    
 