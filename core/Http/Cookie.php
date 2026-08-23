<?php

namespace Core\Http;

use InvalidArgumentException;
use JsonException;
use RuntimeException;
use Core\Utility\ArrayUtility;

final class Cookie
{
    /*---------------------------------------------------------------------------------------------------------------*/
    /*
     * Đọc cookie theo element path.
     *
     * []                       => toàn bộ $_COOKIE
     * 'auth'                   => tương đương ['auth']
     * ['auth']                 => cookie auth
     * ['auth', 'token']        => auth.token
     * ['a', 'b', 'c']          => a.b.c
     */
    public static function get(
        array|string $arrElement = [],
        mixed $default = null
    ): mixed {

        $arrElement = ArrayUtility::normalizePath(
            $arrElement,
            true
        );

        /*
         * Path rỗng đại diện cho root của $_COOKIE.
         */
        if ($arrElement === []) {
            $arrCookie = [];
            foreach ($_COOKIE as $strTopKey => $mixValue) {
                $arrCookie[$strTopKey] = is_string($mixValue)
                    ? self::decode($mixValue)
                    : $mixValue;
            }
            return $arrCookie;
        }

        $strTopKey = $arrElement[0];

        if (!array_key_exists($strTopKey, $_COOKIE)) {
            return $default;
        }

        /*
         * Physical cookie luôn là string.
         *
         * Cookie do Cookie::set() tạo ra được JSON encode ở top-level.
         * Chỉ decode đúng một lần tại đây.
         */
        $mixValue = self::decode(
            $_COOKIE[$strTopKey]
        );

        /*
         * Chỉ truy xuất top-level.
         */
        if (count($arrElement) === 1) {
            return $mixValue;
        }

        /*
         * Muốn đi sâu hơn thì top-level phải decode được thành array.
         */
        if (!is_array($mixValue)) {
            return $default;
        }

        return ArrayUtility::getByPath(
            $mixValue,
            array_slice($arrElement, 1),
            $default
        );
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    /*
     * Ghi cookie.
     *
     * $iLifetimeSeconds:
     *
     *      0   => session cookie
     *      > 0 => lifetime tính bằng giây
     *
     * $arrOption:
     *
     *      secure
     *      httponly
     *      samesite
     *      domain
     */
    public static function set(
        array|string $arrElement,
        mixed $mixValue,
        int $iLifetimeSeconds = 2592000,/*mặc định tồn tại 30 days*/
        string $strPath = '/',
        array $arrOption = []
    ): void {

        if ($iLifetimeSeconds < 0) {
            throw new InvalidArgumentException(
                'iLifetimeSeconds không được nhỏ hơn 0.'
            );
        }

        $arrElement = ArrayUtility::normalizePath(
            $arrElement,
            false
        );

        $strTopKey = $arrElement[0];

        /*
         * Ghi trực tiếp top-level.
         *
         * Ví dụ:
         *
         * Cookie::set('language', 'vi');
         */
        if (count($arrElement) === 1) {

            $mixTopValue = $mixValue;

        } else {

            /*
             * Nested set.
             *
             * Nếu cookie top-level hiện tại decode được thành array
             * thì giữ lại dữ liệu cũ.
             *
             * Nếu không thì tạo một array mới.
             */
            $mixTopValue = [];

            if (array_key_exists($strTopKey, $_COOKIE)) {

                $mixCurrentValue = self::decode(
                    $_COOKIE[$strTopKey]
                );

                if (is_array($mixCurrentValue)) {
                    $mixTopValue = $mixCurrentValue;
                }
            }

            ArrayUtility::setByPath(
                $mixTopValue,
                array_slice($arrElement, 1),
                $mixValue
            );
        }

        $strCookieValue = self::encode(
            $mixTopValue
        );

        self::write(
            $strTopKey,
            $strCookieValue,
            $iLifetimeSeconds,
            $strPath,
            $arrOption
        );

        /*
         * setcookie() chỉ gửi header.
         *
         * Browser chỉ gửi cookie trở lại trong request kế tiếp,
         * nên cập nhật $_COOKIE để dùng được ngay trong request hiện tại.
         */
        $_COOKIE[$strTopKey] = $strCookieValue;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    /*
     * Xóa cookie hoặc element bên trong cookie.
     *
     * Cookie::remove('auth');
     *
     * Cookie::remove(['auth', 'token']);
     */
    public static function remove(
        array|string $arrElement,
        int $iLifetimeSeconds = 2592000, 
        string $strPath = '/',
        array $arrOption = []
    ): void {

        if ($iLifetimeSeconds < 0) {
            throw new InvalidArgumentException(
                'iLifetimeSeconds không được nhỏ hơn 0.'
            );
        }

        $arrElement = ArrayUtility::normalizePath(
            $arrElement,
            false
        );

        $strTopKey = $arrElement[0];

        if (!array_key_exists($strTopKey, $_COOKIE)) {
            return;
        }

        /*
         * Chỉ có top-level.
         *
         * Xóa toàn bộ physical cookie.
         */
        if (count($arrElement) === 1) {

            self::delete(
                $strTopKey,
                $strPath,
                $arrOption
            );

            unset($_COOKIE[$strTopKey]);

            return;
        }

        /*
         * Nested remove chỉ thực hiện được nếu physical cookie
         * decode thành array.
         */
        $mixTopValue = self::decode(
            $_COOKIE[$strTopKey]
        );

        if (!is_array($mixTopValue)) {
            return;
        }

        $isRemoved = ArrayUtility::removeByPath(
            $mixTopValue,
            array_slice($arrElement, 1)
        );

        if (!$isRemoved) {
            return;
        }

        /*
         * Nếu top-level logical array đã rỗng thì
         * xóa luôn physical cookie.
         */
        if ($mixTopValue === []) {

            self::delete(
                $strTopKey,
                $strPath,
                $arrOption
            );

            unset($_COOKIE[$strTopKey]);

            return;
        }

        /*
         * Vẫn còn dữ liệu => ghi lại physical cookie.
         */
        $strCookieValue = self::encode(
            $mixTopValue
        );

        self::write(
            $strTopKey,
            $strCookieValue,
            $iLifetimeSeconds,
            $strPath,
            $arrOption
        );

        $_COOKIE[$strTopKey] = $strCookieValue;
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    /*
     * Decode physical cookie.
     *
     * Nếu value không phải JSON hợp lệ thì trả nguyên raw string.
     *
     * Điều này cho phép Cookie::get('xxx') vẫn đọc được
     * các cookie không được tạo bởi Cookie::set().
     */
    protected static function decode(
        string $strCookieValue
    ): mixed {

        try {

            return json_decode(
                $strCookieValue,
                true,
                flags: JSON_THROW_ON_ERROR
            );

        } catch (JsonException $e) {

            return $strCookieValue;
        }
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected static function encode(
        mixed $mixValue
    ): string {

        return json_encode(
            $mixValue,
            JSON_THROW_ON_ERROR
        );
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected static function write(
        string $strTopKey,
        string $strCookieValue,
        int $iLifetimeSeconds,
        string $strPath,
        array $arrOption
    ): void {

        $arrCookieOption = self::buildCookieOption(
            $iLifetimeSeconds,
            $strPath,
            $arrOption
        );

        $isSuccess = setcookie(
            $strTopKey,
            $strCookieValue,
            $arrCookieOption
        );

        if (!$isSuccess) {
            throw new RuntimeException(
                "Không thể ghi cookie '{$strTopKey}'."
            );
        }
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected static function delete(
        string $strTopKey,
        string $strPath,
        array $arrOption
    ): void {

        /*
         * Xóa cookie phải dùng cùng path/domain
         * với cookie đã được tạo.
         */
        $arrCookieOption = self::buildCookieOption(
            0,
            $strPath,
            $arrOption
        );

        $arrCookieOption['expires'] =
            time() - 3600;

        $isSuccess = setcookie(
            $strTopKey,
            '',
            $arrCookieOption
        );

        if (!$isSuccess) {
            throw new RuntimeException(
                "Không thể xóa cookie '{$strTopKey}'."
            );
        }
    }

    /*---------------------------------------------------------------------------------------------------------------*/
    protected static function buildCookieOption(
        int $iLifetimeSeconds,
        string $strPath,
        array $arrOption
    ): array {

        $arrCookieOption = [
            'expires' => (
                $iLifetimeSeconds === 0
                    ? 0
                    : time() + $iLifetimeSeconds
            ),

            'path' => $strPath,

            'secure' =>
                $arrOption['secure'] ?? false,

            'httponly' =>
                $arrOption['httponly'] ?? false,

            'samesite' =>
                $arrOption['samesite'] ?? 'Lax',
        ];

        if (
            array_key_exists('domain', $arrOption)
            && $arrOption['domain'] !== ''
        ) {
            $arrCookieOption['domain'] =
                $arrOption['domain'];
        }

        return $arrCookieOption;
    }
}