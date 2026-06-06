<?php
namespace Core\Controller\Login;
use Core\Http\Response;
use Core\Controller\BaseHtmlPageController;
use Core\View\HtmlSchema\LoginPageSchema;
use Core\Auth\AuthService;
class LoginController extends BaseHtmlPageController{
    protected AuthService $authService;
    public function __construct(LoginPageSchema $schema, AuthService $authService){
        parent::__construct($schema);
        $this->authService = $authService;
    }
    protected function resolveParams(string $strFunctName): array{
        if($strFunctName === 'renderPage'){
            return [];
        }
        else if($strFunctName === 'login'){
            $arrMCA = $this->requestAuthContext->routePath();
            $strController = $arrMCA[0];
            $isAdmin = array_key_exists($strController, ADMIN_CONTROLLER_RENAME);
            $strRequiredRole = $isAdmin ? ADMIN_ROLE_NAME : null;
            $arrUser = $this->requestAuthContext->request()->post('user');
            return [$arrUser['login'], $arrUser['password'], $strRequiredRole];
        }
    }
    protected function dataAtFragment(string $strFragmentName):array{
        switch ($strFragmentName){
            case 'title':
                return ['status'=> Response::SERVER_OK_STATUS, 'data'=>'Đăng nhập', 'extra'=>null];
            case 'css':
                return [
                    'status'=> Response::SERVER_OK_STATUS, 
                    'data'=>['/lib_assets/css/style.css', 
                        '/lib_assets/css/err.css', 
                        '/lib_assets/css/button.css', 
                        '/lib_assets/css/loading.css', 
                        '/lib_assets/css/login.css'], 
                    'extra'=>null];
            case 'script':
                return ['status'=> Response::SERVER_OK_STATUS,
                    'data'=> [['src' => 'https://challenges.cloudflare.com/turnstile/v0/api.js', 'defer' => true, 'async' => true], 
                        '/lib_assets/js/jcommon.js',
                        ['importmap' => true, 'namespace' => 'url', 'src' => '/lib_assets/js/jurl.js'],
                        ['importmap' => true, 'namespace' => 'loading', 'src' => '/lib_assets/js/control/jloading.js'],
                        ['importmap' => true, 'namespace' => 'autoForm', 'src' => '/lib_assets/js/control/jauto_form.js'],
                        ['src' => '/lib_assets/js/jlogin.js', 'type' => 'module']], 
                    'extra'=>null];
            case 'login':    
                return ['status'=> Response::SERVER_OK_STATUS,'data'=> null, 'extra'=>null];
        }
    }    
    
    public function login(string $strUser, string $strPassword, ?string $strRequiredRole = null){
        $this->authService->login($strUser, $strPassword, $strRequiredRole);
    }
}
