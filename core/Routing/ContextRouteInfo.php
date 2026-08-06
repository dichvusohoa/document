<?php
namespace Core\Routing;
/**
 * Định nghĩa contract của dữ liệu trả về khi ContextRouter thực hiện hàm matchUri.
 * 
 */
class ContextRouteInfo
{
    public const FIELD_MCAO               = 'mcao';
    public const FIELD_ROUTE_INFO         = 'route_info';
    public const FIELD_AUTH_POLICY        = 'auth_policy';
    public const FIELD_MIDDLEWARES         = 'middlewares';
    public const FIELD_PROHIBITED_MODULE  = 'prohibited_module';
    public const FIELD_PROHIBITED_ROLE    = 'prohibited_role';

    /*---------------------------------------------------------------------------------------------------------------*/
    public static function createEmpty(): array
    {
        return [
            self::FIELD_MCAO              => null,
            self::FIELD_ROUTE_INFO        => null,
            self::FIELD_AUTH_POLICY       => null,
            self::FIELD_MIDDLEWARES        => null,
            self::FIELD_PROHIBITED_MODULE => null,
            self::FIELD_PROHIBITED_ROLE   => null
        ];
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    public static function isValid(mixed $arrData): bool
    {
        // Sẽ bổ sung validation sau khi chốt đầy đủ cấu trúc.
    }
}