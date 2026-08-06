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
    public function matchUri(Request $request): ?array {
        $arrSegment = $request->segmentUri();
        if ($arrSegment === null) {
            throw new HttpException(404, 'Not Found');
        }

        $arrMCA = $this->toMCA($arrSegment);
        /*hệ thống không phân tích được url, tình huống thường xảy ra khi gõ sai 
        đường dẫn*/
        if ($arrMCA === null) {
            return [
                'mca' => null,
                'route_info' => null,
                'auth_policy' => null,
                'middlewares' => null,
                'prohibited_module' => null,
                'prohibited_role' => null
            ];
        }
        
        [$strModule, $strController, $strAction]  = [$arrMCA['module'], $arrMCA['controller'], $arrMCA['action']];
        if($strModule === null){
            $path = [$strController, $strAction];
        }
        else{
            $path = [$strModule, $strController, $strAction];
        }
        $leaf = $this->staticRouter->getRouteInfo($path);
        /*Tình huống này là để đề phòng tăng cường, nhưng có lẽ khó xảy ra vì nếu các url
        sai thì có lẽ hầu như rơi vào tình huống trên tức là path trả về null rồi. 
        Vẫn giữ đoạn code dưới đây để đề phòng
         */
        if ($leaf === null) { 
            return [
                'mca' => $arrMCA,
                'route_info' => null,
                'auth_policy' => null,
                'middlewares' => null,
                'prohibited_module' => null,
                'prohibited_role' => null
            ];
        }
  
        // Tính middleware khi $path và $leaf khác null
        
        $middlewares = $this->attachMiddlewares($path, $leaf);
        $authTemplate = $leaf[RouteInfo::FIELD_ROUTE_TYPE] === RouteInfo::ROUTE_TYPE_AUTHENTICATION ?
        $this->staticRouter->createAuthRegistry()->getAuthPolicy($strController) : null;
        if($authTemplate !== null){//lọc các field không cần thiết
            $authPolicy[AuthRegistry::FIELD_MAX_FAIL_COUNT] = $authTemplate[AuthRegistry::FIELD_MAX_FAIL_COUNT];
            $authPolicy[AuthRegistry::FIELD_TURNSTILE] = $authTemplate[AuthRegistry::FIELD_TURNSTILE];
            $authPolicy[AuthRegistry::FIELD_REMEMBER_COOKIE] = $authTemplate[AuthRegistry::FIELD_REMEMBER_COOKIE];
            $authPolicy[AuthRegistry::FIELD_REMEMBER_EXPIRE] = $authTemplate[AuthRegistry::FIELD_REMEMBER_EXPIRE];
        }
        else{
            $authPolicy = null;
        }
        // Kiểm tra module có bị cấm hay không
        if ($strModule && !in_array($strModule, $this->arrEnableModule, true)) {
            return [ // không có quyền truy cập module này
                'mca' => $arrMCA,
                'route_info' => $leaf,
                'auth_policy' => $authPolicy,
                'middlewares' => $middlewares,
                'prohibited_module' => true,
                'prohibited_role' => null
            ];
        }

        // Kiểm tra role
        $commonRoles = array_intersect($this->arrUserRole, $leaf['roles']);
        if (empty($commonRoles)) {
            return [ //không có role để truy cập action này
                'mca' => $arrMCA,
                'route_info' => $leaf,
                'auth_policy' => $authPolicy,
                'middlewares' => $middlewares,
                'prohibited_module' => false,
                'prohibited_role' => true
            ];
        }
        return [
            'mca' => $arrMCA,
            'route_info' => $leaf,
            'auth_policy' => $authPolicy,
            'middlewares' => $middlewares,
            'prohibited_module' => false,
            'prohibited_role' => false
        ];
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected function attachMiddlewares(array $arrMCA, array $leaf): array {
        $result = MCARMeInfo::buildEmpty();
        [$result['role'], $result['method']] = [array_values(array_intersect($this->arrUserRole, $leaf['roles'])), $leaf['method']];
        if (count($arrMCA) === 3) { // module-controller-action
            [$result['module'], $result['controller'], $result['action']] = $arrMCA;
        } elseif (count($arrMCA) === 2) { // controller-action only
            [$result['module'], $result['controller'], $result['action']] = [null, $arrMCA[0], $arrMCA[1]];
        }
        return $this->staticRouter->matchMiddlewares($result);
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected function toMCA(array $arrSegment): ?array {
        $module = $this->toMCAModule($arrSegment);

        $controller = $this->toMCAController($module, $arrSegment);
        if ($controller === null) {
            return null;
        }

        $action = $this->toMCAAction($module, $controller, $arrSegment);
        if ($action === null) {
            return null;
        }
        return ['module' => $module, 'controller' => $controller, 'action' => $action];
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected function toMCAModule(array $arrSegment): ?string {
        if (empty($arrSegment)) {
            return $this->staticRouter->getDefaultModule();
        }
        $first = $arrSegment[0];
        return $this->staticRouter->moduleExists($first) ? $first: null;
        
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected function toMCAController(?string $module, array $arrSegment): ?string {
        if ($module !== null) { 
            //tại đây đảm bảo rằng $arrSegment[0] === $module
            if (isset($arrSegment[1])) {
                return $this->staticRouter->controllerExistsInModule($module, $arrSegment[1])
                    ? $arrSegment[1]
                    //: null; chưa thể khẳng định là $arrSegment[1] là controller sai hay là other param
                    :$this->staticRouter->getDefaultControllerInModule($module);    
            }
            else {
                return $this->staticRouter->getDefaultControllerInModule($module);
            }
            
        }
        //từ đây trở đi là khuyết module
        $controller = $arrSegment[0] ?? null;

        if ($controller === null) {
            return $this->staticRouter->getDefaultStandaloneController();
        }

        return $this->staticRouter->standaloneControllerExists($controller)
            ? $controller
            : null;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected function toMCAAction(?string $module, string $controller, array $arrSegment): ?string {
        $defaultAction = $this->staticRouter->getDefaultAction($module, $controller);
        $candidate = $module !== null
            ? ($arrSegment[2] ?? null)
            : ($arrSegment[1] ?? null);

        if ($candidate === null) {
            return $defaultAction;
        }
        //từ đây là url có action
        if ($this->staticRouter->actionExists($module, $controller, $candidate)) {
            return $candidate;
        }
        /*tới đây chưa thể khẳng định $candidate là action sai hay là đó là $candidate là other-param
         */
        return $defaultAction;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
}