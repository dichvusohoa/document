<?php
    require '../vendor/autoload.php';
    require '../config/deploy.php';
    require '../config/config.php';
   // require '../config/main.php';
    use Core\Models\App;
    use Core\Models\ErrorHandler;
    use Core\Models\HtmlKernel;
    ErrorHandler::register(true);
    App::init($CLASS_NAME_MAP);
    $router = App::get('context_router');
    $match  = $router->matchUri(App::get('request')); 
    var_dump($match);
    /*$htmlKernel = new HtmlKernel(
            App::get('request_auth_context'), 
            App::getFactory('router_factory'),
            App::get('router_cache')
    );
    $htmlKernel->dispatch();*/
    
 