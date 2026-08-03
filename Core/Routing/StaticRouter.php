<?php
namespace Core\Routing;
use UnexpectedValueException;
use Core\Utility\ValidUtility;
use Core\Utility\StringUtility;
class StaticRouter {
    //Đầu vào - load từ file
    protected array $arrM; //M = module name. load từ file /config/config.mc.php
    protected array $arrMC; //C = danh sách các controller phụ thuộc vào module. tạo từ file /config/config.mc.php
    protected array $arrStC; //StC = standalone controller: danh sách các controller độc lập không có module, tạo từ file /config/config.mc.php
    protected array $arrR; //R = Role, danh sách tất cả các role. load từ file /config/config.role.php
    protected array $arrAuthRegistry; //load từ file /config/config.login.php
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
        // 2. Load các config nền còn lại
        $this->buildRole();
        $authRegistry = new AuthRegistry($this->arrStC, $this->arrR);
        $this->arrAuthRegistry = $authRegistry->getAuthRegistry();
        $this->buildFCAction();
        // 3. buildMiddleware. Có arrM  tạo parser
        $parser = new RouteSegmentPatternParser($this->arrM);
        $this->buildMiddleware($parser); 
        // 4. buildMC2FQCN, có arrM,arrMC,arrStC rồi mới tạo được  $parserMC 
        $parserMC = new MCRoutePathParser(
            $this->arrM,
            $this->arrMC,
            $this->arrStC
        );
        $this->buildMC2FQCN($parserMC);//
        // 5. Build MCAR, dùng $parserMC
        $mcarBuilder = new MCARBuilder(
            $parserMC,
            $this->arrMC2FQCN,
            $this->arrFCAction,
            array_keys($this->arrR),
            $authRegistry
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
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public function getStandaloneControllers(): array{
        return $this->arrStC;
    }
    /*--------------------------------------------------------------------------------------------------------------*/
    public function getRoles(): array{
        return $this->arrR;
    }
    /*--------------------------------------------------------------------------------------------------------------*/
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
        $arrTmp = require CONFIG_PATH . '/' . $strFileName;
        if (!is_array($arrTmp)) {
            throw new UnexpectedValueException(
                "File {$strFileName} phải trả về một mảng."
            );
        }
        foreach ($arrTmp as $strRole => $arrRoleInfo) {
            if (!is_string($strRole) || trim($strRole) === '') {
                throw new UnexpectedValueException(
                    "Tên role trong {$strFileName} phải là chuỗi không rỗng."
                );
            }

            if (!is_array($arrRoleInfo)) {
                throw new UnexpectedValueException(
                    "Role '{$strRole}' trong {$strFileName} phải là một mảng."
                );
            }
            ValidUtility::validateNoUnexpectedFields(
                $arrRoleInfo,
                ['display_name', 'weight'],
                "Role '{$strRole}'"
            );
            ValidUtility::validateRequiredNonEmptyStringField(
                $arrRoleInfo,
                'display_name',
                "Role '{$strRole}'"
            );
            ValidUtility::validateRequiredField(
                $arrRoleInfo,
                'weight',
                "Role '{$strRole}'"
            );

            if (!is_int($arrRoleInfo['weight']) || $arrRoleInfo['weight'] < 0) {
                throw new UnexpectedValueException(
                    "Role '{$strRole}': field 'weight' phải là số nguyên không âm."
                );
            }
        }

        // Framework bắt buộc phải có role guest
        if (!isset($arrTmp['guest'])) {
            throw new UnexpectedValueException(
                "File {$strFileName} phải định nghĩa role 'guest'."
            );
        }
        if ($arrTmp['guest']['weight'] !== 0) {
            throw new UnexpectedValueException(
                "Role 'guest' phải có weight = 0."
            );
        }
        $this->arrR = $arrTmp;
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
    protected function buildMiddleware(RouteSegmentPatternParser $parser){
        $this->arrMiddlewareParsed = [];
        $strFileName = 'config.middleware.php';
        $arrTmp = require CONFIG_PATH.'/'.$strFileName;
        if(!ValidUtility::isStringListMap($arrTmp, false)){
            throw new UnexpectedValueException("File {$strFileName} có format không phù hợp"); 
        }
        foreach ($arrTmp as $routePath => $fqcn) {
            if(is_string($fqcn)){
                $fqcn = [$fqcn];
            }
            foreach ($fqcn as $strFQCN){
                if (!class_exists($strFQCN)) {
                    throw new UnexpectedValueException("File {$strFileName}: class middleware '{$strFQCN}' không tồn tại");
                }
            }
            $expr = $parser->parse($routePath);
            /*Sử dụng kỹ thuật đổi hướng báo lỗi - Tạm thời chưa dùng, chưa thấy ích lợi rõ ràng gì
            Sử dụng try/catch để báo lỗi có ngữ cảnh rõ ràng hơn. Nếu không có try/catch
            báo lỗi sẽ do Core\Routing\RouterPattern tung ra
            try {
                 $expr = $parser->parse($routePath);
            } catch (InvalidArgumentException $e) {
                throw new UnexpectedValueException(
                    "File {$strFileName}: route path middleware '{$routePath}' không hợp lệ", 0, $e
                );
            }*/
            $this->arrMiddlewareParsed[] = [
                //'expr' chuyển định dạng biểu thức của $strRoutePath ra dạng 
                //array['module'=> strExprModule, 'controller' => strExprController, 'action' => strExprAction 'method' => strExprMethod ,'role' => strExprRole
                'expr' => $expr,
                'fqcn' => $fqcn// mode aray
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
            $arrMC = $parser->parse($strRouteMCPath);
            foreach ($arrMC as $value) {
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
                //đề phòng phần tử của $arrMC không có dạng [module, controller] hoặc [controller]
                throw new UnexpectedValueException(
                    "File {$strFileName}: route '{$strRouteMCPath}' parse ra cấu trúc không hợp lệ"
                );

            }
        }
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
    /*$arrSegment có format 
     [
        'module' => strModuleValue,
        'controller' => strControllerValue,
        'action' => strActionValue,
        'method' => strMethodValue,
        'role'=> roles . Chú ý là roles có thể là single value hoặc là 1 array
    ]*/
    public function matchMiddlewares(array $arrSegment): array {
        $result = [];
        foreach ($this->arrMiddlewareParsed as $element) {
            if (!RouteSegmentPatternParser::match($element['expr'], $arrSegment)) {
                //$result[] = $element['fqcn'];
                continue;
            }
            foreach ($element['fqcn'] as $strFQCN) {
                // dùng $strFQCN làm key để lọc các $strFQCN trùng, tránh chạy một middleware 2 lần
                $result[$strFQCN] = true; 
            }
        }
        return array_keys($result);
        //return $result;
    } 
    /*---------------------------------------------------------------------------------------------------------------*/
    /*phục vụ cho cache StaticRouter*/
    public function toArray(): array {
        return[
            'arrM' => $this->arrM,
            'arrMC' => $this->arrMC,
            'arrStC' => $this->arrStC,
            'arrR' => $this->arrR,
            'arrAuthRegistry' => $this->arrAuthRegistry,
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
    /*---------------------------------------------------------------------------------------------------------------*/
    public function createAuthRegistry(): AuthRegistry {
        return AuthRegistry::fromArray(
            $this->arrAuthRegistry
        );
    }
}