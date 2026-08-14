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
    public function dispatch(): void{
        $arrFQCN = require CONFIG_PATH.'/middleware.glb.php';
        $arrGlobalMiddleware = $this->middlewareFactory->createList($arrFQCN);
        $middlewareChain = new MiddlewareChain($arrGlobalMiddleware,[$this, 'handler']);
        $middlewareChain->handleChain($this->requestAuthContext);        
    }
    public function handler(): void {
        $contextRouter = $this->routerFactory->create();
        $match = $contextRouter->matchUri($this->requestAuthContext->request()); 
        $this->updateRequestAuthContext($match);
        $arrRouteInfo =    $match[ContextRouteInfo::FIELD_ROUTE_INFO];
        $strFQCN    = $arrRouteInfo[RouteInfo::FIELD_FQCN];
        $strFunction = $arrRouteInfo[RouteInfo::FIELD_FUNCTION];
        $controller = $this->controllerResolver->create($strFQCN, $this->requestAuthContext);
        $handler = function() use ($controller, $strFunction){
            $controller->doFunction($strFunction);
        };
        $arrMiddleware = $this->middlewareFactory->createList($match[ContextRouteInfo::FIELD_MIDDLEWARES]);
        $middlewareChain = new MiddlewareChain($arrMiddleware,$handler);
        $middlewareChain->handleChain($this->requestAuthContext);     
    }
 
    
    protected function updateRequestAuthContext(
        array $arrContextRouteInfo
    ): void {
        $arrFullRouteInfo =
            $arrContextRouteInfo[ContextRouteInfo::FIELD_ROUTE_INFO];
        //lọc ra dùng các fields cần thiết để truyền sang cho requestAuthContext
        $arrRouteInfo = [
            RouteInfo::FIELD_ROLES =>
                $arrFullRouteInfo[RouteInfo::FIELD_ROLES],
            RouteInfo::FIELD_ROUTE_TYPE =>
                $arrFullRouteInfo[RouteInfo::FIELD_ROUTE_TYPE]
        ];

        $this->requestAuthContext->setRouteMatchResult(
            $arrContextRouteInfo[ContextRouteInfo::FIELD_MCAO],
            $arrRouteInfo,
            $arrContextRouteInfo[ContextRouteInfo::FIELD_CONTEXT_ACCEPTED_ROLES],    
            $arrContextRouteInfo[ContextRouteInfo::FIELD_PROHIBITED_MODULE],
            $arrContextRouteInfo[ContextRouteInfo::FIELD_PROHIBITED_ROLE]
        );
    }
}
