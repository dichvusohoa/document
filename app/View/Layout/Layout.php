<?php
namespace App\View\Layout;
use Core\View\Layout\BaseLayout;
use Core\Http\RequestAuthContext;
class Layout extends BaseLayout{
    public function mapToLayoutFile():string{
        $arrRouteTMCA = $this->requestAuthContext->routePath();
        if($arrRouteTMCA && ($arrRouteTMCA[0] === 'login' || $arrRouteTMCA[0] === 'admin-login')){
            return 
            CORE_PATH.'/resources/views/layouts/simple_layout.phtml';
        }
        else{
            return 
            APP_PATH.'/resources/views/layouts/layout_0a.phtml'; //2 column
        }
    } 
    public function mapToUiContext(): array{
        $authInfo = $this->requestAuthContext->authInfo();
        return $authInfo['data'];
    }

    
}
