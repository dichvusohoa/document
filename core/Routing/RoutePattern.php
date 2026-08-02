<?php
/*
 */
namespace Core\Routing;
use InvalidArgumentException;
class RoutePattern {
    public const EXPR_SINGLE_VALUE     = 1;  // Giá trị đơn lẻ, ví dụ: "admin"
    public const EXPR_INCLUDE_VALUES   = 2;  // [type:a|b|c] → chỉ định danh sách cho phép
    public const EXPR_EXCLUDE_VALUES   = 4;  // [type:!a|b] → loại trừ danh sách
    public const EXPR_ALL_VALUES       = 8;  // [type:*] → tất cả giá trị
    /*---------------------------------------------------------------------------------------------------------------*/
    protected static function parseExprSyntax(string $strExpr): array {
        if (preg_match('/^\[(\w+):(.*)\]$/', $strExpr, $matches)) {
            $strType = $matches[1];
            $expr = trim($matches[2]);
            //lỗi kiểu như '[action:]', thiếu value cụ thể
            if ($expr === '') {
                throw new InvalidArgumentException("Empty route pattern expression in '{$strExpr}'.");
            }

            if ($expr === '*') {
                return [
                    'type' => $strType,
                    'mode' => self::EXPR_ALL_VALUES,
                    'rawExpr' => $strExpr,
                    'tokens' => ['*'],
                ];
            }
            
            if (str_starts_with($expr, '!')) {
                $body = trim(substr($expr, 1));
                //lỗi kiểu như '[action:!]', thiếu value sau !
                if ($body === '') {
                    throw new InvalidArgumentException("Empty exclude expression in '{$strExpr}'.");
                }
                $tokens = self::splitTokens($body, $strExpr);
                return [
                    'type' => $strType,
                    'mode' => self::EXPR_EXCLUDE_VALUES,
                    'rawExpr' => $strExpr,
                    'tokens' => $tokens,
                ];
            }
            //từ đây là dự kiến sẽ là EXPR_INCLUDE_VALUES
            $tokens = self::splitTokens($expr, $strExpr);
            return [
                'type' => $strType,
                'mode' => self::EXPR_INCLUDE_VALUES,
                'rawExpr' => $strExpr,
                'tokens' => $tokens,
            ];
        }
        //từ đây là dự kiến sẽ là EXPR_SINGLE_VALUE
       
        //lỗi kiểu như là '[a|b|c]' hoặc là '[a]' hoặc là 'a]' , thừa dấu [ hoặc ] 
        if (str_starts_with($strExpr, '[') || str_ends_with($strExpr, ']')) {
            throw new InvalidArgumentException("Invalid route pattern syntax: '{$strExpr}'.");
        }

        $single = trim($strExpr);
        //lỗi kiểu như là $strExpr === '  '
        if ($single === '') {
            throw new InvalidArgumentException("Empty route pattern expression.");
        }

        return [
            'type' => '',
            'mode' => self::EXPR_SINGLE_VALUE,
            'rawExpr' => $strExpr,
            'tokens' => [$single],
        ];
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected static function splitTokens(string $expr, string $rawExpr): array {
        $tokens = array_map('trim', explode('|', $expr));
        //lỗi kiểu như $rawExpr là '[action:view|edit||]'
        if (in_array('', $tokens, true)) {
            throw new InvalidArgumentException("Empty value in route pattern '{$rawExpr}'.");
        }
        //lỗi kiểu như $rawExpr là '[action:*|edit||]'
        if (in_array('*', $tokens, true)) {
            throw new InvalidArgumentException("Wildcard '*' cannot be mixed with other values in '{$rawExpr}'.");
        }
        foreach ($tokens as $token) {
            if (str_starts_with($token, '!')) {
                throw new InvalidArgumentException(
                    "Negation '!' is only allowed at the beginning of expression '{$rawExpr}'."
                );
            }

            if (str_contains($token, '[') || str_contains($token, ']')) {
                throw new InvalidArgumentException(
                    "Invalid bracket character in route pattern '{$rawExpr}'."
                );
            }
        }
        return $tokens;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
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
    * @param array $arrAllValue Universe dùng để mở rộng * và lọc include/exclude.
    * @return array{
    *     type: string,       // tên segment nếu có (vd: "module"), hoặc chuỗi rỗng nếu là giá trị đơn lẻ
    *     mode: int,          // một trong các hằng số EXPR_* để xác định kiểu biểu thức
    *     rawExpr: string,    // biểu thức gốc đầu vào
    *     values: string[]    // danh sách giá trị sau khi xử lý
    * }
    *
    * @throws \InvalidArgumentException Nếu biểu thức sai định dạng
    */
    public static function parse(string $strExpr, array $arrAllValue = []): array {
        $parsed = self::parseExprSyntax($strExpr);

        switch ($parsed['mode']) {
            case self::EXPR_ALL_VALUES:
                $values = $arrAllValue;
                break;

            case self::EXPR_EXCLUDE_VALUES:
                $values = array_values(array_diff($arrAllValue, $parsed['tokens']));
                break;

            case self::EXPR_INCLUDE_VALUES:
                $values = empty($arrAllValue)
                    ? $parsed['tokens']
                    : array_values(array_intersect($parsed['tokens'], $arrAllValue));
                break;

            case self::EXPR_SINGLE_VALUE:
                $single = $parsed['tokens'][0];
                $values = (!empty($arrAllValue) && !in_array($single, $arrAllValue, true))
                    ? []
                    : [$single];
                break;
            default:
                throw new InvalidArgumentException("Unknown route pattern mode.");
        }

        return [
            'type' => $parsed['type'],
            'mode' => $parsed['mode'],
            'rawExpr' => $parsed['rawExpr'],
            'values' => $values,
        ];
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public static function filter(string $strExpr, string|array $value): array {
        $parsed = self::parseExprSyntax($strExpr);
        $values = is_array($value) ? array_values($value) : [$value];

        switch ($parsed['mode']) {
            case self::EXPR_ALL_VALUES:
                $filteredValues = $values;
                break;

            case self::EXPR_EXCLUDE_VALUES:
                $filteredValues = array_values(array_diff($values, $parsed['tokens']));
                break;

            case self::EXPR_INCLUDE_VALUES:
                $filteredValues = array_values(array_intersect($values, $parsed['tokens']));
                break;

            case self::EXPR_SINGLE_VALUE:
                $single = $parsed['tokens'][0];
                $filteredValues = in_array($single, $values, true) ? [$single] : [];
                break;
            default:
                throw new InvalidArgumentException("Unknown route pattern mode.");
        }

        return [
            'type' => $parsed['type'],
            'mode' => $parsed['mode'],
            'rawExpr' => $parsed['rawExpr'],
            'values' => $filteredValues,
        ];
    }
}