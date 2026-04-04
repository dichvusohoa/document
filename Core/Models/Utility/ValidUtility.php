<?php
namespace Core\Models\Utility;
class ValidUtility{
    
    static public function isStringList(mixed $arr): bool {
        if(!is_array($arr)){
            return false;
        }
        $i = 0;
        foreach ($arr as $key => $value) {
            if($key !== $i){
                return false;
            }
            if(!is_string($value)){
                return false;
            }
            $i++;
        }
        return true;
    } 
    /*---------------------------------------------------------------------------------------------------------------*/
    static public function equalStringListAsSet(array $arr1, array $arr2): bool {
        if (!self::isStringList($arr1) || !self::isStringList($arr2)) {
            return false;
        }
        if (count($arr1) !== count($arr2)) {
            return false;
        }
        // array_diff có độ phức tạp O(n)
        return empty(array_diff($arr1, $arr2)) && empty(array_diff($arr2, $arr1));
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    static public function isStringPairMap(mixed $arr): bool {
        if(!is_array($arr)){
            return false;
        }
        foreach ($arr as $key => $value) {
            if(!is_string($key) || !is_string($value)){
                return false;
            }
        }
        return true;
    } 
    /*---------------------------------------------------------------------------------------------------------------*/
    static public function equalStringPairMapAsSet(array $map1, array $map2, bool $allowDuplicate = false): bool {
        if (!self::isStringPairMap($map1) || !self::isStringPairMap($map2)) {
            return false;
        }
        // Set: chỉ cần key=>value giống nhau, bỏ qua thứ tự key
        if (count($map1) !== count($map2)) {
            return false;
        }
        //Bỏ qua thứ tự key bằng cách kiểm tra hai chiều: array_diff_assoc($map1,$map2) và ngược lại.
        return empty(array_diff_assoc($map1, $map2)) && empty(array_diff_assoc($map2, $map1));
        
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    static public function isIntStringMap(mixed $arr): bool {
        if (!is_array($arr)) {
            return false;
        }
        foreach ($arr as $key => $value) {
            if (!is_int($key) || !is_string($value)) {
                return false;
            }
        }
        return true;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    static public function equalIntStringMapAsSet(array $map1, array $map2): bool{
        if (!self::isIntStringMap($map1) || !self::isIntStringMap($map2)) {
            return false;
        }
        if (count($map1) !== count($map2)) {
            return false;
        }
        return empty(array_diff_assoc($map1, $map2))
            && empty(array_diff_assoc($map2, $map1));
    }
}
