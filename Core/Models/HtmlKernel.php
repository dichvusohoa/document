<?php
namespace Core\Models;
use Core\Models\Route\RouterFactory;
use Core\Middlewares\MiddlewareFactory;
use Core\Controllers\ControllerFactory;
class HtmlKernel  {
    protected RequestAuthContext $requestAuthContext;
    protected RouterFactory $routerFactory;
    protected MiddlewareFactory $middlewareFactory;
    protected ControllerFactory $controllerFactory;
    public function __construct(
        RequestAuthContext $requestAuthContext,
        RouterFactory $routerFactory,
        MiddlewareFactory $middlewareFactory,
        ControllerFactory $controllerFactory
    ) {
        $this->requestAuthContext   = $requestAuthContext;
        $this->routerFactory        = $routerFactory;
        $this->middlewareFactory    = $middlewareFactory;
        $this->controllerFactory    = $controllerFactory;
    }
    public function dispatch(){
        $arrFQCN = require_once CONFIG_PATH.'/middleware.glb.php';
        $arrGlobalMiddleware = $this->middlewareFactory->createList($arrFQCN);
        $middlewareChain = new MiddlewareChain($arrGlobalMiddleware,[$this, 'buildHandler']);
        $middlewareChain->handleChain($this->requestAuthContext);        
    }
    public function buildHandler() {
        $match = $this->route();
        if($match['path'] === null || $match['route_info'] === null){
            //redirect ra file báo lỗi 404
            throw new HttpException(404, 'Not Found');
        }
        $arrRouteInfo =    $match['route_info'];
        $controller = $this->controllerFactory->create(
        $this->requestAuthContext, $arrRouteInfo);
        $strFunction = $arrRouteInfo['function'];
        $handler = function() use ($controller, $strFunction){
            //call_user_func([$controller, 'doAction'], $strFunction);
            $controller->doAction($strFunction);
        };
        //$arrMiddleware = self::buildRouteMiddlewares($match['middlewares']);
        $arrMiddleware = $this->middlewareFactory->createList($match['middlewares']);
        $middlewareChain = new MiddlewareChain($arrMiddleware,$handler);
        $middlewareChain->handleChain($this->requestAuthContext);     
    }
    protected function route(): array{
        $contextRouter = $this->routerFactory->create();
        $match= $contextRouter->matchUri($this->requestAuthContext->request()); 
        $this->requestAuthContext->setRoutePath($match['path']);
        $this->requestAuthContext->setProhibitedModule($match['prohibited_module']);
        $this->requestAuthContext->setProhibitedRole($match['prohibited_role']);
        
        return $match;
    }
    
    
    
}
