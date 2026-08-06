<?php
namespace Core\Foundation;
use Core\Routing\RouterFactory;
use Core\Middleware\MiddlewareChain;
use Core\Middleware\MiddlewareFactory;
use Core\Controller\ControllerResolver;
use Core\Http\RequestAuthContext;
use Core\Http\HttpException;
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
        if($match['mcao'] === null || $match['route_info'] === null){
            //redirect ra file báo lỗi 404
            throw new HttpException(404, 'Not Found');
        }
        $arrRouteInfo =    $match['route_info'];
        $strFQCN    = $arrRouteInfo['fqcn'];
        $strFunction = $arrRouteInfo['function'];
        $controller = $this->controllerResolver->create($strFQCN, $this->requestAuthContext);
        $handler = function() use ($controller, $strFunction){
            //call_user_func([$controller, 'doAction'], $strFunction);
            $controller->doAction($strFunction);
        };
        $arrMiddleware = $this->middlewareFactory->createList($match['middlewares']);
        $middlewareChain = new MiddlewareChain($arrMiddleware,$handler);
        $middlewareChain->handleChain($this->requestAuthContext);     
    }
    protected function route(): array{
        $contextRouter = $this->routerFactory->create();
        $match= $contextRouter->matchUri($this->requestAuthContext->request()); 
        //Bổ sung thông tin cho requestAuthContext. Mục đích
        $this->requestAuthContext->setRoutePath($match['mcao']);
        $this->requestAuthContext->setRouteInfo($match['route_info']);
        $this->requestAuthContext->setProhibitedModule($match['prohibited_module']);
        $this->requestAuthContext->setProhibitedRole($match['prohibited_role']);
        return $match;
    }
    
    
    
}
