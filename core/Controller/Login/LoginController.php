<?php
namespace Core\Controller\Login;
use Core\Http\RequestAuthContext;
use Core\Controller\BaseController;
use Core\Auth\AuthService;
class LoginController extends BaseController{
    protected AuthService $authService;
    public function __construct(
            RequestAuthContext $requestAuthContext, 
            AuthService $authService){
        parent::__construct($requestAuthContext);
        $this->authService = $authService;
    }
   
    protected function argumentsForFunction(string $strFunctName): array{
        if($strFunctName === 'login'){
            $arrUser = $this->requestAuthContext->request()->post('user');
            //$token là public key
            $strToken = $this->requestAuthContext->request()->post('cf-turnstile-response');
            return [$arrUser['login'], $arrUser['password'], $strToken];
        }
    }
    public function login(string $strUser, string $strPassword, ?string $strToken = null){
        $resp = $this->authService->login($strUser, $strPassword, $strToken);
        return $resp;
    }
}
