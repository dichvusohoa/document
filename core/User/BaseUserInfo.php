<?php

namespace Core\User;

use LogicException;
use JsonException;
use UnexpectedValueException;
use InvalidArgumentException;
use Core\Utility\ValidUtility;
use Core\Utility\MathUtility;
/*3 function isExtensionValid, extendGuest, normalizeExtensionDbData nên được override lại ở các lớp thừa kế
 * thêm EXTENSION_FIELDS nữa
 * 3 function isValid, createGuest, normalizeDbData giao tiếp ra bên ngoài
 */
/*---------------------------------------------------------------------------------------------------------------*/
abstract class BaseUserInfo
{
    public const FIELD_ID                 = 'id';
    public const FIELD_NAME               = 'name';
    public const FIELD_SUBSCRIBER_ID      = 'subscriber_id';
    public const FIELD_ROLES              = 'roles';
    public const FIELD_REGISTERED_MODULES = 'registered_modules';

    protected const GUEST_ROLE = 'guest';

    /*
     * Các field thuộc contract lõi của UserInfo.
     */
    private const BASE_FIELDS = [
        self::FIELD_ID,
        self::FIELD_NAME,
        self::FIELD_SUBSCRIBER_ID,
        self::FIELD_ROLES,
        self::FIELD_REGISTERED_MODULES
    ];

    /*
     * UserInfo của App override constant này khi cần thêm field.
     *
     * Ví dụ:
     *
     * protected const EXTENSION_FIELDS = [
     *     self::FIELD_SCHOOL_ID
     * ];
     */
    protected const EXTENSION_FIELDS = [];

