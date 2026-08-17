<?php
namespace Core\User;
use Core\Utility\ValidUtility;
class UserInfo
{
    public const FIELD_ID                 = 'id';
    public const FIELD_NAME               = 'name';
    public const FIELD_PASSWORD           = 'password';
    public const FIELD_SUBSCRIBER_ID      = 'subscriber_id';
    public const FIELD_ROLES              = 'roles';
    public const FIELD_REGISTERED_MODULES = 'registered_modules';
    public const FIELD_LAST_ACTIVITY      = 'last_activity';

    private const GUEST_ROLE = 'guest';

    /*
     * UserInfo thuần.
     *
     * Được sử dụng trong AuthInfo, RequestAuthContext...
     */
    private const FIELDS = [
        self::FIELD_ID,
        self::FIELD_NAME,
        self::FIELD_SUBSCRIBER_ID,
        self::FIELD_ROLES,
        self::FIELD_REGISTERED_MODULES
    ];

    /*
     * Dữ liệu user do stored procedure phục vụ authentication trả về.
     *
     * UserInfo + password.
     */
    private const FIELDS_WITH_PASSWORD = [
        self::FIELD_ID,
        self::FIELD_NAME,
        self::FIELD_PASSWORD,
        self::FIELD_SUBSCRIBER_ID,
        self::FIELD_ROLES,
        self::FIELD_REGISTERED_MODULES
    ];

    /*
     * Dữ liệu user được lưu trong Session['auth'].
     *
     * UserInfo + last_activity.
     */
    private const FIELDS_WITH_LAST_ACTIVITY = [
        self::FIELD_ID,
        self::FIELD_NAME,
        self::FIELD_SUBSCRIBER_ID,
        self::FIELD_ROLES,
        self::FIELD_REGISTERED_MODULES,
        self::FIELD_LAST_ACTIVITY
    ];

    /*---------------------------------------------------------------------------------------------------------------*/
    public static function createGuest(): array
    {
        return [
            self::FIELD_ID            => null,
            self::FIELD_NAME          => null,
            self::FIELD_SUBSCRIBER_ID => null,

            self::FIELD_ROLES => [
                self::GUEST_ROLE => 'khách'
            ],

            /*
             * Contract:
             *
             * [
             *     module_id => module_name,
             *     ...
             * ]
             */
            self::FIELD_REGISTERED_MODULES => GUEST_ACCESSIBLE_MODULES
        ];
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    /**
     * Kiểm tra UserInfo thuần.
     */
    public static function isValid(mixed $arrData): bool
    {
        return ValidUtility::hasExactFields(
            $arrData,
            self::FIELDS
        )
        && self::isValidCommonData($arrData);
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    /**
     * Kiểm tra dữ liệu user do stored procedure phục vụ authentication trả về.
     *
     * Contract:
     *
     * UserInfo + password
     */
    public static function isValidWithPassword(mixed $arrData): bool
    {
        return ValidUtility::hasExactFields(
            $arrData,
            self::FIELDS_WITH_PASSWORD
        )
        && self::isValidCommonData($arrData)

        /*
         * Stored procedure phục vụ authentication chỉ trả user thực,
         * không trả guest.
         */
        && $arrData[self::FIELD_ID] !== null

        && ValidUtility::isNonEmptyString(
            $arrData[self::FIELD_PASSWORD]
        );
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    /**
     * Kiểm tra dữ liệu user được lưu trong Session['auth'].
     *
     * Contract:
     *
     * UserInfo + last_activity
     */
    public static function isValidWithLastActivity(mixed $arrData): bool
    {
        return ValidUtility::hasExactFields(
            $arrData,
            self::FIELDS_WITH_LAST_ACTIVITY
        )
        && self::isValidCommonData($arrData)

        && is_int(
            $arrData[self::FIELD_LAST_ACTIVITY]
        )

        && $arrData[self::FIELD_LAST_ACTIVITY] > 0;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    /**
     * 
     * Kiểm tra các field chung của UserInfo.
     */
    private static function isValidCommonData(array $arrData): bool
    {
        $mixId           = $arrData[self::FIELD_ID];
        $mixName         = $arrData[self::FIELD_NAME];
        $mixSubscriberId = $arrData[self::FIELD_SUBSCRIBER_ID];

        /*
         * roles:
         *
         * [
         *     role_code => display_name,
         *     ...
         * ]
         */
        if (!self::isValidRoles(
            $arrData[self::FIELD_ROLES]
        )) {
            return false;
        }

        /*
         * registered_modules:
         *
         * [
         *     module_id => module_name,
         *     ...
         * ]
         */
        if (!self::isValidRegisteredModules(
            $arrData[self::FIELD_REGISTERED_MODULES]
        )) {
            return false;
        }

        /*
         * Guest.
         */
        if ($mixId === null) {
            return $mixName === null
                && $mixSubscriberId === null
                && array_keys(
                    $arrData[self::FIELD_ROLES]
                ) === [self::GUEST_ROLE];
        }

        /*
         * Authenticated user.
         */
        if (!is_int($mixId) || $mixId <= 0) {
            return false;
        }

        if (!ValidUtility::isNonEmptyString($mixName)) {
            return false;
        }

        if (
            $mixSubscriberId !== null
            && (
                !is_int($mixSubscriberId)
                || $mixSubscriberId <= 0
            )
        ) {
            return false;
        }

        return true;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    /**
     * Contract:
     *
     * [
     *     role_code => display_name,
     *     ...
     * ]
     */
    private static function isValidRoles(mixed $arrRoles): bool
    {
        if (
            !ValidUtility::isStringPairMap($arrRoles)
            || $arrRoles === []
        ) {
            return false;
        }

        foreach ($arrRoles as $strRoleCode => $strDisplayName) {
            if (
                !ValidUtility::isNonEmptyString($strRoleCode)
                || !ValidUtility::isNonEmptyString($strDisplayName)
            ) {
                return false;
            }
        }

        return true;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
        /**
     * Contract:
     *
     * null
     *     Application không sử dụng mô hình module.
     *
     * []
     *     Application có module nhưng user không đăng ký module nào.
     *
     * [
     *     module_id => module_name,
     *     ...
     * ]
     *     Các module user được đăng ký.
     */
    private static function isValidRegisteredModules(mixed $arrModules): bool
    {
        /*
         * null:
         * application không sử dụng mô hình module.
         */
        if ($arrModules === null) {
            return true;
        }

        /*
         * []:
         * application có mô hình module nhưng user không có module nào.
         *
         * [module_id => module_name]&#58;      * các module user được đăng ký.
         */
        return ValidUtility::isIntStringMap(
            $arrModules,
            [
                'min_key'         => 1,
                'non_empty_value' => true,
                'unique_value'    => true
            ]
        );
    }
    /*---------------------------------------------------------------------------------------------------------------*/
}