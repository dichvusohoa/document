<?php
namespace App\Models\Layout;
use Core\Models\Layout\BaseLayout;
use Core\Models\RequestAuthContext;
class Layout extends BaseLayout{
    public function mapToLayoutFile():string{
        $arrRouteTMCA = $this->requestAuthContext->routePath();
        if($arrRouteTMCA && ($arrRouteTMCA[1] === 'login' || $arrRouteTMCA[1] === 'admin-login')){
            return 
            CORE_PATH.'/views/layout/simple_layout.phtml';
        }
        else{
            return 
            APP_PATH.'/views/layout/layout_0a.phtml'; //2 column
        }
    } 
    public function mapToUiContext(): array{
        $authInfo = $this->requestAuthContext->authInfo();
        return $authInfo['data'];
    }
   
    
        
}
