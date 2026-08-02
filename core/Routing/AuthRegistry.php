<?php

namespace Core\Routing;

use Core\Utility\StringUtility;
use Core\Utility\ValidUtility;
use UnexpectedValueException;

final class AuthRegistry
{
    public const TURNSTILE_NEVER  = 'never';
    public const TURNSTILE_ALWAYS = 'always';

    protected const ROLE_PATTERN_TYPE = 'role';
    protected const GUEST_ROLE_NAME   = 'guest';

    public const FIELD_ACCEPTED_ROLES_PATTERN = 'accepted_roles_pattern';
    public const FIELD_ACCEPTED_ROLES         = 'accepted_roles';
    public const FIELD_MAX_FAIL_COUNT         = 'max_fail_count';
    public const FIELD_TURNSTILE               = 'turnstile';
    public const FIELD_REMEMBER_COOKIE         = 'remember_cookie';
    public const FIELD_REMEMBER_EXPIRE         = 'remember_expire';
    public const FIELD_DEFAULT_BUSINESS_PATH        = 'default_business_path';
    public const FIELD_WEIGHTS                 = 'weights';

    protected const WEIGHT_MAX_ROLE = 'max_role_weight';
    protected const WEIGHT_ACCEPTED_ROLE_COUNT = 'accepted_role_count';

    /*
     * Đây chỉ là các field được phép xuất hiện trong config.login.php.
     *
     * Không đưa accepted_roles và weights vào đây vì hai field đó
     * do framework sinh ra sau khi normalize.
     */
    protected const CONFIG_FIELDS = [
        self::FIELD_ACCEPTED_ROLES_PATTERN,
        self::FIELD_MAX_FAIL_COUNT,
        self::FIELD_TURNSTILE,
        self::FIELD_REMEMBER_COOKIE,
        self::FIELD_REMEMBER_EXPIRE,
        self::FIELD_DEFAULT_BUSINESS_PATH,
    ];

    protected const REQUIRED_CONFIG_FIELDS = [
        self::FIELD_ACCEPTED_ROLES_PATTERN,
        self::FIELD_MAX_FAIL_COUNT,
        self::FIELD_TURNSTILE,
        self::FIELD_REMEMBER_COOKIE,
        self::FIELD_DEFAULT_BUSINESS_PATH,
    ];

    /*
     * controller name => login registry entry
     */
    protected array $arrAuthRegistry;

