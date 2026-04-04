<?php
namespace App\Controllers\HtmlPageControllers;
use Core\Controllers\HtmlPageControllers\BaseHtmlPageController;
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
                return ['status'=> Response::SERVER_OK_STATUS, 'data'=>['/lib_assets/css/style.css', '/lib_assets/css/err.css',
                '/lib_assets/css/button.css', '/lib_assets/css/login.css'], 'extra'=>null];
            case 'script':
                return ['status'=> Response::SERVER_OK_STATUS,'data'=> null, 'extra'=>null];
            case 'login':    
                return ['status'=> Response::SERVER_OK_STATUS,'data'=> null, 'extra'=>null];
        }
    }    
}

