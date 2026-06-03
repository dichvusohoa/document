<?php
namespace Core\Middleware;
use \Closure;
use Core\Http\Session;
use Core\Http\Request;
use Core\Http\RequestAuthContext;
use Core\Http\Response;
use Core\View\Layout\BaseDeviceScreenFactory;
class ClientInfoMiddleware {
    protected BaseDeviceScreenFactory $deviceScreenFactory;
    public function __construct(BaseDeviceScreenFactory $deviceScreenFactory){
        $this->deviceScreenFactory = $deviceScreenFactory;
    }
    public function handle(RequestAuthContext $requestAuthContext, Closure $next){
        if( Request::isHtmlResponse() && 
            $this->deviceScreenFactory->requiresScreenDetection() && 
            Session::get('device_screen') === null){
            $strPath = CORE_PATH.'/resources/views/layouts/client_info.phtml';
            Response::sendHtmlFile($strPath, false, ['initialUri' => $_SERVER['REQUEST_URI'], 'postEndpoint' => URI_CLIENT_INFO]);
        }
        return $next($requestAuthContext); // ✅ trả về request để tiếp tục chu trình
    }
}
