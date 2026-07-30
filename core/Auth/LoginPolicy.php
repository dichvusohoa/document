<?php

namespace Core\Auth;

use Core\Utility\StringUtility;
use InvalidArgumentException;
use LogicException;
use UnexpectedValueException;

class LoginPolicy
{
    public const TURNSTILE_NEVER  = 'never';
    public const TURNSTILE_ALWAYS = 'always';

    protected const POLICY_FIELDS = [
        'required_roles',
        'max_fail_count',
        'turnstile',
        'remember_cookie',
        'remember_expire',
        'default_redirect'
    ];

    /*
     * Danh sách toàn bộ login policy:
     *
     * controller name => policy
     */
    protected array $arrLoginPolicy;

    /*
     * Tên login controller của request hiện tại.
     *
     * Ban đầu bằng null vì LoginPolicy có thể được khởi tạo
     * trước khi ContextRouter xác định route hiện tại.
     */
    protected ?string $strLoginControllerName = null;

    /*---------------------------------------------------------------------------------------------------------------*/
    public function __construct(
        array $arrDefinedController,
        array $arrDefinedRole
    ) {
        $arrLoginPolicy = $this->loadConfig();

        $arrLoginPolicy = $this->normalizeControllerNames(
            $arrLoginPolicy
        );

        $this->validate(
            $arrLoginPolicy,
            $arrDefinedController,
            $arrDefinedRole
        );

        $this->arrLoginPolicy = $arrLoginPolicy;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected function loadConfig(): array
    {
        /*
         * Thay CONFIG_PATH bằng hằng đường dẫn config
         * đang được framework sử dụng nếu tên thực tế khác.
         */
        $strConfigFilePath = CONFIG_PATH . '/config.login.php';

        if (!is_file($strConfigFilePath)) {
            throw new UnexpectedValueException(
                "Login policy configuration file not found: "
                . $strConfigFilePath
            );
        }

        $arrLoginPolicy = require $strConfigFilePath;

        if (!is_array($arrLoginPolicy)) {
            throw new UnexpectedValueException(
                "Login policy configuration file must return an array: "
                . $strConfigFilePath
            );
        }

        return $arrLoginPolicy;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected function validate(
        array $arrLoginPolicy,
        array $arrDefinedController,
        array $arrDefinedRole
    ): void {
        if ($arrLoginPolicy === []) {
            throw new UnexpectedValueException(
                'Login policy configuration must not be empty.'
            );
        }

        foreach ($arrLoginPolicy as $strControllerName => $arrPolicy) {
            $this->validateControllerName(
                $strControllerName,
                $arrDefinedController
            );

            $this->validatePolicy(
                $strControllerName,
                $arrPolicy,
                $arrDefinedRole
            );
        }
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected function validateControllerName(
        mixed $strControllerName,
        array $arrDefinedController
    ): void {
        if (
            !is_string($strControllerName)
            || $strControllerName === ''
        ) {
            throw new UnexpectedValueException(
                'Login controller name must be a non-empty string.'
            );
        }

        /*
         * Không tự sửa config.login.php.
         *
         * Chỉ kiểm tra tên controller đã đúng dạng chuẩn chưa:
         *
         * "abc def"   => không hợp lệ
         * "abc-def"   => hợp lệ
         * " login "   => không hợp lệ
         */
        if (
            StringUtility::spacesToDash($strControllerName)
            !== $strControllerName
        ) {
            throw new UnexpectedValueException(
                "Login controller name '{$strControllerName}' is not normalized. "
                . "Expected: '"
                . StringUtility::spacesToDash($strControllerName)
                . "'."
            );
        }

        if (
            !in_array(
                $strControllerName,
                $arrDefinedController,
                true
            )
        ) {
            throw new UnexpectedValueException(
                "Login controller '{$strControllerName}' "
                . 'is not defined as a standalone controller.'
            );
        }
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected function normalizeControllerNames(array $arrLoginPolicy): array
    {
        $arrNormalizedLoginPolicy = [];

        foreach ($arrLoginPolicy as $strControllerName => $arrPolicy) {
            if (!is_string($strControllerName)) {
                throw new UnexpectedValueException(
                    'Login controller name must be a string.'
                );
            }

            $strNormalizedControllerName =
                StringUtility::spacesToDash($strControllerName);

            if ($strNormalizedControllerName === '') {
                throw new UnexpectedValueException(
                    'Login controller name must not be empty.'
                );
            }

            if (
                array_key_exists(
                    $strNormalizedControllerName,
                    $arrNormalizedLoginPolicy
                )
            ) {
                throw new UnexpectedValueException(
                    "Duplicate login controller '{$strNormalizedControllerName}' "
                    . 'after normalization.'
                );
            }

            $arrNormalizedLoginPolicy[
                $strNormalizedControllerName
            ] = $arrPolicy;
        }

        return $arrNormalizedLoginPolicy;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected function validatePolicy(
        string $strControllerName,
        mixed $arrPolicy,
        array $arrDefinedRole
    ): void {
        if (!is_array($arrPolicy)) {
            throw new UnexpectedValueException(
                "Login policy for controller '{$strControllerName}' "
                . 'must be an array.'
            );
        }

        $this->validatePolicyFields(
            $strControllerName,
            $arrPolicy
        );

        $this->validateRequiredRoles(
            $strControllerName,
            $arrPolicy['required_roles'],
            $arrDefinedRole
        );

        $this->validateMaxFailCount(
            $strControllerName,
            $arrPolicy['max_fail_count']
        );

        $this->validateTurnstile(
            $strControllerName,
            $arrPolicy['turnstile']
        );

        $this->validateRememberCookie(
            $strControllerName,
            $arrPolicy
        );

        $this->validateDefaultRedirect(
            $strControllerName,
            $arrPolicy['default_redirect']
        );
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected function validatePolicyFields(
        string $strControllerName,
        array $arrPolicy
    ): void {
        $arrRequiredField = [
            'required_roles',
            'max_fail_count',
            'turnstile',
            'remember_cookie',
            'default_redirect'
        ];

        foreach ($arrRequiredField as $strFieldName) {
            if (!array_key_exists($strFieldName, $arrPolicy)) {
                throw new UnexpectedValueException(
                    "Missing field '{$strFieldName}' in login policy "
                    . "for controller '{$strControllerName}'."
                );
            }
        }

        foreach ($arrPolicy as $strFieldName => $_) {
            if (
                !is_string($strFieldName)
                || !in_array(
                    $strFieldName,
                    self::POLICY_FIELDS,
                    true
                )
            ) {
                throw new UnexpectedValueException(
                    "Unsupported field '{$strFieldName}' in login policy "
                    . "for controller '{$strControllerName}'."
                );
            }
        }
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected function validateRequiredRoles(
        string $strControllerName,
        mixed $arrRequiredRole,
        array $arrDefinedRole
    ): void {
        /*
         * null nghĩa là không giới hạn role khi truy vấn người dùng.
         */
        if ($arrRequiredRole === null) {
            return;
        }

        if (!is_array($arrRequiredRole)) {
            throw new UnexpectedValueException(
                "'required_roles' in login policy for controller "
                . "'{$strControllerName}' must be null or an array."
            );
        }

        if ($arrRequiredRole === []) {
            throw new UnexpectedValueException(
                "'required_roles' in login policy for controller "
                . "'{$strControllerName}' must not be an empty array."
            );
        }

        $arrCheckedRole = [];

        foreach ($arrRequiredRole as $strRoleName) {
            if (
                !is_string($strRoleName)
                || $strRoleName === ''
            ) {
                throw new UnexpectedValueException(
                    "'required_roles' in login policy for controller "
                    . "'{$strControllerName}' must contain "
                    . 'non-empty strings.'
                );
            }

            if (
                !in_array(
                    $strRoleName,
                    $arrDefinedRole,
                    true
                )
            ) {
                throw new UnexpectedValueException(
                    "Role '{$strRoleName}' in login policy for controller "
                    . "'{$strControllerName}' is not defined."
                );
            }

            if (isset($arrCheckedRole[$strRoleName])) {
                throw new UnexpectedValueException(
                    "Duplicate role '{$strRoleName}' in login policy "
                    . "for controller '{$strControllerName}'."
                );
            }

            $arrCheckedRole[$strRoleName] = true;
        }
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected function validateMaxFailCount(
        string $strControllerName,
        mixed $iMaxFailCount
    ): void {
        if (
            !is_int($iMaxFailCount)
            || $iMaxFailCount < 1
        ) {
            throw new UnexpectedValueException(
                "'max_fail_count' in login policy for controller "
                . "'{$strControllerName}' must be a positive integer."
            );
        }
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected function validateTurnstile(
        string $strControllerName,
        mixed $turnstile
    ): void {
        if (
            $turnstile === self::TURNSTILE_NEVER
            || $turnstile === self::TURNSTILE_ALWAYS
        ) {
            return;
        }

        if (
            is_int($turnstile)
            && $turnstile >= 1
        ) {
            return;
        }

        throw new UnexpectedValueException(
            "'turnstile' in login policy for controller "
            . "'{$strControllerName}' must be 'never', 'always', "
            . 'or a positive integer.'
        );
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected function validateRememberCookie(
        string $strControllerName,
        array $arrPolicy
    ): void {
        $rememberCookie = $arrPolicy['remember_cookie'];

        if (!is_bool($rememberCookie)) {
            throw new UnexpectedValueException(
                "'remember_cookie' in login policy for controller "
                . "'{$strControllerName}' must be boolean."
            );
        }

        /*
         * Không dùng remember cookie:
         * remember_expire được phép không khai báo hoặc bằng null.
         */
        if (!$rememberCookie) {
            if (
                array_key_exists('remember_expire', $arrPolicy)
                && $arrPolicy['remember_expire'] !== null
            ) {
                throw new UnexpectedValueException(
                    "'remember_expire' in login policy for controller "
                    . "'{$strControllerName}' must be null or omitted "
                    . "when 'remember_cookie' is false."
                );
            }

            return;
        }

        /*
         * Có dùng remember cookie:
         * remember_expire bắt buộc là số nguyên dương, tính bằng giây.
         */
        if (!array_key_exists('remember_expire', $arrPolicy)) {
            throw new UnexpectedValueException(
                "Missing field 'remember_expire' in login policy "
                . "for controller '{$strControllerName}' because "
                . "'remember_cookie' is true."
            );
        }

        if (
            !is_int($arrPolicy['remember_expire'])
            || $arrPolicy['remember_expire'] < 1
        ) {
            throw new UnexpectedValueException(
                "'remember_expire' in login policy for controller "
                . "'{$strControllerName}' must be a positive integer."
            );
        }
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected function validateDefaultRedirect(
        string $strControllerName,
        mixed $strDefaultRedirect
    ): void {
        if (
            !is_string($strDefaultRedirect)
            || $strDefaultRedirect === ''
        ) {
            throw new UnexpectedValueException(
                "'default_redirect' in login policy for controller "
                . "'{$strControllerName}' must be a non-empty string."
            );
        }

        /*
         * Chỉ cho phép đường dẫn nội bộ:
         *
         * /                       hợp lệ
         * /admin                  hợp lệ
         * /document/list          hợp lệ
         * https://example.com     không hợp lệ
         * //example.com           không hợp lệ
         */
        if (
            $strDefaultRedirect[0] !== '/'
            || str_starts_with($strDefaultRedirect, '//')
        ) {
            throw new UnexpectedValueException(
                "'default_redirect' in login policy for controller "
                . "'{$strControllerName}' must be a local absolute path."
            );
        }
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    public function hasLoginController(
        string $strControllerName
    ): bool {
        return array_key_exists(
            $strControllerName,
            $this->arrLoginPolicy
        );
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    public function setLoginControllerName(
        string $strLoginControllerName
    ): void {
        if (!$this->hasLoginController($strLoginControllerName)) {
            throw new InvalidArgumentException(
                "Controller '{$strLoginControllerName}' "
                . 'does not have a login policy.'
            );
        }

        $this->strLoginControllerName = $strLoginControllerName;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    public function getLoginControllerName(): ?string
    {
        return $this->strLoginControllerName;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    public function hasSelectedLoginController(): bool
    {
        return $this->strLoginControllerName !== null;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected function getCurrentPolicy(): array
    {
        if ($this->strLoginControllerName === null) {
            throw new LogicException(
                'Login controller has not been selected.'
            );
        }

        return $this->arrLoginPolicy[
            $this->strLoginControllerName
        ];
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    public function getRequiredRoles(): ?array
    {
        return $this->getCurrentPolicy()['required_roles'];
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    public function getMaxFailCount(): int
    {
        return $this->getCurrentPolicy()['max_fail_count'];
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    public function getTurnstileRule(): string|int
    {
        return $this->getCurrentPolicy()['turnstile'];
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    public function isTurnstileRequired(
        int $iFailCount
    ): bool {
        if ($iFailCount < 0) {
            throw new InvalidArgumentException(
                'Login fail count must not be negative.'
            );
        }

        $turnstileRule = $this->getTurnstileRule();

        if ($turnstileRule === self::TURNSTILE_NEVER) {
            return false;
        }

        if ($turnstileRule === self::TURNSTILE_ALWAYS) {
            return true;
        }

        return $iFailCount >= $turnstileRule;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    public function usesRememberCookie(): bool
    {
        return $this->getCurrentPolicy()['remember_cookie'];
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    public function getRememberExpire(): ?int
    {
        $arrPolicy = $this->getCurrentPolicy();

        return $arrPolicy['remember_expire'] ?? null;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    public function getDefaultRedirect(): string
    {
        return $this->getCurrentPolicy()['default_redirect'];
    }
}