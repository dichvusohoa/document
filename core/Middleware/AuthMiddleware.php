<?php
namespace Core\Middleware;
use \Closure;
use Core\Routing\RouteInfo;
use Core\Routing\AuthRegistry;
use Core\Routing\RoleRegistry;
use Core\Http\RequestAuthContext;
use Core\Http\Response;
use Core\Auth\AuthResponse;
use Core\Http\Session;
use Core\Http\HttpException;
class AuthMiddleware {
    protected AuthRegistry $authRegistry;
    protected RoleRegistry $roleRegistry;
    public function __construct(AuthRegistry $authRegistry, RoleRegistry $roleRegistry) {
        $this->authRegistry = $authRegistry;
        $this->roleRegistry = $roleRegistry;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public function handle(RequestAuthContext $requestAuthContext, Closure $next){
        if( $requestAuthContext->prohibitedModule() || $requestAuthContext->prohibitedRole() ){
            $routeInfo = $requestAuthContext->routeInfo();
            $strRouteType = $routeInfo[RouteInfo::FIELD_ROUTE_TYPE];
            if($strRouteType === RouteInfo::ROUTE_TYPE_BUSINESS){
                $this->handleProhibitedBusinessPath($requestAuthContext);
            }
            else{
                $this->handleProhibitedAuthPath($requestAuthContext);
            }
        }
        return $next($requestAuthContext); // ✅ trả về request để tiếp tục chu trình
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected function handleProhibitedBusinessPath(RequestAuthContext $requestAuthContext){
        if(AuthResponse::isAuthenticated($requestAuthContext->auth())){
            throw new HttpException(403, 'không đủ quyền truy cập chức năng này');
        }
        $routeInfo = $requestAuthContext->routeInfo();
        //set 2 giá trị này vào Session để sau này  các Authentication Controller sử dụng đến
        Session::set('intended_url', $requestAuthContext->request()->fullUrl());
        Session::set('intended_roles', $routeInfo[RouteInfo::FIELD_ROLES]);
        $arrRoleCode = array_keys($requestAuthContext->userRoles());
        $strAuthUrl = $this->authRegistry->findAuthPathByRoles(
                $arrRoleCode, 
                $routeInfo[RouteInfo::FIELD_ROLES]);
        if($strAuthUrl){
            Response::redirect($strAuthUrl);
        }
        else{
            throw new UnexpectedValueException('Không tìm được đường dẫn authentication cho user'); 
        }
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected function handleProhibitedAuthPath(RequestAuthContext $requestAuthContext){
        //Lý do vì $requestAuthContext->userRoles() là array có format:[roleCode => displayName,...]
        $arrRoleCode = array_keys($requestAuthContext->userRoles());
        $strUrl = $this->roleRegistry->findDefaultBusinessUrlByRoles($arrRoleCode);
        if($strUrl){
            Response::redirect($strUrl);
        }
        else{
            throw new HttpException(403, 'User hiện tại không có quyền truy cập chức năng nghiệp vụ nào. Xem lại file config.role.php');
        }
    }
}
