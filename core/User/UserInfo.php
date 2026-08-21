<?php
namespace Core\User;

use RuntimeException;
use LogicException;
use JsonException;
use UnexpectedValueException;
use InvalidArgumentException;
use Core\Utility\ValidUtility;
use Core\Utility\MathUtility;
use Core\Http\Response;

class UserInfo
{
    public const FIELD_ID                 = 'id';
    public const FIELD_NAME               = 'name';
    public const FIELD_PASSWORD           = 'password';
    public const FIELD_SUBSCRIBER_ID      = 'subscriber_id';
    public const FIELD_ROLES              = 'roles';
    public const FIELD_REGISTERED_MODULES = 'registered_modules';
    public const FIELD_CREATED_AT         = 'created_at';
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
    public const DB_DATA_BASIC         = 'basic';
    public const DB_DATA_WITH_PASSWORD = 'with_password';
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
     * Kiểm tra dữ liệu user do stored procedure
     * phục vụ authentication trả về.
     *
     * Contract:
     *
     * UserInfo + password
     */
    public static function isValidWithPassword(mixed $arrData): bool
    {
        return ValidUtility::hasExactFields(
            $arrData,
            array_merge(
                self::FIELDS,
                [self::FIELD_PASSWORD]
            )
        )
        /*
         * Stored procedure authentication chỉ trả user thực,
         * không trả guest.
         */
        && $arrData[self::FIELD_ID] !== null
        && self::isValidCommonData($arrData)
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
     * UserInfo + created_at + last_activity
     */
    public static function isValidSessionData(mixed $arrData): bool
    {
        if (!ValidUtility::hasExactFields(
            $arrData,
            array_merge(
                self::FIELDS,
                [
                    self::FIELD_CREATED_AT,
                    self::FIELD_LAST_ACTIVITY
                ]
            )
        )) {
            return false;
        }

        if (!self::isValidCommonData($arrData)) {
            return false;
        }

        foreach (
            [self::FIELD_CREATED_AT, self::FIELD_LAST_ACTIVITY]
            as $strField
        ) {
            if (
                !is_int($arrData[$strField])
                || $arrData[$strField] <= 0
            ) {
                return false;
            }
        }

        return $arrData[self::FIELD_LAST_ACTIVITY]
            >= $arrData[self::FIELD_CREATED_AT];
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
    public static function normalizeDbData(
        array   $arrResp,    
        string  $strSPName,
        string  $strType    
    ): array {
        //1. chặn trước các lỗi DB - đây là bước đầu tiên
        if (Response::isResponseError($arrResp)) {
            throw new RuntimeException(
                "Lỗi database khi lấy UserInfo từ {$strSPName}."
            );
        }
        /*
         * 2. Không có record.
         *
         * Đây không phải lỗi format.
         * Việc empty có ý nghĩa gì do caller quyết định.
         */
        if (Response::isResponseEmpty($arrResp)) {
            return $arrResp;
        }
        /*
        * 3. Sau khi loại error và empty,
        * data bắt buộc phải là một record dạng array.
        */
        if (
            !array_key_exists('data', $arrResp)
            || !is_array($arrResp['data'])
        ) {
            throw new LogicException(
                "{$strSPName} trả về field data không đúng format array."
            );
        }
        //4.chuẩn hóa field: id và subscriber_id: filed phải tồn tại, 
        //giá trị bằng null hoặc là số nguyên không âm
        
        foreach (
            [self::FIELD_ID, self::FIELD_SUBSCRIBER_ID]
            as $strField
        ) {
            if (!array_key_exists($strField, $arrResp['data'])) {
                throw new LogicException(
                    "{$strSPName} trả về thiếu field {$strField} của UserInfo."
                );
            }
            if ($arrResp['data'][$strField] === null) {
                continue;
            }
            try {
                $arrResp['data'][$strField] =
                    MathUtility::toNonNegativeInt(
                        $arrResp['data'][$strField]
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
        //6. chuẩn hóa và kiểm tra format của $arrResp, đề phòng store procedure trả về sai format
        /*
         * roles bắt buộc phải tồn tại và khác null.
         */
        $strFieldRoles = self::FIELD_ROLES;

        if (!isset($arrResp['data'][$strFieldRoles]) || 
            !is_string($arrResp['data'][$strFieldRoles])) {
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
                $arrResp['data']
            )
            || (
                $arrResp['data'][$strFieldRegisteredModules] !== null
                && !is_string(
                    $arrResp['data'][$strFieldRegisteredModules]
                )
            )
        ) {
            throw new LogicException(
                "{$strSPName} trả về thiếu field "
                . "{$strFieldRegisteredModules} của UserInfo hoặc giá trị không phải null/string"
            );
        }
        try{
            $arrResp['data'][$strFieldRoles] = json_decode(
                $arrResp['data'][$strFieldRoles],
                true,
                512,
                JSON_THROW_ON_ERROR
            );
            if ($arrResp['data'][$strFieldRegisteredModules] !== null) {
                $arrResp['data'][$strFieldRegisteredModules] = json_decode(
                    $arrResp['data'][$strFieldRegisteredModules],
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
        switch ($strType) {
            case self::DB_DATA_BASIC:
                $boolValid = self::isValid($arrResp['data']);
                break;
            case self::DB_DATA_WITH_PASSWORD:
                $boolValid = self::isValidWithPassword($arrResp['data']);
                break;
            default:
                throw new InvalidArgumentException(
                    __METHOD__
                    . ": type '{$strType}' không hợp lệ."
                );
        }
        if (!$boolValid) {
            throw new UnexpectedValueException(
                "{$strSPName} trả về dữ liệu UserInfo không đúng contract."
            );
        }
        return $arrResp;
    }
    
    
    /*---------------------------------------------------------------------------------------------------------------*/
    /*Kiểm tra chuẩn hóa id và subscriber_id về số nguyên, registered_modules và roles dạng array*/
    public static function normalizeDbData2(
        string $strSPName,
        string $strType,    
        array $arrRespData,
    ): array {
       
        if (!is_array($arrRespData)) {
            throw new LogicException(
                "{$strSPName} trả về field data không đúng format array."
            );
        }
        //1.chuẩn hóa field: id và subscriber_id: filed phải tồn tại, 
        //giá trị bằng null hoặc là số nguyên không âm
        
        foreach (
            [self::FIELD_ID, self::FIELD_SUBSCRIBER_ID]
            as $strField
        ) 
        {
            if (!array_key_exists($strField, $arrRespData)) {
                throw new LogicException(
                    "{$strSPName} trả về thiếu field {$strField} của UserInfo."
                );
            }
            if ($arrRespData[$strField] === null) {
                continue;
            }
            try {
                $arrRespData[$strField] =
                    MathUtility::toNonNegativeInt(
                        $arrRespData[$strField]
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

        if (!isset($arrRespData[$strFieldRoles]) || 
            !is_string($arrRespData[$strFieldRoles])) {
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
                $arrRespData
            )
            || (
                $arrRespData[$strFieldRegisteredModules] !== null
                && !is_string(
                    $arrRespData[$strFieldRegisteredModules]
                )
            )
        ) {
            throw new LogicException(
                "{$strSPName} trả về thiếu field "
                . "{$strFieldRegisteredModules} của UserInfo hoặc giá trị không phải null/string"
            );
        }
        try{
            $arrRespData[$strFieldRoles] = json_decode(
                $arrRespData[$strFieldRoles],
                true,
                512,
                JSON_THROW_ON_ERROR
            );
            if ($arrRespData[$strFieldRegisteredModules] !== null) {
                $arrRespData[$strFieldRegisteredModules] = json_decode(
                    $arrRespData[$strFieldRegisteredModules],
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
        switch ($strType) {
            case self::DB_DATA_BASIC:
                $boolValid = self::isValid($arrRespData);
                break;
            case self::DB_DATA_WITH_PASSWORD:
                $boolValid = self::isValidWithPassword($arrRespData);
                break;
            default:
                throw new InvalidArgumentException(
                    __METHOD__
                    . ": type '{$strType}' không hợp lệ."
                );
        }
        if (!$boolValid) {
            throw new UnexpectedValueException(
                "{$strSPName} trả về dữ liệu UserInfo không đúng contract."
            );
        }
        return $arrRespData;
    }
}