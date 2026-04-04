Sơ đồ load hiện nay

- ErrorHandler::register(true);
-  App::init($CLASS_NAME_MAP);
    + Đăng ký các service phức tạp bằng closure như là connection, dbService,auth, router
    cache router
- App::runGlobalMiddlewares();
- $router = App::get('router');
    $match = $router->matchUri($request); 
    if($match['path'] ===null || $match['route_info'] === null){
        //redirect ra file báo lỗi 404
        throw new HttpException(404, 'Not Found');
    }
 
