<?php
namespace Core\Foundation;
use Core\Routing\RouterFactory;
use Core\Middleware\MiddlewareChain;
use Core\Middleware\MiddlewareFactory;
use Core\Controller\BaseController;
use Core\Controller\BaseControllerFactory;
use Core\Http\RequestAuthContext;
class HttpKernel  {
    protected RequestAuthContext $requestAuthContext;
    protected RouterFactory $routerFactory;
    protected MiddlewareFactory $middlewareFactory;
  
    public function __construct(
        RequestAuthContext $requestAuthContext,
        RouterFactory $routerFactory,
        MiddlewareFactory $middlewareFactory
    ) {
        $this->requestAuthContext   = $requestAuthContext;
        $this->routerFactory        = $routerFactory;
        $this->middlewareFactory    = $middlewareFactory;
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
        /*$controller = $this->controllerFactory->create(
        $this->requestAuthContext, $arrRouteInfo);
        $strFunction = $arrRouteInfo['function'];
        $handler = function() use ($controller, $strFunction){
            //call_user_func([$controller, 'doAction'], $strFunction);
            $controller->doAction($strFunction);
        };*/
        $strFQCN    = $arrRouteInfo['fqcn'];
        $strFunction = $arrRouteInfo['function'];
        $container = $this->middlewareFactory->getContainer();
        if (is_subclass_of($strFQCN, BaseController::class)){
            $controller = $container->get($strFQCN);
        }
        //BaseControllerFactory
        else if (is_subclass_of($strFQCN, BaseControllerFactory::class)){
            $controller = $container->get($strFQCN)->create($this->requestAuthContext);
        } 
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
        $this->requestAuthContext->setRoutePath($match['path']);
        $this->requestAuthContext->setProhibitedModule($match['prohibited_module']);
        $this->requestAuthContext->setProhibitedRole($match['prohibited_role']);
        
        return $match;
    }
    
    
    
}
