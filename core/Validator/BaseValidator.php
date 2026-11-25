<?php

namespace Core\Validator;

use InvalidArgumentException;

abstract class BaseValidator
{
    /*---------------------------------------------------------------------------------------------------------------*/
    protected static function validateOptions(
        array $arrOption,
        array $arrAllowedOption,
        string $strMethod,
        int $iMaxOptionCount = 3
    ): void {
        if ($iMaxOptionCount < 0) {
            throw new InvalidArgumentException(
                __METHOD__
                . ': iMaxOptionCount không được nhỏ hơn 0.'
            );
        }

        if (count($arrOption) > $iMaxOptionCount) {
            throw new InvalidArgumentException(
                "{$strMethod}: option chỉ được có tối đa "
                . "{$iMaxOptionCount} phần tử."
            );
        }

        foreach ($arrOption as $strOptionName => $_) {
            if (
                !is_string($strOptionName)
                || !in_array(
                    $strOptionName,
                    $arrAllowedOption,
                    true
                )
            ) {
                throw new InvalidArgumentException(
                    "{$strMethod}: option "
                    . "'{$strOptionName}' không hợp lệ."
                );
            }
        }
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected static function boolOption(
        array $arrOption,
        string $strOptionName,
        bool $boolDefault = false
    ): bool {
        if (!array_key_exists($strOptionName, $arrOption)) {
            return $boolDefault;
        }

        /*
         * Đây chỉ là kiểm tra primitive rất đơn giản.
         * Không cần gọi AtomicNormalizer.
         */
        if (!is_bool($arrOption[$strOptionName])) {
            throw new InvalidArgumentException(
                "Option '{$strOptionName}' phải là boolean."
            );
        }

        return $arrOption[$strOptionName];
    }
}