    /*---------------------------------------------------------------------------------------------------------------*/
    /**
     * Kiểm tra UserInfo hoàn chỉnh:
     *
     * BaseUserInfo
     * +
     * extension của UserInfo thực sự tại App.
     */
    public static function isValid(mixed $arrData): bool
    {
        if (!ValidUtility::hasExactFields(
            $arrData,
            array_merge(
                self::BASE_FIELDS,
                static::EXTENSION_FIELDS
            )
        )) {
            return false;
        }

        return static::isBaseValid($arrData)
            && static::isExtensionValid($arrData);
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    /**
     * Tạo UserInfo guest hoàn chỉnh.
     */
    public static function createGuest(): array
    {
        $arrUserInfo = static::createBaseGuest();

        $arrUserInfo = static::extendGuest(
            $arrUserInfo
        );

        if (!static::isValid($arrUserInfo)) {
            throw new LogicException(
                static::class
                . '::createGuest() tạo ra UserInfo không đúng contract.'
            );
        }

        return $arrUserInfo;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    /**
     * Chuẩn hóa record UserInfo lấy từ DB.
     *
     * $arrData phải chỉ chứa các field thuộc UserInfo.
     *
     * Các field phụ như password phải được caller xử lý
     * và loại bỏ trước khi gọi hàm này.
     */
    public static function normalizeDbData(
        string $strSPName,
        array $arrData
    ): array {
        $arrData = static::normalizeBaseDbData(
            $strSPName,
            $arrData
        );

        $arrData = static::normalizeExtensionDbData(
            $strSPName,
            $arrData
        );

        if (!static::isValid($arrData)) {
            throw new UnexpectedValueException(
                "{$strSPName} trả về dữ liệu "
                . static::class
                . ' không đúng contract.'
            );
        }

        return $arrData;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    /**
     * Kiểm tra phần contract lõi.
     *
     * Hàm chỉ được gọi sau khi isValid() đã xác nhận
     * đầy đủ và chính xác danh sách field.
     */
    protected static function isBaseValid(array $arrData): bool
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
         *
         * UserInfo luôn phải có ít nhất một role.
         */
        if (
            $arrRoles === []
            || !ValidUtility::isStringPairMap(
                $arrRoles,
                [
                    'non_empty_key'   => true,
                    'non_empty_value' => true
                ]
            )
        ) {
            return false;
        }

        /*
         * registered_modules:
         *
         * null
         *     Application không sử dụng mô hình module.
         *
         * []
         *     Application có module nhưng user không có module nào.
         *
         * [
         *     module_id => module_name,
         *     ...
         * ]
         */
        $arrRegisteredModules =
            $arrData[self::FIELD_REGISTERED_MODULES];

        if (
            $arrRegisteredModules !== null
            && !ValidUtility::isIntStringMap(
                $arrRegisteredModules,
                [
                    'min_key'         => 1,
                    'non_empty_value' => true,
                    'unique_value'    => true
                ]
            )
        ) {
            return false;
        }

        /*
         * Guest.
         */
        if ($mixId === null) {
            return $mixName === null
                && $mixSubscriberId === null
                && array_keys($arrRoles) === [
                    static::GUEST_ROLE
                ];
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

        /*
         * subscriber_id:
         *
         * null
         *     Application/user không thuộc mô hình subscriber.
         *
         * positive int
         *     ID subscriber.
         */
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
     * Hook để UserInfo tại App kiểm tra các field mở rộng.
     *
     * Base mặc định không có extension.
     */
    protected static function isExtensionValid(array $arrData): bool
    {
        return true;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    /**
     * Tạo phần lõi của guest.
     */
    protected static function createBaseGuest(): array
    {
        return [
            self::FIELD_ID            => null,
            self::FIELD_NAME          => null,
            self::FIELD_SUBSCRIBER_ID => null,

            self::FIELD_ROLES => [
                static::GUEST_ROLE => 'khách'
            ],

            self::FIELD_REGISTERED_MODULES =>
                GUEST_ACCESSIBLE_MODULES
        ];
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    /**
     * Hook để App bổ sung các field extension cho guest.
     */
    protected static function extendGuest(array $arrUserInfo): array
    {
        return $arrUserInfo;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    /**
     * Chuẩn hóa các field lõi của UserInfo lấy từ DB.
     */
    protected static function normalizeBaseDbData(
        string $strSPName,
        array $arrData
    ): array {
        /*
         * id và subscriber_id:
         *
         * field phải tồn tại;
         * null được giữ nguyên;
         * int/numeric-string được chuẩn hóa về native int.
         */
        foreach (
            [
                self::FIELD_ID,
                self::FIELD_SUBSCRIBER_ID
            ]
            as $strField
        ) {
            if (!array_key_exists($strField, $arrData)) {
                throw new LogicException(
                    "{$strSPName} trả về thiếu field "
                    . "{$strField} của UserInfo."
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
            } catch (InvalidArgumentException $e) {
                throw new LogicException(
                    "{$strSPName} trả về field {$strField} "
                    . 'không phải số nguyên không âm hợp lệ.',
                    0,
                    $e
                );
            }
        }

        /*
         * roles bắt buộc tồn tại, khác null
         * và DB phải trả dưới dạng JSON string.
         */
        $strFieldRoles = self::FIELD_ROLES;

        if (
            !isset($arrData[$strFieldRoles])
            || !is_string($arrData[$strFieldRoles])
        ) {
            throw new LogicException(
                "{$strSPName} trả về thiếu field "
                . "{$strFieldRoles} của UserInfo "
                . 'hoặc giá trị null/không phải string.'
            );
        }

        /*
         * registered_modules bắt buộc có field.
         *
         * null hợp lệ cho bài toán no-module.
         * Nếu khác null thì DB phải trả JSON string.
         */
        $strFieldRegisteredModules =
            self::FIELD_REGISTERED_MODULES;

        if (
            !array_key_exists(
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
                . "{$strFieldRegisteredModules} của UserInfo "
                . 'hoặc giá trị không phải null/string.'
            );
        }

        /*
         * JSON DB representation
         *     ↓
         * PHP array representation.
         */
        try {
            $arrData[$strFieldRoles] = json_decode(
                $arrData[$strFieldRoles],
                true,
                512,
                JSON_THROW_ON_ERROR
            );

            if (
                $arrData[$strFieldRegisteredModules]
                !== null
            ) {
                $arrData[$strFieldRegisteredModules] =
                    json_decode(
                        $arrData[$strFieldRegisteredModules],
                        true,
                        512,
                        JSON_THROW_ON_ERROR
                    );
            }
        } catch (JsonException $e) {
            throw new LogicException(
                "{$strSPName} trả về dữ liệu JSON không hợp lệ.",
                0,
                $e
            );
        }

        return $arrData;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    /**
     * Hook để UserInfo tại App chuẩn hóa các field DB mở rộng.
     *
     * Base mặc định không có extension.
     */
    protected static function normalizeExtensionDbData(
        string $strSPName,
        array $arrData
    ): array {
        return $arrData;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
}