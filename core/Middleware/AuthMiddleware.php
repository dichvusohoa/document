<?php
namespace Core\Middleware;
use \Closure;
//use App\Foundation\App;
use Core\Http\RequestAuthContext;
use Core\Http\Response;
/*use Core\Foundation\ErrorHandler;
use Core\Foundation\ErrorInfo;
use Core\Foundation\Auth\Auth;*/
use Core\Auth\AuthInfo;
use Core\Http\Session;
class AuthMiddleware {
    public function handle(RequestAuthContext $requestAuthContext, Closure $next){
        if( $requestAuthContext->prohibitedModule() || $requestAuthContext->prohibitedRole() ){
            if(AuthInfo::isUnauthenticated($requestAuthContext->authInfo())){
                Session::set('intended_url', $requestAuthContext->request()->fullUrl()); 
                Response::redirect('/login');
            }
            else{
                throw new HttpException(403, 'không đủ quyền truy cập chức năng này');
            }
        }
        return $next($requestAuthContext); // ✅ trả về request để tiếp tục chu trình
    }
}
