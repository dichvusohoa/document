<?php
namespace Core\Auth;
use Core\Http\Response;
use Core\Database\DbService;
/*prefix authSrvc*/
class AuthService{
    protected DbService $dbService;
    protected string    $strUser;
    protected string     $strPassword;
    function __construct(DbService $dbService){
        $this->dbService    = $dbService;
       
    }
    public function login(string $strUser, string $strPassword, ?string $strRequiredRole){
        $arrResp = $this->dbService->fetchOne("lib_spGetUserByNameAndRole",
            ["pName" => $strUser, "pRole" => $strRequiredRole]);
        if( Response::isResponseError($arrResp) || 
            Response::isResponseEmpty($arrResp)){
            return $arrResp;
        }
   
        if (password_verify($strPassword, $arrResp['data']['password'])) {
            return [Response::SERVER_OK_STATUS, 'data' => 'login success' , 'extra' => null];
        }
        else{
            return [Response::SERVER_OK_STATUS, 'data' => 'login fail' , 'extra' => null];
        }
    }
    public function logoff(){
        
    }
}