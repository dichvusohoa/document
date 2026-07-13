<?php
namespace Core\Routing;
use \InvalidArgumentException;
class MCARMeInfo {
    public static function buildEmpty(){ 
        return ['module'=> null, 'controller' => null, 'action' => null, 'role' => null, 'method' => null];
    }
    public static function isValid(mixed $arrData): bool {
        return  is_array($arrData)
        && array_key_exists('module',$arrData)
        && ($arrData['module'] === null || is_string($arrData['module']))       
        && array_key_exists('controller',$arrData)
        && ($arrData['controller'] === null || is_string($arrData['controller']))       
        && array_key_exists('action',$arrData)
        && ($arrData['action'] === null || is_string($arrData['action']))  
        && array_key_exists('role',$arrData)
        && ($arrData['role'] === null || is_string($arrData['role']) || is_array($arrData['role']))          
        && array_key_exists('method',$arrData)
        && ($arrData['method'] === null || is_string($arrData['method']));          
    }
}