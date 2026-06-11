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
            //$token là public key
            $strToken = $this->requestAuthContext->request()->post('cf-turnstile-response');
            return [$arrUser['login'], $arrUser['password'], $strRequiredRole, $strToken];
        }
    }
    
    
    public function login(string $strUser, string $strPassword, ?string $strRequiredRole = null, ?string $strToken = null){
        $resp = $this->authService->login($strUser, $strPassword, $strRequiredRole, $strToken = null);
        Response::sendJson($resp);
    }
}
