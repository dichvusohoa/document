<?php
namespace Core\Auth;

use Core\User\UserInfo;
use InvalidArgumentException;
use UnexpectedValueException;

class SessionInfo
{
    public const FIELD_CREATED_AT    = 'created_at';
    public const FIELD_LAST_ACTIVITY = 'last_activity';

    /*---------------------------------------------------------------------------------------------------------------*/
    public static function create(array $arrUserInfo): array
    {
        if (!UserInfo::isValid($arrUserInfo)) {
            throw new InvalidArgumentException(
                'arrUserInfo không đúng contract UserInfo.'
            );
        }

        $intNow = time();

        return array_merge(
            $arrUserInfo,
            [
                self::FIELD_CREATED_AT    => $intNow,
                self::FIELD_LAST_ACTIVITY => $intNow
            ]
        );
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    public static function isValid(mixed $arrData): bool
    {
        if (!is_array($arrData)) {
            return false;
        }
        //valid riêng 2 field FIELD_CREATED_AT và FIELD_LAST_ACTIVITY
        foreach (
            [
                self::FIELD_CREATED_AT,
                self::FIELD_LAST_ACTIVITY
            ]
            as $strField
        ) {
            if (
                !array_key_exists($strField, $arrData)
                || !is_int($arrData[$strField])
                || $arrData[$strField] <= 0
            ) {
                return false;
            }
        }

        if (
            $arrData[self::FIELD_LAST_ACTIVITY]
            < $arrData[self::FIELD_CREATED_AT]
        ) {
            return false;
        }

        /*
         * Bỏ session metadata để phần còn lại
         * bắt buộc phải đúng chính xác contract UserInfo.
         */
        $arrUserInfo = $arrData;

        unset(
            $arrUserInfo[self::FIELD_CREATED_AT],
            $arrUserInfo[self::FIELD_LAST_ACTIVITY]
        );

        return UserInfo::isValid($arrUserInfo);
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    public static function toUserInfo(array $arrSessionInfo): array
    {
        if (!self::isValid($arrSessionInfo)) {
            throw new UnexpectedValueException(
                'arrSessionInfo không đúng contract SessionInfo.'
            );
        }

        unset(
            $arrSessionInfo[self::FIELD_CREATED_AT],
            $arrSessionInfo[self::FIELD_LAST_ACTIVITY]
        );

        return $arrSessionInfo;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
}