<?php
/*Cấu trúc dữ liệu ở Leaf của Router*/
namespace Core\Models\Route;
class RouteInfo{
    public static function isValid(mixed $arrData): bool {
        return  is_array($arrData)
        && isset($arrData['roles']) && is_array($arrData['roles'])   
        //fqcn = fully qualified class name
        && isset($arrData['fqcn']) && is_string($arrData['fqcn'])
        //html_schema chỉ có giá trị khi fqcn là dạng html_class
        && array_key_exists('html_schema',$arrData)
        && isset($arrData['function']) && is_string($arrData['function'])
        && isset($arrData['method']) && is_string($arrData['method']); //get, post, ..  
    }
    
}