<?php
namespace Core\Routing;
use \UnexpectedValueException;
use \InvalidArgumentException;
use Core\Utility\ValidUtility;
use Core\Utility\StringUtility;
use Core\Utility\MathUtility;
class StaticRouter {
    //Đầu vào - load từ file
    protected array $arrM; //M = module name. load từ file /config/list.mc.php
    protected array $arrMC; //C = danh sách các controller phụ thuộc vào module. load từ file /config/list.mc.php
    protected array $arrStC; //StC = standalone controller: danh sách các controller độc lập không có module
    protected array $arrR; //R = Role, danh sách tất cả các role. load từ file /config/list.role.php
    //MC2FQCN = module-controller-FQCN (fully qualified class name). 
    //xây dựng  từ file /config/config.mc2fc.php. 
    protected array $arrMC2FQCN; 
    
    //FCQNA2F = FCQN (fully qualified class name)+ A (action) => Function
    //load từ file /config/config.fca2f
    protected array $arrFCQNA2F;
    protected array $arrMiddlewareParsed; //load từ file middleware.route.php và phân tích


    //kết quả cần tính ra  array
    // $arrMCAR: cấu trúc gốm các phần từ [strType][strModule(có thể thiếu)][strController][strAction]=>
    // ['roles' => ..., 'fqcn' =>..., 'html_schema' => ...,'function' => ..., 'method' => ...]
    // đây là cấu trúc cho tất cả mọi user
    protected array $arrMCAR; //build từ config.mcr2a.php.  Đây là khối dữ liệu lớn nhất
    protected array $arrDefaultRoute; //build từ config.default.route
    
