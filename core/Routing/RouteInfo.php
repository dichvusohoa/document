<?php
namespace Core\Routing;

use Core\Utility\ValidUtility;

/**
 * Định nghĩa contract của dữ liệu tại nút lá trong cây MCAR.
 * Tạo ra cấu trúc này còn dự kiến tương lai sẽ chuyển nhiều hàm xử lý dữ liệu lên đây
 * độc lập và giảm tải tại StaticRouter
 * Format:
 * [
 *     'roles'                  => string[],
 *     'fqcn'                   => string,
 *     'function'               => string,
 *     'method'                 => string,
 *     'route_type'             => 'business'|'authentication',
 *     'authentication_path'    => string|null,
 *     'default_business_path'  => string|null,
 * ]
 */
class RouteInfo
{
    public const FIELD_ROLES                 = 'roles';
    public const FIELD_FQCN                  = 'fqcn';
    public const FIELD_FUNCTION              = 'function';
    public const FIELD_METHOD                = 'method';
    public const FIELD_ROUTE_TYPE            = 'route_type';
    public const FIELD_AUTHENTICATION_PATH   = 'authentication_path';

    public const ROUTE_TYPE_BUSINESS       = 'business';
    public const ROUTE_TYPE_AUTHENTICATION = 'authentication';

    protected const FIELD_LIST = [
        self::FIELD_ROLES,
        self::FIELD_FQCN,
        self::FIELD_FUNCTION,
        self::FIELD_METHOD,
        self::FIELD_ROUTE_TYPE,
        self::FIELD_AUTHENTICATION_PATH
    ];

    protected const ROUTE_TYPE_LIST = [
        self::ROUTE_TYPE_BUSINESS,
        self::ROUTE_TYPE_AUTHENTICATION,
    ];

    /*---------------------------------------------------------------------------------------------------------------*/
    /**
     * Tạo cấu trúc rỗng để builder tiếp tục điền dữ liệu.
     *
     * Kết quả của hàm này chưa phải RouteInfo hoàn chỉnh hợp lệ.
     */
    public static function createEmpty(): array
    {
        return [
            self::FIELD_ROLES                 => [],
            self::FIELD_FQCN                  => null,
            self::FIELD_FUNCTION              => null,
            self::FIELD_METHOD                => null,
            self::FIELD_ROUTE_TYPE            => null,
            self::FIELD_AUTHENTICATION_PATH   => null
        ];
    }

    /*---------------------------------------------------------------------------------------------------------------*/
}