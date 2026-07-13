<?php
namespace Core\Routing;
use UnexpectedValueException;
use InvalidArgumentException;
use Core\Utility\ValidUtility;
use Core\Utility\StringUtility;
class StaticRouter {
    //Đầu vào - load từ file
    protected array $arrM; //M = module name. load từ file /config/config.mc.php
    protected array $arrMC; //C = danh sách các controller phụ thuộc vào module. tạo từ file /config/config.mc.php
    protected array $arrStC; //StC = standalone controller: danh sách các controller độc lập không có module, tạo từ file /config/config.mc.php
    protected array $arrR; //R = Role, danh sách tất cả các role. load từ file /config/config.role.php
    //MC2FQCN = module-controller-FQCN (fully qualified class name). 
    //xây dựng  từ file /config/config.mc2fc.php. 
    protected array $arrMC2FQCN; 
    
    //FCQNA2F = FCQN (fully qualified class name)+ A (action) => Function
    //load từ file /config/config.fc.action.php
    protected array $arrFCAction;
    protected array $arrMiddlewareParsed; //load từ file config.middleware.php và phân tích

    //kết quả cần tính ra  array
    // $arrMCAR: cấu trúc gốm các phần từ [strModule(có thể thiếu)][strController][strAction]=>route info
    // đây là cấu trúc cho tất cả mọi user
    protected array $arrMCAR; //build từ config.mc2ra.php.  Đây là khối dữ liệu lớn nhất
    protected array $arrDefaultRoute; //build từ config.default.route
    /*---------------------------------------------------------------------------------------------------------------*/
    function __construct(){
         // 1. Load metadata nền
        $this->buildModuleController();
        // 2. Có arrM/arrMC/arrStC rồi mới tạo parser
        $parser = new MCRoutePathParser(
            $this->arrM,
            $this->arrMC,
            $this->arrStC
        );
         // 3. Load các config nền còn lại
        $this->buildRole();
        $this->buildFCAction();
        $this->buildMiddleware(); 
        // 4. Build MC -> FQCN, có dùng parser  
        $this->buildMC2FQCN($parser);//
        
        // 5. Build MCAR
        $mcarBuilder = new MCARBuilder(
            $parser,
            $this->arrMC2FQCN,
            $this->arrFCAction,
            $this->arrR
        );
        $this->arrMCAR = $mcarBuilder->build();
        //$this->buildMCAR();bỏ
        //6. Build defaultRoute
        $defaultRouteBuilder = new DefaultRouteBuilder(
            $this->arrM,
            $this->arrMC,
            $this->arrStC,
            $this->arrMCAR
        );
        $this->arrDefaultRoute = $defaultRouteBuilder->build();
        //$this->buildDefaultRoute();
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public function getMiddleware() {
        return $this->arrMiddlewareParsed;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public function getRouteInfo(array $path): ?array {
        $data = $this->arrMCAR;
        foreach ($path as $key) {
            if (!isset($data[$key])) {
                return null;
            }
            $data = $data[$key];
        }
        return $data;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public function getDefaultRoute(){
        return $this->arrDefaultRoute;
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
    /*---------------------------------------------------------------------------------------------------------------*/
    protected function buildModuleController(): void {
        $strFileName = 'config.mc.php';
        $arTmp = require CONFIG_PATH .'/'. $strFileName;
        $isValid = is_array($arTmp)
            && isset($arTmp['module-controllers'], $arTmp['standalone-controllers'])
            && ValidUtility::isStringListMap($arTmp['module-controllers'])
            && ValidUtility::isStringList($arTmp['standalone-controllers']);

        if (!$isValid) {
            throw new UnexpectedValueException("File {$strFileName} có format không đúng");
        }
        $this->arrMC = [];
        $this->arrM = [];
        foreach ($arTmp['module-controllers'] as $strModule => $arrController) {
            $normalizedModule = StringUtility::spacesToDash($strModule);

            if (isset($this->arrMC[$normalizedModule])) {
                throw new UnexpectedValueException(
                    "File {$strFileName}: module '{$normalizedModule}' bị trùng sau khi chuẩn hóa tên."
                );
            }
            $normalizedControllers = array_map(
                [StringUtility::class, 'spacesToDash'],
                $arrController
            );
            if (count($normalizedControllers) !== count(array_unique($normalizedControllers))) {
                throw new UnexpectedValueException(
                    "File {$strFileName}: module '{$normalizedModule}' có controller bị trùng sau khi chuẩn hóa tên."
                );
            }
            $this->arrM[] = $normalizedModule;
            $this->arrMC[$normalizedModule] = $normalizedControllers;
        }
        $this->arrStC = array_map(
            [StringUtility::class, 'spacesToDash'],
            $arTmp['standalone-controllers']
        );
        if (count($this->arrStC) !== count(array_unique($this->arrStC))) {
            throw new UnexpectedValueException(
                "File {$strFileName}: standalone-controllers có controller bị trùng sau khi chuẩn hóa tên."
            );
        }
        $conflicted = array_intersect($this->arrM, $this->arrStC);
        if (!empty($conflicted)) {
            throw new UnexpectedValueException(
                "File {$strFileName}: tên standalone controller bị trùng với module sau khi chuẩn hóa: "
                . implode(', ', $conflicted)
            );
        }
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected function buildRole(){
        $strFileName = 'config.role.php';
        $arTmp =  require CONFIG_PATH.'/'. $strFileName;
        if(!ValidUtility::isStringPairMap($arTmp)){
            throw new UnexpectedValueException("File {$strFileName} phải trả về kết quả là một mảng string"); 
        }
        $this->arrR = array_keys($arTmp);
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected function buildFCAction(){
        $strFileName = 'config.fc.action.php';
        $arrConfig =  require CONFIG_PATH.'/'.$strFileName;
        if (!is_array($arrConfig)) {
            throw new UnexpectedValueException("File {$strFileName} phải return một array");
        }
        foreach ($arrConfig as $strClass => $arrAction){
            // 1. Kiểm tra FQCN
            if (!is_string($strClass)) {
                throw new UnexpectedValueException("File {$strFileName}: key '$strClass' phải là string");
            }
            if (!class_exists($strClass)) {
                throw new UnexpectedValueException("File {$strFileName}: class '$strClass' không tồn tại");
            }
            if (!is_array($arrAction)) {
                throw new UnexpectedValueException("File {$strFileName}: value tại key '{$strClass}' phải là array");
            }
            foreach ($arrAction as $strActionName => $arrActionDetail){
                if (!is_string($strActionName)) {
                    throw new UnexpectedValueException("File {$strFileName}: action '$strActionName' tại key '$strClass' phải là string");
                }
                if(!ValidUtility::isStringPairMap($arrActionDetail)){
                    throw new UnexpectedValueException("File {$strFileName}: value tại key '$strClass, $strActionName' phải là một mảng string"); 
                }
            }
        }
        $this->arrFCAction = $arrConfig;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected function buildMiddleware(){
        $this->arrMiddlewareParsed = [];
        $strFileName = 'config.middleware.php';
        $arrTmp = require CONFIG_PATH.'/'.$strFileName;
        if(!ValidUtility::isStringPairMap($arrTmp)){
            throw new UnexpectedValueException("File {$strFileName} phải trả về kết quả là một mảng string"); 
        }
        //$this->arrMiddleware = $arrTmp;
        foreach ($arrTmp as $routePath => $fqcn) {
            if (!class_exists($fqcn)) {
                throw new UnexpectedValueException("File {$strFileName}: class middleware '{$fqcn}' không tồn tại");
            }
            try {
                $expr = RoutePatternList::buildFromRoutePath($routePath);//$expr dạng array
            } catch (InvalidArgumentException) {
                throw new UnexpectedValueException(
                    "File {$strFileName}: route path middleware '{$routePath}' không hợp lệ"
                );
            }
            $this->arrMiddlewareParsed[] = [
                //'expr' chuyển định dạng biểu thức của $strRoutePath ra dạng 
                //array['module'=> strExprModule, 'controller' => strExprController, 'action' => strExprAction 'method' => strExprMethod ,'role' => strExprRole
                'expr' => $expr,
                'fqcn' => $fqcn
            ];
        }
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    //arrMC2FQCN được xây dựng là [module][controller] => $strFQCN
   protected function buildMC2FQCN(MCRoutePathParser $parser): void {
        $strFileName = 'config.mc2fc.php';
        $strFileName2 = 'config.mc.php';
        $arrConfig = require CONFIG_PATH.'/'.$strFileName;

        if (!is_array($arrConfig)) {
            throw new UnexpectedValueException(
                "File {$strFileName} phải return một array"
            );
        }

        $this->arrMC2FQCN = [];

        foreach ($arrConfig as $strRouteMCPath => $strFQCN) {
            if (!is_string($strRouteMCPath) || trim($strRouteMCPath) === '') {
                throw new UnexpectedValueException(
                    "File {$strFileName}: route path phải là string không rỗng"
                );
            }
            if (!is_string($strFQCN) || trim($strFQCN) === '') {
                throw new UnexpectedValueException(
                    "File {$strFileName}: value tại route '{$strRouteMCPath}' phải là FQCN string không rỗng"
                );
            }

            if (!class_exists($strFQCN)) {
                throw new UnexpectedValueException(
                    "File {$strFileName}: class '{$strFQCN}' tại route '{$strRouteMCPath}' không tồn tại"
                );
            }

            try {
                //Sử dụng try/catch để báo lỗi có ngữ cảnh rõ ràng hơn. Nếu không có try/catch
                //báo lỗi sẽ do Core\Routing\RouterPattern tung ra
                //$arrMC = $this->parseMCRoutePath($strRouteMCPath);
                $arrMC = $parser->parse($strRouteMCPath);
            } catch (InvalidArgumentException) {
                throw new UnexpectedValueException(
                    "File {$strFileName}: route path '{$strRouteMCPath}' không hợp lệ hoặc không tương thích với {$strFileName2}. "
                );
            }

            foreach ($arrMC as $value) {
                $this->validateMC2FQCNRouteCompatibility($value, $strRouteMCPath);
                if (count($value) === 2) {
                    [$strModule, $strController] = $value;

                    if (isset($this->arrMC2FQCN[$strModule][$strController])) {
                        throw new UnexpectedValueException(
                            "File {$strFileName}: route '{$strModule}/{$strController}' bị khai báo trùng"
                        );
                    }

                    $this->arrMC2FQCN[$strModule][$strController] = $strFQCN;
                    continue;
                }

                if (count($value) === 1) {
                    [$strController] = $value;

                    if (isset($this->arrMC2FQCN[$strController])) {
                        throw new UnexpectedValueException(
                            "File {$strFileName}: route '{$strController}' bị khai báo trùng"
                        );
                    }

                    $this->arrMC2FQCN[$strController] = $strFQCN;
                    continue;
                }

            }
        }
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected function validateMC2FQCNRouteCompatibility(array $route, string $strRouteMCPath): void {
        $strFileName = 'config.mc2fc.php';
        $strFileName2 = 'config.mc.php';

        if (count($route) === 2) {
            [$module, $controller] = $route;

            if (!$this->moduleExists($module)) {
                throw new UnexpectedValueException(
                    "File {$strFileName}: module '{$module}' trong '{$strRouteMCPath}' không tồn tại trong {$strFileName2}"
                );
            }

            if (!$this->controllerExistsInModule($module, $controller)) {
                throw new UnexpectedValueException(
                    "File {$strFileName}: controller '{$controller}' không thuộc module '{$module}' trong {$strFileName2}"
                );
            }

            return;
        }

        if (count($route) === 1) {
            [$controller] = $route;

            if (!$this->standaloneControllerExists($controller)) {
                throw new UnexpectedValueException(
                    "File {$strFileName}: standalone controller '{$controller}' không tồn tại trong {$strFileName2}"
                );
            }

            return;
        }

        throw new UnexpectedValueException(
            "File {$strFileName}: route '{$strRouteMCPath}' parse ra cấu trúc không hợp lệ"
        );
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
    /*phục vụ cho cache StaticRouter*/
    public function toArray(): array {
        return[
            'arrM' => $this->arrM,
            'arrMC' => $this->arrMC,
            'arrStC' => $this->arrStC,
            'arrR' => $this->arrR,
            'arrMC2FQCN' => $this->arrMC2FQCN,
            'arrFCAction' => $this->arrFCAction,
            'arrMiddlewareParsed' => $this->arrMiddlewareParsed,
            'arrMCAR' => $this->arrMCAR,
            'arrDefaultRoute' => $this->arrDefaultRoute
        ];
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public static function fromArray(array $data): self {
        $ref = new \ReflectionClass(self::class);
        $obj = $ref->newInstanceWithoutConstructor();

        foreach ($data as $key => $value) {
            if (property_exists($obj, $key)) {
                $obj->$key = $value;
            }
        }

        return $obj;
    }
}