<?php
    require '../vendor/autoload.php';
    require '../config/deploy.php';
    require '../config/config.php';
   // require '../config/main.php';
    use Core\Models\App;
    use Core\Models\RequestAuthContext;
    use Core\Models\ErrorHandler;
    use Core\Models\HtmlKernel;
    use Detection\MobileDetect;
    use App\Models\Layout;
    ErrorHandler::register(true);
    $container = new App();
    //1. set các class bị overriden 
    
    //2. chỉ set các class có contructor đặc biệt không tạo tự động được 
    $fn1 = function($c){
        return new MobileDetect(
            $c->get(MobileDetect)
        );
    };
    $fn2 = function($c){
        return Session::get('device_screen');
    };
    $container->set(Layout::class, function($c){
        return new Layout(
            $c->get(RequestAuthContext),
            $fn1,
            $fn2    
        );
    });
    
 