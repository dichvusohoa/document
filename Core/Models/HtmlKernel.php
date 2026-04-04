<?php
namespace Core\Models;
use Core\Models\Utility\ValidUtility;
use Core\Controllers\ControllerFactory;
class HtmlKernel  {
    protected RequestAuthContext $requestAuthContext;
    public function __construct(RequestAuthContext $requestAuthContext) {
        $this->requestAuthContext = $requestAuthContext;
        
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
        $controller = ControllerFactory::create($this->requestAuthContext, $arrRouteMatch);
        $strFunction = $arrRouteInfo['function'];
        $handler = function() use ($controller, $strFunction){
            call_user_func([$controller, 'doAction'], $strFunction);
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
        else if($match['route_info'] === null){
            if($match['prohibited_module'] === true || $match['prohibited_role'] === true){
                throw new HttpException(403, 'Bị cấm rồi');
            }
            else{
                throw new HttpException(404, 'Not Found');
            }
        }
   
        App::set('route_match', $match);
        return $match;
    }
    
    
    
}
