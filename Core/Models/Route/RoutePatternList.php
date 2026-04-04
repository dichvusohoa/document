<?php
namespace Core\Models\Route;
use \InvalidArgumentException;
class RoutePatternList {
    public static function buildEmpty(){ 
        //fctype = fully qualified class name type. 
        return ['fctype'=> null,'module'=> null, 'controller' => null, 'action' => null, 'method' => null ,'role' => null];
    }
    public static function buildFromRoutePath(string $strRoutePath){ 
        $result = self::buildEmpty();//lấy khuôn mẫu kết quả
        $arrTmp = explode('/', $strRoutePath);
        foreach ($arrTmp as $strSegment){
            $isMatch = false;
            if (preg_match('/^\[(\w+):(.+)\]$/', $strSegment, $matches)) {
                $strType = $matches[1];
                if(array_key_exists($strType, $result)){
                    $isMatch = true;
                }
            }
            if($isMatch === false){//vì 2 lý do preg_match không match hoặc $strType là key lạ không có trong $result
                throw new InvalidArgumentException("Biểu thức {$strRoutePath}  có định dạng không phù hợp");
            }
            $result[$strType] = $strSegment;
        }
        return $result;
    }
    public static function match(array $arrSegmentExp, array $arrSegment): bool {
        $isMatchedAtLeastOnce = false;
        foreach($arrSegmentExp as $strType => $strExpr){
            if($strExpr === null || $arrSegment[$strType] === null){
                continue;
            }
            if(RoutePattern::match($strType, $strExpr, $arrSegment[$strType])){
                $isMatchedAtLeastOnce = true;
            } 
            else{
                return false;
            }
        }
        if($isMatchedAtLeastOnce){
            return true;
        }
        else{
             return false; //không xảy ra so sánh một lần nào
        }
    }
}