<?php
namespace Core\Routing;
use Core\Http\Request;
use Core\Http\Session;
use Core\Http\HttpException;
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
        $this->arrEnabledModule = $arrEnabledModule;
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
        return $this->arrEnabledModule;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    public function setUserRoles(array $arrUserRole): void
    {
        $this->arrUserRole = $arrUserRole;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    public function setEnabledModules(array $arrEnabledModule): void
    {
        $this->arrEnabledModule = $arrEnabledModule;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected function calculateContextAcceptedRoles(
        array $arrMCAO,
        array $arrRouteInfo
    ): ?array {
        if (
            $arrRouteInfo[RouteInfo::FIELD_ROUTE_TYPE]
            === RouteInfo::ROUTE_TYPE_BUSINESS
        ) {
            return null;
        }

        $authRegistry = $this->staticRouter->createAuthRegistry();

        $strController =
            $arrMCAO[MCAOInfo::FIELD_CONTROLLER];

        $arrAuthPolicy =
            $authRegistry->getAuthPolicy($strController);

        $arrIntendedRole = Session::get('intended_roles');

        if (
            Session::get('intended_url')
            && is_array($arrIntendedRole)
        ) {
            return array_values(
                array_intersect(
                    $arrAuthPolicy[AuthRegistry::FIELD_ACCEPTED_ROLES],
                    $arrIntendedRole
                )
            );
        }

        return $arrAuthPolicy[
            AuthRegistry::FIELD_ACCEPTED_ROLES
        ];
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
            throw new HttpException(404, 'ContextRouter chạy định tuyến (matchUri) trả về kết quả đường dẫn không hợp lệ');
        }

        $arrMCA = MCAOInfo::toMCAPath($arrMCAO);
        $arrRouteInfo  = $this->staticRouter->getRouteInfo($arrMCA);

        /*
         * Nhánh phòng thủ: MCAO đã nhận diện được nhưng không tìm thấy RouteInfo.
         */
        $arrContextRouteInfo[ContextRouteInfo::FIELD_MCAO] = $arrMCAO;
        if ($arrRouteInfo === null) {
            throw new HttpException(404, 'ContextRouter chạy định tuyến (matchUri) trả về dữ liệu null');
        }
        //Từ đây trở đi thì $arrContextRouteInfo['mcao'] và $arrContextRouteInfo['route_info'] mới khác null
        $arrEffectiveUserRolesAtRoute = $this->effectiveUserRolesAtRoute($arrRouteInfo);
        $arrMiddleware = $this->buildMiddlewares(
            $arrMCA,
            $arrRouteInfo[RouteInfo::FIELD_METHOD],
            $arrEffectiveUserRolesAtRoute
        );

        $strModule = $arrMCAO[MCAOInfo::FIELD_MODULE];
        
        $arrContextRouteInfo[ContextRouteInfo::FIELD_ROUTE_INFO] = $arrRouteInfo;
        $arrContextRouteInfo[ContextRouteInfo::FIELD_CONTEXT_ACCEPTED_ROLES] =
        $this->calculateContextAcceptedRoles($arrMCAO, $arrRouteInfo);        
        $arrContextRouteInfo[ContextRouteInfo::FIELD_MIDDLEWARES] = $arrMiddleware;
        
        if ($this->isModuleProhibited($strModule)) {
            $arrContextRouteInfo[ContextRouteInfo::FIELD_PROHIBITED_MODULE] = true;
            $arrContextRouteInfo[ContextRouteInfo::FIELD_PROHIBITED_ROLE] = null;
            return $arrContextRouteInfo;
        }
        $arrContextRouteInfo[ContextRouteInfo::FIELD_PROHIBITED_MODULE] = false;
        if (empty($arrEffectiveUserRolesAtRoute)) {
            $arrContextRouteInfo[ContextRouteInfo::FIELD_PROHIBITED_ROLE] = true;
            return $arrContextRouteInfo;
        }
        $arrContextRouteInfo[ContextRouteInfo::FIELD_PROHIBITED_ROLE] = false;
        return $arrContextRouteInfo;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected function effectiveUserRolesAtRoute(array $arrRouteInfo): array
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
            && !in_array($strModule, $this->arrEnabledModule, true);
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected function buildMiddlewares(
        array $arrMCA,
        string $strMethod,
        array $arrEffectiveUserRolesAtRoute
    ): array {
        //$arrMCA cung cấp MCA, $arrEffectiveUserRolesAtRoute cung cấp R
        $arrMCARMe = MCARMeInfo::createEmpty();
        $arrMCARMe[MCARMeInfo::FIELD_METHOD] = $strMethod;
        $arrMCARMe[MCARMeInfo::FIELD_ROLE] = $arrEffectiveUserRolesAtRoute;

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
        $middlewareRegistry = $this->staticRouter->createMiddlewareRegistry();
        return $middlewareRegistry->matchMiddlewares($arrMCARMe);
    }

    /*---------------------------------------------------------------------------------------------------------------*/
}