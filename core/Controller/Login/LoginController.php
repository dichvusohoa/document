<?php
namespace Core\Controller\Login;
use Core\Http\RequestAuthContext;
use Core\Controller\BaseController;
use Core\Auth\AuthService;
class LoginController extends BaseController{
    protected AuthService $authService;
    public function __construct(RequestAuthContext $requestAuthContext, AuthService $authService){
        parent::__construct($requestAuthContext);
        $this->authService = $authService;
    }
   
    protected function buildParams(string $strFunctName): array{
        if($strFunctName === 'login'){
            //$isAdminLogin = LoginHelper::isAdminLoginRequest($this->requestAuthContext);
            $isAdminLogin = false;//tạm hard code
            $arrUser = $this->requestAuthContext->request()->post('user');
            //$token là public key
            $strToken = $this->requestAuthContext->request()->post('cf-turnstile-response');
            return [$arrUser['login'], $arrUser['password'], $isAdminLogin, $strToken];
        }
    }
    public function login(string $strUser, string $strPassword, bool $isAdminLogin = false, ?string $strToken = null){
        $resp = $this->authService->login($strUser, $strPassword, $isAdminLogin, $strToken);
        return $resp;
    }
}
