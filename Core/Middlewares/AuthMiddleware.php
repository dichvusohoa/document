<?php
namespace Core\Middlewares;
use \Closure;
//use App\Models\App;
use Core\Models\RequestAuthContext;
use Core\Models\Response;
/*use Core\Models\ErrorHandler;
use Core\Models\ErrorInfo;
use Core\Models\Auth\Auth;*/
use Core\Models\Auth\AuthInfo;
use Core\Models\Session;
class AuthMiddleware {
    public function handle(RequestAuthContext $requestAuthContext, Closure $next){
        if(AuthInfo::isUnauthenticated($requestAuthContext->authInfo())){
            Session::set('intended_url', $request->fullUrl()); 
            Response::redirect('/login');
        }
        return $next($requestAuthContext); // ✅ trả về request để tiếp tục chu trình
    }
}
