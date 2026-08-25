<?php
namespace Core\Auth;
use Core\Http\Response;
use Core\Http\RequestAuthContext;
use Core\User\UserService;
class AuthService{
    protected RequestAuthContext $requestAuthContext;
    protected UserService $userService;
    
    
    function __construct(
        RequestAuthContext $requestAuthContext,
        UserService $userService
        
    ){
        $this->requestAuthContext = $requestAuthContext;
        $this->userService    = $userService;
    }
    
    public function verifyCredentials(string $strUser, string $strPassword): array {
        $arrContextAcceptedRole = $this->requestAuthContext->contextAcceptedRoles();
        $arrResp = $this->userService->getUserByNameAndRoles($strUser, $arrContextAcceptedRole);
        if(Response::isResponseEmpty($arrResp)){
            return ['status' => Response::SERVER_UNAUTHENTICATED_STATUS, 'data' => 'Tên đăng nhập hoặc mật khẩu không đúng' , 'extra' => null];
        }
        //4. verify password, $arrResp['extra'] chứa password
        if (!password_verify($strPassword, $arrResp['extra'])) {
            return ['status' =>Response::SERVER_UNAUTHENTICATED_STATUS, 'data' => 'Tên đăng nhập hoặc mật khẩu không đúng' , 'extra' => null];
        }
        $arrResp['status'] = Response::SERVER_AUTHENTICATED_STATUS;
        $arrResp['extra'] = null; //xóa bỏ thông tin mật khẩu
        return $arrResp;
    }
   
}