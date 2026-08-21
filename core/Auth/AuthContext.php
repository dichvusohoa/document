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
        $arrAuth = $this->getAuthInfoBySession();//SERVER_UNAUTHENTICATED_STATUS hoặc  SERVER_AUTHENTICATED_STATUS
        if($arrAuth['data'] === null){
            //chưa có thông tin lưu session => xác thực bằng session thất bại phải dùng cookie
            $arrAuth = $this->getAuthInfoByCookie();
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
        
        if($arrAuth['data'] === null){
            //bổ sung các thông tin về guest user cho $auth["data"]
            $arrAuth['data'] = UserInfo::createGuest();
            $arrAuth['data'][UserInfo::FIELD_CREATED_AT] = time(); 
        }
        //tới đây là trạng thái Response::SERVER_AUTHENTICATED_STATUS hoặc SERVER_UNAUTHENTICATED_STATUS
        $arrAuth['data'][UserInfo::FIELD_LAST_ACTIVITY] = time(); 
        Session::set('auth', $arrAuth['data']);//cập nhật lại session
        return $arrAuth;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    /* return resp
     * resp["status"] === 
     * Response::SERVER_AUTHENTICATED_STATUS || 
     * Response::SERVER_UNAUTHENTICATED_STATUS
     */
    protected function getAuthInfoBySession(): array {
        $userInfo = Session::get('auth');
        //$userInfo là chuẩn format
        if( UserInfo::isValidSessionData($userInfo)){  
            $boolIdleExpired =
                time() - $userInfo[UserInfo::FIELD_LAST_ACTIVITY]
                >= SESSION_IDLE_TIMEOUT;
            $boolAbsoluteExpired =
                time() - $userInfo[UserInfo::FIELD_CREATED_AT]
                >= SESSION_ABSOLUTE_TIMEOUT;
            if ($boolIdleExpired || $boolAbsoluteExpired) {
                Session::destroy();
            }
            else{
                //field created_at, last_activity lưu và cập nhật ở sesssion, không cần dùng trong logic xử lý sau này
                unset($userInfo[UserInfo::FIELD_LAST_ACTIVITY]);
                unset($userInfo[UserInfo::FIELD_CREATED_AT]);
                $status = $userInfo[UserInfo::FIELD_ID] === null ? Response::SERVER_UNAUTHENTICATED_STATUS : Response::SERVER_AUTHENTICATED_STATUS;
                return ["status" => "$status", "data" => $userInfo, "extra" => "" ]; 
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
        if($strToken === null || strpos($strToken,':') === false){
            return ["status" => Response::SERVER_UNAUTHENTICATED_STATUS, "data" => null, "extra" => "cookie token is missing or malformed"];
        }
        list($strLeftToken, $strRightToken) = explode(':', $strToken);
        if (strlen($strLeftToken) !== AuthToken::LEFT_TOKEN_LENGTH || strlen($strRightToken) !== AuthToken::RIGHT_TOKEN_LENGTH) {
            return ["status" => Response::SERVER_UNAUTHENTICATED_STATUS, "data" => null, "extra" => "invalid token structure: incorrect length"];
        }
        //$arrAuth['status'] chắc chắn bằng Response::SERVER_OK_STATUS
        $arrAuth = $this->userService->getUserByToken($strLeftToken);
        
        if(Response::isResponseEmpty($arrAuth)){ //database trả về null
            $arrAuth['status'] = Response::SERVER_UNAUTHENTICATED_STATUS;
            $arrAuth['extra']  = 'auth by token but data in database is empty';
            return $arrAuth;
        }
        $arrAuth['status'] = Response::SERVER_AUTHENTICATED_STATUS;
        $arrAuth['extra']  = 'auth by cookie';
        return $arrAuth;
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
