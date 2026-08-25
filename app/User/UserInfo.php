<?php
namespace Core\User;
use LogicException;
use JsonException;
use UnexpectedValueException;
use InvalidArgumentException;
use Core\Utility\ValidUtility;
use Core\Utility\MathUtility;

class UserInfo
{
    public const FIELD_ID                 = 'id';
    public const FIELD_NAME               = 'name';
    public const FIELD_SUBSCRIBER_ID      = 'subscriber_id';
    public const FIELD_ROLES              = 'roles';
    public const FIELD_REGISTERED_MODULES = 'registered_modules';
  

    private const GUEST_ROLE = 'guest';

    /*
     * UserInfo thuần.
     *
     * Được sử dụng trong AuthResponse, RequestAuthContext...
     */
    private const FIELDS = [
        self::FIELD_ID,
        self::FIELD_NAME,
        self::FIELD_SUBSCRIBER_ID,
        self::FIELD_ROLES,
        self::FIELD_REGISTERED_MODULES
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
     * Kiểm tra các field chung của UserInfo.
     */
    private static function isValidCommonData(array $arrData): bool
    {
        $mixId           = $arrData[self::FIELD_ID];
        $mixName         = $arrData[self::FIELD_NAME];
        $mixSubscriberId = $arrData[self::FIELD_SUBSCRIBER_ID];
        $arrRoles        = $arrData[self::FIELD_ROLES];

        /*
         * roles:
         *
         * [
         *     role_code => display_name,
         *     ...
         * ]
         */
        if (!self::isValidRoles($arrRoles)) {
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
                && array_keys($arrRoles) === [self::GUEST_ROLE];
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
        return $arrRoles !== []
            && ValidUtility::isStringPairMap(
                $arrRoles,
                [
                    'non_empty_key'   => true,
                    'non_empty_value' => true
                ]
            );
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
    /*Kiểm tra chuẩn hóa id và subscriber_id về số nguyên, registered_modules và roles dạng array*/
    public static function normalizeDbData(
        string $strSPName,
        array $arrData,
    ): array {
        //1.chuẩn hóa field: id và subscriber_id: filed phải tồn tại, 
        //giá trị bằng null hoặc là số nguyên không âm
        
        foreach (
            [self::FIELD_ID, self::FIELD_SUBSCRIBER_ID]
            as $strField
        ) 
        {
            if (!array_key_exists($strField, $arrData)) {
                throw new LogicException(
                    "{$strSPName} trả về thiếu field {$strField} của UserInfo."
                );
            }
            if ($arrData[$strField] === null) {
                continue;
            }
            try {
                $arrData[$strField] =
                    MathUtility::toNonNegativeInt(
                        $arrData[$strField]
                    );
            }
            catch (InvalidArgumentException $e) {
                throw new LogicException(
                    "{$strSPName} trả về field {$strField} "
                    . 'không phải số nguyên không âm hợp lệ.',
                    0,
                    $e
                );
            }   
        }
        //2. chuẩn hóa và kiểm tra format của $arrResp, đề phòng store procedure trả về sai format
        /*
         * roles bắt buộc phải tồn tại và khác null.
         */
        $strFieldRoles = self::FIELD_ROLES;

        if (!isset($arrData[$strFieldRoles]) || 
            !is_string($arrData[$strFieldRoles])) {
            throw new LogicException(
                "{$strSPName} trả về thiếu field {$strFieldRoles} "
                . 'của UserInfo hoặc giá trị bằng null hoặc giá trị không phải kiểu string'
            );
        }

        /*
         * registered_modules bắt buộc phải tồn tại,
         * nhưng giá trị null là hợp lệ trong bài toán no-module.
         */
        $strFieldRegisteredModules = self::FIELD_REGISTERED_MODULES;

        if (!array_key_exists(
                $strFieldRegisteredModules,
                $arrData
            )
            || (
                $arrData[$strFieldRegisteredModules] !== null
                && !is_string(
                    $arrData[$strFieldRegisteredModules]
                )
            )
        ) {
            throw new LogicException(
                "{$strSPName} trả về thiếu field "
                . "{$strFieldRegisteredModules} của UserInfo hoặc giá trị không phải null/string"
            );
        }
        try{
            $arrData[$strFieldRoles] = json_decode(
                $arrData[$strFieldRoles],
                true,
                512,
                JSON_THROW_ON_ERROR
            );
            if ($arrData[$strFieldRegisteredModules] !== null) {
                $arrData[$strFieldRegisteredModules] = json_decode(
                    $arrData[$strFieldRegisteredModules],
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );
            }
        }
        catch (JsonException $e) {
            throw new LogicException(
                "{$strSPName} trả về dữ liệu JSON không hợp lệ.",
                0,
                $e
            );
        }
        /*
         * Validate sau normalization.
         */
        if (!self::isValid($arrData)) {
            throw new UnexpectedValueException(
                "{$strSPName} trả về dữ liệu UserInfo không đúng contract."
            );
        }
        return $arrData;
    }
}