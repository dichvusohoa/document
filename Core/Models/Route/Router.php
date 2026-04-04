<?php
namespace Core\Models\Route;
use \InvalidArgumentException;
use \UnexpectedValueException;
use \Closure;
use Core\Models\ExtArray;
use Core\Models\Request;
use Core\Models\HttpException;
use Core\Models\Utility\ValidUtility;
use Core\Models\Utility\MathUtility;
use Core\Models\Utility\StringUtility;
class Router extends ExtArray{
    //FROM INPUTS $arrUserRole, $arrEnableModule
    protected array $arrUserRole;
    protected array $arrEnableModule;
    
    //Đầu vào - load từ file
    
    protected array $arrM; //M = module name. load từ file /config/list.module.php
    protected array $arrR; //R = Role, danh sách tất cả các role. load từ file /config/list.role.php
    //MC2FQCN = module-controller-FQCN (fully qualified class name). 
    //xây dựng  từ file /config/config.mc2fc.php. 
    protected array $arrMC2FQCN; 
    
    //FCQNA2F = FCQN (fully qualified class name)+ A (action) => Function
    //load từ file /config/config.fca2f
    protected array $arrFCQNA2F;
    protected array $arrMiddleware; //load từ file middleware.route.php


    //kết quả cần tính ra 02 array
    // 01 $arrTMCRA: cấu trúc gốm các phần từ [strType][strModule(có thể thiếu)][strController]=> [strRole=>[arrAction]]
    // đây là cấu trúc cho tất cả mọi user
    protected array $arrTMCRA; //build từ config.api.mcr2a.php và config.html.mcr2a.php. Đây là khối dữ liệu lớn nhất
    //02 $arrData  có cấu trúc [strModule (có thể thiếu)][strController][strAction] => 
    //['prohibited_module'=>..., 'prohibited_role'=>... 'roles' =>[...], 'fqcn' => ..., 'function'=>...,
    //method=>...,middlewares=> [....]] 
    //cấu trúc này là tương ứng với user hiện tại ( thể hiện ra bằng arrEnableModule, và arrUserRole)
    
