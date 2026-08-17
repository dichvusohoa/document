<?php

namespace Core\Routing;
use Core\Utility\StringUtility;
use Core\Utility\ValidUtility;
use UnexpectedValueException;
use Core\Utility\MathUtility;
final class AuthRegistry
{
    public const TURNSTILE_NEVER  = 'never';
    public const TURNSTILE_ALWAYS = 'always';

    protected const ROLE_PATTERN_TYPE = 'role';
    protected const GUEST_ROLE_NAME   = 'guest';
    
    public const FIELD_INPUT_ACTIONS          = 'input_actions';
    public const FIELD_DEFAULT_INPUT_ACTION   = 'default_input_action';
    public const FIELD_DEFAULT_INPUT_ROLES    = 'default_input_roles'; /*field calculate*/
    public const FIELD_ACCEPTED_ROLES_PATTERN = 'accepted_roles_pattern';
    public const FIELD_ACCEPTED_ROLES         = 'accepted_roles'; /*field calculate*/
    public const FIELD_MAX_FAIL_COUNT         = 'max_fail_count';
    public const FIELD_TURNSTILE               = 'turnstile';
    public const FIELD_REMEMBER_COOKIE         = 'remember_cookie';
    public const FIELD_REMEMBER_EXPIRE  = 'remember_expire';
    public const FIELD_WEIGHTS                 = 'weights'; /*field calculate*/

    protected const WEIGHT_MAX_ROLE = 'max_role_weight';
    protected const WEIGHT_ACCEPTED_ROLE_COUNT = 'accepted_role_count';

