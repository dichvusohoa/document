<?php
namespace Core\Models\Utility;
class HtmlUtility{
    static public function toCssLinks(string $strPublicPath, string|array $arrCss): string {
        if (!is_array($arrCss)){
            $arrCss = [$arrCss];//chuyển 1 string thành mảng 1 phần tử
        }
        $output = '';
        foreach ($arrCss as $item) {
            if (is_string($item)) {
                $href = $item;
                $attrs = [];
            } elseif (is_array($item) && isset($item['href'])) {
                $href = $item['href'];
                $attrs = $item;
                unset($attrs['href']);
            } else {
                continue;
            }
            // Append version nếu file nội bộ
            if (self::isLocalAsset($href)) {
                $href = self::appendAssetVersion($strPublicPath, $href);
            }
            // Build attributes
            $attrString = self::buildHtmlAttributes($attrs);
            $output .= "<link rel='stylesheet' href='{$href}' type='text/css'{$attrString} />\n";
        }
        return $output;
    } 
    /*---------------------------------------------------------------------------------------------------------------*/
        /**
     * Build thẻ <script type="importmap"> theo chuẩn.
     *
     * @param array $imports  ['namespace' => 'url']
     */
    protected static function buildImportMap(array $imports): string{
        if (empty($imports)) {
            return '';
        }

        $json = json_encode(
            ['imports' => $imports],
            JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );

        return "<script type=\"importmap\">{$json}</script>\n";
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    static public function toScriptLinks(string $strPublicPath, string|array $arrScript): string{
        if (!is_array($arrScript)) {
            $arrScript = [$arrScript];
        }
        $output = '';
        $importMap = [];
        foreach ($arrScript as $item) {

            // ---------- Normalize ----------
            if (is_string($item)) {
                $src = $item;
                $attrs = [];
                $isImportMap = false;
            }
            elseif (is_array($item) && isset($item['src'])) {
                $src = $item['src'];
                $attrs = $item;
                unset($attrs['src']);

                $isImportMap = !empty($item['importmap']);
            }
            else {
                continue; // invalid
            }

            // ---------- Validate importmap ----------
            if ($isImportMap && empty($item['namespace'])) {
                continue;
            }

            // ---------- Append version for local ----------
            if (self::isLocalAsset($src)) {
                $src = self::appendAssetVersion($strPublicPath, $src);
            }

            // ---------- Importmap ----------
            if ($isImportMap) {
                $importMap[$item['namespace']] = $src;
                continue;
            }

            // ---------- Normal script ----------
            $attrString = self::buildHtmlAttributes($attrs);
            $output .= "<script src=\"{$src}\"{$attrString}></script>\n";
        }

        // Importmap MUST be before module scripts
        return self::buildImportMap($importMap) . $output;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    static public function favicon(string $favicon_path = '/'){
        return <<<HTML
        <!-- Fallback for Older Browsers -->
        <link rel="shortcut icon" href="{$favicon_path}favicon.ico">

        <!-- Standard Favicon -->
        <link rel="icon" type="image/png" sizes="16x16" href="{$favicon_path}favicon-16x16.png">
        <link rel="icon" type="image/png" sizes="32x32" href="{$favicon_path}favicon-32x32.png">
        <link rel="icon" type="image/png" sizes="48x48" href="{$favicon_path}favicon-48x48.png">

        <!-- Android/Google Icons -->
        <link rel="icon" type="image/png" sizes="192x192" href="{$favicon_path}android-chrome-192x192.png">
        <link rel="icon" type="image/png" sizes="512x512" href="{$favicon_path}android-chrome-512x512.png">

        <!-- Apple Touch Icons -->
        <link rel="apple-touch-icon" sizes="60x60" href="{$favicon_path}apple-touch-icon-60x60.png">
        <link rel="apple-touch-icon" sizes="72x72" href="{$favicon_path}apple-touch-icon-72x72.png">
        <link rel="apple-touch-icon" sizes="120x120" href="{$favicon_path}apple-touch-icon-120x120.png">
        <link rel="apple-touch-icon" sizes="152x152" href="{$favicon_path}apple-touch-icon-152x152.png">
        <link rel="apple-touch-icon" sizes="180x180" href="{$favicon_path}apple-touch-icon-180x180.png">

        <!-- Windows Metro Tiles -->
        <meta name="msapplication-TileImage" content="{$favicon_path}mstile-150x150.png">
        <meta name="msapplication-TileColor" content="#da532c">

        <link rel="manifest" href="{$favicon_path}site.webmanifest">
        <meta name="theme-color" content="#ffffff">
        HTML;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected static function isLocalAsset(string $src): bool{
        $url = parse_url($src);
        // Có scheme → external (http, https, data, blob…)
        if (!empty($url['scheme'])) {
            return false;
        }
        // //cdn.example.com
        if (str_starts_with($src, '//')) {
            return false;
        }
        return true;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected static function appendAssetVersion(string $strPublicPath, string $strFile):string{
         // Lấy path thuần (bỏ query nếu có)
        $strPathOnly = parse_url($strFile, PHP_URL_PATH);
        // Ghép filesystem path an toàn để cung cấp cho hàm FileUtility::fileVer
        $realPath = rtrim($strPublicPath, '/').'/'.ltrim($strPathOnly, '/');
        //nếu $strFile đã có ? thì thêm dấu & vào cuối, chưa có thì thêm ?
        $separator  = str_contains($strFile, '?') ? '&' : '?';
        return $strFile.$separator.'v='.FileUtility::fileVer($realPath);
        
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected static function buildHtmlAttributes(array $attrs): string {
        $html = '';
        foreach ($attrs as $key => $value) {
            // Boolean attribute: defer, async, nomodule...
            if ($value === true) {
                $html .= " {$key}";
            }
            // Attribute có giá trị: type = "module", type = "importmap" ...
            elseif ($value !== false && $value !== null) {
                $escaped = htmlspecialchars((string)$value, ENT_QUOTES);
                $html .= " {$key}=\"{$escaped}\"";
            }
        }
        return $html;
    }
    
}
