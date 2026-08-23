<?php
namespace Core\Http;
use InvalidArgumentException;
class Session {
    public static function ensureStarted(): void {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }
    public static function get(null|array|string $arrElement = null, $default = null) {
        self::ensureStarted();
        if($arrElement === null){
            return $_SESSION;
        }
        $arrChunk = $_SESSION;
        // Nếu là chuỗi, chuyển thành mảng 1 phần tử
        if (!is_array($arrElement)) {
            $arrElement = [$arrElement];
        }
        // Nếu mảng rỗng thì trả về toàn bộ session
        if (empty($arrElement)) {
            return $arrChunk;
        }
        foreach ($arrElement as $element) {
            if (!is_string($element)) {
                throw new InvalidArgumentException("All elements in path must be strings");
            }

            if (is_array($arrChunk) && array_key_exists($element, $arrChunk)) {
                $arrChunk = &$arrChunk[$element]; // Tiến sâu vào
            } else {
                return $default;
            }
        }
        return $arrChunk;
    }

    public static function set(array|string $arrElement, $value): void {
        self::ensureStarted();
        if (!is_array($arrElement)) {
            $arrElement = [$arrElement];
        }
        if (empty($arrElement)) {
            throw new InvalidArgumentException("Key path cannot be empty");
        }
        $arrChunk = &$_SESSION; //khởi tạo chắc chắn là array
        foreach ($arrElement as $element) {
            if (!is_string($element)) {
                throw new InvalidArgumentException("All elements in key path must be strings");
            }
            if(!is_array($arrChunk)){
                $arrChunk  = [];
            }
            if (!array_key_exists($element,$arrChunk)) {
                $arrChunk[$element] = [];
            }
            $arrChunk = &$arrChunk[$element]; // Tiến sâu vào
        }
        // Gán giá trị tại điểm cuối
        $arrChunk = $value;
    }

    public static function remove(array|string $arrElement): void {
        self::ensureStarted();
        if (!is_array($arrElement)) {
            $arrElement = [$arrElement];
        }
        if (empty($arrElement)) {
            throw new InvalidArgumentException('Key path cannot be empty');
        }
        $arrChunk = &$_SESSION;
        // Duyệt tới phần tử cha của phần cần xóa
        $depth = count($arrElement);
        for ($i = 0; $i < $depth - 1; $i++) {
            $key = $arrElement[$i];
            if (!is_string($key)) {
                throw new InvalidArgumentException("All elements in key path must be strings");
            }
            if (is_array($arrChunk)&& array_key_exists($key, $arrChunk)) {
                $arrChunk = &$arrChunk[$key];
            }
            else{
                return;//không cần thiết phải xóa
            }
        }
        // Xóa phần tử cuối nếu tồn tại và mảng cha hợp lệ
        $lastKey = $arrElement[$depth - 1];
        if (is_array($arrChunk) && array_key_exists($lastKey, $arrChunk)) {
            unset($arrChunk[$lastKey]);
        }
    }
    /*Mục đích của hàm này là
    1) sau hàm này thì nếu có các lệnh truy câp vaò $_SESSION nhận được kết quả [] - không còn dữ liệu cũ 
    2) yêu cầu client xóa dữ liệu lưu cookie trong lần request kế tiếp 
    3) đảm bảo rằng sau đó nếu: session_start(); => lệnh ghi vào session thì sẽ không bị dùng lại session_id cũ nữa
    (kỹ thuật chống hack) 
    */
    public static function destroy(): void
    {
        self::ensureStarted();
        //xóa $_SESSION trong bộ nhớ RAM
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $strSessionName = session_name();
            $arrCookieParams = session_get_cookie_params();
            //đặt yêu cầu cho client xóa dữ liệu cookie trong request tiếp theo
            setcookie(
                $strSessionName,
                '',
                time() - 42000,
                $arrCookieParams['path'],
                $arrCookieParams['domain'],
                $arrCookieParams['secure'],
                $arrCookieParams['httponly']
            );
            //xóa dữ liệu $_COOKIE trong bộ nhớ - điểu này làm cho lệnh ghi vào session tiêp theo
            //sau hàm này sẽ không dùng lại session_id cũ nữa (khuyến cáo chống hack)
            unset($_COOKIE[$strSessionName]);
        }
        //xóa dữ liệu session lưu trên thiết bị (disk, ram, etc, tùy cấu hình) của server
        session_destroy();
    }
    
    public static function reset(): void{
        self::ensureStarted();
        //xóa $_SESSION trong bộ nhớ RAM
        $_SESSION = [];
        //xóa dữ liệu session lưu trên thiết bị (disk, ram, etc, tùy cấu hình) của server
        //sau đó tạo session với session_id mới
        session_regenerate_id(true);
    }
        
}