<?php

namespace Core\Auth;

use Core\User\BaseUserInfo;
use InvalidArgumentException;
use LogicException;
use UnexpectedValueException;

class SessionInfo
{
    public const FIELD_CREATED_AT    = 'created_at';
    public const FIELD_LAST_ACTIVITY = 'last_activity';

    protected static ?string $strUserInfoFQCN = null;

    /*---------------------------------------------------------------------------------------------------------------*/
    public static function setUserInfoFQCN(string $strUserInfoFQCN): void
    {
        if (
            !is_subclass_of(
                $strUserInfoFQCN,
                BaseUserInfo::class,
                true
            )
        ) {
            throw new InvalidArgumentException(
                $strUserInfoFQCN
                . ' phải kế thừa '
                . BaseUserInfo::class
                . '.'
            );
        }

        self::$strUserInfoFQCN = $strUserInfoFQCN;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    public static function create(array $arrUserInfo): array
    {
        $strUserInfoFQCN = self::userInfoFQCN();

        if (!$strUserInfoFQCN::isValid($arrUserInfo)) {
            throw new InvalidArgumentException(
                'arrUserInfo không đúng contract '
                . $strUserInfoFQCN
                . '.'
            );
        }

        $iNow = time();

        return array_merge(
            $arrUserInfo,
            [
                self::FIELD_CREATED_AT    => $iNow,
                self::FIELD_LAST_ACTIVITY => $iNow
            ]
        );
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    public static function isValid(mixed $mixData): bool
    {
        if (!is_array($mixData)) {
            return false;
        }

        foreach (
            [
                self::FIELD_CREATED_AT,
                self::FIELD_LAST_ACTIVITY
            ]
            as $strField
        ) {
            if (
                !array_key_exists($strField, $mixData)
                || !is_int($mixData[$strField])
                || $mixData[$strField] <= 0
            ) {
                return false;
            }
        }

        if (
            $mixData[self::FIELD_LAST_ACTIVITY]
            < $mixData[self::FIELD_CREATED_AT]
        ) {
            return false;
        }

        /*
         * SessionInfo = UserInfo + session metadata.
         *
         * Bỏ session metadata để phần còn lại
         * bắt buộc phải đúng chính xác contract
         * của UserInfo thực sự tại runtime.
         */
        $arrUserInfo = $mixData;

        unset(
            $arrUserInfo[self::FIELD_CREATED_AT],
            $arrUserInfo[self::FIELD_LAST_ACTIVITY]
        );

        $strUserInfoFQCN = self::userInfoFQCN();

        return $strUserInfoFQCN::isValid($arrUserInfo);
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
    protected static function userInfoFQCN(): string
    {
        if (self::$strUserInfoFQCN === null) {
            throw new LogicException(
                'SessionInfo chưa được cấu hình UserInfoFQCN .'
            );
        }

        return self::$strUserInfoFQCN;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
}