    /*---------------------------------------------------------------------------------------------------------------*/
    function __construct(array $arrEnableModule, array $arrUserRole){
        parent::__construct();
        $this->arrEnableModule = $arrEnableModule;
        $this->arrUserRole = $arrUserRole;
        
        $this->buildModule();
        $this->buildRole();
        $this->buildFCA2F();
        $this->buildMiddleware(); 
        $this->buildMC2FQCN();//
        $this->buildTMCRA();
   
        $this->arrData = [];
        $this->buildMainData();
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public function getUserRoles():array{
        return $this->arrUserRole;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public function getEnableModules():array{
        return $this->arrEnableModule;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public function setUserRoles(array $arrUserRole):void{
        $this->arrUserRole = $arrUserRole;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public function setEnableModules(array $arrEnableModule):void{
        $this->arrEnableModule = $arrEnableModule;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public function dataToArray(): array {
        $result = [];
        //ReflectionClass($this) lấy thông tin metadata của object hiện tại.
        $ref = new \ReflectionClass($this);
        foreach ($ref->getProperties() as $prop) {//trả về tất cả các property (kể cả private/protected).
            // Bỏ qua static property
            if ($prop->isStatic()) {
                continue;
            }
            $prop->setAccessible(true);
            $propName = $prop->getName();
            $val = $prop->getValue($this);
            $skip = false;
            if ($prop->hasType()) {
                $type = $prop->getType();
                //loại $type =  null, ReflectionUnionType kiểu hợp (int|string),
                //ReflectionIntersectionType kiểu giao A&B trong PHP 8.1+).
                //chỉ lấy kiểu đơn giản như là Closure, ?Closure, int, ?int
                if ($type instanceof \ReflectionNamedType) {
                    $typeName = $type->getName();
                    // Loại bỏ Closure và ?Closure
                    if ($typeName === \Closure::class) {
                        $skip = true;
                    }
                }
            }
            if (!$skip) {
                $result[$propName] = $val;
            }
        }
        return $result;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public static function fromToArrayData(array $data): self {
       // $obj = new self([],[]); // tạo instance mới
      //  $ref = new \ReflectionClass($obj);
        $ref = new \ReflectionClass(self::class);
        //tạo instance mà không d
        $obj = $ref->newInstanceWithoutConstructor();
        foreach ($ref->getProperties() as $prop) {
            // Bỏ qua static property
            if ($prop->isStatic()) {
                continue;
            }
            $prop->setAccessible(true);
            $propName = $prop->getName();
            // Nếu $data không có key $propName thì bỏ qua
            if (!array_key_exists($propName, $data)) {
                continue;
            }
            $skip = false;
            if ($prop->hasType()) {
                $type = $prop->getType();
                if ($type instanceof \ReflectionNamedType) {
                    $typeName = $type->getName();
                    // Loại bỏ Closure và ?Closure
                    if ($typeName === \Closure::class) {
                        $skip = true;
                    }
                }
            }
            if (!$skip) {
                $prop->setValue($obj, $data[$propName]);
            }
        }
        // 🔥 BẮT BUỘC
        $obj->setDefaultClosures();
        return $obj;
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
        $arrTmp = require_once CONFIG_PATH.'/middleware.route.php';
        if(!ValidUtility::isStringPairMap($arrTmp)){
            throw new UnexpectedValueException('File middleware.route.php phải là một mảng string'); 
        }
        $this->arrMiddleware = $arrTmp;
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
        //var_dump($this->arrMC2FQCN);
        
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected function buildTMCRA(){
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
                $arrTmp[$strType] = $this->buildTMCRAForOneRule(
                    $strType,
                    $arrTmp[$strType], //đây là dữ liệu cũ ở step trước và đã chứa module, controller
                    $arrMC,
                    $arrExprRA
                );
            }
        }
        $this->arrTMCRA = $arrTmp;
        //var_dump($this->arrTMCRA);
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected function buildTMCRAForOneRule(
        string $strType,
        array $arrTree, //đây là dữ liệu cũ ở step trước và đã chứa module, controller, không có type
        array $arrMC,
        array $arrExprRA): array {
        foreach ($arrMC as $pairMC) {
            $arrTree = $this->buildTMCRAForOnePairMC(
                $strType,
                $arrTree, //đây là dữ liệu cũ ở step trước và đã chứa module, controller, không có type
                $pairMC,
                $arrExprRA
            );
        }
        return $arrTree;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected function buildTMCRAForOnePairMC(
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
            $refTree[$strController] = $this->buildTMCRAAtRALevel(
                $arrFCQN[$strType],
                $arrExprRA,
                $refTree[$strController]
            );
        } else {
            if(!isset($arrFCQN[$strType])){
                throw new \RuntimeException("Hai file config không tương thích. File config.{$arr[$strType]}.mcr2a có đường dẫn {$pairMC[0]}} có tồn tại role/action nhưng không map được class name trong file config.mc2fc");
            }
            $refTree = $this->buildTMCRAAtRALevel(
                $arrFCQN[$strType],
                $arrExprRA,
                $refTree
            );
        }
        $arrTree[$str] = $refTree;
        return $arrTree;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected function buildTMCRAAtRALevel(
        string $strFQCN,
        array $arrExprRA,
        array $arrNode //cái này là đã vào sâu tới mức controller rồi 
    ): array {

        $arrPairRA = $this->parseExprRAList($strFQCN, $arrExprRA);

        foreach ($arrPairRA as [$strRole, $strAction]) {

            $arrNode[$strRole] ??= [];

            if (!in_array($strAction, $arrNode[$strRole], true)) {
                $arrNode[$strRole][] = $strAction;
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
    /*hàm filter để thiết lập $this->arrData. Lý do vì $this->arrTMCRA đă được thiết lập qua hàm
     * buildTMCRA trước đó và valid rồi nên MCAFilterFn sẽ bớt tổng quát đi để tăng hiệu năng */
    protected function TMCRAFilterFn(array $path, mixed $value):int{
        if(count($path) <= 2){
            return self::BRANCH;//chưa xác định được là có module hay không nên trả về giá trị nhánh
        }
        elseif (count($path) === 3){
            //trường hợp nhánh ngắn không có module chỉ có controller
            if(self::isActionStringList($value)){
                return self::LEAF;
            }
            //trường hợp nhánh dài
            return self::BRANCH;
        }
        //here count($path) === 4
        if(!self::isActionStringList($value)){
            throw new InvalidArgumentException("Giá trị value phải là vecto của các string");
        }
   
        return self::LEAF;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    /*hàm xử lý tại nút lá của $this->arrTMCRA để thiết lập $this->arrData*/
    protected function TMCRALeafFn(array $path,array &$arrAction):void{
        $strType = $path[0];
        if(!isset($this->arrData[$strType])){
            $this->arrData[$strType] = [];
        }
        $ref = &$this->arrData[$strType];
        if(count($path) === 3){//chỉ có controller không có module
            $strModule      = null;
            $strController  = $path[1];
            $strRole        = $path[2];
            if(!isset($ref[$strController])){
                $ref[$strController] = [];
            }
            $ref = &$ref[$strController];
        }
        else{
            //from here mean count($path) === 4;
            $strModule      = $path[1];
            $strController  = $path[2];
            $strRole        = $path[3];
            if(!isset($ref[$strModule])){
                $ref[$strModule] = [];
            }
            $ref = &$ref[$strModule];
            /*if(!isset($this->arrData[$strModule][$strController])){
                $this->arrData[$strModule][$strController] = [];
            }*/
            if(!isset($ref[$strController])){
                $ref[$strController] = [];
            }
            //$ref = &$this->arrData[$strModule][$strController];
            $ref = &$ref[$strController];
        }
        //$ref tiến đến mức controller
        $this->TMCRALeafDetail($ref, $strType, $strModule, $strController, $strRole, $arrAction);
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected function TMCRADetailPlus(&$ref, string $strType, array $arrFQCN, string $strRole, string $strAction,
            bool $isProhibitedByModule, bool $isProhibitedByRole): void{
        if(!isset($ref[$strAction])){ //chưa có dữ liệu
            $ref[$strAction] = array_merge(
                ['prohibited_module' => $isProhibitedByModule],   
                ['prohibited_role' => $isProhibitedByRole],
                ['fqcn' => $arrFQCN[$strType]],  
                ['html_schema' => $strType === 'html_class' ? $arrFQCN['html_schema']:null],      
                $this->arrFCQNA2F[$arrFQCN[$strType]][$strAction], //function và method
                ['middlewares' => null]);
            if(!$isProhibitedByModule && !$isProhibitedByRole){
                $ref[$strAction]['roles'] = [$strRole];
            }
            else{
                //khi bị chặn không được truy cập bởi lý do không được cấp module hoặc không có role phù hợp thì không cần tính toán roles nữa
                $ref[$strAction]['roles'] = []; 
            }
            return;
        }
        //đã có dữ liệu rồi
        if(!$isProhibitedByModule && !$isProhibitedByRole){
            if(!in_array($strRole, $ref[$strAction]['roles'], true)){
                $ref[$strAction]['roles'][] = $strRole;//thêm 1 phần tử
            }
            /*nếu &$ref được set dữ liệu nhiều lần (prohibited_module, prohibited_role
            nhiều lần thay đổi true, false thì ưu tiên set cho kết quả bằng false
            tức là chỉ cần l lần duy nhất
            Giải thích tình huống này: prohibited_module = true rồi lại chuyển prohibited_module = false thì không có
            nhưng prohibited_role = true rồi đảo prohibited_role = false thì có
            Ví dụ user này có roleA, roleB. Lần đầu gọi TMCRALeafDetail với $strRole = roleC nên prohibited_role = true.
            Sau đó lại gọi hàm TMCRALeafDetail với $strRole = roleA thì  prohibited_role thay đổi = false
            */
            $ref[$strAction]['prohibited_module'] = false;//luôn ưu tiên set prohibited = false
            $ref[$strAction]['prohibited_role'] = false;//luôn ưu tiên set prohibited = false
        }
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected function TMCRALeafDetail(&$ref, string $strType, ?string $strModule, string $strController, string $strRole, array $arrAction): void{
        //$isProhibited = false;
        $isProhibitedByModule = false;
        $isProhibitedByRole = false;
        if(!in_array($strRole, $this->arrUserRole,true)){
            //$isProhibited = true;
            $isProhibitedByRole = true;
        }
        if($strModule){
            if(!in_array($strModule, $this->arrEnableModule,true)){
                //$isProhibited = true;
                $isProhibitedByModule = true;
            }
            $arrFQCN =  $this->arrMC2FQCN[$strModule][$strController];
        }
        else{
            $arrFQCN = $this->arrMC2FQCN[$strController];
        }
        foreach ($arrAction as $strAction){
            $this->TMCRADetailPlus($ref, $strType, $arrFQCN, $strRole, $strAction, $isProhibitedByModule, $isProhibitedByRole);
        }
        
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected function setDefaultClosures(){
        $this->validLeafFn  = Closure::fromCallable([RouteInfo::class, 'isValid']);
        $this->filterFn     = Closure::fromCallable([$this, 'filterFn']);
        $this->leafFn       = null; // function xử lý tại nút sẽ tùy trường hợp cụ thể mà tự viết
        $this->startBranchFn = null; // tùy trường hợp cụ thể mà tự viết
        $this->endBranchFn   = null; // tùy trường hợp cụ thể mà tự viết
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public function buildMainData(){
        $extArrTMCRA = new ExtArray($this->arrTMCRA); 
        $extArrTMCRA->validLeafFn = Closure::fromCallable([static::class, 'isActionStringList']);// leaf là một chuỗi các string action
        $extArrTMCRA->filterFn = Closure::fromCallable([$this, 'TMCRAFilterFn']);
        $extArrTMCRA->leafFn = Closure::fromCallable([$this, 'TMCRALeafFn']);
       
        $extArrTMCRA->traverseTree($this->arrTMCRA); 
        /*sau khi build xong $arrData set các default cho các closure 
        điều này là bắt buộc. vì 1 ExtArray luôn phải set giá trị cho
        closure validLeafFn
         */
        $this->setDefaultClosures();
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
    protected function filterFn(array $path, mixed $value):int{
        if(RouteInfo::isValid($value)){
            return self::LEAF;
        }
        else{
            return self::BRANCH;
        }
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public function securityAdminControllerName(array $arrSegment): array{
        if(empty($arrSegment)){
            return $arrSegment;
        }
        foreach (ADMIN_CONTROLLER_RENAME as $strName => $strReName) {
            if($arrSegment[0] === $strReName){
                //cho phép truy cập theo admin controller đã rename bằng cách thay trong segment bằng chuỗi admin controller gốc
                $arrSegment[0] = $strName;
            }
            else if($arrSegment[0] === $strName){
                //chặn không cho truy cập bằng admin controller thật bằng cách thay trong segment bằng chuỗi admin controller đã bị rename
                $arrSegment[0] = $strReName;
            }
        }
        return $arrSegment;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public function matchUri(Request $request): ?array{
        $arrSegment = $request->segmentUri();
        if($arrSegment === null){
            throw new HttpException(404, 'Not Found');
        }
        $arrSegment = $this->securityAdminControllerName($arrSegment);
        $numSeg = count($arrSegment);
        if(Request::isHtmlResponse()){
            $routes = DEFAULT_HTML_ROUTE; //PHP không cho viết trực tiếp DEFAULT_ROUTE[$str1]
        }
        else{
            $routes = DEFAULT_API_ROUTE;
        }
        //$routes = DEFAULT_ROUTE; //PHP không cho viết trực tiếp isset(DEFAULT_ROUTE[DEFAULT_ENTRY]
        if($numSeg === 0){
            if(!isset($routes[DEFAULT_ENTRY])){
                return null;
            }
            else{
                $strTmp = DEFAULT_ENTRY;
            }
        }
        else{
            $strTmp = $arrSegment[0];
        }
        
        if(in_array($strTmp, $this->arrM)){//có module
            $path = $this->toTMCAWithModule($strTmp, $arrSegment);
        }
        else{//chỉ có controller
            $path = $this->toTMCAWithoutModule($strTmp, $arrSegment);
        }
        if($path === null){
            return ['path' => null, 'route_info' => null, 'attach_middlewares_after_match' => false];
        }
        $leaf = &$this->getReferenceAt($path);
        if($leaf === null){
            return ['path' => $path, 'route_info' => null, 'attach_middlewares_after_match' => false];
            //return $res;
        }
        if($leaf['middlewares'] === null){
            $this->attachMiddlewares($path, $leaf);
            return ['path' => $path, 'route_info' => $leaf, 'attach_middlewares_after_match' => true];
        }
        return ['path' => $path, 'route_info' => $leaf, 'attach_middlewares_after_match' => false];;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected function attachMiddlewares(array $arrTMCA, mixed &$leafInfo):void{
        $arrSegment['method'] = $leafInfo['method'];
        $arrSegment['role']  = $leafInfo['roles'];
        if(count($arrTMCA) === 4){
            $arrSegment['fctype']      = $arrTMCA[0];
            $arrSegment['module']      = $arrTMCA[1];
            $arrSegment['controller']  = $arrTMCA[2];
            $arrSegment['action']      = $arrTMCA[3];
        }
        else if(count($arrTMCA) === 3){
            $arrSegment['fctype']      = $arrTMCA[0];
            $arrSegment['module']      = null;
            $arrSegment['controller']  = $arrTMCA[1];
            $arrSegment['action']      = $arrTMCA[2];
        }
        $leafInfo['middlewares'] = [];
        foreach($this->arrMiddleware as $strRoutePath => $strFCQN){
            //chuyển định dạng biểu thức của $strRoutePath ra dạng 
            //array['fctype'=> ...,'module'=> ..., 'controller' => ..., 'action' => ..., 'method' => ... ,'role' => ...];
            $arrSegmentExpr = RoutePatternList::buildFromRoutePath($strRoutePath);
            if(RoutePatternList::match($arrSegmentExpr, $arrSegment)){
                //đính kèm middleware vào nhánh này
                $leafInfo['middlewares'][] = $strFCQN;
            }
        }
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected function toTMCAWithModule(string $strModule, array $arrSegment) {
        if(Request::isHtmlResponse()){
            $strType = 'html_class';
            $routes = DEFAULT_HTML_ROUTE; //PHP không cho viết trực tiếp DEFAULT_ROUTE[$str1]
        }
        else{
            $strType = 'api_class';
            $routes = DEFAULT_API_ROUTE;
        }
        $numSeg = count($arrSegment);
        if($numSeg >= 3){
            return [$strType, $strModule, $arrSegment[1], $arrSegment[2]]; //module-controller-action
        }
        //từ đây đi là khuyết thành phần, phải dùng các DEFAULT_API_ROUTE hoặc DEFAULT_HTML_ROUTE để xác định
       
        if( !isset($routes[$strModule]) ||  //không có value [$controller => $action]
            !ValidUtility::isStringPairMap($routes[$strModule]) || // không đúng format không có value [$controller => $action]
            // nghĩa là $routes[$strModule] có định dạng [$controller => $action] => count($routes[$strModule]) ===1
            count($routes[$strModule]) >=1){ 
            //return false;
            return null;
        }
        $arrCA = $routes[$strModule];
        if($numSeg === 2){
            $strController = $arrSegment[1];
        }
        else{//lấy từ default
            $strController = array_keys($arrCA)[0];//controller
        }
        if(!isset($arrCA[$strController])){
            return null;
        }
        else{
            $strAction = $arrCA[$strController];
        }
        return [$strType, $strModule, $strController, $strAction];
        
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected function toTMCAWithoutModule(string $strController, array $arrSegment) {
        if(Request::isHtmlResponse()){
            $strType = 'html_class';
            $routes = DEFAULT_HTML_ROUTE; //PHP không cho viết trực tiếp DEFAULT_ROUTE[$str1]
        }
        else{
            $strType = 'api_class';
            $routes = DEFAULT_API_ROUTE;
        }
        if(count($arrSegment) >= 2){
            $strAction = $arrSegment[1];
        }
        else{//khuyết action
            if(isset($routes[$strController]) && is_string($routes[$strController])){
                $strAction = $routes[$strController];
            }
            else{
                return null;
            }
        }
        return [$strType, $strController, $strAction];
    }
    
}