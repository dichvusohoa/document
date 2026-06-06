<?php
namespace Core\Controller\Login;
use Core\Auth\AuthService;
use Core\Controller\BaseController;
class LoginController extends BaseController{
    protected function resolveParams(string $strFunctionName): array{
        //$arrMCA = Session::get('route_tmca');
        $arrMCA = $this->requestAuthContext->routePath();
        $strController = $arrMCA[0];
        $isAdmin = array_key_exists($strController, ADMIN_CONTROLLER_RENAME);
        $strRequiredRole = $isAdmin ? ADMIN_ROLE_NAME : null;
        $arrUser = $this->requestAuthContext->request()->post('user');
        if($strFunctionName === 'login'){
            return [$arrUser['login'], $arrUser['password'], $isAdmin, $strRequiredRole];
        }
    }
    public function login(string $strUser, string $strPassword, bool $isAdmin = false, ?string $strRequiredRole = null){
        $authService = new AuthService($strUser, $strPassword, $isAdmin, $strRequiredRole);
        $authService->login();
    }
}
