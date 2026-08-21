<?php
namespace Core\Auth;
use Core\Http\Response;
use Core\Auth\SessionInfo;
class AuthResponse{
    /*---------------------------------------------------------------------------------------------------------------*/
    /*sử dụng function static để có thể sử dụng linh hoạt kiểm tra dữ liệu bên ngoài
    không cần khởi tạo đối tượng bằng toán tử new*/
    public static function isValid(mixed $arrData): bool {
        return Response::isValid($arrData) && (
            ($arrData['data'] === null && $arrData['status'] === Response::SERVER_UNAUTHENTICATED_STATUS) ||
            SessionInfo::isValid($arrData['data'])
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
    
}