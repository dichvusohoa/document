<?php

namespace Core\Validator;

use InvalidArgumentException;
use Core\Utility\StringUtility;

class ArrayShape extends BaseValidator
{
    /*---------------------------------------------------------------------------------------------------------------*/
    /*
     * $arr có dạng:
     *
     * [
     *     'a',
     *     'b',
     *     'c'
     * ]
     */
    public static function isStringList(
        mixed $arr,
        array $arrOption = []
    ): bool {
        self::validateOptions(
            $arrOption,
            [
                'non_empty',
                'item_non_empty',
                'unique'
            ],
            __METHOD__
        );

        if (!is_array($arr)) {
            return false;
        }

        $boolNonEmpty = self::boolOption(
            $arrOption,
            'non_empty'
        );

        $boolItemNonEmpty = self::boolOption(
            $arrOption,
            'item_non_empty'
        );

        $boolUnique = self::boolOption(
            $arrOption,
            'unique'
        );

        if ($boolNonEmpty && $arr === []) {
            return false;
        }

        $i = 0;

        foreach ($arr as $key => $value) {
            /*
             * String list phải có index liên tục:
             *
             * 0, 1, 2, ...
             */
            if (
                $key !== $i
                || !is_string($value)
            ) {
                return false;
            }

            if (
                $boolItemNonEmpty
                && !StringUtility::isNonEmptyString($value)
            ) {
                return false;
            }

            $i++;
        }

        if (
            $boolUnique
            && count($arr)
                !== count(array_unique($arr))
        ) {
            return false;
        }

        return true;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    /*
     * $arr có dạng:
     *
     * [
     *     'a' => 'x',
     *     'b' => 'y'
     * ]
     */
    public static function isStringPairMap(
        mixed $arr,
        array $arrOption = []
    ): bool {
        self::validateOptions(
            $arrOption,
            [
                'non_empty_key',
                'non_empty_value',
                'unique_value'
            ],
            __METHOD__
        );

        if (!is_array($arr)) {
            return false;
        }

        $boolNonEmptyKey = self::boolOption(
            $arrOption,
            'non_empty_key'
        );

        $boolNonEmptyValue = self::boolOption(
            $arrOption,
            'non_empty_value'
        );

        $boolUniqueValue = self::boolOption(
            $arrOption,
            'unique_value'
        );

        $arrValue = [];

        foreach ($arr as $key => $value) {
            if (
                !is_string($key)
                || !is_string($value)
            ) {
                return false;
            }

            if (
                $boolNonEmptyKey
                && !StringUtility::isNonEmptyString($key)
            ) {
                return false;
            }

            if (
                $boolNonEmptyValue
                && !StringUtility::isNonEmptyString($value)
            ) {
                return false;
            }

            if ($boolUniqueValue) {
                $arrValue[] = $value;
            }
        }

        if (
            $boolUniqueValue
            && count($arrValue)
                !== count(array_unique($arrValue))
        ) {
            return false;
        }

        return true;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    /*
     * $arr có dạng:
     *
     * [
     *     1 => 'a',
     *     2 => 'b'
     * ]
     */
    public static function isIntStringMap(
        mixed $arr,
        array $arrOption = []
    ): bool {
        self::validateOptions(
            $arrOption,
            [
                'min_key',
                'non_empty_value',
                'unique_value'
            ],
            __METHOD__
        );

        if (!is_array($arr)) {
            return false;
        }

        $mixMinKey = $arrOption['min_key'] ?? null;

        /*
         * Primitive check đơn giản:
         * không cần AtomicNormalizer.
         */
        if (
            $mixMinKey !== null
            && !is_int($mixMinKey)
        ) {
            throw new InvalidArgumentException(
                __METHOD__
                . ": option 'min_key' phải là int."
            );
        }

        $boolNonEmptyValue = self::boolOption(
            $arrOption,
            'non_empty_value'
        );

        $boolUniqueValue = self::boolOption(
            $arrOption,
            'unique_value'
        );

        $arrValue = [];

        foreach ($arr as $key => $value) {
            if (
                !is_int($key)
                || !is_string($value)
            ) {
                return false;
            }

            if (
                $mixMinKey !== null
                && $key < $mixMinKey
            ) {
                return false;
            }

            if (
                $boolNonEmptyValue
                && !StringUtility::isNonEmptyString($value)
            ) {
                return false;
            }

            if ($boolUniqueValue) {
                $arrValue[] = $value;
            }
        }

        if (
            $boolUniqueValue
            && count($arrValue)
                !== count(array_unique($arrValue))
        ) {
            return false;
        }

        return true;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    /*
     * $obj có dạng:
     *
     * [
     *     'a' => ['x', 'y'],
     *     'b' => ['z']
     * ]
     *
     * Nếu allow_string_value = true thì value cũng có thể
     * trực tiếp là string.
     */
    public static function isStringListMap(
        mixed $obj,
        array $arrOption = []
    ): bool {
        self::validateOptions(
            $arrOption,
            [
                'allow_string_value',
                'non_empty_key'
            ],
            __METHOD__
        );

        if (!is_array($obj)) {
            return false;
        }

        $boolAllowStringValue = self::boolOption(
            $arrOption,
            'allow_string_value'
        );

        $boolNonEmptyKey = self::boolOption(
            $arrOption,
            'non_empty_key'
        );

        foreach ($obj as $key => $value) {
            if (!is_string($key)) {
                return false;
            }

            if (
                $boolNonEmptyKey
                && !StringUtility::isNonEmptyString($key)
            ) {
                return false;
            }

            if (self::isStringList($value)) {
                continue;
            }

            if (
                $boolAllowStringValue
                && is_string($value)
            ) {
                continue;
            }

            return false;
        }

        return true;
    }
}
