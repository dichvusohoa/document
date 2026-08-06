<?php
namespace Core\Routing;
class MCARMeInfo {
    public const FIELD_MODULE                 = 'module';
    public const FIELD_CONTROLLER             = 'controller';
    public const FIELD_ACTION                 = 'action';
    public const FIELD_ROLE                   = 'role';
    public const FIELD_METHOD                 = 'method';
    public static function createEmpty(): array
    {
        return [
            self::FIELD_MODULE      => null,
            self::FIELD_CONTROLLER  => null,
            self::FIELD_ACTION      => null,
            self::FIELD_ROLE        => null,
            self::FIELD_METHOD      => null
        ];
    }
    public static function isValid(mixed $arrData): bool
    {
        return is_array($arrData)

            && array_key_exists(self::FIELD_MODULE, $arrData)
            && (
                $arrData[self::FIELD_MODULE] === null
                || is_string($arrData[self::FIELD_MODULE])
            )

            && array_key_exists(self::FIELD_CONTROLLER, $arrData)
            && (
                $arrData[self::FIELD_CONTROLLER] === null
                || is_string($arrData[self::FIELD_CONTROLLER])
            )

            && array_key_exists(self::FIELD_ACTION, $arrData)
            && (
                $arrData[self::FIELD_ACTION] === null
                || is_string($arrData[self::FIELD_ACTION])
            )

            && array_key_exists(self::FIELD_ROLE, $arrData)
            && (
                $arrData[self::FIELD_ROLE] === null
                || is_string($arrData[self::FIELD_ROLE])
                || ValidUtility::isStringList($arrData[self::FIELD_ROLE])
            )

            && array_key_exists(self::FIELD_METHOD, $arrData)
            && (
                $arrData[self::FIELD_METHOD] === null
                || is_string($arrData[self::FIELD_METHOD])
            );
    }
}