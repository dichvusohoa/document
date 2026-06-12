<?php
namespace Core\Auth;
use Core\Http\Response;
use Core\Database\DbService;
/*prefix authSrvc*/
class AuthTokenService{
    protected DbService $dbService;
    function __construct(DbService $dbService){
        $this->dbService    = $dbService;
        
    }
    public function tokenToDB(AuthToken $authToken, $strUserId){
        $sExpDateTime = date('Y-m-d H:i:s', strtotime(COOKIE_EXP_BY_DAYS.' day', time()));
        $arrParam= ["leftToken"=>$authToken->getLeftToken(),"hashedRightToken"=>$authToken->hashedRightToken(),"userId"=>$strUserId,"expDate"=>$sExpDateTime];
        $exec = $this->dbService->execActionSP("spAddAuthToken",$arrParam);
        if($exec["status"] === DbTable::ERR_STATUS){
            $exec = ["status"=>DbTable::ERR_STATUS,"info"=>null,"extra"=>$exec["extra"]];
            return $exec;
        }
        return $exec;
    }
    
}