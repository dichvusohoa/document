<?php
namespace Core\Routing;
use InvalidArgumentException;

class MCRoutePathParser {
    protected array $arrM;
    protected array $arrMC;
    protected array $arrStC;
    static protected string $strFileName = 'config.mc.php';
    public function __construct(array $arrM, array $arrMC, array $arrStC) {
        $this->arrM   = $arrM;
        $this->arrMC  = $arrMC;
        $this->arrStC = $arrStC;
    }
    /*$strMRoutePath có format dạng 'module/controller' hoặc 'controller' (khuyết phần module)
     * Trong đó phần module có format như sau
     * - tên một module đơn như là 'nutrition'
     * - biểu thức có format như sau '[module:*]' , '[module:nutrion|pupil]' , '[module:!nutrion]'
     * Return: 
     * [[module_a, controller_x], [module_b, controller_y], ...] nếu như có module
     * [[controller_x],[controller_y]...] nếu khuyết không có tên module
     */
    public function parse(string $strMCRoutePath): array {
        $strMCRoutePath = trim($strMCRoutePath);
        $slashCount = substr_count($strMCRoutePath, '/');

        if ($slashCount > 1) {
            throw new InvalidArgumentException(
                "Invalid MCRoutePath format: too many slashes in '{$strMCRoutePath}'"
            );
        }

        if ($slashCount === 1) {
            return $this->parseModuleControllerRoutePath($strMCRoutePath);
        }

        return $this->parseStandaloneControllerRoutePath($strMCRoutePath);
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected function parseModuleControllerRoutePath(string $strMCRoutePath): array {
        [$strModuleExpr, $strControllerExpr] = explode('/', $strMCRoutePath, 2);

        if ($strModuleExpr === '' || $strControllerExpr === '') {
            throw new InvalidArgumentException("Invalid MCRoutePath format '{$strMCRoutePath}'");
        }

        $modules = $this->parseModuleExpr($strModuleExpr);
        $result = [];

        foreach ($modules as $strModule) {
            $controllers = $this->parseControllerExprForModule($strControllerExpr, $strModule);

            foreach ($controllers as $strController) {
                $result[] = [$strModule, $strController];
            }
        }

        return $result;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected function parseModuleExpr(string $strModuleExpr): array {
        $arrParse = RoutePattern::parse(
            $strModuleExpr,
            $this->arrM
        );

        if ($arrParse['type'] !== '' && $arrParse['type'] !== 'module') {
            throw new InvalidArgumentException(
                "Biểu thức {$strModuleExpr} phải có type là module, chứ không được là {$arrParse['type']}"
            );
        }

        return $arrParse['values'];
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected function parseControllerExprForModule(string $strControllerExpr, string $strModule): array {
        $strFileName = self::$strFileName;
        if (!isset($this->arrMC[$strModule])) {
            throw new InvalidArgumentException(
                "File {$strFileName}: module '{$strModule}' không tồn tại"
            );
    
        }
        $arrParse = RoutePattern::parse(
            $strControllerExpr,
            $this->arrMC[$strModule]
        );

        if ($arrParse['type'] !== '' && $arrParse['type'] !== 'controller') {
            throw new InvalidArgumentException(
                "Biểu thức {$strControllerExpr} phải có type là controller, chứ không được là {$arrParse['type']}"
            );
        }

        return $arrParse['values'];
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected function parseStandaloneControllerRoutePath(string $strControllerExpr): array {
        $arrParse = RoutePattern::parse(
            $strControllerExpr,
            $this->arrStC
        );

        if ($arrParse['type'] !== '' && $arrParse['type'] !== 'controller') {
            throw new InvalidArgumentException(
                "Biểu thức {$strControllerExpr} phải có type là controller, chứ không được là {$arrParse['type']}"
            );
        }

        return array_map(
            fn(string $strController) => [$strController],
            $arrParse['values']
        );
    }
}