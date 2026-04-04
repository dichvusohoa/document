<?php
namespace Core\Models\Utility;
class UriUtility{
    
    //tham khảo chuẩn RFC 3986
    public static function appendQueryParams(array|string $arrParam, ?string $strUri = null): string{
        $strUri = $strUri ?? ($_SERVER['REQUEST_URI'] ?? '/');
        $arrParsed = parse_url($strUri);
        parse_str($arrParsed['query'] ?? '', $arrQueryParam);
        // Chuẩn hóa input nếu $arrParam là dạng string
        if (is_array($arrParam)) {
            $arrQueryParam = array_merge($arrQueryParam, $arrParam);
        } else {
            parse_str($arrParam, $arrAdded);
            $arrQueryParam = array_merge($arrQueryParam, $arrAdded);
        }
        // Gắn lại query string mới vào arrParsed
        $arrParsed['query'] = http_build_query($arrQueryParam);
        // Ghép lại URL hoàn chỉnh
        return static::buildUrl($arrParsed);
    }
    public static function removeQueryParams(array|string $arrParam, ?string $strUri = null): string{
        $strUri = $strUri ?? ($_SERVER['REQUEST_URI'] ?? '/');
        $arrParsed = parse_url($strUri);
        parse_str($arrParsed['query'] ?? '', $arrQueryParam);
        
        
        if (is_array($arrParam)) {
            $keysToRemove = $arrParam;
        } else {
            parse_str($arrParam, $parsed);
            $keysToRemove = array_keys($parsed);
        }
        foreach ($keysToRemove as $key) {
            unset($arrQueryParam[$key]);
        }
        $arrParsed['query'] = http_build_query($arrQueryParam);
        // Dùng hàm chung để ghép lại
        return static::buildUrl($arrParsed);
    }
    /**
     * Tạo URL từ mảng parse_url().
     * $arrPart có thể chứa: scheme, host, path, query, fragment, port, user, pass
     */
    protected static function buildUrl(array $arrPart): string {
        $scheme   = $arrPart['scheme']   ?? '';
        $host     = $arrPart['host']     ?? '';
        $port     = isset($arrPart['port']) ? ':' . $arrPart['port'] : '';
        $user     = $arrPart['user']     ?? '';
        $pass     = isset($arrPart['pass']) ? ':' . $arrPart['pass']  : '';
        $auth     = $user ? "{$user}{$pass}@" : '';
        $path     = $arrPart['path']     ?? '';
        $query    = $arrPart['query']    ?? '';
        $fragment = $arrPart['fragment'] ?? '';

        return ($scheme ? "{$scheme}://" : '') .
               "{$auth}{$host}{$port}{$path}" .
               ($query ? "?{$query}" : '') .
               ($fragment ? "#{$fragment}" : '');
    }
    
}
