<?php
namespace Core\Controllers;
use Core\Models\Session;
use Core\Models\Auth\AuthService;
class LoginController extends BaseController{
    protected function resolveParams(string $strFunctionName): array{
        $arrMCA = Session::get('route_tmca');
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
