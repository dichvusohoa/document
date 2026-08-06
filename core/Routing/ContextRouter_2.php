<?php
namespace Core\Routing;
use Core\Http\Request;
use Core\Http\HttpException;
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
                'mcao' => null,
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
                'mcao' => $arrMCA,
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
                'mcao' => $arrMCA,
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
                'mcao' => $arrMCA,
                'route_info' => $leaf,
                'auth_policy' => $authPolicy,
                'middlewares' => $middlewares,
                'prohibited_module' => false,
                'prohibited_role' => true
            ];
        }
        return [
            'mcao' => $arrMCA,
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
       if (empty($arrSegment)) {
           return $this->toDefaultMCA();
       }

       $strFirstSegment = $arrSegment[0];
       if ($this->staticRouter->moduleExists($strFirstSegment)) {
           return $this->toModuleMCA($strFirstSegment, $arrSegment);
       }

       if ($this->staticRouter->standaloneControllerExists($strFirstSegment)) {
           return $this->toStandaloneMCA($strFirstSegment, $arrSegment);
       }

       return null;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected function toDefaultMCA(): ?array {
        $strModule = $this->staticRouter->getDefaultModule();

        if ($strModule !== null) {
            $strController = $this->staticRouter
                ->getDefaultControllerInModule($strModule);

            if ($strController === null) {
                return null;
            }

            return $this->buildMCA(
                $strModule,
                $strController,
                $this->staticRouter->getDefaultAction(
                    $strModule,
                    $strController
                )
            );
        }

        $strController = $this->staticRouter
            ->getDefaultStandaloneController();

        if ($strController === null) {
            return null;
        }

        return $this->buildMCA(
            null,
            $strController,
            $this->staticRouter->getDefaultAction(
                null,
                $strController
            )
        );
    } 
    /*---------------------------------------------------------------------------------------------------------------*/
    protected function toModuleMCA(
        string $strModule,
        array $arrSegment
    ): ?array {
        $strDefaultController = $this->staticRouter
            ->getDefaultControllerInModule($strModule);

        if ($strDefaultController === null) {
            return null;
        }

        /*
         * [1] khuyết:
         * dùng default controller và default action.
         */
        if (!isset($arrSegment[1])) {
            return $this->buildMCA(
                $strModule,
                $strDefaultController,
                $this->staticRouter->getDefaultAction(
                    $strModule,
                    $strDefaultController
                )
            );
        }

        /*
         * [1] không phải controller:
         * từ [1] trở đi đều là other params.
         */
        if (
            !$this->staticRouter->controllerExistsInModule(
                $strModule,
                $arrSegment[1]
            )
        ) {
            return $this->buildMCA(
                $strModule,
                $strDefaultController,
                $this->staticRouter->getDefaultAction(
                    $strModule,
                    $strDefaultController
                ),
                array_slice($arrSegment, 1)
            );
        }

        $strController = $arrSegment[1];
        $strDefaultAction = $this->staticRouter
            ->getDefaultAction($strModule, $strController);

        /*
         * [2] khuyết:
         * dùng default action.
         */
        if (!isset($arrSegment[2])) {
            return $this->buildMCA(
                $strModule,
                $strController,
                $strDefaultAction
            );
        }

        /*
         * [2] không phải action:
         * từ [2] trở đi đều là other params.
         */
        if (
            !$this->staticRouter->actionExists(
                $strModule,
                $strController,
                $arrSegment[2]
            )
        ) {
            return $this->buildMCA(
                $strModule,
                $strController,
                $strDefaultAction,
                array_slice($arrSegment, 2)
            );
        }

        /*
         * [2] là action:
         * từ [3] trở đi đều là other params.
         */
        return $this->buildMCA(
            $strModule,
            $strController,
            $arrSegment[2],
            array_slice($arrSegment, 3)
        );
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected function toStandaloneMCA(
        string $strController,
        array $arrSegment
    ): ?array {
        $strDefaultAction = $this->staticRouter
            ->getDefaultAction(null, $strController);

        /*
         * [1] khuyết:
         * dùng default action.
         */
        if (!isset($arrSegment[1])) {
            return $this->buildMCA(
                null,
                $strController,
                $strDefaultAction
            );
        }

        /*
         * [1] không phải action:
         * từ [1] trở đi đều là other params.
         */
        if (
            !$this->staticRouter->actionExists(
                null,
                $strController,
                $arrSegment[1]
            )
        ) {
            return $this->buildMCA(
                null,
                $strController,
                $strDefaultAction,
                array_slice($arrSegment, 1)
            );
        }

        /*
         * [1] là action:
         * từ [2] trở đi đều là other params.
         */
        return $this->buildMCA(
            null,
            $strController,
            $arrSegment[1],
            array_slice($arrSegment, 2)
        );
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected function buildMCA(
        ?string $strModule,
        string $strController,
        ?string $strAction,
        array $arrOtherParam = []
    ): ?array {
        if ($strAction === null) {
            return null;
        }

        return [
            'module'       => $strModule,
            'controller'   => $strController,
            'action'       => $strAction,
            'other_params' => array_values($arrOtherParam)
        ];
    }
}