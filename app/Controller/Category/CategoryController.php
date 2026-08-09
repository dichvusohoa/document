<?php
namespace App\Controller\Category;
use Core\Http\RequestAuthContext;
use Core\Controller\BaseController;
use Core\Auth\AuthService;
class CategoryController extends BaseController{
    public function __construct(RequestAuthContext $requestAuthContext){
        parent::__construct($requestAuthContext);
    }
   
    protected function buildParams(string $strFunctName): array{
        if($strFunctName === 'login'){
            $isAdminLogin = LoginHelper::isAdminLoginRequest($this->requestAuthContext);
            $arrUser = $this->requestAuthContext->request()->post('user');
            //$token là public key
            $strToken = $this->requestAuthContext->request()->post('cf-turnstile-response');
            return [$arrUser['login'], $arrUser['password'], $isAdminLogin, $strToken];
        }
    }
    public function index(){
        $resp = $this->authService->login($strUser, $strPassword, $isAdminLogin, $strToken);
        return $resp;
    }
}
