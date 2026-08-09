<?php
namespace Core\Routing;

use Core\Http\HttpException;
use Core\Http\Request;

class ContextRouter
{
   
    /*
    |--------------------------------------------------------------------------
    | MCAO fields
    |--------------------------------------------------------------------------
    */
    protected const FIELD_MODULE       = 'module';
    protected const FIELD_CONTROLLER   = 'controller';
    protected const FIELD_ACTION       = 'action';
    protected const FIELD_OTHER_PARAMS = 'other_params';
   
    /*
    |--------------------------------------------------------------------------
    | Runtime context
    |--------------------------------------------------------------------------
    */
    protected array $arrEnabledModule;
    protected array $arrUserRole;
    protected StaticRouter $staticRouter;

    /*---------------------------------------------------------------------------------------------------------------*/
    public function __construct(
        array $arrEnabledModule,
        array $arrUserRole,
        StaticRouter $staticRouter
    ) {
        $this->arrEnableModule = $arrEnabledModule;
        $this->arrUserRole     = $arrUserRole;
        $this->staticRouter    = $staticRouter;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    public function getUserRoles(): array
    {
        return $this->arrUserRole;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    public function getEnabledModules(): array
    {
        return $this->arrEnableModule;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    public function setUserRoles(array $arrUserRole): void
    {
        $this->arrUserRole = $arrUserRole;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    public function setEnabledModules(array $arrEnabledModule): void
    {
        $this->arrEnableModule = $arrEnabledModule;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    public function matchUri(Request $request): array
    {
        $arrSegment = $request->segmentUri();

        if ($arrSegment === null) {
            throw new HttpException(404, 'Not Found');
        }

        $arrMCAO = $this->toMCAO($arrSegment);

        /*
         * Không nhận diện được prefix route.
         */
        $arrContextRouteInfo = ContextRouteInfo::createEmpty();
        if ($arrMCAO === null) {
            //Tới đây nghĩa là $arrContextRouteInfo['mcao'] === null
            return $arrContextRouteInfo;
        }

        $arrMCA = $this->toMCA($arrMCAO);
        $arrRouteInfo  = $this->staticRouter->getRouteInfo($arrMCA);

        /*
         * Nhánh phòng thủ: MCA đã nhận diện được nhưng không tìm thấy RouteInfo.
         */
        $arrContextRouteInfo[ContextRouteInfo::FIELD_MCAO] = $arrMCAO;
        if ($arrRouteInfo === null) {
            //Tới đây nghĩa là $arrContextRouteInfo['route_info'] === null
            return $arrContextRouteInfo;
        }
        //Từ đây trở đi thì $arrContextRouteInfo['mcao'] và $arrContextRouteInfo['route_info'] mới khác null
        $arrAccessibleRole = $this->calculateAccessibleRole($arrRouteInfo);

        $arrMiddleware = $this->buildMiddlewares(
            $arrMCA,
            $arrRouteInfo[RouteInfo::FIELD_METHOD],
            $arrAccessibleRole
        );

        $arrAuthPolicy = $this->buildAuthPolicy(
            $arrMCAO[self::FIELD_CONTROLLER],
            $arrRouteInfo[RouteInfo::FIELD_ROUTE_TYPE]
        );

        $strModule = $arrMCAO[self::FIELD_MODULE];
        
        $arrContextRouteInfo[ContextRouteInfo::FIELD_ROUTE_INFO] = $arrRouteInfo;
        $arrContextRouteInfo[ContextRouteInfo::FIELD_AUTH_POLICY] = $arrAuthPolicy;
        $arrContextRouteInfo[ContextRouteInfo::FIELD_MIDDLEWARES] = $arrMiddleware;
        
        if ($this->isModuleProhibited($strModule)) {
            $arrContextRouteInfo[ContextRouteInfo::FIELD_PROHIBITED_MODULE] = true;
            $arrContextRouteInfo[ContextRouteInfo::FIELD_PROHIBITED_ROLE] = null;
            return $arrContextRouteInfo;
        }
        $arrContextRouteInfo[ContextRouteInfo::FIELD_PROHIBITED_MODULE] = false;
        if (empty($arrAccessibleRole)) {
            $arrContextRouteInfo[ContextRouteInfo::FIELD_PROHIBITED_ROLE] = true;
            return $arrContextRouteInfo;
        }
        $arrContextRouteInfo[ContextRouteInfo::FIELD_PROHIBITED_ROLE] = false;
        return $arrContextRouteInfo;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    //chuyển định dạng $arrMCAO sang định dạng [$strModule, $strController, $strAction]
    //hoặc định dạng [$strController, $strAction] khi khuyết module. Định dạng simple này thường dùng
    //thường dùng cho truy xuất StaticRouter
    protected function toMCA(array $arrMCAO): array
    {
        $strModule     = $arrMCAO[self::FIELD_MODULE];
        $strController = $arrMCAO[self::FIELD_CONTROLLER];
        $strAction     = $arrMCAO[self::FIELD_ACTION];

        return $strModule === null
            ? [$strController, $strAction]
            : [$strModule, $strController, $strAction];
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected function calculateAccessibleRole(array $arrRouteInfo): array
    {
        return array_values(
            array_intersect(
                $this->arrUserRole,
                $arrRouteInfo[RouteInfo::FIELD_ROLES]
            )
        );
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected function isModuleProhibited(?string $strModule): bool
    {
        return $strModule !== null
            && !in_array($strModule, $this->arrEnableModule, true);
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected function buildMiddlewares(
        array $arrMCA,
        string $strMethod,
        array $arrAccessibleRole
    ): array {
        //$arrMCA cung cấp MCA, $arrAccessibleRole cung cấp R
        $arrMCARMe = MCARMeInfo::createEmpty();
        $arrMCARMe[MCARMeInfo::FIELD_METHOD] = $strMethod;
        $arrMCARMe[MCARMeInfo::FIELD_ROLE] = $arrAccessibleRole;

        if (count($arrMCA) === 3) {
            [
                $arrMCARMe[MCARMeInfo::FIELD_MODULE],
                $arrMCARMe[MCARMeInfo::FIELD_CONTROLLER],
                $arrMCARMe[MCARMeInfo::FIELD_ACTION]
            ] = $arrMCA;
        } else {
            [
                $arrMCARMe[MCARMeInfo::FIELD_CONTROLLER],
                $arrMCARMe[MCARMeInfo::FIELD_ACTION]
            ] = $arrMCA;
        }

        return $this->staticRouter->matchMiddlewares($arrMCARMe);
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected function buildAuthPolicy(
        string $strController,
        string $strRouteType
    ): ?array {
        if (
            $strRouteType
            !== RouteInfo::ROUTE_TYPE_AUTHENTICATION
        ) {
            return null;
        }

        

        /*
         * Theo invariant của StaticRouter:
         * authentication controller hợp lệ luôn có AuthRegistry entry tương ứng.
         */
        $arrAuthTemplate = $this->staticRouter
            ->createAuthRegistry()
            ->getAuthPolicy($strController);

        return [
            AuthRegistry::FIELD_MAX_FAIL_COUNT =>
                $arrAuthTemplate[AuthRegistry::FIELD_MAX_FAIL_COUNT],

            AuthRegistry::FIELD_TURNSTILE =>
                $arrAuthTemplate[AuthRegistry::FIELD_TURNSTILE],

            AuthRegistry::FIELD_REMEMBER_COOKIE =>
                $arrAuthTemplate[AuthRegistry::FIELD_REMEMBER_COOKIE],

            AuthRegistry::FIELD_REMEMBER_EXPIRE =>
                $arrAuthTemplate[AuthRegistry::FIELD_REMEMBER_EXPIRE]
        ];
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected function toMCAO(array $arrSegment): ?array
    {
        if (empty($arrSegment)) {
            return $this->buildDefaultMCAO();
        }

        $strFirstSegment = $arrSegment[0];

        if ($this->staticRouter->moduleExists($strFirstSegment)) {
            return $this->toMCAOWithM(
                $strFirstSegment,
                $arrSegment
            );
        }

        if (
            $this->staticRouter
                ->standaloneControllerExists($strFirstSegment)
        ) {
            return $this->toStCAOWithC(
                $strFirstSegment,
                $arrSegment
            );
        }

        return null;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected function buildDefaultMCAO(): ?array
    {
        $strModule = $this->staticRouter->getDefaultModule();

        if ($strModule !== null) {
            return $this->buildDefaultMCAOWithM($strModule);
        }

        return $this->buildDefaultStCAO();
        
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    //là step trung gian để buildDefaultMCAO khi đã có module
    protected function buildDefaultMCAOWithM(
        string $strModule,
        array $arrOtherParam = []
    ): ?array {
        $strController = $this->staticRouter
            ->getDefaultControllerInModule($strModule);

        if ($strController === null) {
            return null;
        }

        return $this->buildDefaultMCAOWithMC(
            $strModule,
            $strController,
            $arrOtherParam
        );
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    //là buildDefaultMCAO trong trường hợp đặc biệt khi không có module
    protected function buildDefaultStCAO(): ?array
    {
        $strController = $this->staticRouter
            ->getDefaultStandaloneController();

        if ($strController === null) {
            return null;
        }

        return $this->buildDefaultMCAOWithMC(
            null,
            $strController
        );
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected function toMCAOWithM(
        string $strModule,
        array $arrSegment
    ): ?array {
        /*
         * [1] khuyết:
         * dùng default controller và default action.
         */
        if (!isset($arrSegment[1])) {
            return $this->buildDefaultMCAOWithM($strModule);
        }

        $strControllerCandidate = $arrSegment[1];

        /*
         * [1] không phải controller:
         * dùng default controller + default action;
         * từ [1] trở đi là other params.
         */
        if (
            !$this->staticRouter->controllerExistsInModule(
                $strModule,
                $strControllerCandidate
            )
        ) {
            return $this->buildDefaultMCAOWithM(
                $strModule,
                array_slice($arrSegment, 1)
            );
        }

        return $this->toMCAOWithMC(
            $strModule,
            $strControllerCandidate,
            $arrSegment,
            2
        );
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected function toStCAOWithC(
        string $strController,
        array $arrSegment
    ): ?array {
        return $this->toMCAOWithMC(
            null,
            $strController,
            $arrSegment,
            1
        );
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected function toMCAOWithMC(
        ?string $strModule,
        string $strController,
        array $arrSegment,
        int $intActionPos
    ): ?array {
        /*
         * Action segment khuyết:
         * dùng default action.
         */
        if (!isset($arrSegment[$intActionPos])) {
            return $this->buildDefaultMCAOWithMC(
                $strModule,
                $strController
            );
        }

        $strActionCandidate = $arrSegment[$intActionPos];

        /*
         * Segment hiện tại không phải action:
         * dùng default action;
         * từ segment hiện tại trở đi là other params.
         */
        if (
            !$this->staticRouter->actionExists(
                $strModule,
                $strController,
                $strActionCandidate
            )
        ) {
            return $this->buildDefaultMCAOWithMC(
                $strModule,
                $strController,
                array_slice($arrSegment, $intActionPos)
            );
        }

        /*
         * Segment hiện tại là action:
         * các segment phía sau là other params.
         */
        return $this->buildMCAO(
            $strModule,
            $strController,
            $strActionCandidate,
            array_slice($arrSegment, $intActionPos + 1)
        );
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected function buildDefaultMCAOWithMC(
        ?string $strModule,
        string $strController,
        array $arrOtherParam = []
    ): ?array {
        $strAction = $this->staticRouter->getDefaultAction(
            $strModule,
            $strController
        );

        return $this->buildMCAO(
            $strModule,
            $strController,
            $strAction,
            $arrOtherParam
        );
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected function buildMCAO(
        ?string $strModule,
        string $strController,
        ?string $strAction,
        array $arrOtherParam = []
    ): ?array {
        if ($strAction === null) {
            return null;
        }

        return [
            self::FIELD_MODULE       => $strModule,
            self::FIELD_CONTROLLER   => $strController,
            self::FIELD_ACTION       => $strAction,
            self::FIELD_OTHER_PARAMS => array_values($arrOtherParam)
        ];
    }
}