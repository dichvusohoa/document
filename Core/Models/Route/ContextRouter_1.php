<?php
namespace Core\Models\Route;
use \InvalidArgumentException;
use \Closure;
use Core\Models\ExtArray;
use Core\Models\Request;
use Core\Models\HttpException;
use Core\Models\Utility\ValidUtility;

class ContextRouter extends ExtArray{
    //FROM INPUTS $arrUserRole, $arrEnableModule
    protected array $arrUserRole;
    protected array $arrEnableModule;
    protected StaticRouter $staticRouter;
    /*---------------------------------------------------------------------------------------------------------------*/
    function __construct(array $arrEnableModule, array $arrUserRole, StaticRouter $staticRouter){
        parent::__construct();
        $this->arrEnableModule  =   $arrEnableModule;
        $this->arrUserRole      =   $arrUserRole;
        $this->staticRouter     =   $staticRouter;
   
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
    /*hàm filter để thiết lập $this->arrData. Lý do vì $this->staticRouter đă được thiết lập qua hàm
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
    /*hàm xử lý tại nút lá của $this->staticRouter để thiết lập $this->arrData*/
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
                $this->staticRouter->getFCQNA2F()[$arrFQCN[$strType]][$strAction], //function và method
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
            $arrFQCN =  $this->staticRouter->getMC2FQCN()[$strModule][$strController];
        }
        else{
            $arrFQCN = $this->staticRouter->getMC2FQCN()[$strController];
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
        $extArrTMCRA = new ExtArray($this->staticRouter->getTMCRA()); 
        $extArrTMCRA->validLeafFn = Closure::fromCallable([static::class, 'isActionStringList']);// leaf là một chuỗi các string action
        $extArrTMCRA->filterFn = Closure::fromCallable([$this, 'TMCRAFilterFn']);
        $extArrTMCRA->leafFn = Closure::fromCallable([$this, 'TMCRALeafFn']);
       
        $extArrTMCRA->traverseTree($this->staticRouter->getTMCRA()); 
        /*sau khi build xong $arrData set các default cho các closure 
        điều này là bắt buộc. vì 1 ExtArray luôn phải set giá trị cho
        closure validLeafFn
         */
        $this->setDefaultClosures();
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
        
        if(in_array($strTmp, $this->staticRouter->getModule())){//có module
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
        foreach($this->staticRouter->getMiddleware() as $strRoutePath => $strFCQN){
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
            count($routes[$strModule]) !==1){ // chỉ có 1 cặp giá trị duy nhất [$controller => $action]
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