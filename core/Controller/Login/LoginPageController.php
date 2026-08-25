<?php
namespace Core\Controller\Login;
use Core\Http\Response;
use Core\Http\Session;
use Core\Controller\BaseHtmlPageController;
use Core\View\HtmlSchema\LoginPageSchema;
use Core\User\BaseUserInfo;
use Core\Http\HttpException;
use Core\Routing\RoleRegistry;

class LoginPageController extends BaseHtmlPageController{
    protected LoginController $apiController;
    protected RoleRegistry $roleRegistry;
    
    public function __construct(
            LoginPageSchema $schema, 
            LoginController $apiController,
            RoleRegistry $roleRegistry){
        parent::__construct($schema);
        $this->apiController = $apiController;
        $this->roleRegistry = $roleRegistry;
    }
    
    protected function argumentsForFunction(string $strFunctName): array{
        return [];
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
    protected function uiContextAtFragment(string $strFragmentName): array {
        if($strFragmentName === 'login'){
            $needTurnstile = $this->apiController->needTurnstile();
            return ['needTurnstile' => $needTurnstile];
        }    
    }
    public function login() {
        $arrResp = $this->apiController->doFunction('login');
        if($arrResp['status'] === Response::SERVER_AUTHENTICATED_STATUS){
            //$arrRoleCode = array_keys($this->requestAuthContext->userRoles());
            //Lý do vì $arrResp['data']['roles'] là array có format:[roleCode => displayName,...]
            $arrRoleCode = array_keys(
                $arrResp['data'][BaseUserInfo::FIELD_ROLES]
            );
            $strUrl = Session::get('intended_url') ?? 
            $this->roleRegistry->findDefaultBusinessUrlByRoles($arrRoleCode);
            if($strUrl === null){
                throw new HttpException(403, 'User hiện tại không có quyền truy cập chức năng nghiệp vụ nào. Xem lại file config.role.php');
            }
            Session::remove('intended_url');
            Session::remove('intended_roles');
            $arrResp['data'] = $strUrl;
            $arrResp['extra'] =  'redirect';//báo hiệu cho client biết cần redirect
        }
        Response::sendJson($arrResp);
    }
    
}
