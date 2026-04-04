<?php
namespace Core\Models;
class ErrorInfo{
    /*---------------------------------------------------------------------------------------------------------------*/
    public static function buildEmpty(string $status = Response::SERVER_ERR_STATUS,
        string $message = ''): array  {
        return 
        [    
            'status' => $status,
            'data' => [
                'message'   => $message,
                'code'      => null,
                'type'      => null, //thường là class name
                'file'      => null,
                'line'      => null,
                'trace'     => null,
                'headers'   => null
            ],
            'extra' => null
        ];
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public static function isValid(mixed $arrData): bool {
        return is_array($arrData)
            && isset($arrData['status'])
            && isset($arrData['data']) && is_array($arrData['data'])
            && isset($arrData['data']['message']) && is_string($arrData['data']['message'])
            && array_key_exists('code',$arrData['data']) 
            && ($arrData['data']['code'] === null || is_int($arrData['data']['code']) || is_string($arrData['data']['code']) )     
            && array_key_exists('type', $arrData['data']) //class name nếu có
            && ($arrData['data']['type'] === null || is_string($arrData['data']['type']) )         
            && array_key_exists('file', $arrData['data'])
            && ($arrData['data']['file'] === null || is_string($arrData['data']['file']))    
            && array_key_exists('line', $arrData['data'])
            && ($arrData['data']['line'] === null || is_int($arrData['data']['line']) )        
            && array_key_exists('trace', $arrData['data'])
            && ($arrData['data']['trace'] === null || is_array($arrData['data']['trace']) )
            && array_key_exists('headers', $arrData['data'])
            && ($arrData['data']['headers'] === null || is_array($arrData['data']['headers']) );    
           
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    /**
    * Render error info ra HTML để hiển thị cho người dùng cuối
    */
    public static function renderHtml($arrErr): string {
        if(!self::isValid($arrErr)){
            return '<h1>500 - Lỗi hệ thống</h1>';
        }
        if($arrErr['status'] === Response::SERVER_MAINTENANCE_STATUS){
            return self::render503($arrErr);
        }
        elseif($arrErr['status'] === Response::SERVER_RESOURCE_NOT_FOUND_STATUS){
            return self::render404($arrErr);
        }
        else{
            return self::renderHtmlDefault($arrErr);
        }
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected static function render404($arrErr): string {
        if(!self::isValid($arrErr)){
            return '<h1>404 - Không tìm thấy đường dẫn này</h1>';
        }
        $strMessage = isset($arrErr['data']['message']) ? htmlspecialchars((string)$arrErr['data']['message'], ENT_QUOTES, 'UTF-8') : 'service unavailable';
        return 
        <<<HTML
        <div>
            
            <h1>404 - Không tìm thấy đường dẫn này</h1>
            <p>{$strMessage}</p>
        </div>
        HTML;  
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected static function render503($arrErr): string {
        if(!self::isValid($arrErr)){
            return '<h1>503 - Bảo trì hệ thống</h1>';
        }
        $strMessage = isset($arrErr['data']['message']) ? htmlspecialchars((string)$arrErr['data']['message'], ENT_QUOTES, 'UTF-8') : 'service unavailable';
        return 
        <<<HTML
        <div>
            <img src="/lib_assets/images/svgs/maintenance.svg" width="150" height="150">
            <h1>503 - Bảo trì hệ thống</h1>
            <p>{$strMessage}</p>
        </div>
        HTML;  
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected static function renderHtmlDefault($arrErr): string {
        if(!self::isValid($arrErr) || !APP_DEBUG ){
            return '<h1>500 - Lỗi hệ thống</h1>';
        }
        $arr = $arrErr["data"];
        $type = isset($arr['type']) ? $arr['type'] : '';//thường là class name có lỗi
        $code = isset($arr['code']) ? $arr['code'] : ''; //quan trọng trong PDOExecption
        $strFile = isset($arr['file']) ? $arr['file'] : '(n/a)';
        $line = isset($arr['line']) ? $arr['line'] : '';
        /*ENT_QUOTES có nghĩa là chuyển đổi cả dấu nháy đơn ' và dấu nháy kép " thành thực thể HTML tương ứng:*/
        $strMessage = isset($arr['message']) ? htmlspecialchars((string)$arr['message'], ENT_QUOTES, 'UTF-8') : 'Unknown error';
        

        $strTraceHtml = '';
        if (!empty($arr['trace']) && is_array($arr['trace'])) {
            $arrTraceLines = array_map(function ($line) {
                return htmlspecialchars($line, ENT_QUOTES, 'UTF-8');
            }, $arr['trace']);
            /*dùng thẻ pre để giữ đúng định dạng*/
            $strTraceHtml = "<pre style=\"background:#eee;padding:1em;border:1px solid #ccc;\">" .
                         implode("\n", $arrTraceLines) .
                         "</pre>";
        }

        return <<<HTML
                <h1>500 - {$type}</h1>
                <strong>Loại lỗi:</strong> {$type}<br>
                <strong>Mã lỗi:</strong> {$code}<br>
                <strong>Thông điệp:</strong> {$strMessage}<br>
                <strong>Vị trí:</strong> {$strFile} : {$line}<br>
                {$strTraceHtml}
            
        HTML;  
    }
    /*---------------------------------------------------------------------------------------------------------------*/
}