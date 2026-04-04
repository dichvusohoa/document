<?php
/*
 */
namespace Core\Models\Route;
use \InvalidArgumentException;
class RoutePattern {
    public const EXPR_SINGLE_VALUE     = 1;  // Giá trị đơn lẻ, ví dụ: "admin"
    public const EXPR_INCLUDE_VALUES   = 2;  // [type:a|b|c] → chỉ định danh sách cho phép
    public const EXPR_EXCLUDE_VALUES   = 4;  // [type:!a|b] → loại trừ danh sách
    public const EXPR_ALL_VALUES       = 8;  // [type:*] → tất cả giá trị
    public const EXPR_ALL_MODES = self::EXPR_SINGLE_VALUE | self::EXPR_INCLUDE_VALUES | 
    self::EXPR_EXCLUDE_VALUES | self::EXPR_ALL_VALUES;
    
    public const EXPR_MUST_BE_TYPED = self::EXPR_INCLUDE_VALUES | self::EXPR_EXCLUDE_VALUES | self::EXPR_ALL_VALUES;
    /**
    * Phân tích một biểu thức định tuyến dạng [type:expr] hoặc giá trị đơn lẻ.
    *
    * Biểu thức hỗ trợ các định dạng:
    *   - '[type:*]'         → tất cả các giá trị có trong $arrAllValue
    *   - '[type:!a|b|c]'    → tất cả trừ a, b, c
    *   - '[type:a|b|c]'     → chỉ gồm các giá trị a, b, c (nếu hợp lệ)
    *   - 'a'                → giá trị đơn lẻ
    *
    * @param string $strExpr Biểu thức cần phân tích.
    * @param array $arrAllValue Danh sách tất cả giá trị hợp lệ.
    * @param int $iAllowedModes Tổ hợp bitmask của các hằng số:
    *     - RoutePattern::EXPR_SINGLE_VALUE
    *     - RoutePattern::EXPR_INCLUDE_VALUES
    *     - RoutePattern::EXPR_EXCLUDE_VALUES
    *     - RoutePattern::EXPR_ALL_VALUES
    *   Chỉ cho phép các loại biểu thức tương ứng được sử dụng.
    *
    * @return array{
    *     type: string,       // tên segment nếu có (vd: "module"), hoặc chuỗi rỗng nếu là giá trị đơn lẻ
    *     mode: int,          // một trong các hằng số EXPR_* để xác định kiểu biểu thức
    *     rawExpr: string,    // biểu thức gốc đầu vào
    *     values: string[]    // danh sách giá trị sau khi xử lý
    * }
    *
    * @throws \InvalidArgumentException Nếu biểu thức sai định dạng hoặc không được phép bởi $iAllowedModes
    */
    public static function parse(string $strExpr, array $arrAllValue, int $iAllowedModes): array {
        if (preg_match('/^\[(\w+):(.+)\]$/', $strExpr, $matches)) {
            $strType = $matches[1];
            $expr = trim($matches[2]);

            // [type:*]
            if ($expr === '*') {
                if (!($iAllowedModes & self::EXPR_ALL_VALUES)) {
                    throw new InvalidArgumentException("Wildcard '*' expression is not allowed.");
                }
                return [
                    'type' => $strType,
                    'mode' => self::EXPR_ALL_VALUES,
                    'rawExpr' => $strExpr,
                    'values' => $arrAllValue
                ];
            }

            // [type:!a|b]
            if (str_starts_with($expr, '!')) {
                if (!($iAllowedModes & self::EXPR_EXCLUDE_VALUES)) {
                    throw new InvalidArgumentException("Exclusion expression '[{$strType}:!a|b]' is not allowed.");
                }
                $excluded = array_map('trim', explode('|', substr($expr, 1)));
                $values = array_values(array_filter($arrAllValue, fn($v) => !in_array($v, $excluded, true)));
                return [
                    'type' => $strType,
                    'mode' => self::EXPR_EXCLUDE_VALUES,
                    'rawExpr' => $strExpr,
                    'values' => $values
                ];
            }

            // [type:a|b]
            if (!($iAllowedModes & self::EXPR_INCLUDE_VALUES)) {
                throw new InvalidArgumentException("Include expression '[{$strType}:a|b]' is not allowed.");
            }
            if(count($arrAllValue)>0){
                $included = array_map('trim', explode('|', $expr));
                $values = array_values(array_filter($included, fn($v) => in_array($v, $arrAllValue, true)));
            }
            else{//không có bộ lọc $arrAllValue
                $values = array_map('trim', explode('|', $expr));
            }
            return [
                'type' => $strType,
                'mode' => self::EXPR_INCLUDE_VALUES,
                'rawExpr' => $strExpr,
                'values' => $values
            ];
        }

        // From here Simple string
        if (!($iAllowedModes & self::EXPR_SINGLE_VALUE)) {
            throw new InvalidArgumentException("Single value expression '$strExpr' is not allowed.");
        }
        if (!empty($arrAllValue) && !in_array($strExpr, $arrAllValue, true)) {
            throw new InvalidArgumentException("Invalid expression format: '$strExpr'");
        }
        return [
            'type' => '',
            'mode' => self::EXPR_SINGLE_VALUE,
            'rawExpr' => $strExpr,
            'values' => [$strExpr]
        ];
    }
    /*  $strExpr phải có dạng [type:strSomething]
     *  $value là dạng chuỗi đơn hoặc array (cho đến nay chỉ có trường hợp $strType= role thì $value là array)
     *  
     */
    public static function match(string $strType, string $strExpr, string|array $value) {
        if (preg_match('/^\[(\w+):(.+)\]$/', $strExpr, $matches)) {
            if($strType !== $matches[1]){
                throw new InvalidArgumentException('có lỗi xyz');
            }
            $expr = trim($matches[2]);
             // [type:*]
            if ($expr === '*') {
                return true;
            }
            if(is_string($value)){
                $value = [$value];
            }
            // [type:!a|b]
            if (str_starts_with($expr, '!')) {
                $excluded = array_map('trim', explode('|', substr($expr, 1)));
                //if(in_array($strValue, $excluded, true)){
                if(count(array_intersect($excluded, $value)) > 0){
                    return false;
                }
                else{
                    return true;
                }
            }
            // [type:a|b]
            $included = array_map('trim', explode('|', $expr));
            //if(in_array($strValue, $included, true)){
            if(count(array_intersect($included, $value)) > 0){
                return true;
            }
            else{
                return false;
            }
        } 
        return false; //không đúng format ['type:something']
    }
}