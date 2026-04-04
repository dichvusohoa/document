<?php
namespace Core\Models\Layout;
use Core\Models\Request;
use Core\Models\Response;
use Core\Models\Auth\AuthContext;
use Detection\MobileDetect;
class LayoutFactory {
    /*$arrDeviceScreen chỉ xác định khi BaseLayout::layoutDependStrictlyOnScreen = true
     $mobileDetect chỉ xác định khi BaseLayout::layoutDependOnDevice = true 
     $arrRouteTMCA thì hiện nay luôn khác null vì đó là kết quả sau khi HtmlKernel đã chạy route() và set Session:set('route_tmca',... )
     tuy nhiên cũng có thể không thật cần dùng 
     */
    public static function create(string $strLayoutClassFCQN, Request $request, AuthContext $auth, 
            ?array $arrDeviceScreen = null, ?MobileDetect $mobileDetect =null, ?array $arrRouteTMCA = null){
        if(Request::getResponseType() !== Response::RESPONSE_HTML_TYPE){
            throw new RuntimeException("Không tạo đối tượng ".$strLayoutClassFCQN." khi response_type không là html");
        }
        //chưa có kết quả định tuyến. cái này chỉ là cẩn thận thôi vì sau khi HtmlKernel chạy hàm route thì luôn có $arrRouteTMCA vì được set trong route_tmca của Session
        if($arrRouteTMCA === null){
            throw new RuntimeException("Không tạo đối tượng ".$strLayoutClassFCQN." khi chưa có kết quả định tuyến");
        }
        $arrAuthInfo = $auth->getAuthInfo();
        if($arrAuthInfo['status'] === Response::SERVER_DB_ERR_STATUS){
            throw new RuntimeException('Lỗi cơ sở dữ liệu khi xác thực người dùng');
        }
        $oLayout = new ($strLayoutClassFCQN)($request, $arrAuthInfo, $arrDeviceScreen, $mobileDetect, $arrRouteTMCA);
        return $oLayout;
    }
    
}
