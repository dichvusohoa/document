<?php
namespace Core\Models;
use Core\Models\Request;
use Core\Models\Response; 
use Core\Models\Auth\AuthInfo;
use \InvalidArgumentException;
class RequestAuthContext{
    protected Request $request;
    protected array $arrAuthInfo;
    //đến bước contextRouter->matchUri thì mới tính ra được 3 thành phần dưới này
    protected ?array $arrRouteTMCA;
    protected ?bool $isProhibitedModule;
    protected ?bool $isProhibitedRole;
    public function __construct(Request $request, array $arrAuthInfo) {
        $this->request    = $request;
        if(!AuthInfo::isValid($arrAuthInfo)){
            throw new InvalidArgumentException('arrAuthInfo có format không chính xác');
        }
        $this->arrAuthInfo  = $arrAuthInfo;
        $this->arrRouteTMCA =  null;
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
        return $this->arrRouteTMCA;
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
    //khi chạy contextRouter->matchUri thì lưu thông tin kết của của match['path'] vào $this->arrRouteTMCA
    public function setRoutePath(?array $routePath) {
        $this->arrRouteTMCA = $routePath;
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
        return is_array($this->arrRouteTMCA);
    }

    
}