    /*
     * Các field được phép khai báo trực tiếp trong config.login.php.
     *
     * accepted_roles, default_input_roles, weights không nằm ở đây vì chúng là
     * calculated fields do framework bổ sung vào registry.
     */
    protected const CONFIG_FIELDS = [
        self::FIELD_INPUT_ACTIONS,
        self::FIELD_DEFAULT_INPUT_ACTION,
        self::FIELD_ACCEPTED_ROLES_PATTERN,
        self::FIELD_MAX_FAIL_COUNT,
        self::FIELD_TURNSTILE,
        self::FIELD_REMEMBER_COOKIE,
        self::FIELD_REMEMBER_EXPIRE
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
    public static function fromArray(array $arrAuthRegistry): self{
        $ref = new \ReflectionClass(self::class);

        /** @var self $obj */
        $obj = $ref->newInstanceWithoutConstructor();

        $obj->arrAuthRegistry = $arrAuthRegistry;

        return $obj;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected function loadConfig(): array{
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
        /*
        * Chỉ cho phép các field được định nghĩa trong config.login.php.
        */
        ValidUtility::validateNoUnexpectedFields(
            $mixAuthConfig,
            self::CONFIG_FIELDS,
            $strContext
        );

        

        /*
         * Validate các field trực tiếp từ config trước.
         */
        $this->validateInputActions(
            $mixAuthConfig,
            $strContext
        );
        //validateRequiredPositiveIntField
        ValidUtility::validateRequiredField(
            $mixAuthConfig,
            self::FIELD_MAX_FAIL_COUNT,
            $strContext,
            [
                'type' => 'int',
                'min'  => 1
            ]    
        );

        $this->validateTurnstile(
            $mixAuthConfig,
            $strContext
        );

        $mixAuthConfig = $this->normalizeRememberCookie(
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
        //validateRequiredNonEmptyStringField
        ValidUtility::validateRequiredField(
            $arrRegistryEntry,
            self::FIELD_ACCEPTED_ROLES_PATTERN,
            $strContext,
            [
                'type'      => 'string',
                'non_empty' => true,
                'trimmed'   => true
            ]
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
        ValidUtility::validateRequiredField(
            $arrRegistryEntry,
            self::FIELD_TURNSTILE,
            $strContext
        );
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
        //validateRequiredBoolField
        ValidUtility::validateRequiredField(
            $arrRegistryEntry,
            self::FIELD_REMEMBER_COOKIE,
            $strContext,
            ['type' => 'bool']    
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
        //ValidUtility::validateRequiredPositiveIntField
        ValidUtility::validateRequiredField(
            $arrRegistryEntry,
            self::FIELD_REMEMBER_EXPIRE,
            $strContext,
            [
                'type' => 'int',
                'min'  => 1
            ]      
        );
        return $arrRegistryEntry;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected function calculateWeights(
        array $arrAcceptedRole,
        array $arrDefinedRole
    ): array {
        $iMaxRoleWeight = 0;

        foreach ($arrAcceptedRole as $strRoleName) {
            $iRoleWeight =
                $arrDefinedRole[$strRoleName][RoleRegistry::FIELD_WEIGHT];

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
    protected function validateInputActions(
        array $arrRegistryEntry,
        string $strContext
    ): void {
        //ValidUtility::validateRequiredNonEmptyStringListField(
        ValidUtility::validateRequiredField(
            $arrRegistryEntry,
            self::FIELD_INPUT_ACTIONS,
            $strContext,
            [
                'type'           => 'string_list',
                'non_empty'      => true,
                'item_non_empty' => true
            ]    
        );
        //ValidUtility::validateUniqueListField
        ValidUtility::validateRequiredField(
            $arrRegistryEntry,
            self::FIELD_INPUT_ACTIONS,
            $strContext,
            [
                'type'   => 'array',
                'unique' => true
            ]    
        );
        //ValidUtility::validateRequiredEnumField(
        ValidUtility::validateRequiredField(
            $arrRegistryEntry,
            self::FIELD_DEFAULT_INPUT_ACTION,
            //$arrRegistryEntry[self::FIELD_INPUT_ACTIONS],
            $strContext,
            [
                'type' => 'string',
                'values' => $arrRegistryEntry[self::FIELD_INPUT_ACTIONS]
            ]    
        );
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public function findAuthPathByRoles(
        array $arrUserRoles,    
        array $arrIntendedRoles
    ): ?string {
        $strCandidateAuthPath = null;
        $arrCandidateWeights  = null;

        foreach (
            $this->arrAuthRegistry as
            $strControllerName => $arrAuthEntry
        ) {
            $arrDefaultInputRoles =
                $arrAuthEntry[self::FIELD_DEFAULT_INPUT_ROLES];
            //phải đảm bảo rằng $arrAuthEntry này có các roles đầu vào giao nhau với $arrUserRoles
            //là khác rỗng thì $arrAuthEntry mới được chọn
            if (
                empty(
                    array_intersect(
                        $arrUserRoles,
                        $arrDefaultInputRoles
                    )
                )
            ) {
                continue;
            }
            
            $arrAcceptedRoles =
                $arrAuthEntry[self::FIELD_ACCEPTED_ROLES];
            //phải đảm bảo rằng $arrAuthEntry này cung cấp các roles sau khi xác thực thành công giao nhau với
            //tập các roles kỳ vọng ($arrIntendedRoles) khác rỗng thì $arrAuthEntry mới được chọn
            if (
                empty(
                    array_intersect(
                        $arrIntendedRoles,
                        $arrAcceptedRoles
                    )
                )
            ) {
                continue;
            }

            $arrWeights = array_values(
                $arrAuthEntry[self::FIELD_WEIGHTS]
            );
            //khi có nhiều $arrAuthEntry thì bắt đầu thuật toán tìm $arrAuthEntry có weight là min
            if (
                $arrCandidateWeights === null
                || MathUtility::compareNumberArray(
                    $arrWeights,
                    $arrCandidateWeights
                ) < 0
            ) {
                $strCandidateAuthPath =
                    $strControllerName
                    . '/'
                    . $arrAuthEntry[self::FIELD_DEFAULT_INPUT_ACTION];

                $arrCandidateWeights = $arrWeights;
            }
        }

        return $strCandidateAuthPath === null
            ? null
            : '/' . ltrim($strCandidateAuthPath, '/');
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public function getAuthRegistry(): array{
        return $this->arrAuthRegistry;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public function getAuthPolicy(string $strControllerName): ?array{
        return $this->arrAuthRegistry[$strControllerName] ?? null;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public function getAuthControllers(): array
    {
        return array_keys($this->arrAuthRegistry);
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public function hasAuthController(string $strControllerName): bool{
        return isset($this->arrAuthRegistry[$strControllerName]);
    }
}