<?php
namespace Core\Routing;
use Core\Http\Request;
use Core\Http\HttpException;
use Core\Utility\ValidUtility;

class ContextRouter{
    //FROM INPUTS $arrUserRole, $arrEnableModule
    protected array $arrUserRole;
    protected array $arrEnableModule;
    protected StaticRouter $staticRouter;
    /*---------------------------------------------------------------------------------------------------------------*/
    function __construct(array $arrEnableModule, array $arrUserRole, StaticRouter $staticRouter){
        $this->arrEnableModule  =   $arrEnableModule;
        $this->arrUserRole      =   $arrUserRole;
        $this->staticRouter     =   $staticRouter;
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
    public function matchUri(Request $request): ?array {
        $arrSegment = $request->segmentUri();
        if ($arrSegment === null) {
            throw new HttpException(404, 'Not Found');
        }

        // Xử lý rename admin controller
        $arrSegment = $this->securityAdminControllerName($arrSegment);
        $numSeg = count($arrSegment);

        // Chọn route mặc định theo loại response
        /*if (Request::isHtmlResponse()) {
            $routes = DEFAULT_HTML_ROUTE;
        } else {
            $routes = DEFAULT_API_ROUTE;
        }*/
        $routes = DEFAULT_ROUTE;
        // Xác định segment đầu tiên
        $strTmp = $numSeg === 0 ? DEFAULT_ENTRY : $arrSegment[0];

        // Kiểm tra có module hay không
        if (in_array($strTmp, $this->staticRouter->getModule(), true)) {
            $path = $this->toMCAWithModule($strTmp, $arrSegment);
            $strModule = $path[1] ?? null;
        } else {
            $path = $this->toMCAWithoutModule($strTmp, $arrSegment);
            $strModule = null;
        }
        /*hệ thống không phân tích được url, tình huống thường xảy ra khi gõ sai 
        đường dẫn*/
        if ($path === null) {
            return [
                'path' => null,
                'route_info' => null,
                'middlewares' => null,
                'prohibited_module' => null,
                'prohibited_role' => null
            ];
        }
        $leaf = self::getValueAt($this->staticRouter->getMCAR(), $path);
        /*Tình huống này là để đề phòng tăng cường, nhưng có lẽ khó xảy ra vì nếu các url
        sai thì có lẽ hầu như rơi vào tình huống trên tức là path trả về null rồi. 
        Vẫn giữ đoạn code dưới đây để đề phòng
         */
        if ($leaf === null) { 
            return [
                'path' => $path,
                'route_info' => null,
                'middlewares' => null,
                'prohibited_module' => null,
                'prohibited_role' => null
            ];
        }
        // Tính middleware khi $path và $leaf khác null
        $middlewares = $this->attachMiddlewares($path, $leaf);
        // Kiểm tra module có bị cấm hay không
        if ($strModule && !in_array($strModule, $this->arrEnableModule, true)) {
            return [ // không có quyền truy cập module này
                'path' => $path,
                'route_info' => $leaf,
                'middlewares' => $middlewares,
                'prohibited_module' => true,
                'prohibited_role' => null
            ];
        }

        // Kiểm tra role
        $commonRoles = array_intersect($this->arrUserRole, $leaf['roles']);
        if (empty($commonRoles)) {
            return [ //không có role để truy cập action này
                'path' => $path,
                'route_info' => $leaf,
                'middlewares' => $middlewares,
                'prohibited_module' => false,
                'prohibited_role' => true
            ];
        }
        return [
            'path' => $path,
            'route_info' => $leaf,
            'middlewares' => $middlewares,
            'prohibited_module' => false,
            'prohibited_role' => false
        ];
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected function attachMiddlewares(array $arrMCA, array $leaf): array {
        $arrSegment = [
            'method'     => $leaf['method'],
            'role'       => array_intersect($this->arrUserRole, $leaf['roles'])//có thể là nhiều role
        ];
        if (count($arrMCA) === 3) { // module-controller-action
           // $arrSegment['fctype']     = $arrMCA[0];
            $arrSegment['module']     = $arrMCA[0];
            $arrSegment['controller'] = $arrMCA[1];
            $arrSegment['action']     = $arrMCA[2];
        } elseif (count($arrMCA) === 2) { // controller-action only
            //$arrSegment['fctype']     = $arrMCA[0];
            $arrSegment['module']     = null;
            $arrSegment['controller'] = $arrMCA[0];
            $arrSegment['action']     = $arrMCA[1];
        }

        $result = [];
        foreach ($this->staticRouter->getMiddleware() as $element) {
            if (RoutePatternList::match($element['expr'], $arrSegment)) {
                $result[] = $element['fqcn'];
            }
        }

        return $result;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    //$arrSegment[0] = $strModule
    protected function toMCAWithModule(string $strModule, array $arrSegment) {
        $numSeg = count($arrSegment);
        if($numSeg >= 3){
            return [$strModule, $arrSegment[1], $arrSegment[2]]; //module-controller-action
        }
        //từ đây đi là khuyết thành phần, phải dùng các DEFAULT_ROUTE để xác định
        $routes = DEFAULT_ROUTE;
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
        return [$strModule, $strController, $strAction];
        
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected function toMCAWithoutModule(string $strController, array $arrSegment) {
        $routes = DEFAULT_ROUTE;
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
        return [$strController, $strAction];
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected static function getValueAt(array $data, array $path): mixed {
        foreach ($path as $key) {
            if (!is_array($data) || !array_key_exists($key, $data)) {
                return null;
            }
            $data = $data[$key];
        }
        return $data;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected static function setValueAt(array &$data, array $path, array $value): void{
        $ref = &$data;
        foreach ($path as $key) {
            if (!isset($ref[$key]) || !is_array($ref[$key])) {
                $ref[$key] = [];
            }
            $ref = &$ref[$key];
        }

        $ref = $value;
    }
}