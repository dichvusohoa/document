<?php
namespace Core\Models;
use \Throwable;
use \ErrorException;
class ErrorHandler {
    protected static bool $convertNotice = false;
    /*---------------------------------------------------------------------------------------------------------------*/
    protected static function getHandleErrorFilePath($filename){
        $strCustomPath = APP_PATH . 'views'. DIRECTORY_SEPARATOR.'errors'. DIRECTORY_SEPARATOR. $filename;
        $strDefaultPath = CORE_PATH . 'views'. DIRECTORY_SEPARATOR.'errors'. DIRECTORY_SEPARATOR. $filename;
        if (file_exists($strCustomPath)) {
            return $strCustomPath;
        } elseif (file_exists($strDefaultPath)) {
            return $strDefaultPath;
        }
        return '';
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public static function toHttpStatus(Throwable|array $e){
        return ($e instanceof \Core\Models\HttpException)
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
    /*$e là array là do handleShutdown truyền vào. khi đó $e thường chỉ có 3 field
    message,file, line*/
    public static function toResponseFormat(Throwable|array $e){
        $serverStatus =  self::toResponseStatus($e);
        $httpStatus   =  self::toHttpStatus($e);
        $resp = ErrorInfo::buildEmpty();
        if($e instanceof Throwable){
            $resp['data']['message'] = $e->getMessage();
            $resp['data']['code']    = $e->getCode();
            $resp['data']['type']  = get_class($e);
            $resp['data']['file']  = $e->getFile();
            $resp['data']['line']  = $e->getLine();
            $resp['data']['trace'] = explode('\n', $e->getTraceAsString());
            if($e instanceof HttpException){
                $resp['data']['headers'] = $e->getHeaders();
            }
        }
        elseif (is_array($e)) {
            /*Đề phòng tương thích với lịch sử của PHP thôi. Đã test thử trên PHP 8.0
            và thấy rằng khi lỗi xảy ra thì đều là dạng class instanceof Throwable nên
             nhánh này sẽ không còn xảy ra nữa
             */
            $resp['data']['message'] = isset($e['message'])? $e['message'] : 'Unknown error';
            $resp['data']['file']  = isset($e['file'])? $e['file'] : null;
            $resp['data']['line']  = isset($e['file'])? $e['file'] : null;
            
        }
        $resp['status'] = $serverStatus;
        $resp['extra'] = $httpStatus;
        return $resp;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public static function register(bool $convertNotice = false): void{
        self::$convertNotice = $convertNotice;
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
        // Nếu là Notice nhưng không muốn chuyển
        if (!$convertNotice = self::$convertNotice) {
            if (in_array($errno, [E_NOTICE, E_USER_NOTICE])) {
                return false;
            }
        }
        throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public static function handleException(Throwable $e): void{
        $errInfo = self::toResponseFormat($e);//sẽ dùng $errInfo trong file include
        self::handleErrorResponse($errInfo);
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    /*hàm này là để đề phòng tương thích với lịch sử của PHP thôi. Đã test thử trên PHP 8.0
    và thấy rằng khi lỗi xảy ra thì đều là dạng class instanceof Throwable */
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
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected static function handleErrorHTMLResponse(array $respErr): void{
        $httpStatus =  $respErr['extra'];
        $strFileName = $httpStatus .'.phtml';
        $strFullPathFileName = self::getHandleErrorFilePath($strFileName);
        if($strFullPathFileName !== ''){
            $errInfo = $respErr; //sẽ dùng trong file include
            //báo cho client biết có lỗi bằng http_response_code
            http_response_code($httpStatus);
            if($respErr['data']['headers']){
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
