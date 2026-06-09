<?php
namespace Core\Controller\Login;
use Core\Http\RequestAuthContext;
use Core\Http\Response;
use Core\Controller\BaseController;
use Core\Auth\AuthService;
class LoginController extends BaseController{
    protected AuthService $authService;
    public function __construct(RequestAuthContext $requestAuthContext, AuthService $authService){
        parent::__construct($requestAuthContext);
        $this->authService = $authService;
    }
    
    protected function resolveParams(string $strFunctName): array{
        if($strFunctName === 'login'){
            $arrMCA = $this->requestAuthContext->routePath();
            $strController = $arrMCA[0];
            $isAdmin = array_key_exists($strController, ADMIN_CONTROLLER_RENAME);
            $strRequiredRole = $isAdmin ? ADMIN_ROLE_NAME : null;
            $arrUser = $this->requestAuthContext->request()->post('user');
            return [$arrUser['login'], $arrUser['password'], $strRequiredRole];
        }
    }
    
    
    public function login(string $strUser, string $strPassword, ?string $strRequiredRole = null){
        $resp = $this->authService->login($strUser, $strPassword, $strRequiredRole);
        Response::sendJson($resp);
    }
}
