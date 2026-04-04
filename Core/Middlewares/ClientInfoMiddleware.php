<?php
namespace Core\Middlewares;
use \Closure;
use Core\Models\App;
use Core\Models\Request;
use Core\Models\RequestAuthContext;
use Core\Models\Response;
use Core\Models\Session;
class ClientInfoMiddleware {
    public function handle(RequestAuthContext $requestAuthContext, Closure $next){
        if(Request::isHtmlResponse()){
            $arrRouteTMCA   = Session::get('route_tmca');
            $strLayoutFQCN  = App::getClass('layout');
            if( $strLayoutFQCN::requiresScreenDetection($requestAuthContext, $arrRouteTMCA)&&
                Session::get('device_screen') === null){
                $strPath = CORE_PATH.'/views/layout/client_info.phtml';
                Response::sendHtmlFile($strPath, false, ['initialUri' => $_SERVER['REQUEST_URI'], 'postEndpoint' => URI_CLIENT_INFO]);
            }
        }
        return $next($requestAuthContext); // ✅ trả về request để tiếp tục chu trình
    }
}
