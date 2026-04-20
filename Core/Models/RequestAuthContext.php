<?php
namespace Core\Models;
use Core\Models\Request;
use Core\Models\Response; 
use Core\Models\Auth\AuthInfo;
use \InvalidArgumentException;
class RequestAuthContext{
    protected Request $request;
    protected array $arrAuthInfo;
    //đến bước router->matchUri thì mới tính ra được thành phần này
    protected ?array $arrRouteTMCA;
    public function __construct(Request $request, array $arrAuthInfo) {
        $this->request    = $request;
        if(!AuthInfo::isValid($arrAuthInfo)){
            throw new InvalidArgumentException('arrAuthInfo có format không chính xác');
        }
        $this->arrAuthInfo  = $arrAuthInfo;
        $this->arrRouteTMCA =  null;
    }
    // ----------------------------------------------------------------
    public function resquest() {
        return $this->request;
    }
    // ----------------------------------------------------------------
    public function authInfo() {
        return $this->arrAuthInfo;
    }
    public function routePath(): array {
        return $this->arrRouteTMCA;
    }
    // ----------------------------------------------------------------
    //khi chạy outer->matchUri thì lưu thông tin kết của của match['path'] vào $this->arrRouteTMCA
    public function setRoutePath(array $routePath) {
        $this->arrRouteTMCA = $routePath;
    }
    // ----------------------------------------------------------------
    public function isSetRoutePath() {
        return is_array($this->arrRouteTMCA);
    }

    
}