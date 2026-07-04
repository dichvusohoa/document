<?php
namespace App\Controller\Category;
use Core\Http\RequestAuthContext;
use Core\Controller\BaseController;
use Core\Auth\AuthService;
class CategoryController extends BaseController{
    public function __construct(RequestAuthContext $requestAuthContext){
        parent::__construct($requestAuthContext);
    }
   
    protected function resolveParams(string $strFunctName): array{
        if($strFunctName === 'login'){
            $isAdminLogin = LoginHelper::isAdminLoginRequest($this->requestAuthContext);
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
