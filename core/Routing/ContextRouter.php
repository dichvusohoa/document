<?php
namespace Core\Routing;
use Core\Http\Request;
class ContextRouter
{
   
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
        $mcaBasic = $this->staticRouter->createMCABasic();
        $parser =  new UrlToMCAOParser($mcaBasic);
        $arrMCAO = $parser->parse($request->uri());
        $arrContextRouteInfo = ContextRouteInfo::createEmpty();
        /*
         * Không nhận diện được prefix route.
         */
        if ($arrMCAO === null) {
            //Tới đây nghĩa là $arrContextRouteInfo['mcao'] === null
            //return $arrContextRouteInfo;
            throw new HttpException(404, 'ContextRouter chạy định tuyến (matchUri) trả về kết quả đường dẫn không hợp lệ');
        }

        $arrMCA = MCAOInfo::toMCAPath($arrMCAO);
        $arrRouteInfo  = $this->staticRouter->getRouteInfo($arrMCA);

        /*
         * Nhánh phòng thủ: MCAO đã nhận diện được nhưng không tìm thấy RouteInfo.
         */
        $arrContextRouteInfo[ContextRouteInfo::FIELD_MCAO] = $arrMCAO;
        if ($arrRouteInfo === null) {
            //Tới đây nghĩa là $arrContextRouteInfo['route_info'] === null
            //return $arrContextRouteInfo;
            throw new HttpException(404, 'ContextRouter chạy định tuyến (matchUri) trả về dữ liệu null');
        }
        //Từ đây trở đi thì $arrContextRouteInfo['mcao'] và $arrContextRouteInfo['route_info'] mới khác null
        $arrAccessibleRole = $this->calculateAccessibleRole($arrRouteInfo);

        $arrMiddleware = $this->buildMiddlewares(
            $arrMCA,
            $arrRouteInfo[RouteInfo::FIELD_METHOD],
            $arrAccessibleRole
        );

        $arrAuthPolicy = $this->buildAuthPolicy(
            $arrMCAO[MCAOInfo::FIELD_CONTROLLER],
            $arrRouteInfo[RouteInfo::FIELD_ROUTE_TYPE]
        );

        $strModule = $arrMCAO[MCAOInfo::FIELD_MODULE];
        
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
}