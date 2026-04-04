<?php
namespace Core\Models;
class Response {
    const RESPONSE_HTML_TYPE = 'html';
    const RESPONSE_JSON_TYPE = 'json';
    
    const SERVER_OK_STATUS  = 'server_ok';
    const SERVER_MAINTENANCE_STATUS  = 'server_maintenance_status'; /*hệ thống suspended*/
    const SERVER_AUTHENTICATED_STATUS = 'server_authenticated'; 
    const SERVER_UNAUTHENTICATED_STATUS = 'server_unauthenticated'; 
    /*Mã lỗi SERVER_ERR_STATUS là mã lỗi chung nhất thường được trả về khi xử lý dữ liệu bị lỗi*/
    const SERVER_ERR_STATUS = 'server_error'; 
    /* khi muốn báo lỗi cụ thể hơn thì có thể dùng các mã lỗi sau */
    /* Thường khi run SQL bị lỗi cú pháp, bị lỗi vi phạm contraints ... có thể trả về lỗi này SERVER_DB_ERR_STATUS*/
    const SERVER_DB_ERR_STATUS = 'server_db_error';
    /*lỗi do logic dữ liệu. Ví dụ ta qui ước logic không được tạo các thực phẩm trùng tên trong database */
    const SERVER_LOGIC_ERR_STATUS = 'server_logic_error';
    // sữ dụng mã này khi thực hiện nhiều lệnh SQL, có một số thành công và một số thất bại 
    const SERVER_INCOMPLETE_STATUS = 'server_incomplete';
    const SERVER_PARSE_ERR_STATUS = 'server_parse_error'; //ví dụ dùng khi json_encode lỗi
    const SERVER_RESOURCE_NOT_FOUND_STATUS = 'server_resource_not_found';// 404 not found 
    
    public static array $ERROR_STATUSES = [
        self::SERVER_ERR_STATUS,
        self::SERVER_DB_ERR_STATUS,
        self::SERVER_LOGIC_ERR_STATUS,
        self::SERVER_INCOMPLETE_STATUS,
        self::SERVER_PARSE_ERR_STATUS
    ];
    
