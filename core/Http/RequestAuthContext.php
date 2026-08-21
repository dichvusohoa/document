<?php

namespace Core\Http;

use Core\Auth\AuthResponse;
use InvalidArgumentException;

class RequestAuthContext
{
    protected Request $request;

    protected array $arrAuthResponse;

    /*
     * Các property dưới đây chỉ có giá trị
     * sau khi ContextRouter hoàn thành việc match request.
     */
    protected ?array $arrMCAO = null;
    protected ?array $arrRouteInfo = null;
    protected ?array $arrContextAcceptedRole = null;//
    protected ?bool $isProhibitedModule = null;
    protected ?bool $isProhibitedRole = null;

    /*---------------------------------------------------------------------------------------------------------------*/

    public function __construct(
        Request $request,
        array $arrAuthResponse
    ) {
        if (!AuthResponse::isValid($arrAuthResponse)) {
            throw new InvalidArgumentException(
                'arrAuthResponse có format không chính xác.'
            );
        }

        $this->request = $request;
        $this->arrAuthResponse = $arrAuthResponse;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    public function request(): Request
    {
        return $this->request;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public function auth(): array
    {
        return $this->arrAuthResponse;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public function mcao(): ?array
    {
        return $this->arrMCAO;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public function routeInfo(): ?array
    {
        return $this->arrRouteInfo;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    /*chú ý kêt quả trả về có định dạng [strRoleCode => strDisplayName, ....]*/
    public function userRoles(): array{
        return $this->arrAuthResponse['data']['roles'];
    }    
    /*---------------------------------------------------------------------------------------------------------------*/
    public function contextAcceptedRoles(): ?array{
        return $this->arrContextAcceptedRole;
    } 
    /*---------------------------------------------------------------------------------------------------------------*/
    public function prohibitedModule(): ?bool
    {
        return $this->isProhibitedModule;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public function prohibitedRole(): ?bool
    {
        return $this->isProhibitedRole;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public function setRouteMatchResult(
        array $arrMCAO,
        array $arrRouteInfo,
        ?array $arrContextAcceptedRole,    
        bool $isProhibitedModule,
        bool $isProhibitedRole
    ): void {
        $this->arrMCAO = $arrMCAO;
        $this->arrRouteInfo = $arrRouteInfo;
        $this->arrContextAcceptedRole = $arrContextAcceptedRole;
        $this->isProhibitedModule = $isProhibitedModule;
        $this->isProhibitedRole = $isProhibitedRole;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    public function hasRouteMatchResult(): bool
    {
        return $this->arrRouteInfo !== null;
    }
}