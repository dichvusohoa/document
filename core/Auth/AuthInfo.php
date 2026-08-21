<?php
namespace Core\Auth;
use Core\Http\Response;
use Core\User\UserInfo;
class AuthInfo{
    /*---------------------------------------------------------------------------------------------------------------*/
    /*sử dụng function static để có thể sử dụng linh hoạt kiểm tra dữ liệu bên ngoài
    không cần khởi tạo đối tượng bằng toán tử new*/
    public static function isValid(mixed $arrData): bool {
        return Response::isValid($arrData) && (
            ($arrData['data'] === null && $arrData['status'] === Response::SERVER_UNAUTHENTICATED_STATUS) ||
            UserInfo::isValidSessionData($arrData['data'])
        );
            
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public static function isAuthenticated($arrData): bool {
        return Response::isValid($arrData) && 
            $arrData['status'] === Response::SERVER_AUTHENTICATED_STATUS ;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public static function isUnauthenticated($arrData): bool {
        return self::isValid($arrData) && 
            $arrData['status'] === Response::SERVER_UNAUTHENTICATED_STATUS;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public static function fromDbData(array $arrResp, string $strSPName ){
        if (Response::isResponseError($arrResp)) {
            throw new RuntimeException(
                "Lỗi database khi lấy UserInfo từ {$strSPName}."
            );
        }
        if (Response::isResponseEmpty($arrResp)) {
            $arrResp['status'] = Response::SERVER_UNAUTHENTICATED_STATUS;
            $arrResp['data'] = UserInfo::createGuest();
            $arrResp['data'][UserInfo::FIELD_CREATED_AT] = time();
        }
        $arrResp['data'][UserInfo::FIELD_LAST_ACTIVITY] = time(); 
        return $arrResp;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    
}