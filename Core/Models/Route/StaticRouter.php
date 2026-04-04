<?php
namespace Core\Models\Route;
use Core\Models\Utility\ValidUtility;
use Core\Models\Utility\StringUtility;
use Core\Models\Utility\MathUtility;
class StaticRouter {
    //Đầu vào - load từ file
    protected array $arrM; //M = module name. load từ file /config/list.module.php
    protected array $arrR; //R = Role, danh sách tất cả các role. load từ file /config/list.role.php
    //MC2FQCN = module-controller-FQCN (fully qualified class name). 
    //xây dựng  từ file /config/config.mc2fc.php. 
    protected array $arrMC2FQCN; 
    
    //FCQNA2F = FCQN (fully qualified class name)+ A (action) => Function
    //load từ file /config/config.fca2f
    protected array $arrFCQNA2F;
    protected array $arrMiddlewareParsed; //load từ file middleware.route.php và phân tích


    //kết quả cần tính ra  array
    // $arrTMCAR: cấu trúc gốm các phần từ [strType][strModule(có thể thiếu)][strController][strAction]=>
    // ['roles' => ..., 'fqcn' =>..., 'html_schema' => ...,'function' => ..., 'method' => ...]
    // đây là cấu trúc cho tất cả mọi user
    protected array $arrTMCAR; //build từ config.api.mcr2a.php và config.html.mcr2a.php. Đây là khối dữ liệu lớn nhất
   
    
    /*---------------------------------------------------------------------------------------------------------------*/
    function __construct(){
        $this->buildModule();
        $this->buildRole();
        $this->buildFCA2F();
        $this->buildMiddleware(); 
        $this->buildMC2FQCN();//
        $this->buildTMCAR();
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public function getModule() {
        return $this->arrM;
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
    public function getTMCAR() {
        return $this->arrTMCAR;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected function buildModule(){
        $arTmp =  require_once CONFIG_PATH.'/list.module.php';
        if(!ValidUtility::isStringPairMap($arTmp)){
            throw new UnexpectedValueException('File list.module.php phải là một mảng string'); 
        }
        $this->arrM = array_map([StringUtility::class, 'spacesToDash'], array_keys($arTmp));
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected function buildRole(){
        $arTmp =  require_once CONFIG_PATH.'/list.role.php';
        if(!ValidUtility::isStringPairMap($arTmp)){
            throw new UnexpectedValueException('File list.role.php phải là một mảng string'); 
        }
        $this->arrR = array_keys($arTmp);
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected function buildFCA2F(){
        $arrConfig =  require_once CONFIG_PATH.'/config.fca2f.php';
        foreach ($arrConfig as $strClass => $arrAction){
            // 1. Kiểm tra FQCN
            if (!is_string($strClass)) {
                throw new UnexpectedValueException("key '$strClass' của file config.fca2f.php phải là string");
            }
            if (!class_exists($strClass)) {
                throw new UnexpectedValueException("Class '$strClass' không tồn tại");
            }
            foreach ($arrAction as $strActionName => $arrActionDetail){
                if (!is_string($strActionName)) {
                    throw new UnexpectedValueException("action '$strActionName' tại key '$strClass' của file config.fca2f.php phải là string");
                }
                if(!ValidUtility::isStringPairMap($arrActionDetail)){
                    throw new UnexpectedValueException("Value tại key '$strClass, $strActionName' phải là một mảng string"); 
                }
            }
        }
        $this->arrFCQNA2F = $arrConfig;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected function buildMiddleware(){
        $this->arrMiddlewareParsed = [];
        $arrTmp = require_once CONFIG_PATH.'/middleware.route.php';
        if(!ValidUtility::isStringPairMap($arrTmp)){
            throw new UnexpectedValueException('File middleware.route.php phải là một mảng string'); 
        }
        //$this->arrMiddleware = $arrTmp;
        foreach ($arrTmp as $routePath => $fqcn) {
            $this->arrMiddlewareParsed[] = [
                //'expr' chuyển định dạng biểu thức của $strRoutePath ra dạng 
                //array['fctype'=> strExprFcTyoe,'module'=> strExprModule, 'controller' => strExprController, 'action' => strExprAction 'method' => strExprMethod ,'role' => strExprRole
                'expr' => RoutePatternList::buildFromRoutePath($routePath),
                'fqcn' => $fqcn
            ];
        }
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    //arrMC2FQCN được xây dựng là [module][controller] => ['api_class'=>...'html_class'=>...,'html_schema'=>...]
    //nó không xây dựng đường dẫn kiểu [type][module][controller] vì lý do còn vường html_schema
    protected function buildMC2FQCN(): void{
        $arrTmp = require_once CONFIG_PATH.'/config.mc2fc.php';
        //FQCN = fully qualified class name
        $this->arrMC2FQCN = [];
        foreach ($arrTmp as $strRouteMCPath => $arrFQCN) {
            $arrMCProduct = $this->parseMCRoutePath($strRouteMCPath);
            foreach ($arrMCProduct as $value){
                if(count($value) === 2){//cặp module - controller
                    $strModule = $value[0];
                    $strController = $value[1];
                    if(!isset($this->arrMC2FQCN[$strModule])){
                        $this->arrMC2FQCN[$strModule] = [];
                    }
                    $this->arrMC2FQCN[$strModule][$strController] = $arrFQCN;
                }
                else{
                    $strController = $value[0];
                    $this->arrMC2FQCN[$strController] = $arrFQCN;
                } 
            }
        }
      
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected function buildTMCAR(){
        $arrConfigFile = [
            'api_class'  => 'config.api.mcr2a.php',
            'html_class' => 'config.html.mcr2a.php'
        ];
        $arrTmp = [];
        foreach ($arrConfigFile as $strType => $strFileName) {
            $arrData = require CONFIG_PATH . '/' . $strFileName;
            $arrTmp[$strType] = []; 
            foreach ($arrData as $strExprMC => $arrExprRA) {
                if (!is_string($strExprMC)) {
                    throw new UnexpectedValueException(
                        "Thành phần module/controller file {$strFileName} phải là string"
                    );
                }
                $arrMC = $this->parseMCRoutePath($strExprMC);
                $arrTmp[$strType] = $this->buildTMCARForOneRule(
                    $strType,
                    $arrTmp[$strType], //đây là dữ liệu cũ ở step trước và đã chứa module, controller
                    $arrMC,
                    $arrExprRA
                );
            }
        }
        $this->arrTMCAR = $arrTmp;
        //var_dump($this->arrTMCAR);
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected function buildTMCARForOneRule(
        string $strType,
        array $arrTree, //đây là dữ liệu cũ ở step trước và đã chứa module, controller, không có type
        array $arrMC,
        array $arrExprRA): array {
        foreach ($arrMC as $pairMC) {
            $arrTree = $this->buildTMCARForOnePairMC(
                $strType,
                $arrTree, //đây là dữ liệu cũ ở step trước và đã chứa module, controller, không có type
                $pairMC,
                $arrExprRA
            );
        }
        return $arrTree;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected function buildTMCARForOnePairMC(
        string $strType,
        array $arrTree,//đây là dữ liệu cũ ở step trước và đã chứa module, controller, không có type (1/2 dữ liệu theo file)
        array $pairMC,
        array $arrExprRA
    ): array {
        // module hoặc controller (nếu khuyết module)
        $str = $pairMC[0];
        $arrTree[$str] ??= [];
        $arrFCQN = $this->arrMC2FQCN[$str];
        $refTree = $arrTree[$str];//đi sâu vào một mức
        $arr = ['html_class' => 'html', 'api_class' => 'api'];
        if (count($pairMC) === 2) { //$pairMC đủ cả module và controller
            
            $strController = $pairMC[1];
            $refTree[$strController] ??= [];
            $arrFCQN = $arrFCQN[$strController];
            if(!isset($arrFCQN[$strType])){
                throw new \RuntimeException("Hai file config không tương thích. File config.{$arr[$strType]}.mcr2a có đường dẫn {$pairMC[0]}/{$pairMC[1]} có tồn tại role/action nhưng không map được class name trong file config.mc2fc");
            }
            $strHtmlSchema = $strType === 'html_class' ? $arrFCQN['html_schema'] : null;
            $refTree[$strController] = $this->buildTMCARAtRALevel(
                $arrFCQN[$strType],
                $strHtmlSchema,    
                $arrExprRA,
                $refTree[$strController]
            );
        } else {
            if(!isset($arrFCQN[$strType])){
                throw new \RuntimeException("Hai file config không tương thích. File config.{$arr[$strType]}.mcr2a có đường dẫn {$pairMC[0]} có tồn tại role/action nhưng không map được class name trong file config.mc2fc");
            }
            $strHtmlSchema = $strType === 'html_class' ? $arrFCQN['html_schema'] : null;
            $refTree = $this->buildTMCARAtRALevel(
                $arrFCQN[$strType],
                $strHtmlSchema,    
                $arrExprRA,
                $refTree
            );
        }
        $arrTree[$str] = $refTree;
        return $arrTree;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected function buildTMCARAtRALevel(
        string $strFQCN,
        ?string $strHtmlSchema,   
        array $arrExprRA,
        array $arrNode //cái này là đã vào sâu tới mức controller rồi 
    ): array {

        $arrPairRA = $this->parseExprRAList($strFQCN, $arrExprRA);
        foreach ($arrPairRA as [$strRole, $strAction]) {
            //không cần kiểm tra !isset($this->arrFCQNA2F[$strFQCN]) vì đã kiểm tra trong
            //$arrPairRA = $this->parseExprRAList($strFQCN, $arrExprRA) rồi
            if (!isset($this->arrFCQNA2F[$strFQCN][$strAction])) {
                throw new \RuntimeException(
                    "Action '{$strAction}' không tồn tại trong config.fca2f của class {$strFQCN}"
                );
            }
            $arrActionDetail = $this->arrFCQNA2F[$strFQCN][$strAction];

            // Khởi tạo leaf nếu chưa tồn tại
            if (!isset($arrNode[$strAction])) {

                $arrNode[$strAction] = [
                    'roles'            => [],
                    'fqcn'             => $strFQCN,
                    'html_schema'      => $strHtmlSchema,
                    'function'         => $arrActionDetail['function'],
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
        $slashCount = substr_count(trim($strMCRoutePath), '/');
        if ($slashCount > 1) {
            //chỉ có tối đa 2 segment là module và controller
            throw new InvalidArgumentException("Invalid MCRoutePath format: too many slashes in '$strMCRoutePath'");
        }
        if ($slashCount === 1) { //$strMCRoutePath có format dạng moduleExpr/ControllerExpr
            [$strModuleExpr, $strControllerExpr] = explode('/', $strMCRoutePath, 2);
            if($strModuleExpr === '' || $strControllerExpr === ''){
                throw new InvalidArgumentException("Invalid MCRoutePath format '$strMCRoutePath'");
            }
            $arrParse = RoutePattern::parse($strModuleExpr, $this->arrM, RoutePattern::EXPR_ALL_MODES );
            if($arrParse['type'] !== '' && $arrParse['type'] !== 'module'){
                throw new InvalidArgumentException("Biểu thức {$strModuleExpr} phải có type là module, chứ không được là {$arrParse['type']}");
            }
            $modules = $arrParse['values'];
            $arrParse2 = RoutePattern::parse($strControllerExpr, [], RoutePattern::EXPR_SINGLE_VALUE | RoutePattern::EXPR_INCLUDE_VALUES );
            $controllers = $arrParse2['values'];
        }
        else{
            $modules = null; //null là không có chứ không phải là []
            $arrParse = RoutePattern::parse($strMCRoutePath, [], RoutePattern::EXPR_SINGLE_VALUE | RoutePattern::EXPR_INCLUDE_VALUES );
            $controllers = $arrParse['values'];
        }
        if($modules === null){
            //xoay vecto $controllers thành vecto dạng cột
            $result = array_map(function($item) {
                return [$item];
            }, $controllers);
        }
        else{
            $result = MathUtility::cartesianProduct([$modules, $controllers]);
        }
        return $result;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public function toArray(): array {
        return [
            'arrM' => $this->arrM,
            'arrR' => $this->arrR,
            'arrMC2FQCN' => $this->arrMC2FQCN,
            'arrFCQNA2F' => $this->arrFCQNA2F,
            'arrMiddlewareParsed' => $this->arrMiddlewareParsed,
            'arrTMCAR' => $this->arrTMCAR,
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