<?php
namespace Core\Auth;
use Core\Http\Session;
use Core\User\UserInfo;
use Core\User\UserService;
use Core\Http\Response;
use Core\Http\Cookie;

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
            //chưa có thông tin lưu session => xác thực bằng session thất bại phải dùng cookie
            $arrAuthInfo = $this->getAuthInfoByCookie();
        }
        /*đến bước cuối cùng này thì đánh giá tổng thể
        và modify lại dữ liệu để thuận tiện cho việc xử lý bên ngoài  function
        $auth["status"] có 2 khả năng:
         *  SERVER_UNAUTHENTICATED_STATUS: 
         *  do 
         *      - không có token hoặc lỗi lưu trữ token trong cookie
         *      - query dữ liệu ra empty hoặc có dữ liệu nhưng format không chuẩn
         *  SERVER_AUTHENTICATED_STATUS
         */
        //kiểm tra và chuyển hướng kết thúc nếu phải login hoặc xảy ra Response::SERVER_DB_ERR_STATUS
       // $arrAuthInfo['status'] = Response::SERVER_DB_ERR_STATUS;
        
        if(AuthInfo::isUnauthenticated($arrAuthInfo)){
            //bổ sung các thông tin về guest user cho $auth["data"]
            $arrAuthInfo["data"] = UserInfo::createGuest();
            $arrAuthInfo['data'][UserInfo::FIELD_CREATED_AT] = time(); 
        }
        //tới đây là trạng thái Response::SERVER_AUTHENTICATED_STATUS hoặc SERVER_UNAUTHENTICATED_STATUS
        $arrAuthInfo['data'][UserInfo::FIELD_LAST_ACTIVITY] = time(); 
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
        if( UserInfo::isValidSessionData($auth)){  
            $boolIdleExpired =
                time() - $auth[UserInfo::FIELD_LAST_ACTIVITY]
                >= SESSION_IDLE_TIMEOUT;
            $boolAbsoluteExpired =
                time() - $auth[UserInfo::FIELD_CREATED_AT]
                >= SESSION_ABSOLUTE_TIMEOUT;
            if ($boolIdleExpired || $boolAbsoluteExpired) {
                Session::destroy();
            }
            else{
                //field created_at, last_activity lưu và cập nhật ở sesssion, không cần dùng trong logic xử lý sau này
                unset($auth[UserInfo::FIELD_LAST_ACTIVITY]);
                unset($auth[UserInfo::FIELD_CREATED_AT]);
                $status = $auth["id"] === null ? Response::SERVER_UNAUTHENTICATED_STATUS : Response::SERVER_AUTHENTICATED_STATUS;
                return ["status" => "$status", "data" => $auth, "extra" => "" ]; 
            }
            
        }
        return ["status" => Response::SERVER_UNAUTHENTICATED_STATUS, "data" => null, "extra" => "session expired or missing" ]; //chưa authenticate by session
    }
    /*---------------------------------------------------------------------------------------------------------------*/    
    /* return resp
     * resp["status"] === 
     *  Response::SERVER_AUTHENTICATED_STATUS || 
     *  Response::SERVER_UNAUTHENTICATED_STATUS || 
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
            $arrAuthInfo["status"] = Response::SERVER_AUTHENTICATED_STATUS;
            $arrAuthInfo["extra"]  = "auth by cookie";
            return $arrAuthInfo;
        }
        /*còn lại là các trường hợp Response::isResponseEmpty*/
        $arrAuthInfo["status"] = Response::SERVER_UNAUTHENTICATED_STATUS;
        $arrAuthInfo["extra"]  = 'auth by token but data in database is empty';
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
