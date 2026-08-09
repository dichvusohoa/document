<?php
namespace Core\Foundation;
use Core\Routing\RouterFactory;
use Core\Middleware\MiddlewareChain;
use Core\Middleware\MiddlewareFactory;
use Core\Controller\ControllerResolver;
use Core\Http\RequestAuthContext;
use Core\Http\HttpException;
use Core\Routing\RouteInfo;
use Core\Routing\ContextRouteInfo;
class HttpKernel  {
    protected RequestAuthContext $requestAuthContext;
    protected RouterFactory $routerFactory;
    protected MiddlewareFactory $middlewareFactory;
    protected ControllerResolver $controllerResolver;
    public function __construct(
        RequestAuthContext $requestAuthContext,
        RouterFactory $routerFactory,
        MiddlewareFactory $middlewareFactory,
        ControllerResolver $controllerResolver
    ) {
        $this->requestAuthContext   = $requestAuthContext;
        $this->routerFactory        = $routerFactory;
        $this->middlewareFactory    = $middlewareFactory;
        $this->controllerResolver    = $controllerResolver;
    }
    public function dispatch(){
        $arrFQCN = require_once CONFIG_PATH.'/middleware.glb.php';
        $arrGlobalMiddleware = $this->middlewareFactory->createList($arrFQCN);
        $middlewareChain = new MiddlewareChain($arrGlobalMiddleware,[$this, 'buildHandler']);
        $middlewareChain->handleChain($this->requestAuthContext);        
    }
    public function buildHandler() {
        $match = $this->route();
        if($match[ContextRouteInfo::FIELD_MCAO] === null || 
                $match[ContextRouteInfo::FIELD_ROUTE_INFO] === null){
            //redirect ra file báo lỗi 404
            throw new HttpException(404, 'ContextRouter chạy định tuyến (matchUri) trả về kết quả null');
        }
        $arrRouteInfo =    $match[ContextRouteInfo::FIELD_ROUTE_INFO];
        $strFQCN    = $arrRouteInfo[RouteInfo::FIELD_FQCN];
        $strFunction = $arrRouteInfo[RouteInfo::FIELD_FUNCTION];
        $controller = $this->controllerResolver->create($strFQCN, $this->requestAuthContext);
        $handler = function() use ($controller, $strFunction){
            //call_user_func([$controller, 'doAction'], $strFunction);
            $controller->doAction($strFunction);
        };
        $arrMiddleware = $this->middlewareFactory->createList($match[ContextRouteInfo::FIELD_MIDDLEWARES]);
        $middlewareChain = new MiddlewareChain($arrMiddleware,$handler);
        $middlewareChain->handleChain($this->requestAuthContext);     
    }
    protected function route(): array{
        $contextRouter = $this->routerFactory->create();
        $arrContextRouteInfo= $contextRouter->matchUri($this->requestAuthContext->request()); 
        
        // truyền cho requestAuthContext thông tin route_info chỉ bao gồm route_type,authentication_path,default_business_path
        $arrRouteInfo = [];
        $arrRouteInfo[RouteInfo::FIELD_ROUTE_TYPE] =
        $arrContextRouteInfo[ContextRouteInfo::FIELD_ROUTE_INFO][RouteInfo::FIELD_ROUTE_TYPE];
        $arrRouteInfo[RouteInfo::FIELD_AUTHENTICATION_PATH] =
        $arrContextRouteInfo[ContextRouteInfo::FIELD_ROUTE_INFO][RouteInfo::FIELD_AUTHENTICATION_PATH];
        $arrRouteInfo[RouteInfo::FIELD_DEFAULT_BUSINESS_PATH] =
        $arrContextRouteInfo[ContextRouteInfo::FIELD_ROUTE_INFO][RouteInfo::FIELD_DEFAULT_BUSINESS_PATH];
        
        //Bổ sung thông tin cho requestAuthContext
        $this->requestAuthContext->setRouteMatchResult(
                $arrContextRouteInfo[ContextRouteInfo::FIELD_MCAO], 
                $arrRouteInfo, 
                $arrContextRouteInfo[ContextRouteInfo::FIELD_AUTH_POLICY], 
                $arrContextRouteInfo[ContextRouteInfo::FIELD_PROHIBITED_MODULE], 
                $arrContextRouteInfo[ContextRouteInfo::FIELD_PROHIBITED_ROLE]);
        return $arrContextRouteInfo;
    }
    
}
