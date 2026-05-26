<?php
namespace Core\Middlewares;
use \Closure;
use Core\Models\Session;
use Core\Models\Request;
use Core\Models\RequestAuthContext;
use Core\Models\Response;
use Core\Models\Layout\BaseDeviceScreenFactory;
class ClientInfoMiddleware {
    protected BaseDeviceScreenFactory $deviceScreenFactory;
    public function __construct(BaseDeviceScreenFactory $deviceScreenFactory){
        $this->deviceScreenFactory = $deviceScreenFactory;
    }
    public function handle(RequestAuthContext $requestAuthContext, Closure $next){
        if( Request::isHtmlResponse() && 
            $this->deviceScreenFactory->requiresScreenDetection() && 
            Session::get('device_screen') === null){
            $strPath = CORE_PATH.'/views/layout/client_info.phtml';
            Response::sendHtmlFile($strPath, false, ['initialUri' => $_SERVER['REQUEST_URI'], 'postEndpoint' => URI_CLIENT_INFO]);
        }
        return $next($requestAuthContext); // ✅ trả về request để tiếp tục chu trình
    }
}
