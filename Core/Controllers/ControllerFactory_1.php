<?php
namespace Core\Controllers;
use Core\Models\Route\RouteInfo;
use Core\Models\App;
use Core\Models\Request;
use Core\Models\RequestAuthContext;
use Core\Controllers\BaseController;
use Core\Controllers\HtmlPageControllers\BaseHtmlPageController;
use \RuntimeException;

class ControllerFactory{
    
    public static function create(RequestAuthContext $requestAuthContext, array $arrTMCA, RouteInfo $routerInfo){
        $strControllerFQCN = $routerInfo['fqcn'];
        $strKey = App::getKey($strControllerFQCN);
        //đây là các controller đặc biệt được thiết kế riêng
        if(App::hasElement($strKey)){
            return App::get($strKey);
        }
        
        if(Request::isHtmlResponse()){
            return static::createHtmlController($strControllerFQCN, $request, $arrAuthInfo, $strLayoutFilePath, $arrUIContext, $strHtmlPageSchemaFQCN);
        }
        else{
            return static::createApiController($requestAuthContext, $strControllerFQCN);
        }
    }
    public static function createApiController(RequestAuthContext $requestAuthContext, string $strControllerFQCN){
        if (!class_exists($strControllerFQCN)) {
            throw new RuntimeException("Không tìm thấy controller: $strControllerFQCN");
        }
        $controller = new ($strControllerFQCN)($requestAuthContext);
        if (!($controller instanceof BaseController)) {
            throw new RuntimeException("Controller $strControllerFQCN phải kế thừa BaseController");
        }
        return $controller;
    }
    public static function createHtmlController(string $strControllerFQCN ){
        if (!class_exists($strControllerFQCN)) {
            throw new RuntimeException("Không tìm thấy controller: $strControllerFQCN");
        }
        //RequestAuthContext $requestAuthContext, string $strLayoutFilePath, array $arrUIContext, string $strHtmlPageSchemaFQCN
        $oLayout =  App::get('layout');
        $controller = new $strControllerFQCN($requestAuthContext, $oLayout->mapToLayoutFile(), $oLayout->mapToUiContext(), $arrRouteInfo['fqcn']['html_schema']);
        if (!($controller instanceof BaseHtmlPageController)) {
            throw new RuntimeException("Controller $strControllerFQCN phải kế thừa BaseHtmlPageController");
        }
        return $controller;
    }
}
