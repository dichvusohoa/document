<?php
namespace Core\Foundation;
use \Throwable;
use \ErrorException;
use Core\Http\Request;
use Core\Http\Response;
use Core\Http\HttpException;

class ErrorHandler {
    /*---------------------------------------------------------------------------------------------------------------*/
    protected static function getHandleErrorFilePath($filename){
        $strCustomPath = APP_PATH.'resources'.DIRECTORY_SEPARATOR.'views'.DIRECTORY_SEPARATOR.'errors'. DIRECTORY_SEPARATOR.$filename;
        $strDefaultPath = CORE_PATH .'resources'.DIRECTORY_SEPARATOR.'views'.DIRECTORY_SEPARATOR.'errors'.DIRECTORY_SEPARATOR.$filename;
        if (file_exists($strCustomPath)) {
            return $strCustomPath;
        } elseif (file_exists($strDefaultPath)) {
            return $strDefaultPath;
        }
        return '';
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public static function toHttpStatus(Throwable|array $e){
        return ($e instanceof HttpException)
        ? $e->getHttpStatusCode() //các mã như 503, 404
        : 500; // mặc định 500 nếu không phải là HttpException
        
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public static function toResponseStatus(Throwable|array $e){
        $httpStatus = self::toHttpStatus($e);
        switch ($httpStatus){
            case 503:
            $resStatus = Response::SERVER_MAINTENANCE_STATUS;
            break;
            case 403:
            $resStatus = Response::SERVER_EXECUTE_ACCESS_FORBIDDEN;
            break;    
            case 404:
            $resStatus = Response::SERVER_RESOURCE_NOT_FOUND_STATUS;
            break;
            default:
            $resStatus = Response::SERVER_ERR_STATUS; 
            break;
        }
        return $resStatus;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected static function toErrorInfoData(Throwable $e): array {
        $arr = ErrorInfoData::createEmpty();

        $arr['message'] = $e->getMessage();
        $arr['code']    = $e->getCode();
        $arr['type']    = get_class($e);
        $arr['file']    = $e->getFile();
        $arr['line']    = $e->getLine();
        $arr['trace']   = explode("\n", $e->getTraceAsString());

        if ($e instanceof HttpException) {
            $arr['headers'] = $e->getHeaders();
        }

        if ($e->getPrevious() !== null) {
            $arr['previous'] = self::toErrorInfoData($e->getPrevious());
        }

        return $arr;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    /*$e là array là do handleShutdown truyền vào. khi đó $e thường chỉ có 3 field
    message,file, line
    - $strRespStatus có giá trị tường minh thường khi do chủ động gọi hàm ErrorHandler::toResponseFormat, ví dụ như trong DbService
    - $strRespStatus = null khi throw Exception (kể cả chủ động hay thụ động). Sau đó tại handleException
     thu được Throwable $e và đem biến đổi ra format Response bằng lệnh toResponseFormat($e) không có tham số $strRespStatus
     */
    public static function toResponseFormat(Throwable|array $e, ?string $strRespStatus = null): array {
        $serverStatus =  $strRespStatus ?? self::toResponseStatus($e);
        $httpStatus   =  self::toHttpStatus($e);
        $resp = ErrorInfo::createEmpty();
        $resp['status'] = $serverStatus;
        $resp['extra']  = $httpStatus;
        if ($e instanceof Throwable) {
            $resp['data'] = self::toErrorInfoData($e);
        } elseif (is_array($e)) {
            $resp['data']['message'] = $e['message'] ?? 'Unknown error';
            $resp['data']['file']    = $e['file'] ?? null;
            $resp['data']['line']    = $e['line'] ?? null;
            $resp['data']['code']    = null;
            $resp['data']['type']    = self::phpErrorTypeToString($e['type'] ?? null);
        }
        return $resp;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected static function phpErrorTypeToString(?int $type): string {    
        return match ($type) {
            E_ERROR         => 'E_ERROR',
            E_PARSE         => 'E_PARSE',
            E_CORE_ERROR    => 'E_CORE_ERROR',
            E_COMPILE_ERROR => 'E_COMPILE_ERROR',
            default         => 'PHP_FATAL_ERROR',
        };
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public static function register(): void{
        // Chuyển warning, recoverable error... thành exception
        set_error_handler([self::class, 'handleError']);
        // Bắt mọi throwable chưa catch
        set_exception_handler([self::class, 'handleException']);
        // Dùng để bắt lỗi fatal (parse error, etc.)
        register_shutdown_function([self::class, 'handleShutdown']);
    }
   /*---------------------------------------------------------------------------------------------------------------*/
    public static function handleError(int $errno, string $errstr, string $errfile, int $errline): bool{
        // Nếu lỗi không thuộc nhóm error_reporting hiện tại → bỏ qua
        if (!(error_reporting() & $errno)) {
            return false;
        }
        throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public static function handleException(Throwable $e): void{
        $errInfo = self::toResponseFormat($e);//sẽ dùng $errInfo trong file include
        self::handleErrorResponse($errInfo);
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    /*
    * Trên PHP 8.x, đa số lỗi runtime đã là Throwable.
    * Tuy nhiên vẫn giữ shutdown handler để bắt một số fatal error
    * không đi qua set_error_handler/set_exception_handler.
    */
    public static function handleShutdown(): void {
        $error = error_get_last();
        //$error chỉ có các field type, message, file, line
        if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $errInfo = self::toResponseFormat($error);//sẽ dùng $errInfo trong file include
            self::handleErrorResponse($errInfo);
        }
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public static function handleErrorResponse(array $respErr): void{
        $strRespType = Request::getResponseType();
        if($strRespType === 'html'){
            self::handleErrorHTMLResponse($respErr);
        }
        elseif($strRespType === 'json'){
            self::handleErrorJsonResponse($respErr);
        }
        else {
            self::handleErrorHTMLResponse($respErr);
        }
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected static function handleErrorHTMLResponse(array $respErr): void{
        $httpStatus =  $respErr['extra'];
        $strFileName = $httpStatus .'.phtml';
        $strFullPathFileName = self::getHandleErrorFilePath($strFileName);
        http_response_code($httpStatus);
        if($strFullPathFileName !== ''){
            $errInfo = $respErr; //sẽ dùng trong file include
            if (!empty($respErr['data']['headers']) && is_array($respErr['data']['headers'])) {
                foreach ($respErr['data']['headers'] as $k => $v) {
                    header("$k: $v");
                }
            }
            include $strFullPathFileName;
        }
        else{
            echo 'Unknown error';
        }
        exit();
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected static function handleErrorJsonResponse(array $respErr): void{
        $httpStatus =  $respErr['extra'];
        http_response_code($httpStatus);
        Response::sendJson($respErr);
    }
}
