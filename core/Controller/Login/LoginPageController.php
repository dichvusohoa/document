<?php
namespace Core\Controller\Login;
use Core\Http\Response;
use Core\Http\Session;
use Core\Controller\BaseHtmlPageController;
use Core\View\HtmlSchema\LoginPageSchema;
use Core\Auth\AuthService;

class LoginPageController extends BaseHtmlPageController{
    protected LoginController $apiController;
    protected AuthService $authService;
    public function __construct(LoginPageSchema $schema, LoginController $apiController){
        parent::__construct($schema);
        $this->apiController = $apiController;
    }
    protected function needTurnstile(): bool {
        $maxFailedBeforeTurnstile = 3;
        if (!Session::get('login_failed_count')) {
            Session::set('login_failed_count', 0);
        }
        $needTurnstile = Session::get('login_failed_count') >= $maxFailedBeforeTurnstile;
        return $needTurnstile;
    }
    protected function resolveParams(string $strFunctName): array{
        if($strFunctName === 'renderPage'){
            $needTurnstile = $this->needTurnstile();
            /*không được viết return  ['needTurnstile' => $needTurnstile] mà phải viết là
            return  [['needTurnstile' => $needTurnstile]]
            vì BaseHtmlPageController->renderPage(?array $arrOptionVar = null) sẽ 
            không nhận dược tham số truyền vào dạng array */
            return  [['needTurnstile' => $needTurnstile]];
            
        }
        else{//các hàm do $apiController chạy
            return [];
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
    public function login() {
        $this->apiController->doAction('login');
    }
    
}
