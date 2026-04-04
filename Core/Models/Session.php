<?php
namespace Core\Models;
use \InvalidArgumentException;
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

    public static function destroy(): void {
        self::ensureStarted();
        $_SESSION = []; //dùng cả $_SESSION = [] và session_unset() cho chắc
        session_unset(); //dùng cả $_SESSION = [] và session_unset() cho chắc
        session_destroy();
        if (ini_get("session.use_cookies")) { //chắc chắn rằng PHP dùng cookie lưu PHPSESSID 
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
    }
}