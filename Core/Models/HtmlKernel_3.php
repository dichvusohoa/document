<?php
namespace Core\Models;
use Core\Models\Utility\ValidUtility;
use Core\Models\Route\Router;
use Core\Models\Cache\RouterCache;
class HtmlKernel  {
    protected string  $strAppFQCN;
    //từ $strAppFQCN tính ra request và arrAuthInfo
    /*protected Request $request;
    protected array   $arrAuthInfo;*/
    protected RequestAuthContext $requestAuthContext;
    protected RouterCache $routerCache;
    protected $routerProvider;
    protected $layoutProvider;
    
    public function __construct(
        RequestAuthContext $requestAuthContext, 
        RouterCache $routerCache,
        callable $routerProvider,
        callable $layoutProvider) {
        $this->requestAuthContext = $requestAuthContext;
        $this->routerCache     = $routerCache;
        $this->routerProvider  = $routerProvider;
        $this->layoutProvider  = $layoutProvider;
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
        $result = $this->route();
        $arrRouteInfo =    $result['route_info'];
        //tính ra controller để chạy tại nút của router
        $controller   =    $this->buildController($arrRouteInfo);
        if(Request::isHtmlResponse()){
            $strFunction = 'renderPage';
        }
        else{
            $strFunction = $arrRouteInfo['function'];
        }
        $handler = function() use ($controller, $strFunction){
            call_user_func([$controller, 'doAction'], $strFunction);
        };
        
        $arrMiddleware = self::buildRouteMiddlewares($arrRouteInfo);
        $middlewareChain = new MiddlewareChain($arrMiddleware,$handler);
        $middlewareChain->handleChain($this->requestAuthContext);     
    }
    protected function route(): array{
        $router = ($this->routerProvider)();
        $match  = $router->matchUri($this->requestAuthContext->resquest()); 
        if($match['path'] ===null || $match['route_info'] === null){
                //redirect ra file báo lỗi 404
            throw new HttpException(404, 'Not Found');
        }
        Session::set('route_mca', $match['path']);
        if(!$this->routerCache->exists()){//chưa tồn tại cache trong session
            $this->routerCache->saveCache($router);
        }
        elseif($match['attach_middlewares_after_match']){//cập nhật lại cache nếu có
            $this->routerCache->updateMiddlewareAtLeaf($match['path'],$match['route_info']['middlewares']);
        }
        return $match;
    }
    
    
    protected function buildController(array $arrRouteInfo){
        if(Request::isHtmlResponse()){
            $strControllerFQCN = $arrRouteInfo['fqcn']['html_class'];
            //$oLayout = new (App::$classMap['layout'])($request, $arrAuthInfo, Session::get('device_screen'), App::get('mobile_detect'), $match['path']);
            $oLayout = $this->layoutProvider();
            $strLayoutFilePath = $oLayout->mapToLayoutFile();
            $arrUIContext = $oLayout->mapToUiContext();
            $strHtmlPageSchemaFQCN = $arrRouteInfo['fqcn']['html_schema'];
        }
        else{
            $strControllerFQCN = $arrRouteInfo['fqcn']['json'];
            $strLayoutFilePath = null;
            $arrUIContext = null;
            $strHtmlPageSchemaFQCN =  null;
        }    
        //tạm thời đến đây cho tương thích
        $controller = $this->strAppFQCN::getClass('controller_factory')::createController($strControllerFQCN, $this->requestAuthContext->resquest(), $this->requestAuthContext->authInfo(), $strLayoutFilePath, $arrUIContext, $strHtmlPageSchemaFQCN);
        return $controller;
    } 
}