    /*---------------------------------------------------------------------------------------------------------------*/
    public function __construct(
        array $arrDefinedController,
        array $arrDefinedRole
    ) {
        $arrAuthConfig = $this->loadConfig();

        $arrAuthConfig = $this->normalizeControllerNames(
            $arrAuthConfig
        );

        if ($arrAuthConfig === []) {
            throw new UnexpectedValueException(
                'Cấu hình login registry không được rỗng.'
            );
        }

        $arrAuthRegistry = [];

        foreach (
            $arrAuthConfig as
            $strControllerName => $mixAuthConfig
        ) {
            $this->validateControllerName(
                $strControllerName,
                $arrDefinedController
            );

            $arrAuthRegistry[$strControllerName] =
                $this->buildRegistryEntry(
                    $strControllerName,
                    $mixAuthConfig,
                    $arrDefinedRole
                );
        }

        $this->arrAuthRegistry = $arrAuthRegistry;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected function loadConfig(): array
    {
        $strConfigFilePath =
            CONFIG_PATH . '/config.login.php';

        if (!is_file($strConfigFilePath)) {
            throw new UnexpectedValueException(
                "Không tìm thấy file cấu hình login "
                . "'{$strConfigFilePath}'."
            );
        }

        $mixAuthConfig = require $strConfigFilePath;

        if (!is_array($mixAuthConfig)) {
            throw new UnexpectedValueException(
                "File cấu hình login '{$strConfigFilePath}' "
                . 'phải trả về array; nhận được '
                . get_debug_type($mixAuthConfig)
                . '.'
            );
        }

        return $mixAuthConfig;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected function normalizeControllerNames(
        array $arrAuthConfig
    ): array {
        $arrNormalizedAuthConfig = [];

        foreach (
            $arrAuthConfig as
            $mixControllerName => $mixControllerConfig
        ) {
            if (!is_string($mixControllerName)) {
                throw new UnexpectedValueException(
                    'Tên login controller phải là string; nhận được '
                    . get_debug_type($mixControllerName)
                    . '.'
                );
            }

            $strControllerName =
                StringUtility::spacesToDash($mixControllerName);

            if ($strControllerName === '') {
                throw new UnexpectedValueException(
                    'Tên login controller không được rỗng.'
                );
            }

            if (
                array_key_exists(
                    $strControllerName,
                    $arrNormalizedAuthConfig
                )
            ) {
                throw new UnexpectedValueException(
                    "Auth controller '{$strControllerName}' bị trùng "
                    . 'sau khi chuẩn hóa.'
                );
            }

            $arrNormalizedAuthConfig[$strControllerName] =
                $mixControllerConfig;
        }

        return $arrNormalizedAuthConfig;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected function validateControllerName(
        string $strControllerName,
        array $arrDefinedController
    ): void {
        if (
            !in_array(
                $strControllerName,
                $arrDefinedController,
                true
            )
        ) {
            throw new UnexpectedValueException(
                "Auth controller '{$strControllerName}' "
                . 'không thuộc danh sách standalone controller '
                . 'đã được định nghĩa.'
            );
        }
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected function buildRegistryEntry(
        string $strControllerName,
        mixed $mixAuthConfig,
        array $arrDefinedRole
    ): array {
        $strContext =
            "Cấu hình login của controller '{$strControllerName}'";

        if (!is_array($mixAuthConfig)) {
            throw new UnexpectedValueException(
                "{$strContext} phải là array; nhận được "
                . get_debug_type($mixAuthConfig)
                . '.'
            );
        }

        ValidUtility::validateNoUnexpectedFields(
            $mixAuthConfig,
            self::CONFIG_FIELDS,
            $strContext
        );

        foreach (
            self::REQUIRED_CONFIG_FIELDS as $strFieldName
        ) {
            ValidUtility::validateRequiredField(
                $mixAuthConfig,
                $strFieldName,
                $strContext
            );
        }

        /*
         * Validate các field trực tiếp từ config trước.
         */
        ValidUtility::validateRequiredPositiveIntField(
            $mixAuthConfig,
            self::FIELD_MAX_FAIL_COUNT,
            $strContext
        );

        $this->validateTurnstile(
            $mixAuthConfig,
            $strContext
        );

        $mixAuthConfig = $this->normalizeRememberCookie(
            $mixAuthConfig,
            $strContext
        );

        $this->validateDefaultRedirect(
            $mixAuthConfig,
            $strContext
        );

        /*
         * Chuyển pattern đầu vào thành dữ liệu runtime.
         */
        $arrRegistryEntry = $this->calculateAcceptedRoles(
            $mixAuthConfig,
            $arrDefinedRole,
            $strContext
        );

        /*
         * Calculated field: không xuất hiện trong config.login.php.
         */
        $arrRegistryEntry[self::FIELD_WEIGHTS] =
            $this->calculateWeights(
                $arrRegistryEntry[self::FIELD_ACCEPTED_ROLES],
                $arrDefinedRole
            );

        return $arrRegistryEntry;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected function calculateAcceptedRoles(
        array $arrRegistryEntry,
        array $arrDefinedRole,
        string $strContext
    ): array {
        ValidUtility::validateRequiredNonEmptyStringField(
            $arrRegistryEntry,
            self::FIELD_ACCEPTED_ROLES_PATTERN,
            $strContext
        );

        $strAcceptedRolesPattern =
            $arrRegistryEntry[self::FIELD_ACCEPTED_ROLES_PATTERN];

        $arrRoleParse = RoutePattern::parse(
            $strAcceptedRolesPattern,
            array_keys($arrDefinedRole)
        );

        if (
            $arrRoleParse['type'] !== ''
            && $arrRoleParse['type'] !== self::ROLE_PATTERN_TYPE
        ) {
            throw new UnexpectedValueException(
                "{$strContext} field '"
                . self::FIELD_ACCEPTED_ROLES_PATTERN
                . "' phải có type '"
                . self::ROLE_PATTERN_TYPE
                . "'; nhận được type "
                . "'{$arrRoleParse['type']}'."
            );
        }

        $arrAcceptedRole = $arrRoleParse['values'];

        if ($arrAcceptedRole === []) {
            throw new UnexpectedValueException(
                "{$strContext} field '"
                . self::FIELD_ACCEPTED_ROLES_PATTERN
                . "' không được tạo ra tập role rỗng."
            );
        }

        if (
            in_array(
                self::GUEST_ROLE_NAME,
                $arrAcceptedRole,
                true
            )
        ) {
            throw new UnexpectedValueException(
                "{$strContext} field '"
                . self::FIELD_ACCEPTED_ROLES_PATTERN
                . "' không được chấp nhận role '"
                . self::GUEST_ROLE_NAME
                . "'."
            );
        }

        unset(
            $arrRegistryEntry[
                self::FIELD_ACCEPTED_ROLES_PATTERN
            ]
        );

        $arrRegistryEntry[self::FIELD_ACCEPTED_ROLES] =
            $arrAcceptedRole;

        return $arrRegistryEntry;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected function validateTurnstile(
        array $arrRegistryEntry,
        string $strContext
    ): void {
        $mixTurnstileRule =
            $arrRegistryEntry[self::FIELD_TURNSTILE];

        if (
            $mixTurnstileRule === self::TURNSTILE_NEVER
            || $mixTurnstileRule === self::TURNSTILE_ALWAYS
        ) {
            return;
        }

        if (
            is_int($mixTurnstileRule)
            && $mixTurnstileRule >= 1
        ) {
            return;
        }

        throw new UnexpectedValueException(
            "{$strContext} field '"
            . self::FIELD_TURNSTILE
            . "' phải là '"
            . self::TURNSTILE_NEVER
            . "', '"
            . self::TURNSTILE_ALWAYS
            . "' hoặc số nguyên dương."
        );
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected function normalizeRememberCookie(
        array $arrRegistryEntry,
        string $strContext
    ): array {
        ValidUtility::validateRequiredBoolField(
            $arrRegistryEntry,
            self::FIELD_REMEMBER_COOKIE,
            $strContext
        );

        $isRememberCookie =
            $arrRegistryEntry[self::FIELD_REMEMBER_COOKIE];

        if (!$isRememberCookie) {
            if (
                array_key_exists(
                    self::FIELD_REMEMBER_EXPIRE,
                    $arrRegistryEntry
                )
                && $arrRegistryEntry[
                    self::FIELD_REMEMBER_EXPIRE
                ] !== null
            ) {
                throw new UnexpectedValueException(
                    "{$strContext} field '"
                    . self::FIELD_REMEMBER_EXPIRE
                    . "' phải bằng null hoặc không được khai báo khi '"
                    . self::FIELD_REMEMBER_COOKIE
                    . "' bằng false."
                );
            }

            /*
             * Default runtime value.
             */
            $arrRegistryEntry[self::FIELD_REMEMBER_EXPIRE] =
                null;

            return $arrRegistryEntry;
        }

        ValidUtility::validateRequiredPositiveIntField(
            $arrRegistryEntry,
            self::FIELD_REMEMBER_EXPIRE,
            $strContext
        );

        return $arrRegistryEntry;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected function validateDefaultRedirect(
        array $arrRegistryEntry,
        string $strContext
    ): void {
        ValidUtility::validateRequiredNonEmptyStringField(
            $arrRegistryEntry,
            self::FIELD_DEFAULT_BUSINESS_PATH,
            $strContext
        );

        $strDefaultRedirect =
            $arrRegistryEntry[self::FIELD_DEFAULT_BUSINESS_PATH];

        if (
            $strDefaultRedirect[0] !== '/'
            || str_starts_with($strDefaultRedirect, '//')
        ) {
            throw new UnexpectedValueException(
                "{$strContext} field '"
                . self::FIELD_DEFAULT_BUSINESS_PATH
                . "' phải là đường dẫn tuyệt đối nội bộ."
            );
        }
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected function calculateWeights(
        array $arrAcceptedRole,
        array $arrDefinedRole
    ): array {
        $iMaxRoleWeight = 0;

        foreach ($arrAcceptedRole as $strRoleName) {
            $iRoleWeight =
                $arrDefinedRole[$strRoleName]['weight'];

            if ($iRoleWeight > $iMaxRoleWeight) {
                $iMaxRoleWeight = $iRoleWeight;
            }
        }

        return [
            self::WEIGHT_MAX_ROLE =>
                $iMaxRoleWeight,

            self::WEIGHT_ACCEPTED_ROLE_COUNT =>
                count($arrAcceptedRole),
        ];
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    public function getAuthRegistry(): array{
        return $this->arrAuthRegistry;
    }
}