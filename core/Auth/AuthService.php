<?php
namespace Core\Auth;
use Core\Http\Response;
use Core\Http\RequestAuthContext;
use Core\Auth\AuthToken;
use Core\User\UserInfo;
use Core\User\UserService;
class AuthService{
    protected RequestAuthContext $requestAuthContext;
    protected UserService $userService;
    protected AuthTokenService    $tokenService;
    
    function __construct(
        RequestAuthContext $requestAuthContext,
        UserService $userService,
        AuthTokenService $tokenService
        
    ){
        $this->requestAuthContext = $requestAuthContext;
        $this->userService    = $userService;
        $this->tokenService = $tokenService;
    }
    
    public function verifyCredentials(string $strUser, string $strPassword): array {
        $arrContextAcceptedRole = $this->requestAuthContext->contextAcceptedRoles();
        /*$strContextAcceptedRolesJson = json_encode(
            $arrContextAcceptedRole,
            JSON_THROW_ON_ERROR
        );
        $arrResp = $this->dbService->fetchOne("lib_spGetUserByNameAndRoles",
            ["pName" => $strUser, "pRoles" => $strContextAcceptedRolesJson]);
        //$arrResp = UserInfo::normalizeDbData('lib_spGetUserByNameAndRoles', UserInfo::DB_DATA_WITH_PASSWORD , $arrResp);
        if($arrResp['data'] !== null){
            $arrResp['data'] = UserInfo::normalizeDbData2(
                    'lib_spGetUserByNameAndRoles',
                    UserInfo::DB_DATA_WITH_PASSWORD,
                    $arrResp['data']
                    );
        }*/
        $arrResp = $this->userService->getUserByNameAndRoles($strUser, $arrContextAcceptedRole);
        if(Response::isResponseEmpty($arrResp)){
            return ['status' => Response::SERVER_UNAUTHENTICATED_STATUS, 'data' => 'Tên đăng nhập hoặc mật khẩu không đúng' , 'extra' => null];
        }
        //4. verify password
        if (!password_verify($strPassword, $arrResp['data'][UserInfo::FIELD_PASSWORD])) {
            return ['status' =>Response::SERVER_UNAUTHENTICATED_STATUS, 'data' => 'Tên đăng nhập hoặc mật khẩu không đúng' , 'extra' => null];
        }
        $arrResp['status'] = Response::SERVER_AUTHENTICATED_STATUS;
        return $arrResp;
    }
    
    public function tokenToDb(
            AuthToken $authToken, 
            int $iUserId, 
            int $iRememberExpireSecond) {
        return  $this->tokenService->tokenToDB(
                    $authToken, 
                    $iUserId, 
                    $iRememberExpireSecond);
            
    }
}