<?php
namespace Core\User;
use Core\Http\Response;
use Core\Database\DbService;
/*prefix usrSrv hoặc usrService*/
class UserService {
    protected DbService $dbService;
    public function __construct(DbService $dbService){
        $this->dbService = $dbService;
    }
    function getUserByToken(string $strLeftToken): array{
        $arrResp = $this->dbService->fetchOne("lib_spGetUserByToken",["leftToken" => $strLeftToken]);
        if( !Response::isResponseOK($arrResp) ){
            return $arrResp;
        }
        // Chuyển JSON string sang array
        $data = $arrResp["data"];
        $data["roles"] = $data["roles"] !== null ? json_decode($data["roles"], true)
        : [];
        $data["registered_modules"] = $data["registered_modules"] !== null
        ? json_decode($data["registered_modules"], true)
        : [];

        // Cập nhật lại data vào response
        $arrResp["data"] = $data;

        return $arrResp;
;
    }
}