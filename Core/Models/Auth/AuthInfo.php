<?php
namespace Core\Models\Auth;
use Core\Models\Response;
use Core\Models\User\UserInfo;
class AuthInfo{
    /*---------------------------------------------------------------------------------------------------------------*/
    /*sử dụng function static để có thể sử dụng linh hoạt kiểm tra dữ liệu bên ngoài
    không cần khởi tạo đối tượng bằng toán tử new*/
    public static function isValid(mixed $arrData): bool {
        return Response::isValid($arrData) && (
            (($arrData['data'] === null || $arrData['data'] === false) && $arrData['status'] === Response::SERVER_UNAUTHENTICATED_STATUS  ) ||
            UserInfo::isValid($arrData['data'])
        );
            
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public static function isAuthenticated($arrData): bool {
        return self::isValid($arrData) && 
            $arrData['status'] === Response::SERVER_AUTHENTICATED_STATUS && isset($arrData['data']['id']) ;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public static function isUnauthenticated($arrData): bool {
        return self::isValid($arrData) && 
            $arrData['status'] === Response::SERVER_UNAUTHENTICATED_STATUS ;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    
}