    /*---------------------------------------------------------------------------------------------------------------*/
    function __construct(){
        $this->buildModuleController();
        $this->buildRole();
        $this->buildFCA2F();
        $this->buildMiddleware(); 
        $this->buildMC2FQCN();//
        $this->buildMCAR();
        $this->buildDefaultRoute();
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public function getModule() {
        return $this->arrM;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public function getMC() {
        return $this->arrMC;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public function getStC() {
        return $this->arrStC;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public function getRole() {
        return $this->arrR;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public function getMC2FQCN() {
        return $this->arrMC2FQCN;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public function getFCQNA2F() {
        return $this->arrFCQNA2F;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public function getMiddleware() {
        return $this->arrMiddlewareParsed;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public function getMCAR() {
        return $this->arrMCAR;
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
        return array_key_first($this->arrDefaultRoute[$module] ?? []);
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
        $strFileName = 'list.mc.php';
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
        $strFileName = 'list.role.php';
        $arTmp =  require CONFIG_PATH.'/'. $strFileName;
        if(!ValidUtility::isStringPairMap($arTmp)){
            throw new UnexpectedValueException("File {$strFileName} phải trả về kết quả là một mảng string"); 
        }
        $this->arrR = array_keys($arTmp);
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected function buildFCA2F(){
        $strFileName = 'config.fca2f.php';
        $arrConfig =  require CONFIG_PATH.'/'.$strFileName;
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
        $this->arrFCQNA2F = $arrConfig;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected function buildMiddleware(){
        $this->arrMiddlewareParsed = [];
        $strFileName = 'middleware.route.php';
        $arrTmp = require CONFIG_PATH.'/'.$strFileName;
        if(!ValidUtility::isStringPairMap($arrTmp)){
            throw new UnexpectedValueException("File {$strFileName} phải trả về kết quả là một mảng string"); 
        }
        //$this->arrMiddleware = $arrTmp;
        foreach ($arrTmp as $routePath => $fqcn) {
            if (!class_exists($fqcn)) {
                throw new UnexpectedValueException("File {$strFileName}: class middleware '{$fqcn}' không tồn tại");
            }
            $this->arrMiddlewareParsed[] = [
                //'expr' chuyển định dạng biểu thức của $strRoutePath ra dạng 
                //array['fctype'=> strExprFcTyoe,'module'=> strExprModule, 'controller' => strExprController, 'action' => strExprAction 'method' => strExprMethod ,'role' => strExprRole
                'expr' => RoutePatternList::buildFromRoutePath($routePath),
                'fqcn' => $fqcn
            ];
        }
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    //arrMC2FQCN được xây dựng là [module][controller] => $strFQCN
    //nó không xây dựng đường dẫn kiểu [type][module][controller] vì lý do còn vường html_schema
    protected function buildMC2FQCN(): void {
        $strFileName = 'config.mc2fc.php';
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

            $this->validateControllerFQCN($strFQCN, $strRouteMCPath);

            $arrMCProduct = $this->parseMCRoutePath($strRouteMCPath);

            foreach ($arrMCProduct as $value) {
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

                throw new UnexpectedValueException(
                    "File {$strFileName}: route path '{$strRouteMCPath}' parse ra kết quả không hợp lệ"
                );
            }
        }
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected function validateControllerFQCN(mixed $strFQCN, string $strRouteMCPath): void {
        $strFileName = 'config.mc2fc.php';
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
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected function buildMCAR(){
        $strFileName = 'config.mcr2a.php';
        $arrTmp = [];
        $arrData = require CONFIG_PATH . '/' . $strFileName;
        if (!is_array($arrData)) {
            throw new UnexpectedValueException("File {$strFileName} phải return một array");
        }
        foreach ($arrData as $strExprMC => $arrExprRA) {
            if (!is_string($strExprMC)) {
                throw new UnexpectedValueException(
                    "File {$strFileName}: thành phần module/controller phải là string"
                );
            }
            $arrMC = $this->parseMCRoutePath($strExprMC);
            $arrTmp = $this->buildMCARForOneRule(
               
                $arrTmp, //đây là dữ liệu cũ ở step trước và đã chứa module, controller
                $arrMC,
                $arrExprRA
            );
        }
       
        $this->arrMCAR = $arrTmp;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected function buildMCARForOneRule(
   
        array $arrTree, //đây là dữ liệu cũ ở step trước và đã chứa module, controller, không có type
        array $arrMC,
        array $arrExprRA): array {
        foreach ($arrMC as $pairMC) {
            $arrTree = $this->buildMCARForOnePairMC(
               
                $arrTree, //đây là dữ liệu cũ ở step trước và đã chứa module, controller, không có type
                $pairMC,
                $arrExprRA
            );
        }
        return $arrTree;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected function buildMCARForOnePairMC(
    
        array $arrTree,//đây là dữ liệu cũ ở step trước và đã chứa module, controller, không có type (1/2 dữ liệu theo file)
        array $pairMC,
        array $arrExprRA
    ): array {
       
        $str = $pairMC[0]; // module hoặc controller (nếu khuyết module)
        if(!isset($this->arrMC2FQCN[$str])){ ///config/config.mc2fc.php
            throw new \RuntimeException("Hai file config không tương thích. File config.mcr2a có đường dẫn {$pairMC[0]} nhưng không có đường dẫn {$pairMC[0]} trong file config.mc2fc");
        }
        $arrTree[$str] ??= [];
        $refTree = $arrTree[$str];//đi sâu vào một mức
        if (count($pairMC) === 2) { //$pairMC đủ cả module và controller
            $strController = $pairMC[1];
            $refTree[$strController] ??= [];
          
            if(!isset($this->arrMC2FQCN[$str][$strController])){
                throw new \RuntimeException("Hai file config không tương thích. File config.mcr2a có đường dẫn {$str}/{$strController} nhưng không có đường dẫn {$str}/{$strController} trong file config.mc2fc");
            }
            $strFCQN = $this->arrMC2FQCN[$str][$strController];
            //$strHtmlSchema = $strType === 'html_class' ? $arrFCQN['html_schema'] : null;
            $refTree[$strController] = $this->buildMCARAtRALevel(
                $strFCQN,
                $arrExprRA,
                $refTree[$strController]
            );
        } else {
            $strFCQN = $this->arrMC2FQCN[$str];
            $refTree = $this->buildMCARAtRALevel(
                //$arrFCQN[$strType],
                $strFCQN,    
                //$strHtmlSchema,    
                $arrExprRA,
                $refTree
            );
        }
        $arrTree[$str] = $refTree;
        return $arrTree;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected function buildMCARAtRALevel(
        string $strFQCN,
        //?string $strHtmlSchema,   
        array $arrExprRA,
        array $arrNode //cái này là đã vào sâu tới mức controller rồi 
    ): array {

        $arrPairRA = $this->parseExprRAList($strFQCN, $arrExprRA);
        foreach ($arrPairRA as [$strRole, $strAction]) {
            //không cần kiểm tra !isset($this->arrFCQNA2F[$strFQCN]) vì đã kiểm tra trong
            //$arrPairRA = $this->parseExprRAList($strFQCN, $arrExprRA) rồi
            if (!isset($this->arrFCQNA2F[$strFQCN][$strAction])) {
                throw new \RuntimeException(
                    "File config.fca2f, class {$strFQCN}, action '{$strAction}' không tồn tại"
                );
            }
            $arrActionDetail = $this->arrFCQNA2F[$strFQCN][$strAction];

            // Khởi tạo leaf nếu chưa tồn tại
            if (!isset($arrNode[$strAction])) {
                if (empty($arrActionDetail['method'])) {
                    throw new InvalidArgumentException(
                        "File config.fca2f, class {$strFQCN}, action '{$strAction}' thiếu khai báo method"
                    );
                }
                $arrNode[$strAction] = [
                    'roles'            => [],
                    'fqcn'             => $strFQCN,
                    'function'         => $arrActionDetail['function'] ?? $strAction,
                    'method'           => strtoupper($arrActionDetail['method'])
                ];
            }

            // Bổ sung role
            if (!in_array($strRole, $arrNode[$strAction]['roles'], true)) {
                $arrNode[$strAction]['roles'][] = $strRole;
            }
        }

        return $arrNode;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    //$strFQCN: fully qualified class name.
    //array $arrRAExpr: array các phần tử dạng [$strRExpr => $strAExpr]
    //mục tiêu là tạo ra array các phần tử dạng đơn giản [$strRole => $strAction]
    //$strFQCN có ý nghĩa là class name chứa các action trong $arrExpRA
    protected function parseExprRAList(string $strFQCN, array $arrExprRA): array{
        //cần $allAction để cung cấp cho phân tích biểu thức
        if(!isset($this->arrFCQNA2F[$strFQCN])){
            throw new \RuntimeException("File config.fca2f không tìm thấy class: {$strFQCN}");
        }
        $allAction = array_keys($this->arrFCQNA2F[$strFQCN]);
        $arrTmp = [];
        foreach($arrExprRA as $strExprR => $strExprA){
            //$strExprR và $strExprA đều có thể là expression
            $arrRoleParse = RoutePattern::parse($strExprR, $this->arrR, RoutePattern::EXPR_ALL_MODES);
            if($arrRoleParse['type'] !== '' && $arrRoleParse['type'] !== 'role'){
                throw new UnexpectedValueException("Biểu thức {$strExprR} phải có type là role, chứ không được là {$arrRoleParse['type']}");
            }
            //phân tích ra $arrRoleParse dạng list các role đơn
            $roles = $arrRoleParse['values'];
            
            $arrActionParse = RoutePattern::parse($strExprA, $allAction, RoutePattern::EXPR_ALL_MODES);
            if($arrActionParse['type'] !== '' && $arrActionParse['type'] !== 'action'){
                throw new UnexpectedValueException("Biểu thức {$strExprA} phải có type là action, chứ không được là {$arrActionParse['type']}");
            }
            //phân tích ra $actions dạng list các action đơn
            $actions = $arrActionParse['values'];
            //$arrTmp1 là tập hợp các [strRole, strAction]
            $arrTmp1 = MathUtility::cartesianProduct([$roles,$actions]);
            //tích lũy kết quả từ $arrTmp1 vào $arrTmp
            $arrTmp = array_merge($arrTmp,$arrTmp1);
        } 
        //loại bỏ các phần tử trùng lặp trong $arrTmp để tính ra $arrPairRA, $arrPairRA
        //là cấu trúc chứa các cặp [strRole, strAction] không trùng lặp
        $arrPairRA = [];
        $seen = [];
        foreach ($arrTmp as $item) {
            $key = implode('|', $item); // tạo key duy nhất cho mảng con bằng cách nối 2 chuỗi: role|action
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $arrPairRA[] = $item;
            }
        }
        return $arrPairRA;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    static protected function isActionStringList($obj){
        return ValidUtility::isStringList($obj) && (count($obj) > 0);
    }    
    /*---------------------------------------------------------------------------------------------------------------*/
    /*$strMRoutePath có format dạng 'module/controller' hoặc 'controller' (khuyết phần module)
     * Trong đó phần module có format như sau
     * - tên một module đơn như là 'nutrition'
     * - biểu thức có format như sau '[module:*]' , '[module:nutrion|pupil]' , '[module:!nutrion]'
     * Return: 
     * [[module_1, controller], [module_2, controller], ...[module_n, controller] ] nếu như có nhiều module
     * [[module, controller]] nếu chỉ có một module
     * [[controller]] nếu khuyết không có tên module
     */
    protected function parseMCRoutePath(string $strMCRoutePath): array {
        $strMCRoutePath = trim($strMCRoutePath);
        $slashCount = substr_count($strMCRoutePath, '/');
        if ($slashCount > 1) {
            throw new InvalidArgumentException(
                "Invalid MCRoutePath format: too many slashes in '$strMCRoutePath'"
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
            throw new InvalidArgumentException("Invalid MCRoutePath format '$strMCRoutePath'");
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
            $this->arrM,
            RoutePattern::EXPR_ALL_MODES
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
        $strFileName = 'list.mc.php';
        if (!isset($this->arrMC[$strModule])) {
            throw new InvalidArgumentException(
                "File {$strFileName}: module '{$strModule}' không tồn tại"
            );
        }

        $arrParse = RoutePattern::parse(
            $strControllerExpr,
            $this->arrMC[$strModule],
            RoutePattern::EXPR_ALL_MODES
        );

        if ($arrParse['type'] !== '' && $arrParse['type'] !== 'controller') {
            throw new InvalidArgumentException(
                "File {$strFileName}: biểu thức {$strControllerExpr} phải có type là controller, chứ không được là {$arrParse['type']}"
            );
        }

        return $arrParse['values'];
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected function parseStandaloneControllerRoutePath(string $strControllerExpr): array {
        // $controllers = $this->parseStandaloneControllerExpr($strControllerExpr);
        $strFileName = 'list.mc.php';
        $arrParse = RoutePattern::parse(
            $strControllerExpr,
            $this->arrStC,
            RoutePattern::EXPR_ALL_MODES
        );

        if ($arrParse['type'] !== '' && $arrParse['type'] !== 'controller') {
            throw new InvalidArgumentException(
                "File {$strFileName}: biểu thức {$strControllerExpr} phải có type là controller, chứ không được là {$arrParse['type']}"
            );
        }
         $controllers = $arrParse['values'];
         /*xoay vecto $controllers thành vecto dạng cột, ý nghĩa là biến đổi  ['login', 'client-info'] thành
         [['login'], ['client-info']]
         */
        return array_map(
            fn(string $strController) => [$strController],
            $controllers
        );
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected function buildDefaultRoute(): void {
        $arrConfig = $this->loadAndValidateDefaultRouteConfig();

        $this->arrDefaultRoute = [
            'default_entry' => $this->normalizeDefaultEntry($arrConfig['default_entry']),
            'routes' => $this->normalizeDefaultRoutes($arrConfig['routes']),
        ];
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected function loadAndValidateDefaultRouteConfig(): array {
        $strFileName = 'config.default.route.php';
        $arrConfig = require CONFIG_PATH . '/'. $strFileName;
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
        $strFileName = 'config.default.route.php';
        $type = $entry['type'];
        $value = StringUtility::spacesToDash($entry['value']);

        if (!in_array($type, ['module', 'controller'], true)) {
            throw new UnexpectedValueException(
                "File config.default.route.php: default_entry.type phải là 'module' hoặc 'controller'"
            );
        }

        if ($type === 'module' && !in_array($value, $this->arrM, true)) {
            throw new UnexpectedValueException(
                "File {$strFileName}: default_entry.value '{$value}' không phải giá trị module hợp lệ"
            );
        }

        if ($type === 'controller' && !in_array($value, $this->arrStC, true)) {
            throw new UnexpectedValueException(
                "File {$strFileName}: default_entry.value '{$value}' không phải giá trị standalone controller hợp lệ"
            );
        }

        return [
            'type' => $type,
            'value' => $value,
        ];
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected function normalizeDefaultRoutes(array $routes): array {
        $result = [];
        foreach ($routes as $entry => $routeValue) {
            $entry = StringUtility::spacesToDash($entry);
            
            if (is_string($routeValue)) {// Trường hợp controller => action
                $result[$entry] = $this->normalizeStandaloneDefaultRoute($entry, $routeValue);
            }
            else{
                $result[$entry] = $this->normalizeModuleDefaultRoute($entry, $routeValue);
            }
        }

        return $result;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected function normalizeStandaloneDefaultRoute(string $controller, string $action): string {
        $strFileName = 'config.default.route.php';
        $action = StringUtility::spacesToDash($action);

        if (!in_array($controller, $this->arrStC, true)) {
            throw new UnexpectedValueException(
                "File {$strFileName}: default route '{$controller}' không phải standalone controller hợp lệ"
            );
        }

        if (!$this->actionExists(null, $controller, $action)) {
            throw new UnexpectedValueException(
                "File {$strFileName}: action mặc định '{$action}' không tồn tại trong controller '{$controller}'"
            );
        }

        return $action;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected function normalizeModuleDefaultRoute(string $module, mixed $routeValue): array {
        $strFileName = 'config.default.route.php';
        if (!in_array($module, $this->arrM, true)) {
            throw new UnexpectedValueException(
                "File {$strFileName}: default route '{$module}' không phải giá trị module hợp lệ"
            );
        }
        if (!ValidUtility::isStringPairMap($routeValue) || count($routeValue) !== 1) {
            throw new UnexpectedValueException(
                "File {$strFileName}: default route của module '{$module}' phải có format ['controller' => 'action']"
            );
        }

        $controller = StringUtility::spacesToDash(array_key_first($routeValue));
        $action = StringUtility::spacesToDash($routeValue[array_key_first($routeValue)]);

        if (!isset($this->arrMC[$module]) || !in_array($controller, $this->arrMC[$module], true)) {
            throw new UnexpectedValueException(
                "File {$strFileName}: controller mặc định '{$controller}' không thuộc module '{$module}'"
            );
        }

        if (!$this->actionExists($module, $controller, $action)) {
            throw new UnexpectedValueException(
                "File {$strFileName}: action mặc định '{$action}' không tồn tại trong '{$module}/{$controller}'"
            );
        }

        return [$controller => $action];
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
            'arrFCQNA2F' => $this->arrFCQNA2F,
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