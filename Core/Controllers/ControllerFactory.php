<?php
namespace Core\Controllers;
use Core\Models\Route\RouteInfo;
use Core\Models\App;
use Core\Models\RequestAuthContext;
use Core\Controllers\BaseController;
use Core\Controllers\HtmlPageControllers\BaseHtmlPageController;

class ControllerFactory{
    public static function create(RequestAuthContext $requestAuthContext, array $arrRouteMatch){
        $arrRouteInfo = $arrRouteMatch['route_info'];
        $strControllerFQCN = $arrRouteInfo['fqcn'];
        $strParentClass = get_parent_class($strControllerFQCN);
        if($strParentClass === 'Core\Controllers\BaseController'){
            return static::createController($strControllerFQCN);
        }
        else if($strParentClass === 'Core\Controllers\HtmlPageControllers\BaseHtmlPageController'){
            return static::createHtmlController($requestAuthContext, $strControllerFQCN);
        }
    }
    public static function createController(RequestAuthContext $requestAuthContext, string $strControllerFQCN){
        return new ($strControllerFQCN)($requestAuthContext);
    }
    public static function createHtmlController(string $strSchema, string $strControllerFQCN ){
        $layout =  App::get('layout');
        $schema = new $strSchema($layout);
        return new $strControllerFQCN($schema);
    }
}
