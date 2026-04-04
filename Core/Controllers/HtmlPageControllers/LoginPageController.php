<?php
namespace Core\Controllers\HtmlPageControllers;
use Core\Models\Response;
/*HtmlRenderableController vẫn là abstract nên chưa cần implement resolveParam*/
class LoginPageController extends BaseHtmlPageController{
    protected function resolveParams(string $strFunctionName): array{
        if($strFunctionName === 'renderPage'){
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
}

