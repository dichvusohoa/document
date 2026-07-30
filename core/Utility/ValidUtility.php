<?php
namespace Core\Utility;
class ValidUtility{
    /*$arr có dạng ['a','b', 'c']*/
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
    /*$obj có dạng ['a' => ['x','y'], 'b'=>['z']]*/
    public static function isStringListMap(mixed $obj, bool $strict = true): bool {
        if (!is_array($obj)) {
            return false;
        }

        foreach ($obj as $key => $value) {
            if (!is_string($key)) {
                return false;
            }
            $rule =  self::isStringList($value);
            if(!$strict){
                $rule = $rule || is_string($value);
            }
            if (!$rule) {
                return false;
            }
        }
        return true;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    /*$arrData là array cần kiểm tra. 
     * $arrAllowedFields là template chứa các keys được phép trong
    $arrData. Tức là các key của arrData bắt buộc phải nằm trong $arrAllowedFields
     * $strBasePath là tên field trỏ vào $arrData ví dụ render_detail
     * $strContext tên đường dẫn kiêủ "HTML fragment schema '{$strFragmentName}'"
     * trong đó $strFragmentName là tên fragment
     */
    
    public static function validateNoUnexpectedFields(
        array $arrData,
        array $arrAllowedFields,
        string $strBasePath,
        string $strContext
    ): void {
        foreach (array_keys($arrData) as $fieldName) {
            if (!is_string($fieldName)) {
                $strLocation = $strBasePath === ''
                    ? 'cấp ngoài cùng'
                    : "field '{$strBasePath}'";

                throw new UnexpectedValueException(
                    "{$strContext} có field name không phải string "
                    . "tại {$strLocation}."
                );
            }

            if (!in_array($fieldName, $arrAllowedFields, true)) {
                $strPath = $strBasePath === ''
                    ? $fieldName
                    : "{$strBasePath}.{$fieldName}";

                throw new UnexpectedValueException(
                    "{$strContext} chứa field không hợp lệ '{$strPath}'."
                );
            }
        }
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    /*Kiểm tra $arrData phải có tồn tại key là $strFieldName. Giá trị tại $arrData[$strFieldName] phải hợp lệ
     * $strContext và $strFieldPath để thông tin bổ trợ cho báo lỗi
     */
    public static function validateRequiredNonEmptyStringField(
        array $arrData,
        string $strFieldName,
        string $strContext,
        ?string $strFieldPath = null
    ): void {
        $strFieldPath ??= $strFieldName;

        if (!array_key_exists($strFieldName, $arrData)) {
            throw new UnexpectedValueException(
                "{$strContext} thiếu field '{$strFieldPath}'."
            );
        }

        if (!is_string($arrData[$strFieldName])) {
            throw new UnexpectedValueException(
                "{$strContext} field '{$strFieldPath}' phải là string; "
                . 'nhận được '
                . get_debug_type($arrData[$strFieldName])
                . '.'
            );
        }

        if (trim($arrData[$strFieldName]) === '') {
            throw new UnexpectedValueException(
                "{$strContext} field '{$strFieldPath}' không được rỗng."
            );
        }
        if ($arrData[$strFieldName] !== trim($arrData[$strFieldName])) {
            throw new UnexpectedValueException(
                "{$strContext} field '{$strFieldPath}' không được có "
                . "khoảng trắng ở đầu hoặc cuối."
            );
        }
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    /*Kiểm tra xem $arrData có field $strFieldName và giá trị của $arrData[$strFieldName] có nằm trong $arrAllowedValues 
    không
    $strContext là thông tin thêm để báo lỗi*/
    protected static function validateRequiredEnumField(
        array $arrData,
        string $strFieldName,
        array $arrAllowedValues,
        string $strContext
    ): void {
        if (!array_key_exists($strFieldName, $arrData)) {
            throw new UnexpectedValueException(
                "{$strContext} thiếu field '{$strFieldName}'."
            );
        }

        if (!is_string($arrData[$strFieldName])) {
            throw new UnexpectedValueException(
                "{$strContext} field '{$strFieldName}' phải là string; "
                . 'nhận được '
                . get_debug_type($arrData[$strFieldName])
                . '.'
            );
        }

        if (!in_array($arrData[$strFieldName], $arrAllowedValues, true)) {
            $strAllowedValues = implode(
                "', '",
                $arrAllowedValues
            );

            throw new UnexpectedValueException(
                "{$strContext} field '{$strFieldName}' có giá trị không hợp lệ "
                . "'{$arrData[$strFieldName]}'; các giá trị cho phép là "
                . "'{$strAllowedValues}'."
            );
        }
    }
}
