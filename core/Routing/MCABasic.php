<?php
namespace Core\Routing;
/**
 * Cung cấp các API cơ bản để khai thác cấu trúc Module - Controller - Action.
 *
 * MCABasic không xử lý role, authentication hay request context.
 * $arrMCAR chỉ được sử dụng để xác định sự tồn tại của action.
 */
class MCABasic{
    protected array $arrM;
    protected array $arrMC;
    protected array $arrStC;
    protected array $arrMCAR;
    protected array $arrDefaultRoute;
    /*---------------------------------------------------------------------------------------------------------------*/
    public function __construct(
        array $arrM,
        array $arrMC,
        array $arrStC,
        array $arrMCAR,
        array $arrDefaultRoute
    ) {
        $this->arrM            = $arrM;
        $this->arrMC           = $arrMC;
        $this->arrStC          = $arrStC;
        $this->arrMCAR         = $arrMCAR;
        $this->arrDefaultRoute = $arrDefaultRoute;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public function moduleExists(string $module): bool {
        return in_array($module, $this->arrM, true);
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public function controllerExistsInModule(string $module, string $controller): bool {
        return isset($this->arrMC[$module])
        && in_array($controller, $this->arrMC[$module], true);
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public function standaloneControllerExists(string $controller): bool {
        return in_array($controller, $this->arrStC, true);
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public function actionExists(?string $module, string $controller, string $action): bool {
        if ($module !== null) {
            return isset($this->arrMCAR[$module][$controller][$action]);
        }

        return isset($this->arrMCAR[$controller][$action]);
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public function getDefaultModule(): ?string {
        return $this->arrDefaultRoute['default_entry']['type'] === 'module'
                ? $this->arrDefaultRoute['default_entry']['value']
                : null;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public function getDefaultControllerInModule(string $module): ?string {
        return array_key_first($this->arrDefaultRoute['routes'][$module] ?? []);
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public function getDefaultStandaloneController(): ?string {
        return $this->arrDefaultRoute['default_entry']['type'] === 'controller'
                ? $this->arrDefaultRoute['default_entry']['value']
                : null;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public function getDefaultAction(?string $module, string $controller): ?string {
        $routes = $this->arrDefaultRoute['routes'];

        if ($module !== null) {
            return $routes[$module][$controller] ?? null;
        }

        return $routes[$controller] ?? null;
    }
}