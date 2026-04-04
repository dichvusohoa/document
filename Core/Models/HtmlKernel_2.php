<?php
namespace Core\Models;
use Core\Models\Utility\ValidUtility;

class HtmlKernel  {
    protected string  $strAppFQCN;
    //từ $strAppFQCN tính ra request và arrAuthInfo
    /*protected Request $request;
    protected array   $arrAuthInfo;*/
    protected RequestAuthContext $requestAuthContext;


    public function __construct(string $strAppFQCN, RequestAuthContext $requestAuthContext) {
        $this->strAppFQCN = $strAppFQCN;
       // $this->request    = $this->strAppFQCN::get('request');
     //   $this->arrAuthInfo =  $this->strAppFQCN::get('auth')->getAuthInfo(); 
        $this->requestAuthInfo = $requestAuthContext;
    }
    public function dispatch(){
        $arrMiddleware =  self::buildGlobalMiddlewares();
        $middlewareChain = new MiddlewareChain($arrMiddleware,[$this, 'buildHandler']);
        $middlewareChain->handleChain($this->requestAuthInfo);        
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
        if(Request::getResponseType() === Response::RESPONSE_HTML_TYPE){
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
        $middlewareChain->handleChain($this->requestAuthInfo);     
    }
    protected function route(): array{
        $router = $this->strAppFQCN::get('router');
        $match  = $router->matchUri($this->requestAuthInfo->resquest()); 
        if($match['path'] ===null || $match['route_info'] === null){
                //redirect ra file báo lỗi 404
            throw new HttpException(404, 'Not Found');
        }
        Session::set('route_tmca', $match['path']);
        $routerCache = $this->strAppFQCN::get('router_cache');
        if(!$routerCache->exists()){//chưa tồn tại cache trong session
            $routerCache->saveCache($router);
        }
        elseif($match['attach_middlewares_after_match']){//cập nhật lại cache nếu có
            $routerCache->updateMiddlewareAtLeaf($match['path'],$match['route_info']['middlewares']);
        }
        return $match;
    }
    
    
    protected function buildController(array $arrRouteInfo){
        if(Request::getResponseType() == Response::RESPONSE_HTML_TYPE){
            $strControllerFQCN = $arrRouteInfo['fqcn']['html_page'];
            //$oLayout = new (App::$classMap['layout'])($request, $arrAuthInfo, Session::get('device_screen'), App::get('mobile_detect'), $match['path']);
            $oLayout = $this->strAppFQCN::get('layout');
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
        $controller = $this->strAppFQCN::getClass('controller_factory')::createController($strControllerFQCN, $this->requestAuthInfo->resquest(), $this->requestAuthInfo->authInfo(), $strLayoutFilePath, $arrUIContext, $strHtmlPageSchemaFQCN);
        return $controller;
    } 
}
