<?php
namespace App\View\Layout;
use Core\View\Layout\BaseLayout;
//use Core\Http\RequestAuthContext;
class Layout extends BaseLayout{
    public function mapToLayoutFile():string{
        $arrMCA = $this->requestAuthContext->routePath();
        if($arrMCA && ($arrMCA['controller'] === 'login' || $arrMCA['controller'] === 'cacquak')){
            return 
            CORE_PATH.'/resources/views/layouts/simple_layout.phtml';
        }
        else{
            return 
            APP_PATH.'/resources/views/layouts/layout_main2.phtml'; //2 column
        }
    } 
    
    
}
