<?php
namespace Core\Foundation;
use Core\Http\Response;
class ErrorInfo{
    /*---------------------------------------------------------------------------------------------------------------*/
    public static function createEmpty(string $status = Response::SERVER_ERR_STATUS,
        string $message = ''): array  {
        return 
        [    
            'status' => $status,
            'data' => ErrorInfoData::createEmpty($message),
            'extra' => null
        ];
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public static function isValid(mixed $arrData): bool {
        return Response::isValid($arrData) && ErrorInfoData::isValid($arrData['data']);
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
        elseif($arrErr['status'] === Response::SERVER_EXECUTE_ACCESS_FORBIDDEN){
            return self::render403($arrErr);
        }
        elseif($arrErr['status'] === Response::SERVER_RESOURCE_NOT_FOUND_STATUS){
            return self::render404($arrErr);
        }
        else{
            return self::renderHtmlDefault($arrErr);
        }
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected static function render403($arrErr): string {
        if(!self::isValid($arrErr)){
            return '<h1>403 - </h1>';
        }
        $strMessage = isset($arrErr['data']['message']) ? htmlspecialchars((string)$arrErr['data']['message'], ENT_QUOTES, 'UTF-8') : 'service unavailable';
        return 
        <<<HTML
        <div>
            
            <h1>403 - Không đủ quyền truy cập</h1>
            <p>{$strMessage}</p>
        </div>
        HTML;  
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
        $type = isset($arrErr['data']['type']) ? $arrErr['data']['type'] : '';
        return "<h1>500 - {$type}</h1>"
        . ErrorInfoData::renderHtml($arrErr['data']);
    }
    /*---------------------------------------------------------------------------------------------------------------*/
}