<?php
namespace Core\Http;
use Core\Http\Request;
use Core\Auth\AuthInfo;
use \InvalidArgumentException;
class RequestAuthContext{
    protected Request $request;
    protected array $arrAuthInfo;
    //đến bước contextRouter->matchUri thì mới tính ra được 3 thành phần dưới này
    protected ?array $arrMCA;
    protected ?array $arrRouteInfo;
    protected ?bool $isProhibitedModule;
    protected ?bool $isProhibitedRole;
    public function __construct(Request $request, array $arrAuthInfo) {
        $this->request    = $request;
        if(!AuthInfo::isValid($arrAuthInfo)){
            throw new InvalidArgumentException('arrAuthInfo có format không chính xác');
        }
        $this->arrAuthInfo  = $arrAuthInfo;
        $this->arrMCA =  null;
        $this->arrRouteInfo =  null;
        $this->isProhibitedModule =  null;
        $this->isProhibitedRole =  null;
    }
    // ----------------------------------------------------------------
    public function request() {
        return $this->request;
    }
    // ----------------------------------------------------------------
    public function authInfo() {
        return $this->arrAuthInfo;
    }
    // ----------------------------------------------------------------
    public function routePath(): ?array {
        return $this->arrMCA;
    }
    // ----------------------------------------------------------------
    public function routeInfo(): ?array {
        return $this->arrRouteInfo;
    }
    // ----------------------------------------------------------------
    public function prohibitedModule(): ?bool {
        return $this->isProhibitedModule;
    }
    // ----------------------------------------------------------------
    public function prohibitedRole(): ?bool {
        return $this->isProhibitedRole;
    }
    // ----------------------------------------------------------------
    //khi chạy contextRouter->matchUri thì lưu thông tin kết của của match['mca'] vào $this->arrMCA
    public function setRoutePath(?array $routePath) {
        $this->arrMCA = $routePath;
    }
    // ----------------------------------------------------------------
    public function setRouteInfo(?array $routeInfo) {
        $this->arrRouteInfo = $routeInfo;
    }
    // ----------------------------------------------------------------
    public function setProhibitedModule(?bool $isProhibitedModule) {
        $this->isProhibitedModule = $isProhibitedModule;
    }
    // ----------------------------------------------------------------
    public function setProhibitedRole(?bool $isProhibitedRole) {
        $this->isProhibitedRole = $isProhibitedRole;
    }
    // ----------------------------------------------------------------
    public function isSetRoutePath() {
        return is_array($this->arrMCA);
    }

    
}