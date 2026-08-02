<?php

namespace Core\Auth;

use Core\Utility\StringUtility;
use Core\Utility\ValidUtility;
use InvalidArgumentException;
use LogicException;
use UnexpectedValueException;

class LoginRegistry
{
    public const TURNSTILE_NEVER  = 'never';
    public const TURNSTILE_ALWAYS = 'always';

    protected const REGISTRY_FIELDS = [
        'required_roles',
        'max_fail_count',
        'turnstile',
        'remember_cookie',
        'remember_expire',
        'default_redirect'
    ];

    protected const REQUIRED_REGISTRY_FIELDS = [
        'required_roles',
        'max_fail_count',
        'turnstile',
        'remember_cookie',
        'default_redirect'
    ];

    /*
     * controller name => registry
     */
    protected array $arrLoginRegistry;

    /*
     * Tên login controller của request hiện tại.
     */
    protected ?string $strLoginControllerName = null;

    /*---------------------------------------------------------------------------------------------------------------*/
    public function __construct(
        array $arrDefinedController,
        array $arrDefinedRole
    ) {
        $arrLoginRegistry = $this->loadConfig();
        //chuẩn hoá lại $arrLoginRegistry
        $arrLoginRegistry = $this->normalizeControllerNames(
            $arrLoginRegistry
        );

        $this->validate(
            $arrLoginRegistry,
            $arrDefinedController,
            $arrDefinedRole
        );

        $this->arrLoginRegistry = $arrLoginRegistry;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected function loadConfig(): array{
        $strConfigFilePath = CONFIG_PATH . '/config.login.php';

        if (!is_file($strConfigFilePath)) {
            throw new UnexpectedValueException(
                "Không tìm thấy file cấu hình login: "
                . "'{$strConfigFilePath}'."
            );
        }

        $mixLoginRegistry = require $strConfigFilePath;

        if (!is_array($mixLoginRegistry)) {
            throw new UnexpectedValueException(
                "File cấu hình login '{$strConfigFilePath}' "
                . 'phải trả về array; nhận được '
                . get_debug_type($mixLoginRegistry)
                . '.'
            );
        }

        return $mixLoginRegistry;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected function normalizeControllerNames(
        array $arrLoginRegistry
    ): array {
        $arrNormalizedLoginRegistry = [];

        foreach ($arrLoginRegistry as $mixControllerName => $arrRegistry) {
            if (!is_string($mixControllerName)) {
                throw new UnexpectedValueException(
                    'Tên login controller phải là string; nhận được '
                    . get_debug_type($mixControllerName)
                    . '.'
                );
            }

            $strControllerName = StringUtility::spacesToDash(
                $mixControllerName
            );

            if ($strControllerName === '') {
                throw new UnexpectedValueException(
                    'Tên login controller không được rỗng.'
                );
            }

            if (
                array_key_exists(
                    $strControllerName,
                    $arrNormalizedLoginRegistry
                )
            ) {
                throw new UnexpectedValueException(
                    "Login controller '{$strControllerName}' bị trùng "
                    . 'sau khi chuẩn hóa.'
                );
            }

            $arrNormalizedLoginRegistry[$strControllerName] = $arrRegistry;
        }

        return $arrNormalizedLoginRegistry;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected function validate(
        array $arrLoginRegistry,
        array $arrDefinedController,
        array $arrDefinedRole
    ): void {
        if ($arrLoginRegistry === []) {
            throw new UnexpectedValueException(
                'Cấu hình login registry không được rỗng.'
            );
        }

        foreach ($arrLoginRegistry as $strControllerName => $mixRegistry) {
            $this->validateControllerName(
                $strControllerName,
                $arrDefinedController
            );

            $this->validateRegistry(
                $strControllerName,
                $mixRegistry,
                $arrDefinedRole
            );
        }
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected function validateControllerName(
        string $strControllerName,
        array $arrDefinedController
    ): void {
        if (!in_array($strControllerName, $arrDefinedController, true)) {
            throw new UnexpectedValueException(
                "Login controller '{$strControllerName}' "
                . 'không thuộc danh sách standalone controller đã định nghĩa.'
            );
        }
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected function validateRegistry(
        string $strControllerName,
        mixed $mixRegistry,
        array $arrDefinedRole
    ): void {
        $strContext = "Login registry của controller '{$strControllerName}'";

        if (!is_array($mixRegistry)) {
            throw new UnexpectedValueException(
                "{$strContext} phải là array; nhận được "
                . get_debug_type($mixRegistry)
                . '.'
            );
        }
        //đảm bảo trong $mixRegistry không có key lạ ra ngoài self::REGISTRY_FIELDS
        ValidUtility::validateNoUnexpectedFields(
            $mixRegistry,
            self::REGISTRY_FIELDS,
            $strContext
        );
        //đảm bảo các key bắt buộc trong self::REQUIRED_REGISTRY_FIELDS thì $mixRegistry đều phải có
        foreach (self::REQUIRED_REGISTRY_FIELDS as $strFieldName) {
            ValidUtility::validateRequiredField(
                $mixRegistry,
                $strFieldName,
                $strContext
            );
        }

        $this->validateRequiredRoles(
            $mixRegistry,
            $arrDefinedRole,
            $strContext
        );

        ValidUtility::validateRequiredPositiveIntField(
            $mixRegistry,
            'max_fail_count',
            $strContext
        );

        $this->validateTurnstile(
            $mixRegistry,
            $strContext
        );

        $this->validateRememberCookie(
            $mixRegistry,
            $strContext
        );

        $this->validateDefaultRedirect(
            $mixRegistry,
            $strContext
        );
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected function validateRequiredRoles(
        array $arrRegistry,
        array $arrDefinedRole,
        string $strContext
    ): void {
        $mixRequiredRole = $arrRegistry['required_roles'];
        if (!ValidUtility::isStringList($mixRequiredRole)) {
            throw new UnexpectedValueException(
                "{$strContext} field 'required_roles' "
                . 'phải là null hoặc danh sách string.'
            );
        }

        if ($mixRequiredRole === []) {
            throw new UnexpectedValueException(
                "{$strContext} field 'required_roles' "
                . 'không được là danh sách rỗng.'
            );
        }

        $arrCheckedRole = [];

        foreach ($mixRequiredRole as $strRoleName) {
            if ($strRoleName === '') {
                throw new UnexpectedValueException(
                    "{$strContext} field 'required_roles' "
                    . 'không được chứa role rỗng.'
                );
            }

            if (!in_array($strRoleName, $arrDefinedRole, true)) {
                throw new UnexpectedValueException(
                    "{$strContext} chứa role chưa được định nghĩa "
                    . "'{$strRoleName}'."
                );
            }

            if (isset($arrCheckedRole[$strRoleName])) {
                throw new UnexpectedValueException(
                    "{$strContext} chứa role bị trùng "
                    . "'{$strRoleName}'."
                );
            }

            $arrCheckedRole[$strRoleName] = true;
        }
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected function validateTurnstile(
        array $arrRegistry,
        string $strContext
    ): void {
        $mixTurnstileRule = $arrRegistry['turnstile'];

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
            "{$strContext} field 'turnstile' phải là "
            . "'" . self::TURNSTILE_NEVER . "', "
            . "'" . self::TURNSTILE_ALWAYS . "' "
            . 'hoặc số nguyên dương.'
        );
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected function validateRememberCookie(
        array $arrRegistry,
        string $strContext
    ): void {
        ValidUtility::validateRequiredBoolField(
            $arrRegistry,
            'remember_cookie',
            $strContext
        );

        $isRememberCookie = $arrRegistry['remember_cookie'];

        if (!$isRememberCookie) {
            if (
                array_key_exists('remember_expire', $arrRegistry)
                && $arrRegistry['remember_expire'] !== null
            ) {
                throw new UnexpectedValueException(
                    "{$strContext} field 'remember_expire' "
                    . 'phải bằng null hoặc không được khai báo khi '
                    . "'remember_cookie' bằng false."
                );
            }

            return;
        }

        ValidUtility::validateRequiredPositiveIntField(
            $arrRegistry,
            'remember_expire',
            $strContext
        );
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected function validateDefaultRedirect(
        array $arrRegistry,
        string $strContext
    ): void {
        ValidUtility::validateRequiredNonEmptyStringField(
            $arrRegistry,
            'default_redirect',
            $strContext
        );

        $strDefaultRedirect = $arrRegistry['default_redirect'];

        if (
            $strDefaultRedirect[0] !== '/'
            || str_starts_with($strDefaultRedirect, '//')
        ) {
            throw new UnexpectedValueException(
                "{$strContext} field 'default_redirect' "
                . 'phải là đường dẫn tuyệt đối nội bộ.'
            );
        }
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    

    /*---------------------------------------------------------------------------------------------------------------*/
    public function isTurnstileRequired(
        int $iFailCount
    ): bool {
        if ($iFailCount < 0) {
            throw new InvalidArgumentException(
                'Số lần đăng nhập thất bại không được là số âm.'
            );
        }

        $mixTurnstileRule = $this->getTurnstileRule();

        if ($mixTurnstileRule === self::TURNSTILE_NEVER) {
            return false;
        }

        if ($mixTurnstileRule === self::TURNSTILE_ALWAYS) {
            return true;
        }

        return $iFailCount >= $mixTurnstileRule;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    public function usesRememberCookie(): bool
    {
        return $this->getCurrentRegistry()['remember_cookie'];
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    public function getRememberExpire(): ?int
    {
        return $this->getCurrentRegistry()['remember_expire'] ?? null;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    public function getDefaultRedirect(): string
    {
        return $this->getCurrentRegistry()['default_redirect'];
    }
}