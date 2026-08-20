<?php
namespace Core\Routing;
use Core\Http\Request;
use Core\Http\Session;
use LogicException;
use Core\Http\HttpException;
class ContextRouter
{
    /*vì applicaton có thể không có module => $arrEnabledModule === null*/
    protected ?array $arrEnabledModule;
    protected array $arrUserRole;
    protected StaticRouter $staticRouter;

    /*---------------------------------------------------------------------------------------------------------------*/
    public function __construct(
        ?array $arrEnabledModule,
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
    public function getEnabledModules(): ?array
    {
        return $this->arrEnabledModule;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    public function setUserRoles(array $arrUserRole): void
    {
        $this->arrUserRole = $arrUserRole;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public function setEnabledModules(?array $arrEnabledModule): void
    {
        $this->arrEnabledModule = $arrEnabledModule;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected function calculateContextAcceptedRoles(
        array $arrMCAO,
        array $arrRouteInfo
    ): ?array {
        //nhánh route bussiness thì không cần thông tin này
        if (
            $arrRouteInfo[RouteInfo::FIELD_ROUTE_TYPE]
            === RouteInfo::ROUTE_TYPE_BUSINESS
        ) {
            return null;
        }

        $authRegistry = $this->staticRouter->createAuthRegistry();

        $strController =
            $arrMCAO[MCAOInfo::FIELD_CONTROLLER];

        $arrAuthPolicy = $authRegistry->getAuthPolicy($strController);
        $strIntendedUrl = Session::get('intended_url');
        $arrIntendedRole = Session::get('intended_roles');
        /*Phải đảm bảo rằng 
        Session::get('intended_url') và Session::get('intended_roles')
        phải đồng thời bằng null hoặc khác null
        */
        if (
            ($strIntendedUrl === null) !== ($arrIntendedRole === null)
        ) {
            throw new LogicException(
                'Session intended_url và intended_roles không đồng bộ.'
            );
        }
        if (is_array($arrIntendedRole)){        
            $arrContextAcceptedRole = array_values(
                array_intersect(
                    $arrAuthPolicy[AuthRegistry::FIELD_ACCEPTED_ROLES],
                    $arrIntendedRole
                )
            );
            if ($arrContextAcceptedRole !== []) {
                return $arrContextAcceptedRole;
            }

            /*
             * Authentication route hiện tại không thể phục vụ
             * intended business context lưu trữ trong Session
             * - không thể redirect về url trong Session sau khi login thành công
             * Bỏ intended context và thực hiện login thông thường.
             */
            Session::remove('intended_url');
            Session::remove('intended_roles');
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
        $arrContextAcceptedRole = $this->calculateContextAcceptedRoles($arrMCAO, $arrRouteInfo);  
        if (
            $arrRouteInfo[RouteInfo::FIELD_ROUTE_TYPE]
                === RouteInfo::ROUTE_TYPE_AUTHENTICATION
            && $arrContextAcceptedRole === []
        ) {
            throw new LogicException(
                'Authentication route không có context accepted role bằng empty là không phù hợp.'
            );
        }
        $arrContextRouteInfo[ContextRouteInfo::FIELD_CONTEXT_ACCEPTED_ROLES] = $arrContextAcceptedRole;
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
        /*
         * Route không thuộc module.
         */
        if ($strModule === null) {
            return false;
        }
        //từ đây trở xuống là $strModule !== null
        /*
         * Có route thuộc module nhưng application lại được xác định
         * là bài toán không có module ($this->arrEnabledModule === null)
         *
         * Đây là trạng thái cấu hình không nhất quán.
         */
        if ($this->arrEnabledModule === null) {
            throw new LogicException(
                "Route {$strModule} khác null nhưng hệ thống đang cấu hình không có module"
            );
        }

        return !in_array(
            $strModule,
            $this->arrEnabledModule,
            true
        );
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