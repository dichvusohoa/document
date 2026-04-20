<?php
namespace Core\Models;
use Core\Models\Utility\ValidUtility;
use Core\Models\Route\ContextRouter;
use Core\Controllers\ControllerFactory;
class HtmlKernel  {
    protected RequestAuthContext $requestAuthContext;
    protected ContextRouter $router;
    protected ControllerFactory $controllerFactory;
    public function __construct(
        RequestAuthContext $requestAuthContext,
        ContextRouter $router,
        ControllerFactory $controllerFactory
    ) {
        $this->requestAuthContext = $requestAuthContext;
        $this->router = $router;
        $this->controllerFactory = $controllerFactory;
    }
    public function dispatch(){
        $arrGlobalMiddleware =  self::buildGlobalMiddlewares();
        $middlewareChain = new MiddlewareChain($arrGlobalMiddleware,[$this, 'buildHandler']);
        $middlewareChain->handleChain($this->requestAuthContext);        
    }
    protected static function buildGlobalMiddlewares(): array{                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                      
        $arrFQCN = require_once CONFIG_PATH.'/middleware.glb.php';
        return self::mapToClosureMiddlewares($arrFQCN);
    }
    protected static function buildRouteMiddlewares(array $arrRouteInfo): array{
        $arrFQCN = $arrRouteInfo['middlewares']; // danh sách middleware cho route
        
        return self::mapToClosureMiddlewares($arrFQCN);
    }
    protected static function mapToClosureMiddlewares(array $arrFQCN){
        if(!ValidUtility::isStringList($arrFQCN)){
            throw new UnexpectedValueException('Phải là một mảng string'); 
        }
        if(count($arrFQCN) === 0){
            return [];
        }
        $fnMap = function(string $strFQCN){
            return [new $strFQCN(), 'handle'];
        };
        return array_map($fnMap, $arrFQCN);
    }
    public function buildHandler() {
        $match = $this->route();
        $arrRouteInfo =    $match['route_info'];
        //tính ra controller để chạy tại nút của router
        $controller = $this->controllerFactory->create(
            $this->requestAuthContext, $arrRouteInfo
        );
        $strFunction = $arrRouteInfo['function'];
        $handler = function() use ($controller, $strFunction){
            //call_user_func([$controller, 'doAction'], $strFunction);
            $controller->doAction($action);
        };
        
        $arrMiddleware = self::buildRouteMiddlewares($arrRouteInfo);
        $middlewareChain = new MiddlewareChain($arrMiddleware,$handler);
        $middlewareChain->handleChain($this->requestAuthContext);     
    }
    protected function route(): array{
        $match= $this->ctxRouter->matchUri($this->requestAuthContext->resquest()); 
        if($match['path'] === null){
            //redirect ra file báo lỗi 404
            throw new HttpException(404, 'Not Found');
        }
        if($match['route_info'] === null){
            if($match['prohibited_module'] === true || $match['prohibited_role'] === true){
                throw new HttpException(403, 'Forbidden');
            }
            
            throw new HttpException(404, 'Not Found');
            
        }
        $this->requestAuthContext->setRoutePath($match['path']);
        //App::set('route_match', $match);
        return $match;
    }
    
    
    
}
