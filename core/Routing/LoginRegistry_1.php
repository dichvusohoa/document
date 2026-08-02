<?php
namespace Core\Routing;
use Core\Utility\StringUtility;
use Core\Utility\ValidUtility;
use UnexpectedValueException;

class LoginRegistry
{
    public const TURNSTILE_NEVER  = 'never';
    public const TURNSTILE_ALWAYS = 'always';

    protected const CONFIG_FIELDS = [
        'accepted_roles_pattern',
        'max_fail_count',
        'turnstile',
        'remember_cookie',
        'remember_expire',
        'default_redirect'
    ];

    protected const REQUIRED_CONFIG_FIELDS = [
        'accepted_roles_pattern',
        'max_fail_count',
        'turnstile',
        'remember_cookie',
        'default_redirect'
    ];
    /*
     * controller name => registry
     */
    protected array $arrLoginRegistry;
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

            $arrLoginRegistry[$strControllerName] = $this->buildRegistry(
                $strControllerName,
                $mixRegistry,
                $arrDefinedRole
            );
        }
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
    protected function buildRegistry(
        string $strControllerName,
        mixed $mixRegistry,
        array $arrDefinedRole
    ): array {
        $strContext = "Login registry của controller '{$strControllerName}'";

        if (!is_array($mixRegistry)) {
            throw new UnexpectedValueException(
                "{$strContext} phải là array; nhận được "
                . get_debug_type($mixRegistry)
                . '.'
            );
        }
        //đảm bảo trong $mixRegistry không có key lạ ra ngoài self::CONFIG_FIELDS
        ValidUtility::validateNoUnexpectedFields(
            $mixRegistry,
            self::CONFIG_FIELDS,
            $strContext
        );
        //đảm bảo các key bắt buộc trong self::REQUIRED_CONFIG_FIELDS thì $mixRegistry đều phải có
        foreach (self::REQUIRED_CONFIG_FIELDS as $strFieldName) {
            ValidUtility::validateRequiredField(
                $mixRegistry,
                $strFieldName,
                $strContext
            );
        }

        $mixRegistry = $this->calcAcceptedRoles(
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
        $mixRegistry = $this->calcWeights(
            $mixRegistry,
            $arrDefinedRole,
            $strContext
        );
        return $mixRegistry;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected function calcAcceptedRoles(
        array $arrRegistry,
        array $arrDefinedRole,
        string $strContext
    ): array {
        $mixRequiredRole = $arrRegistry['accepted_roles_pattern'];
        
        $arrRoleParse = RoutePattern::parse(
            $mixRequiredRole,
            array_keys($arrDefinedRole)
        );
        if ($arrRoleParse['type'] !== '' && $arrRoleParse['type'] !== 'role') {
            throw new UnexpectedValueException(
                "{$strContext} field 'required_roles' phải có type là role, chứ không được là {$arrRoleParse['type']}"
            );
        }
        if (empty($arrRoleParse['values'])){
            throw new UnexpectedValueException(
                "{$strContext} field 'required_roles' không được trả về giá trị mảng rỗng"
            );
        }
        $arrRegistry['accepted_roles'] = $arrRoleParse['values'];
        unset($arrRegistry['accepted_roles_pattern']);
        return $arrRegistry;
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
    protected function calcWeights(
            array  $arrRegistry,
            array $arrDefinedRole,
            string $strContext
        ): array{
        
        
        $iMaxRoleWeight = 0;
        foreach ($arrRegistry['accepted_roles'] as $strRole) {
            $iRoleWeight = $arrDefinedRole[$strRole]['weight'];
            if ($iRoleWeight > $iMaxRoleWeight) {
                $iMaxRoleWeight = $iRoleWeight;
            }
        }
        $arrRegistry['weights'] = [
            'max_role_weight' => $iMaxRoleWeight,
            'accepted_role_count' => count($arrRegistry['accepted_roles'])    
                ];
        return $arrRegistry;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public function getLoginRegistry(): array{
        return $this->arrLoginRegistry;
    }
}