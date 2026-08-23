<?php

namespace Core\Http;

use RuntimeException;
use Core\Utility\ArrayUtility;

class Session
{
    /*---------------------------------------------------------------------------------------------------------------*/
    public static function ensureStarted(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        if (session_status() === PHP_SESSION_DISABLED) {
            throw new RuntimeException(
                'PHP session đang bị disabled.'
            );
        }

        if (!session_start()) {
            throw new RuntimeException(
                'Không thể khởi tạo PHP session.'
            );
        }
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    public static function get(
        array|string $mixElement = [],
        mixed $default = null
    ): mixed {
        self::ensureStarted();

        $arrElement = ArrayUtility::normalizePath(
            $mixElement,
            true
        );

        return ArrayUtility::getByPath(
            $_SESSION,
            $arrElement,
            $default
        );
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    public static function set(
        array|string $mixElement,
        mixed $value
    ): void {
        self::ensureStarted();

        $arrElement = ArrayUtility::normalizePath(
            $mixElement,
            false
        );

        ArrayUtility::setByPath(
            $_SESSION,
            $arrElement,
            $value
        );
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    public static function remove(
        array|string $mixElement
    ): void {
        self::ensureStarted();

        $arrElement = ArrayUtility::normalizePath(
            $mixElement,
            false
        );

        ArrayUtility::removeByPath(
            $_SESSION,
            $arrElement
        );
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    /*
     * Mục đích:
     *
     * 1) Sau hàm này, code trong request hiện tại truy cập $_SESSION
     *    sẽ nhận được [] và không còn dữ liệu session cũ.
     *
     * 2) Nếu session ID được lưu bằng cookie thì yêu cầu browser
     *    xóa session cookie.
     *
     * 3) Xóa dữ liệu của session hiện tại khỏi session storage
     *    của server.
     *
     * 4) Xóa session cookie khỏi $_COOKIE của request hiện tại,
     *    tránh việc session_start() tiếp theo trong cùng request
     *    lấy lại session ID cũ từ $_COOKIE.
     */
    public static function destroy(): void
    {
        self::ensureStarted();

        /*
         * Xóa dữ liệu session trong request hiện tại.
         */
        $_SESSION = [];

        /*
         * Nếu PHP sử dụng cookie để truyền session ID
         * thì yêu cầu browser xóa session cookie.
         */
        if (ini_get('session.use_cookies')) {
            $strSessionName = session_name();
            $arrCookieParams = session_get_cookie_params();

            setcookie(
                $strSessionName,
                '',
                [
                    'expires'  => time() - 42000,
                    'path'     => $arrCookieParams['path'],
                    'domain'   => $arrCookieParams['domain'],
                    'secure'   => $arrCookieParams['secure'],
                    'httponly' => $arrCookieParams['httponly'],
                    'samesite' => $arrCookieParams['samesite'],
                ]
            );

            /*
             * setcookie() chỉ tác động lên response gửi về browser,
             * không tự thay đổi $_COOKIE trong request hiện tại.
             */
            unset($_COOKIE[$strSessionName]);
        }

        /*
         * Xóa dữ liệu session gắn với session ID hiện tại
         * khỏi session storage của server.
         */
        if (!session_destroy()) {
            throw new RuntimeException(
                'Không thể hủy PHP session.'
            );
        }
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    /*
     * Xóa toàn bộ dữ liệu session hiện tại và chuyển sang
     * một session ID mới.
     *
     * Session vẫn tiếp tục active sau hàm này.
     */
    public static function reset(): void
    {
        self::ensureStarted();

        $_SESSION = [];

        if (!session_regenerate_id(true)) {
            throw new RuntimeException(
                'Không thể tạo session ID mới.'
            );
        }
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    
}