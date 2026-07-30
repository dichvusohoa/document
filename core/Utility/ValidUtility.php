<?php
namespace Core\Utility;
use UnexpectedValueException;
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
     * $strParentPath là tên field trỏ vào $arrData ví dụ render_detail
     * $strContext tên đường dẫn kiêủ "HTML fragment schema '{$strFragmentName}'"
     * trong đó $strFragmentName là tên fragment
     */
    public static function validateNoUnexpectedFields(
        array $arrData,
        array $arrAllowedFields,
        string $strContext,
        ?string $strParentPath = null
    ): void {
        foreach (array_keys($arrData) as $mixFieldName) {
            if (!is_string($mixFieldName)) {
                $strParentInfo = $strParentPath === null
                    ? ''
                    : " tại '{$strParentPath}'";

                throw new UnexpectedValueException(
                    "{$strContext}{$strParentInfo} có field name "
                    . 'không phải string; nhận được '
                    . get_debug_type($mixFieldName)
                    . '.'
                );
            }

            $strFieldPath = self::buildFieldPath(
                $mixFieldName,
                $strParentPath
            );

            if (!in_array($mixFieldName, $arrAllowedFields, true)) {
                throw new UnexpectedValueException(
                    "{$strContext} chứa field không hợp lệ "
                    . "'{$strFieldPath}'."
                );
            }
        }
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    /**
     * Kiểm tra field bắt buộc:
     * - tồn tại;
     * - có giá trị string;
     * - không rỗng;
     * - không có khoảng trắng ở đầu hoặc cuối.
     */
    public static function validateRequiredNonEmptyStringField(
        array $arrData,
        string $strFieldName,
        string $strContext,
        ?string $strParentPath = null
    ): void {
        $strFieldPath = self::buildFieldPath(
            $strFieldName,
            $strParentPath
        );

        if (!array_key_exists($strFieldName, $arrData)) {
            throw new UnexpectedValueException(
                "{$strContext} thiếu field '{$strFieldPath}'."
            );
        }

        $mixFieldValue = $arrData[$strFieldName];

        if (!is_string($mixFieldValue)) {
            throw new UnexpectedValueException(
                "{$strContext} field '{$strFieldPath}' phải là string; "
                . 'nhận được '
                . get_debug_type($mixFieldValue)
                . '.'
            );
        }

        $strTrimmedValue = trim($mixFieldValue);

        if ($strTrimmedValue === '') {
            throw new UnexpectedValueException(
                "{$strContext} field '{$strFieldPath}' không được rỗng."
            );
        }

        if ($mixFieldValue !== $strTrimmedValue) {
            throw new UnexpectedValueException(
                "{$strContext} field '{$strFieldPath}' không được có "
                . 'khoảng trắng ở đầu hoặc cuối.'
            );
        }
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    /**
     * Kiểm tra field bắt buộc là string và thuộc danh sách giá trị cho phép.
     */
    public static function validateRequiredEnumField(
        array $arrData,
        string $strFieldName,
        array $arrAllowedValues,
        string $strContext,
        ?string $strParentPath = null
    ): void {
        $strFieldPath = self::buildFieldPath(
            $strFieldName,
            $strParentPath
        );

        if (!array_key_exists($strFieldName, $arrData)) {
            throw new UnexpectedValueException(
                "{$strContext} thiếu field '{$strFieldPath}'."
            );
        }

        $mixFieldValue = $arrData[$strFieldName];

        if (!is_string($mixFieldValue)) {
            throw new UnexpectedValueException(
                "{$strContext} field '{$strFieldPath}' phải là string; "
                . 'nhận được '
                . get_debug_type($mixFieldValue)
                . '.'
            );
        }

        if (!in_array($mixFieldValue, $arrAllowedValues, true)) {
            $strAllowedValues = implode(
                "', '",
                $arrAllowedValues
            );

            throw new UnexpectedValueException(
                "{$strContext} field '{$strFieldPath}' có giá trị "
                . "không hợp lệ '{$mixFieldValue}'; "
                . "các giá trị cho phép là '{$strAllowedValues}'."
            );
        }
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    private static function buildFieldPath(
        string $strFieldName,
        ?string $strParentPath = null
    ): string {
        return $strParentPath === null
            ? $strFieldName
            : "{$strParentPath}.{$strFieldName}";
    }
    
}
