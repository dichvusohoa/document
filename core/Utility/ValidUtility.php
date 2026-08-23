<?php
namespace Core\Utility;

use InvalidArgumentException;
use UnexpectedValueException;

class ValidUtility
{
    /*---------------------------------------------------------------------------------------------------------------*/
    /* $arr có dạng ['a', 'b', 'c'] */
    public static function isStringList(mixed $arr, array $arrOption = []): bool
    {
        self::validateOptions(
            $arrOption,
            ['non_empty', 'item_non_empty', 'unique'],
            __METHOD__
        );

        if (!is_array($arr)) {
            return false;
        }

        $boolNonEmpty = self::boolOption($arrOption, 'non_empty');
        $boolItemNonEmpty = self::boolOption($arrOption, 'item_non_empty');
        $boolUnique = self::boolOption($arrOption, 'unique');

        if ($boolNonEmpty && $arr === []) {
            return false;
        }

        $i = 0;
        foreach ($arr as $key => $value) {
            if ($key !== $i || !is_string($value)) {
                return false;
            }

            if ($boolItemNonEmpty && !self::isNonEmptyString($value)) {
                return false;
            }

            $i++;
        }

        if ($boolUnique && count($arr) !== count(array_unique($arr))) {
            return false;
        }

        return true;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    public static function isStringPairMap(mixed $arr, array $arrOption = []): bool
    {
        self::validateOptions(
            $arrOption,
            ['non_empty_key', 'non_empty_value', 'unique_value'],
            __METHOD__
        );

        if (!is_array($arr)) {
            return false;
        }

        $boolNonEmptyKey = self::boolOption($arrOption, 'non_empty_key');
        $boolNonEmptyValue = self::boolOption($arrOption, 'non_empty_value');
        $boolUniqueValue = self::boolOption($arrOption, 'unique_value');
        $arrValue = [];

        foreach ($arr as $key => $value) {
            if (!is_string($key) || !is_string($value)) {
                return false;
            }

            if ($boolNonEmptyKey && !self::isNonEmptyString($key)) {
                return false;
            }

            if ($boolNonEmptyValue && !self::isNonEmptyString($value)) {
                return false;
            }

            if ($boolUniqueValue) {
                $arrValue[] = $value;
            }
        }

        if ($boolUniqueValue && count($arrValue) !== count(array_unique($arrValue))) {
            return false;
        }

        return true;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    public static function isIntStringMap(mixed $arr, array $arrOption = []): bool
    {
        self::validateOptions(
            $arrOption,
            ['min_key', 'non_empty_value', 'unique_value'],
            __METHOD__
        );

        if (!is_array($arr)) {
            return false;
        }

        $mixMinKey = $arrOption['min_key'] ?? null;
        if ($mixMinKey !== null && !is_int($mixMinKey)) {
            throw new InvalidArgumentException(__METHOD__ . ": option 'min_key' phải là int.");
        }

        $boolNonEmptyValue = self::boolOption($arrOption, 'non_empty_value');
        $boolUniqueValue = self::boolOption($arrOption, 'unique_value');
        $arrValue = [];

        foreach ($arr as $key => $value) {
            if (!is_int($key) || !is_string($value)) {
                return false;
            }

            if ($mixMinKey !== null && $key < $mixMinKey) {
                return false;
            }

            if ($boolNonEmptyValue && !self::isNonEmptyString($value)) {
                return false;
            }

            if ($boolUniqueValue) {
                $arrValue[] = $value;
            }
        }

        if ($boolUniqueValue && count($arrValue) !== count(array_unique($arrValue))) {
            return false;
        }

        return true;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    /* $obj có dạng ['a' => ['x','y'], 'b' => ['z']] */
    public static function isStringListMap(mixed $obj, array $arrOption = []): bool
    {
        self::validateOptions(
            $arrOption,
            ['allow_string_value', 'non_empty_key'],
            __METHOD__
        );

        if (!is_array($obj)) {
            return false;
        }

        $boolAllowStringValue = self::boolOption($arrOption, 'allow_string_value');
        $boolNonEmptyKey = self::boolOption($arrOption, 'non_empty_key');

        foreach ($obj as $key => $value) {
            if (!is_string($key)) {
                return false;
            }

            if ($boolNonEmptyKey && !self::isNonEmptyString($key)) {
                return false;
            }

            if (self::isStringList($value)) {
                continue;
            }

            if ($boolAllowStringValue && is_string($value)) {
                continue;
            }

            return false;
        }

        return true;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
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
                    "{$strContext} chứa field không hợp lệ '{$strFieldPath}'."
                );
            }
        }
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    /**
     * Kiểm tra field bắt buộc.
     *
     * Option là array phẳng, tối đa 3 phần tử.
     * Nếu có option thì bắt buộc phải có 'type'.
     *
     * type = string:
     *   non_empty => bool
     *   trimmed   => bool
     *   values    => array
     *
     * type = int:
     *   min       => int
     *
     * type = bool:
     *   không có option bổ sung
     *
     * type = array:
     *   non_empty => bool
     *   unique    => bool
     *
     * type = string_list:
     *   non_empty      => bool
     *   item_non_empty => bool
     *   unique         => bool
     */
    public static function validateRequiredField(
        array $arrData,
        string $strFieldName,
        string $strContext,
        array $arrOption = [],
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

        if ($arrOption === []) {
            return;
        }

        self::validateOptions(
            $arrOption,
            ['type', 'non_empty', 'trimmed', 'values', 'min', 'unique', 'item_non_empty'],
            __METHOD__
        );

        if (!array_key_exists('type', $arrOption) || !is_string($arrOption['type'])) {
            throw new InvalidArgumentException(
                __METHOD__ . ": option 'type' là bắt buộc và phải là string."
            );
        }

        $mixFieldValue = $arrData[$strFieldName];
        $strType = $arrOption['type'];

        switch ($strType) {
            case 'string':
                self::validateOptions(
                    $arrOption,
                    ['type', 'non_empty', 'trimmed', 'values'],
                    __METHOD__
                );

                if (!is_string($mixFieldValue)) {
                    throw new UnexpectedValueException(
                        "{$strContext} field '{$strFieldPath}' phải là string; "
                        . 'nhận được '
                        . get_debug_type($mixFieldValue)
                        . '.'
                    );
                }

                if (self::boolOption($arrOption, 'non_empty') && $mixFieldValue === '') {
                    throw new UnexpectedValueException(
                        "{$strContext} field '{$strFieldPath}' không được rỗng."
                    );
                }

                if (self::boolOption($arrOption, 'trimmed') && $mixFieldValue !== trim($mixFieldValue)) {
                    throw new UnexpectedValueException(
                        "{$strContext} field '{$strFieldPath}' không được có "
                        . 'khoảng trắng ở đầu hoặc cuối.'
                    );
                }

                if (array_key_exists('values', $arrOption)) {
                    if (!is_array($arrOption['values'])) {
                        throw new InvalidArgumentException(
                            __METHOD__ . ": option 'values' phải là array."
                        );
                    }

                    if (!in_array($mixFieldValue, $arrOption['values'], true)) {
                        $strAllowedValues = implode("', '", $arrOption['values']);

                        throw new UnexpectedValueException(
                            "{$strContext} field '{$strFieldPath}' có giá trị "
                            . "không hợp lệ '{$mixFieldValue}'; "
                            . "các giá trị cho phép là '{$strAllowedValues}'."
                        );
                    }
                }
                break;

            case 'int':
                self::validateOptions(
                    $arrOption,
                    ['type', 'min'],
                    __METHOD__
                );

                if (!is_int($mixFieldValue)) {
                    throw new UnexpectedValueException(
                        "{$strContext} field '{$strFieldPath}' phải là int; "
                        . 'nhận được '
                        . get_debug_type($mixFieldValue)
                        . '.'
                    );
                }

                if (array_key_exists('min', $arrOption)) {
                    if (!is_int($arrOption['min'])) {
                        throw new InvalidArgumentException(
                            __METHOD__ . ": option 'min' phải là int."
                        );
                    }

                    if ($mixFieldValue < $arrOption['min']) {
                        throw new UnexpectedValueException(
                            "{$strContext} field '{$strFieldPath}' phải >= "
                            . $arrOption['min']
                            . "; nhận được {$mixFieldValue}."
                        );
                    }
                }
                break;

            case 'bool':
                self::validateOptions(
                    $arrOption,
                    ['type'],
                    __METHOD__
                );

                if (!is_bool($mixFieldValue)) {
                    throw new UnexpectedValueException(
                        "{$strContext} field '{$strFieldPath}' phải là boolean; "
                        . 'nhận được '
                        . get_debug_type($mixFieldValue)
                        . '.'
                    );
                }
                break;

            case 'array':
                self::validateOptions(
                    $arrOption,
                    ['type', 'non_empty', 'unique'],
                    __METHOD__
                );

                if (!is_array($mixFieldValue)) {
                    throw new UnexpectedValueException(
                        "{$strContext} field '{$strFieldPath}' phải là array."
                    );
                }

                if (self::boolOption($arrOption, 'non_empty') && $mixFieldValue === []) {
                    throw new UnexpectedValueException(
                        "{$strContext} field '{$strFieldPath}' không được là array rỗng."
                    );
                }

                if (
                    self::boolOption($arrOption, 'unique')
                    && count($mixFieldValue) !== count(array_unique($mixFieldValue))
                ) {
                    throw new UnexpectedValueException(
                        "{$strContext} field '{$strFieldPath}' "
                        . 'không được chứa giá trị trùng nhau.'
                    );
                }
                break;

            case 'string_list':
                self::validateOptions(
                    $arrOption,
                    ['type', 'non_empty', 'item_non_empty', 'unique'],
                    __METHOD__
                );

                $arrListOption = [];
                foreach (['non_empty', 'item_non_empty', 'unique'] as $strOptionName) {
                    if (array_key_exists($strOptionName, $arrOption)) {
                        $arrListOption[$strOptionName] = $arrOption[$strOptionName];
                    }
                }

                if (!self::isStringList($mixFieldValue, $arrListOption)) {
                    throw new UnexpectedValueException(
                        "{$strContext} field '{$strFieldPath}' "
                        . 'không đúng format string list yêu cầu.'
                    );
                }
                break;

            default:
                throw new InvalidArgumentException(
                    __METHOD__ . ": type '{$strType}' không được hỗ trợ."
                );
        }
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    /**
     * Kiểm tra một giá trị là string không rỗng và không có khoảng trắng
     * ở đầu hoặc cuối.
     */
    public static function isNonEmptyString(mixed $value): bool
    {
        return is_string($value)
            && $value !== ''
            && $value === trim($value);
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    /**
     * Kiểm tra mảng có đúng tập field yêu cầu:
     * - không thiếu field;
     * - không có field ngoài dự kiến;
     * - field name phải là string.
     *
     * Thứ tự các field không quan trọng.
     */
    public static function hasExactFields(
        mixed $arrData,
        array $arrExpectedFields
    ): bool {
        if (!is_array($arrData)) {
            return false;
        }

        if (count($arrData) !== count($arrExpectedFields)) {
            return false;
        }

        $arrExpected = [];

        foreach ($arrExpectedFields as $strField) {
            if (!self::isNonEmptyString($strField)) {
                return false;
            }

            $arrExpected[$strField] = true;
        }

        foreach ($arrData as $strField => $_) {
            if (!is_string($strField) || !isset($arrExpected[$strField])) {
                return false;
            }
        }

        return true;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    /**
     * Kiểm tra đường dẫn tuyệt đối nội bộ:
     * - là string không rỗng;
     * - bắt đầu bằng một dấu "/";
     * - không bắt đầu bằng "//".
     */
    public static function isInternalAbsolutePath(mixed $value): bool
    {
        return self::isNonEmptyString($value)
            && $value[0] === '/'
            && !str_starts_with($value, '//');
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

    /*---------------------------------------------------------------------------------------------------------------*/
    public static function validateOptions(
        array $arrOption,
        array $arrAllowedOption,
        string $strMethod,
        int $iMaxOptionCount = 3    
    ): void {
        if ($iMaxOptionCount < 0) {
            throw new InvalidArgumentException(
                __METHOD__ . ': iMaxOptionCount không được nhỏ hơn 0.'
            );
        }
        if (count($arrOption) > $iMaxOptionCount) {
            throw new InvalidArgumentException(
                "{$strMethod}: option chỉ được có tối đa "
                . "{$iMaxOptionCount} phần tử."
            );
        }

        foreach ($arrOption as $strOptionName => $_) {
            if (!is_string($strOptionName) || !in_array($strOptionName, $arrAllowedOption, true)) {
                throw new InvalidArgumentException(
                    "{$strMethod}: option '{$strOptionName}' không hợp lệ."
                );
            }
        }
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    private static function boolOption(
        array $arrOption,
        string $strOptionName,
        bool $boolDefault = false
    ): bool {
        if (!array_key_exists($strOptionName, $arrOption)) {
            return $boolDefault;
        }

        if (!is_bool($arrOption[$strOptionName])) {
            throw new InvalidArgumentException(
                "Option '{$strOptionName}' phải là boolean."
            );
        }

        return $arrOption[$strOptionName];
    }
}
