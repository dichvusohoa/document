<?php
namespace Core\Models\Auth;
use Core\Models\Session;
use Core\Models\User\UserInfo;
use Core\Models\User\UserService;
use Core\Models\Response;
use Core\Models\Cookie;
use Core\Models\AuthToken;
class AuthContext {
    protected UserService $userService;
   // protected array $arrAuth;//lưu trữ lại dữ liệu authenticate

    public function __construct(UserService $userService){
        $this->userService = $userService;
        //$this->arrAuth = [];
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public function getAuthInfo(): array{
        $arrAuthInfo = $this->getAuthInfoBySession();//SERVER_UNAUTHENTICATED_STATUS hoặc  SERVER_AUTHENTICATED_STATUS
        if($arrAuthInfo['data'] === null){
            //xác thực bằng session thất bại phải dùng cookie
            //SERVER_UNAUTHENTICATED_STATUS, SERVER_AUTHENTICATED_STATUS, SERVER_DB_ERR_STATUS
            $arrAuthInfo = $this->getAuthInfoByCookie();
        }
        /*đến bước cuối cùng này thì đánh giá tổng thể
        và modify lại dữ liệu để thuận tiện cho việc xử lý bên ngoài  function
        $auth["status"] có 3 khả năng:
         *  SERVER_DB_ERR_STATUS: do query dữ liệu khi check cookie bị lỗi
         *  SERVER_UNAUTHENTICATED_STATUS: 
         *  do 
         *      - không có token hoặc lỗi lưu trữ token trong cookie
         *      - query dữ liệu ra empty hoặc có dữ liệu nhưng format không chuẩn
         *  SERVER_AUTHENTICATED_STATUS
         */
        //kiểm tra và chuyển hướng kết thúc nếu phải login hoặc xảy ra Response::SERVER_DB_ERR_STATUS
       // $arrAuthInfo['status'] = Response::SERVER_DB_ERR_STATUS;
        Response::checkAndDispatch($arrAuthInfo,false);//
        if(AuthInfo::isUnauthenticated($arrAuthInfo) && $arrAuthInfo["data"] === null){
            //bổ sung các thông tin về guest user cho $auth["data"]
            $arrAuthInfo["data"] = UserInfo::buildGuest();
        }
      
        
        //tới đây là trạng thái Response::SERVER_AUTHENTICATED_STATUS hoặc SERVER_UNAUTHENTICATED_STATUS
        $arrAuthInfo['data']['last_activity'] = time(); 
        Session::set('auth', $arrAuthInfo['data']);//cập nhật lại session
        //$this->arrAuth = $auth;
        return $arrAuthInfo;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    /* return resp
     * resp["status"] === 
     * Response::SERVER_AUTHENTICATED_STATUS || 
     * Response::SERVER_UNAUTHENTICATED_STATUS
     */
    protected function getAuthInfoBySession(): array {
        $auth = Session::get('auth');
        if( UserInfo::isValid($auth)  
            && isset($auth['last_activity'])  //đây là field bổ sung vào để kiểm soát chính xác thời gian timeout
            && is_int($auth['last_activity'])){    
            
            if(time() - $auth['last_activity'] >= SESSION_TIMEOUT){
                Session::destroy();
            }
            else{
                unset($auth['last_activity']);//field last_activity lưu và cập nhật ở sesssion
                $status = $auth["id"] === null ? Response::SERVER_UNAUTHENTICATED_STATUS : Response::SERVER_AUTHENTICATED_STATUS;
                return ["status" => "$status", "data" => $auth, "extra" => "" ]; 
            }
            /*elseif($auth["id"] === null){
                return ["status" => Response::SERVER_UNAUTHENTICATED_STATUS, "data" => $auth, "extra" => "" ]; 
            }
            else{
                return ["status" => Response::SERVER_AUTHENTICATED_STATUS, "data" => $auth, "extra" => "" ]; 
            }*/
        }
        return ["status" => Response::SERVER_UNAUTHENTICATED_STATUS, "data" => null, "extra" => "session expired or missing" ]; //chưa authenticate by session
    }
    /*---------------------------------------------------------------------------------------------------------------*/    
    /* return resp
     * resp["status"] === 
     *  Response::SERVER_AUTHENTICATED_STATUS || 
     *  Response::SERVER_UNAUTHENTICATED_STATUS || 
     *  Response::SERVER_DB_ERR_STATUS
     */
    protected function getAuthInfoByCookie(): array {
        $strToken = Cookie::get(['auth','token']);
        //$strToken = 'c2bebdee0f0349d4c3796f419bf7f8:724c6b3bd4af0d9f7280943637bcbb59b827017798c207939eacbd901a490940';
        if($strToken === null || strpos($strToken,':') === false){
            return ["status" => Response::SERVER_UNAUTHENTICATED_STATUS, "data" => null, "extra" => "cookie token is missing or malformed"];
        }
        list($strLeftToken, $strRightToken) = explode(':', $strToken);
        if (strlen($strLeftToken) !== AuthToken::LEFT_TOKEN_LENGTH || strlen($strRightToken) !== AuthToken::RIGHT_TOKEN_LENGTH) {
            return ["status" => Response::SERVER_UNAUTHENTICATED_STATUS, "data" => null, "extra" => "invalid token structure: incorrect length"];
        }
        //$usrService = new UserService($this->dbService); 
        $arrAuthInfo = $this->userService->getUserByToken($strLeftToken);
        if(Response::isResponseOK($arrAuthInfo) && isset($arrAuthInfo["data"]) && UserInfo::isValid($arrAuthInfo["data"])){
            //sửa lại status từ Response::SERVER_OK_STATUS thành Response::SERVER_AUTHENTICATED_STATUS
            $arrAuthInfo["status"] = Response::SERVER_AUTHENTICATED_STATUS;
            $arrAuthInfo["extra"]  = "auth by cookie";
        }
        /*còn lại là các trường hợp
        Response::SERVER_DB_ERR_STATUS
        Response::SERVER_OK_STATUS  nhưng dữ liệu không thỏa mãn AuthInfo::isStandardPartData */
        if(Response::isResponseOK($arrAuthInfo)){
            /*Response::SERVER_OK_STATUS  nhưng dữ liệu không thỏa mãn AuthInfo::isStandardPartData 
            tức là $arrAuthInfo["data"] === null hoặc false hoặc có format không đúng định dạng isStandardPartData
            */
            $arrAuthInfo["status"] = Response::SERVER_UNAUTHENTICATED_STATUS;
            $arrAuthInfo["extra"]  = 'valid token but data format is invalid (not standard auth structure)';
        }
        //tới đây còn 2 khả năng  Response::SERVER_DB_ERR_STATUS và Response::SERVER_UNAUTHENTICATED_STATUS
        return $arrAuthInfo;
    }

    /*public function isLoggedIn(): bool {
        return $this->existElement('user_id');
    }

    public function getUserId(): ?int {
        return $this->getValue('user_id');
    }

    public function hasRole(string $role): bool {
        return in_array($role, $this->getValue('roles', []));
    }

    public function hasPermission(string $module, string $right): bool {
        return in_array($right, $this->getValue("permissions.$module", []));
    }

    public function login(array $userData): void {
        Session::set('auth', $userData);
        $this->data = $userData;
    }

    public function logout(): void {
        Session::remove('auth');
        $this->data = [];
    }*/
}
