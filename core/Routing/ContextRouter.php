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
        $path = $this->toMCA($arrSegment);
        $strModule = count($path ?? []) === 3 ? $path[0] : null;
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

        return $module === null
            ? [$controller, $action]
            : [$module, $controller, $action];
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected function toMCAModule(array $arrSegment): ?string {
        //$defaultRoute = $this->staticRouter->getDefaultRoute();

        if (empty($arrSegment)) {
            return $this->staticRouter->getDefaultModule();
        }

        $first = $arrSegment[0];
        return $this->staticRouter->moduleExists($first) ? $first: null;
        
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected function toMCAController(?string $module, array $arrSegment): ?string {
        //$defaultRoute = $this->staticRouter->getDefaultRoute();
        //$routes = $defaultRoute['routes'];
        if ($module !== null) {
            if (isset($arrSegment[1])) {
                return $this->staticRouter->controllerExistsInModule($module, $arrSegment[1])
                    ? $arrSegment[1]
                    : null;
            }
            else {
                return $this->staticRouter->getDefaultControllerInModule($module);
            }
            
        }

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

        if ($this->staticRouter->actionExists($module, $controller, $candidate)) {
            return $candidate;
        }

        return $defaultAction;
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