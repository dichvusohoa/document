<?php
namespace Core\Models;
use Core\Models\Request;
use Core\Models\Response; 
use Core\Models\Auth\AuthInfo;
use \InvalidArgumentException;
class RequestAuthContext{
    protected Request $request;
    protected array $arrAuthInfo;
    public function __construct(Request $request, array $arrAuthInfo) {
        $this->request    = $request;
        if(!AuthInfo::isValid($arrAuthInfo)){
            throw new InvalidArgumentException('arrAuthInfo có format không chính xác');
        }
        $this->arrAuthInfo  = $arrAuthInfo;
    }
    // ----------------------------------------------------------------
    public function resquest() {
        return $this->request;
    }
    // ----------------------------------------------------------------
    public function authInfo() {
        return $this->arrAuthInfo;
    }
}