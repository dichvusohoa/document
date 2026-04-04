<?php
namespace Core\Models\Auth;
use Core\Models\Response;
/*prefix authSrvc*/
class AuthService{
    protected DbService $dbService;
    protected string    $strUser;
    protected string     $strPassword;
    function __construct(DbService $dbService, string $strUser, string $strPassword, bool $isAdmin = false, ?string $strRequiredRole = null){
        $this->dbService    = $dbService;
        $this->strUser      = $strUser;
        $this->strPassword  = $strPassword;
        $this->isAdmin      = isAdmin;
        $this->strRequiredRole = $strRequiredRole;
    }
    public function login(){
        $arrResp = $this->dbService->fetchOne("lib_spGetUserByNameAndRole",
            ["pName" => $this->strUser, "pRole" => $this->strRequiredRole]);
        if(Response::isResponseError($arrResp)){
            return $arrResp;
        }
        
    }
    public function logoff(){
        
    }
}