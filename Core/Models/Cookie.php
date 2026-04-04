<?php
namespace Core\Models;
use \InvalidArgumentException;
/*thông tin trong cookie (real cookie) lưu trữ dạng như sau
 $_COOKIE = array(2) {
  "auth" => '{"token":"3ccd6c1b46b64d86d2970046d7b88a:6847badd17a59fe60899968dc0ceca9b4afe5e0b5a60bcf296605a692d9c4443b2"}'
  "xxx"  => ....
  "yyy"  => ....
}
*/
class Cookie {
    // Đọc giá trị cookie, có hỗ trợ key dạng mảng lồng (giống $_SESSION)
    public static function get(null|array|string $arrElement = null, $default = null): mixed {
        $cookie = $_COOKIE;
        if (is_string($arrElement)) {
            $arrElement = [$arrElement];
        }
        if ($arrElement === null || empty($arrElement)) {
            return $cookie;
        }
        $len = count($arrElement);
        foreach ($arrElement as $i => $element) {
            if (!is_string($element)) {
                throw new InvalidArgumentException("All elements in path must be strings");
            }
            if (!is_array($cookie) || !array_key_exists($element, $cookie)) {
                return $default;
            } 
            $cookie = $cookie[$element];
            //khi chưa tới phần tử cuối thì $cookie phải decode được ra dạng array
            //nếu không được chấm dứt vòng lặp
            if (is_string($cookie)) {
                $decoded = json_decode($cookie, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $cookie = $decoded;//chuyển sang dạng array để tiếp tục vòng for
                } else if($i < $len-1) {//chưa tới phần tử cuối đã không chuyển được sang dạng array
                    return $default;
                }
            }
        }
        return $cookie;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    // Ghi giá trị cookie, mặc định sống 30 ngày (2592000 giây)
    public static function set(array|string $arrElement, $value, int $expireSeconds = 2592000, string $path = "/"): void {
        if (!is_array($arrElement)) {
            $arrElement = [$arrElement];
        }

        if (empty($arrElement)) {
            throw new InvalidArgumentException("Key path cannot be empty");
        }
        $topKey = $arrElement[0];
        $cookie = [];//$cookie này chỉ cần bám theo 1 nhánh từ gốc của $_COOKIE, không cần gán = toàn bộ $_COOKIE
        // Giải mã dữ liệu cookie gốc nếu tồn tại
        if (isset($_COOKIE[$topKey])) {
            $decoded = json_decode($_COOKIE[$topKey], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $cookie[$topKey] = $decoded;
            }
        }
        // Duyệt để lồng giá trị
        $ref = &$cookie[$topKey];
        $arrElement = array_slice($arrElement,1);//bỏ phần tử đầu vì đã xử lý rồi
        foreach ($arrElement as $key) {
            if (!is_string($key)) {
                throw new InvalidArgumentException("All keys must be strings.");
            }
            if(!is_array($ref)){
                $ref = [];
            }
            if(!array_key_exists($key, $ref)){
                $ref[$key] = [];
            }
            $ref = &$ref[$key];
        }
        $ref = $value;
        unset($ref);
        // Ghi lại cookie theo key gốc
        $cookieValue = json_encode($cookie[$topKey]);
        setcookie($topKey, $cookieValue, time() + $expireSeconds, $path);
        /*
        setcookie(...) — gửi cookie đến trình duyệt
        Thực tế: Đây là lệnh gửi một header HTTP đến trình duyệt để tạo cookie.
        Cookie chưa tồn tại trong $_COOKIE ngay lập tức sau khi gọi setcookie() — vì cookie chỉ được trình duyệt gửi lại trong request tiếp theo.
        Cần lệnh gán $_COOKIE[$topKey] để có thể sử dụng được ngay trong request
        hiện tại không chờ đến request tiếp theo      
        */
        $_COOKIE[$topKey] = $cookieValue;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    // Xóa cookie theo key
    public static function remove(array|string $arrElement,int $expireSeconds = 2592000, string $path = "/"): void {
        if (!is_array($arrElement)) {
            $arrElement = [$arrElement];
        }
        if (empty($arrElement)) {
            throw new InvalidArgumentException("Key path cannot be empty");
        }
        $topKey = $arrElement[0];
        if (!is_string($topKey)) {
            throw new InvalidArgumentException("All keys must be strings.");
        }
        if (!isset($_COOKIE[$topKey])) {
            return;
        }
        
        if(count($arrElement) === 1){//chỉ có một phần tử top key
            setcookie($topKey, '', time() - 3600, $path);
            unset($_COOKIE[$topKey]);
            return;
        }
        
        // Giải mã dữ liệu cookie gốc nếu có
        $cookie = [];
        $decoded = json_decode($_COOKIE[$topKey], true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $cookie[$topKey] = $decoded;
        } else {
            return; // Không thể giải mã -> không thao tác gì
        }
        $ref = &$cookie[$topKey];
      
        $arrElement = array_slice($arrElement,1);//bỏ phần tử đầu vì đã xử lý rồi
        $lastKey = array_pop($arrElement); // bỏ tiếp phần tử cuối cùng

        // Duyệt vào mảng
        foreach ($arrElement as $key) {//$arrElement đã được cắt bớt đi phần tử cuối
            if (!is_string($key)) {
                throw new InvalidArgumentException("All keys must be strings.");
            }
            if (!is_array($ref) || !array_key_exists($key, $ref)) {
                return; // Không tìm thấy đường dẫn
            }
            $ref = &$ref[$key];
        }
        if (!is_array($ref) || !array_key_exists($lastKey, $ref)) {
            return;
        }    
        // Xóa nếu tồn tại
        unset($ref[$lastKey]);
        // Nếu sau khi xóa, mảng gốc rỗng => xóa luôn cookie
        if (empty($cookie[$topKey])) {
            setcookie($topKey, '', time() - 3600, $path);
            unset($_COOKIE[$topKey]);
        } else {
            $cookieValue = json_encode($cookie[$topKey]);
            setcookie($topKey, $cookieValue, time() + $expireSeconds, $path);
            $_COOKIE[$topKey] = $cookieValue;
        }
        
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    // Xóa toàn bộ cookie hiện tại
    public static function destroy(string $path = "/"): void {
        foreach ($_COOKIE as $key => $value) {
            setcookie($key, '', time() - 3600, $path);
            unset($_COOKIE[$key]);
        }
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public static function isExistsCookieInDb(DbService $dbService){
        $strToken = self::get(['auth','token']);
        if($strToken === null || strpos($strToken,':') === false){
            return false;
        }
        list($sLeftToken, $sRightToken) = explode(':', $strToken);
        if (strlen($sLeftToken) !== AuthToken::LEFT_TOKEN_LENGTH || strlen($sRightToken) !== AuthToken::RIGHT_TOKEN_LENGTH) {
            return false;
        }
       // $exec = $dbService->getRowData("spGetUserByToken",["selector"=> $this->sLeftToken]);
            //return $this->dbService->fetchOne("spGetUserByToken", ["selector"=> $this->sLeftToken]);
    }
}