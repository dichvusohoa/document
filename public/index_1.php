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
    $requestAuth = App::get('request_auth_context');
    $router = App::get('context_router');
    $layout = App::get('layout');
    
    $schemaFactory = new SchemaFactory($layout);
    $controllerFactory = new ControllerFactory($schemaFactory);
    
    $kernel = new HtmlKernel(
        $requestAuth,
        $router,
        $controllerFactory
    );
    $kernel->dispatch();
    
 