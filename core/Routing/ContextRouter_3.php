<?php
namespace Core\Routing;

use Core\Http\HttpException;
use Core\Http\Request;

class ContextRouter
{
    /*
    |--------------------------------------------------------------------------
    | Match-result fields
    |--------------------------------------------------------------------------
    */
    protected const FIELD_MCAO               = 'mcao';
    protected const FIELD_ROUTE_INFO         = 'route_info';
    protected const FIELD_AUTH_POLICY        = 'auth_policy';
    protected const FIELD_MIDDLEWARES         = 'middlewares';
    protected const FIELD_PROHIBITED_MODULE  = 'prohibited_module';
    protected const FIELD_PROHIBITED_ROLE    = 'prohibited_role';

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
    | Middleware matching fields
    |--------------------------------------------------------------------------
    */
    protected const FIELD_ROLE   = 'role';
    protected const FIELD_METHOD = 'method';

    /*
    |--------------------------------------------------------------------------
    | Runtime context
    |--------------------------------------------------------------------------
    */
    protected array $arrEnableModule;
    protected array $arrUserRole;

    protected StaticRouter $staticRouter;

    /*---------------------------------------------------------------------------------------------------------------*/
    public function __construct(
        array $arrEnableModule,
        array $arrUserRole,
        StaticRouter $staticRouter
    ) {
        $this->arrEnableModule = $arrEnableModule;
        $this->arrUserRole     = $arrUserRole;
        $this->staticRouter    = $staticRouter;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    public function getUserRoles(): array
    {
        return $this->arrUserRole;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    public function getEnableModules(): array
    {
        return $this->arrEnableModule;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    public function setUserRoles(array $arrUserRole): void
    {
        $this->arrUserRole = $arrUserRole;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    public function setEnableModules(array $arrEnableModule): void
    {
        $this->arrEnableModule = $arrEnableModule;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    public function matchUri(Request $request): array
    {
        $arrSegment = $request->segmentUri();

        if ($arrSegment === null) {
            throw new HttpException(404, 'Not Found');
        }

        $arrMCAO = $this->resolveMCAO($arrSegment);

        /*
         * Không nhận diện được prefix route.
         */
        if ($arrMCAO === null) {
            return $this->buildMatchResult();
        }

        $arrStaticPath = $this->buildStaticPath($arrMCAO);
        $arrRouteInfo  = $this->staticRouter->getRouteInfo($arrStaticPath);

        /*
         * Nhánh phòng thủ: MCA đã nhận diện được nhưng không tìm thấy RouteInfo.
         */
        if ($arrRouteInfo === null) {
            return $this->buildMatchResult($arrMCAO);
        }

        $arrAccessibleRole = $this->calculateAccessibleRole($arrRouteInfo);

        $arrMiddleware = $this->resolveMiddlewares(
            $arrStaticPath,
            $arrRouteInfo,
            $arrAccessibleRole
        );

        $arrAuthPolicy = $this->resolveAuthPolicy(
            $arrMCAO,
            $arrRouteInfo
        );

        $strModule = $arrMCAO[self::FIELD_MODULE];

        if ($this->isModuleProhibited($strModule)) {
            return $this->buildMatchResult(
                $arrMCAO,
                $arrRouteInfo,
                $arrAuthPolicy,
                $arrMiddleware,
                true,
                null
            );
        }

        if (empty($arrAccessibleRole)) {
            return $this->buildMatchResult(
                $arrMCAO,
                $arrRouteInfo,
                $arrAuthPolicy,
                $arrMiddleware,
                false,
                true
            );
        }

        return $this->buildMatchResult(
            $arrMCAO,
            $arrRouteInfo,
            $arrAuthPolicy,
            $arrMiddleware,
            false,
            false
        );
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected function buildMatchResult(
        ?array $arrMCAO = null,
        ?array $arrRouteInfo = null,
        ?array $arrAuthPolicy = null,
        ?array $arrMiddleware = null,
        ?bool $isProhibitedModule = null,
        ?bool $isProhibitedRole = null
    ): array {
        return [
            self::FIELD_MCAO              => $arrMCAO,
            self::FIELD_ROUTE_INFO        => $arrRouteInfo,
            self::FIELD_AUTH_POLICY       => $arrAuthPolicy,
            self::FIELD_MIDDLEWARES        => $arrMiddleware,
            self::FIELD_PROHIBITED_MODULE => $isProhibitedModule,
            self::FIELD_PROHIBITED_ROLE   => $isProhibitedRole
        ];
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected function buildStaticPath(array $arrMCAO): array
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
    protected function resolveMiddlewares(
        array $arrStaticPath,
        array $arrRouteInfo,
        array $arrAccessibleRole
    ): array {
        $arrMCARMe = MCARMeInfo::createEmpty();

        $arrMCARMe[self::FIELD_ROLE] =
            $arrAccessibleRole;

        $arrMCARMe[self::FIELD_METHOD] =
            $arrRouteInfo[RouteInfo::FIELD_METHOD];

        if (count($arrStaticPath) === 3) {
            [
                $arrMCARMe[self::FIELD_MODULE],
                $arrMCARMe[self::FIELD_CONTROLLER],
                $arrMCARMe[self::FIELD_ACTION]
            ] = $arrStaticPath;
        } else {
            [
                $arrMCARMe[self::FIELD_CONTROLLER],
                $arrMCARMe[self::FIELD_ACTION]
            ] = $arrStaticPath;
        }

        return $this->staticRouter->matchMiddlewares($arrMCARMe);
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected function resolveAuthPolicy(
        array $arrMCAO,
        array $arrRouteInfo
    ): ?array {
        if (
            $arrRouteInfo[RouteInfo::FIELD_ROUTE_TYPE]
            !== RouteInfo::ROUTE_TYPE_AUTHENTICATION
        ) {
            return null;
        }

        $strController = $arrMCAO[self::FIELD_CONTROLLER];

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
    protected function resolveMCAO(array $arrSegment): ?array
    {
        if (empty($arrSegment)) {
            return $this->resolveDefaultMCAO();
        }

        $strFirstSegment = $arrSegment[0];

        if ($this->staticRouter->moduleExists($strFirstSegment)) {
            return $this->resolveModuleMCAO(
                $strFirstSegment,
                $arrSegment
            );
        }

        if (
            $this->staticRouter
                ->standaloneControllerExists($strFirstSegment)
        ) {
            return $this->resolveStandaloneMCAO(
                $strFirstSegment,
                $arrSegment
            );
        }

        return null;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected function resolveDefaultMCAO(): ?array
    {
        $strModule = $this->staticRouter->getDefaultModule();

        if ($strModule !== null) {
            return $this->resolveDefaultModuleMCAO($strModule);
        }

        return $this->resolveDefaultStandaloneMCAO();
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected function resolveDefaultModuleMCAO(
        string $strModule,
        array $arrOtherParam = []
    ): ?array {
        $strController = $this->staticRouter
            ->getDefaultControllerInModule($strModule);

        if ($strController === null) {
            return null;
        }

        return $this->buildMCAOWithDefaultAction(
            $strModule,
            $strController,
            $arrOtherParam
        );
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected function resolveDefaultStandaloneMCAO(): ?array
    {
        $strController = $this->staticRouter
            ->getDefaultStandaloneController();

        if ($strController === null) {
            return null;
        }

        return $this->buildMCAOWithDefaultAction(
            null,
            $strController
        );
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected function resolveModuleMCAO(
        string $strModule,
        array $arrSegment
    ): ?array {
        /*
         * [1] khuyết:
         * dùng default controller và default action.
         */
        if (!isset($arrSegment[1])) {
            return $this->resolveDefaultModuleMCAO($strModule);
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
            return $this->resolveDefaultModuleMCAO(
                $strModule,
                array_slice($arrSegment, 1)
            );
        }

        return $this->resolveActionMCAO(
            $strModule,
            $strControllerCandidate,
            $arrSegment,
            2
        );
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected function resolveStandaloneMCAO(
        string $strController,
        array $arrSegment
    ): ?array {
        return $this->resolveActionMCAO(
            null,
            $strController,
            $arrSegment,
            1
        );
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected function resolveActionMCAO(
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
            return $this->buildMCAOWithDefaultAction(
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
            return $this->buildMCAOWithDefaultAction(
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
    protected function buildMCAOWithDefaultAction(
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