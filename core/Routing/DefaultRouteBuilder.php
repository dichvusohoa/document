<?php
namespace Core\Routing;
use UnexpectedValueException;
use Core\Utility\ValidUtility;
use Core\Utility\StringUtility;

class DefaultRouteBuilder {
    protected array $arrM;
    protected array $arrMC;
    protected array $arrStC;
    protected array $arrMCAR;

    static protected string $strFileName    = 'config.default.route.php';
    static protected string $strFileName2   = 'config.mc.php';
    static protected string $strFileName3 = 'config.mc2ra.php';
    static protected string $strFileName4 = 'config.fc.action.php';
    
    public function __construct(
        array $arrM,
        array $arrMC,
        array $arrStC,
        array $arrMCAR
    ) {
        $this->arrM    = $arrM;
        $this->arrMC   = $arrMC;
        $this->arrStC  = $arrStC;
        $this->arrMCAR = $arrMCAR;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public function build(): array {
        $arrConfig = $this->loadAndValidateConfig();

        return [
            'default_entry' => $this->normalizeDefaultEntry($arrConfig['default_entry']),
            'routes'        => $this->normalizeDefaultRoutes($arrConfig['routes']),
        ];
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected function loadAndValidateConfig(): array {
        $strFileName = self::$strFileName;
        $arrConfig = require CONFIG_PATH . '/' . $strFileName;
        if (
            !is_array($arrConfig)
            || !isset($arrConfig['default_entry'], $arrConfig['routes'])
            || !is_array($arrConfig['default_entry'])
            || !is_array($arrConfig['routes'])
            || !isset($arrConfig['default_entry']['type'], $arrConfig['default_entry']['value'])
            || !is_string($arrConfig['default_entry']['type'])
            || !is_string($arrConfig['default_entry']['value'])
        ) {
            throw new UnexpectedValueException(
                "File {$strFileName} có format không hợp lệ"
            );
        }
        return $arrConfig;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected function normalizeDefaultEntry(array $entry): array {
        $strFileName = self::$strFileName;
        $type = $entry['type'];
        $value = StringUtility::spacesToDash($entry['value']);

        if (!in_array($type, ['module', 'controller'], true)) {
            throw new UnexpectedValueException(
                "File {$strFileName}: default_entry.type phải là 'module' hoặc 'controller'"
            );
        }

        if ($type === 'module' && !in_array($value, $this->arrM, true)) {
            throw new UnexpectedValueException(
                "File {$strFileName}: default_entry.value '{$value}' không phải module hợp lệ"
            );
        }

        if ($type === 'controller' && !in_array($value, $this->arrStC, true)) {
            throw new UnexpectedValueException(
                "File {$strFileName}: default_entry.value '{$value}' không phải standalone controller hợp lệ"
            );
        }

        return [
            'type'  => $type,
            'value' => $value,
        ];
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected function normalizeDefaultRoutes(array $routes): array {
        $result = [];

        foreach ($routes as $entry => $routeValue) {
            $entry = StringUtility::spacesToDash($entry);

            if (is_string($routeValue)) {
                $result[$entry] = $this->normalizeStandaloneDefaultRoute($entry, $routeValue);
            } else {
                $result[$entry] = $this->normalizeModuleDefaultRoute($entry, $routeValue);
            }
        }

        return $result;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected function normalizeStandaloneDefaultRoute(string $controller, string $action): string {
        $strFileName = self::$strFileName;
        $strFileName2 = self::$strFileName2;
        $strFileName3 = self::$strFileName3;
        $strFileName4 = self::$strFileName4;
        
        $action = StringUtility::spacesToDash($action);

        if (!in_array($controller, $this->arrStC, true)) {
            throw new UnexpectedValueException(
                "File {$strFileName}: controller '{$controller}' không tồn tại file {$strFileName2}"
            );
        }

        if (!$this->routeActionExists(null, $controller, $action)) {
            throw new UnexpectedValueException(
                "File {$strFileName}: controller/action '{$controller}/{$action}' không tồn tại trong route metadata. Kiểm tra lại file '{$strFileName3}' và file '{$strFileName4}'"
            );
        }

        return $action;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected function normalizeModuleDefaultRoute(string $module, mixed $routeValue): array {
        $strFileName = self::$strFileName;
        $strFileName2 = self::$strFileName2;
        $strFileName3 = self::$strFileName3;
        $strFileName4 = self::$strFileName4;
        if (!in_array($module, $this->arrM, true)) {
            throw new UnexpectedValueException(
                "File {$strFileName}: module '{$module}' không tồn tại trong file {$strFileName2}"
            );
        }

        if (!ValidUtility::isStringPairMap($routeValue) || count($routeValue) !== 1) {
            throw new UnexpectedValueException(
                "File {$strFileName}: default route của module '{$module}' phải có format ['controller' => 'action']"
            );
        }

        $rawController = array_key_first($routeValue);
        $controller = StringUtility::spacesToDash($rawController);
        $action = StringUtility::spacesToDash($routeValue[$rawController]);

        if (!isset($this->arrMC[$module]) || !in_array($controller, $this->arrMC[$module], true)) {
            throw new UnexpectedValueException(
                "File {$strFileName}: module/controller '{$module}/{$controller}' không tồn tại trong file {$strFileName2}"
            );
        }
        
        if (!$this->routeActionExists($module, $controller, $action)) {
            throw new UnexpectedValueException(
                "File {$strFileName}: module/controller/action  '{$module}/{$controller}/{$action}' không tồn tại trong route metadata. Kiểm tra lại file '{$strFileName3}' và file '{$strFileName4}'"
            );
        }

        return [$controller => $action];
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected function routeActionExists(?string $module, string $controller, string $action): bool {
        if ($module !== null) {
            return isset($this->arrMCAR[$module][$controller][$action]);
        }

        return isset($this->arrMCAR[$controller][$action]);
    }
}