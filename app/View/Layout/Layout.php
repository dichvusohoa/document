<?php
namespace App\View\Layout;
use Core\View\Layout\BaseLayout;
class Layout extends BaseLayout{
    public function mapToLayoutFile():string{
        //$arrMCAO = $this->requestAuthContext->mcao();
        $arrMCAO = $this->requestAuthContext->request()->mcao();
        if($arrMCAO && ($arrMCAO['controller'] === 'login' || $arrMCAO['controller'] === 'cacquak')){
            return 
            CORE_PATH.'/resources/views/layouts/simple_layout.phtml';
        }
        else{
            return 
            APP_PATH.'/resources/views/layouts/layout_main2.phtml'; //2 column
        }
    } 
    
    
}