    /*---------------------------------------------------------------------------------------------------------------*/
    public static function isValid(mixed $response): bool {
        return is_array($response) && isset($response["status"]) && is_string($response["status"]) && array_key_exists("data", $response);
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public static function isResponseError(mixed $response): bool {
        /*$arr = [self::SERVER_OK_STATUS, self::SERVER_MAINTENANCE_STATUS,self::SERVER_AUTHENTICATED_STATUS ,self::SERVER_UNAUTHENTICATED_STATUS];
        return !self::isValid($response) || !in_array($response["status"], $arr);
         
         */
        return self::isValid($response) && in_array($response["status"], self::$ERROR_STATUSES);
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public static function isResponseOK(mixed $response): bool {
        return self::isValid($response) && $response["status"] === self::SERVER_OK_STATUS;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public static function isResponseEmpty(mixed $response): bool {
        return self::isValid($response) && array_key_exists("data", $response) && ($response["data"] === null || $response["data"] === false);
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected static function getResponseType(): string {
        return strtolower(trim($_GET['response_type'] ?? self::RESPONSE_HTML_TYPE));
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public static function sendJson(array $payload): void {
        header('Content-Type: application/json; charset=UTF-8');
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            http_response_code(500);
            $json = json_encode(['status' => self::SERVER_PARSE_ERR_STATUS, 'data' => 'JSON encoding failed', 'extra' => '']);
        }
        echo $json;
        exit();
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public static function sendHtmlFile(string $strFilePath, bool $isToBuffer = false, ?array $vars = null) {
        if (!file_exists($strFilePath)) {
            throw new Exception("Html file not found: $strFilePath");
        }
        // “bơm” mảng thành các biến cục bộ trong phạm vi include
        if(isset($vars)){
            extract($vars, EXTR_SKIP);
        }
        if($isToBuffer){
            ob_start();
            include $strFilePath;
            return ob_get_clean();
        }
        /*--------------------------------------
        * DIRECT OUTPUT MODE
        *------------------------------------*/
        header('Content-Type: text/html; charset=UTF-8');
        include $strFilePath;
        exit();
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected static function chkAndDispForJsonRequest(mixed $response, bool $isFinalStep):void{
        if( !self::isValid($response) || self::isResponseError($response)){
            http_response_code(500);
        }
        if( !self::isValid($response) || self::isResponseError($response) || $isFinalStep){
            self::sendJson($response);
        }
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected static function chkAndDispForHtmlRequest(mixed $response, bool $isFinalStep, ?string $strLayout): void{
        if(!self::isValid($response) || self::isResponseError($response)){
            /*các trạng thái có thể. 
            SERVER_ERR_STATUS, 
            SERVER_DB_ERR_STATUS, 
            SERVER_LOGIC_ERR_STATUS
            SERVER_INCOMPLETE_STATUS ;
            
            Nhưng sau này cô gắng xử lý bên ngoài function các trạng thái
            SERVER_LOGIC_ERR_STATUS
            SERVER_INCOMPLETE_STATUS 
            và tránh dùng báo lỗi chung chung SERVER_ERR_STATUS
            
            chỉ chuyền vào trong hàm này trạng thái SERVER_DB_ERR_STATUS thôi
             */
            ErrorHandler::handleErrorResponse($response,500);
        }
        if($isFinalStep && isset($strLayout)){
            require $strLayout;
            exit();
        }
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    /*  $arrResponseData: Với một hệ thống dữ liệu lớn, $arrResponseData sẽ do nhiều phần ghép lại kiểu như
     *  [ "$partId_1" => $arrPartData_1 , 
     *    "$partId_2" => $arrPartData_2 ,   
     *    ....  
     *    "$partId_final"  => $arrPartData_final ,   
     *  ]
     *  trong đó cấu trúc của một phần cụ thể (ví dụ $arrPartData_1) sẽ là ["status" => ... , "data" => ...,"extra" =>... ]
     *  
     *  $isFinalStep: data có thể xây dựng nhiều step query vào database rồi ghép lại ($part_1, $part_2,...). Khi tới bước cuối cùng thì gọi
     *  function này với $isFinalStep = true;
     *  Khi đó $arrResponseData có thể có cấu trúc multi part như mô tả ở trên
     * 
     *  $isFinalStep = false. là một bước trung gian nào đó. Thường sử dụng khi tới một step xây dựng data quan trọng
     *  yêu cầu nếu có lỗi thì phải dừng chương trình. Chú ý rằng khi $isFinalStep = false thì tham số $arrResponseData lúc đó chỉ là một cấu trúc đơn
     *  kiểu như ["status" => ... , "data" => ...,"extra" =>... ]
     *  
     *  $strPathFileLayout: dùng khi request yêu cầu trả về dữ liệu dạng HTML. đây thường là đường dẫn tới file layout
     *  file layout này và $arrResponseData, kết hợp javaScript ở client sẽ hiển thị dữ liệu
     *  $strPathFileLayout cũng chỉ có ý nghĩa khi $isFinalStep = true;
     *  
     *  Xử lý kiểm soát lỗi: 
     *  1) Lỗi quan trọng:  mỗi một step data quan trọng ( nếu false thì phải kết thúc chương trình) thì phải gọi 
     *  handleData với $arrResponseData là dạng đơn, $isFinalStep = false. 
     *  2. Lỗi không quan trọng Có một số dạng data không quan trọng lắm ( thí dụ data Title, Footer) thì có thể bỏ qua
     *  3) kiểm soát lỗi ở bước cuối cùng. khi $arrResponseData gồm nhiều phần gửi về client thì trong đó có thể có phần có lỗi 
     * ( phần không quan trọng như header, footer, ..) , phần không có lỗi ( thí dụ data table ...). Khi đó client có thể
     *  dùng javaScript để hiển thị các phần data không lỗi, và hiển thị lỗi cho các error data 
     * 
     * chú ý là checkAndDispatch không thực hiện phân luồng về suspend.php đó là việc của hàm checkSystemSuspended
     */
    public static function checkAndDispatch(mixed $response,bool $isFinalStep = false, $strPathFileLayout = null ): void{
        //$resType = filter_input(INPUT_GET, "response_type",FILTER_SANITIZE_STRING,array("options" => array("default" => "html")));
        $resType = self::getResponseType();
        if($resType === "json"){
            self::chkAndDispForJsonRequest($response,$isFinalStep);       }
        elseif($resType === "html"){
            self::chkAndDispForHtmlRequest($response,$isFinalStep, $strPathFileLayout);
        }
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public static function redirect(string $url, int $statusCode = 302) {
        header("Location: " . $url, true, $statusCode);
        exit(); // Đảm bảo dừng luồng xử lý
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    /*public static function mapFromHttpCode(int $httpCode){
        switch ($httpCode){
            case 503:
               return SERVER_MAINTENANCE_STATUS;
                
            case 500:
                return SERVER_ERR_STATUS;
        }
        
    }*/
}