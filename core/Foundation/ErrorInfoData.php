<?php
namespace Core\Foundation;
class ErrorInfoData{
    public static function isValid(mixed $arrData): bool {
        return is_array($arrData)
            && isset($arrData['message']) && is_string($arrData['message'])
            && array_key_exists('code',$arrData) 
            && ($arrData['code'] === null || is_int($arrData['code']) || is_string($arrData['code']) )     
            && array_key_exists('type', $arrData) //class name nếu có
            && ($arrData['type'] === null || is_string($arrData['type']) )         
            && array_key_exists('file', $arrData)
            && ($arrData['file'] === null || is_string($arrData['file']))    
            && array_key_exists('line', $arrData)
            && ($arrData['line'] === null || is_int($arrData['line']) )        
            && array_key_exists('trace', $arrData)
            && ($arrData['trace'] === null || is_array($arrData['trace']) )
            && array_key_exists('headers', $arrData)
            && ($arrData['headers'] === null || is_array($arrData['headers']) )
            && array_key_exists('previous', $arrData) 
            && ($arrData['previous'] === null || self::isValid($arrData['previous']));
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public static function buildEmpty(string $message = ''): array  {
        return [    
            'message'   => $message,
            'code'      => null,
            'type'      => null, //thường là class name
            'file'      => null,
            'line'      => null,
            'trace'     => null,
            'headers'   => null,
            'previous'  => null
        ];
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public static function renderHtml(array $arr, int $level = 0): string {
        if (!self::isValid($arr)) {
            return '';
        }
        $type = htmlspecialchars((string)($arr['type'] ?? ''), ENT_QUOTES, 'UTF-8');
        $code = htmlspecialchars((string)($arr['code'] ?? ''), ENT_QUOTES, 'UTF-8');
        $file = htmlspecialchars((string)($arr['file'] ?? '(n/a)'), ENT_QUOTES, 'UTF-8');
        $line = htmlspecialchars((string)($arr['line'] ?? ''), ENT_QUOTES, 'UTF-8');
        $message = htmlspecialchars((string)($arr['message'] ?? 'Unknown error'), ENT_QUOTES, 'UTF-8');

        $title = $level === 0 ? 'Exception' : 'Previous exception';

        $traceHtml = '';
        if (!empty($arr['trace']) && is_array($arr['trace'])) {
            $traceLines = array_map(function ($line) {
                return htmlspecialchars((string)$line, ENT_QUOTES, 'UTF-8');
            }, $arr['trace']);

            $traceHtml = '<pre style="background:#eee;padding:1em;border:1px solid #ccc;">'
                . implode("\n", $traceLines)
                . '</pre>';
        }

        $previousHtml = '';
        if (!empty($arr['previous']) && is_array($arr['previous'])) {
            $previousHtml = '<hr>' . self::renderHtml($arr['previous'], $level + 1);
        }

        return <<<HTML
            <h2>{$title}</h2>
            <strong>Loại lỗi:</strong> {$type}<br>
            <strong>Mã lỗi:</strong> {$code}<br>
            <strong>Thông điệp:</strong> {$message}<br>
            <strong>Vị trí:</strong> {$file} : {$line}<br>
            {$traceHtml}
            {$previousHtml}
        HTML;
    }
}