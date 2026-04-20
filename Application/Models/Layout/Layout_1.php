<?php
namespace App\Models\Layout;
use Core\Models\Layout\BaseLayout;
use Core\Models\RequestAuthContext;
class Layout extends BaseLayout{
    protected static function requiresDeviceDetection(RequestAuthContext $requestAuthContext, array $arrRouteTMCA): bool{
        return true;
    }
    protected static function requiresScreenDetection(RequestAuthContext $requestAuthContext, array $arrRouteTMCA ): bool{
        return false;
    }
    public function mapToLayoutFile():string{
        if($this->arrRouteTMCA && ($this->arrRouteTMCA[1] === 'login' || $this->arrRouteTMCA[1] === 'admin-login')){
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
