<?php
namespace Core\Auth;
use Core\Http\Session;
use Core\User\UserInfo;
use Core\User\UserService;
use Core\Http\Response;
use Core\Http\Cookie;

class AuthContext {
    protected UserService $userService;
 
    public function __construct(UserService $userService){
        $this->userService = $userService;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public function getAuth(): array{
        $arrAuth = $this->getAuthBySession();//SERVER_UNAUTHENTICATED_STATUS hoặc  SERVER_AUTHENTICATED_STATUS
        if($arrAuth['data'] === null){
            //chưa có thông tin lưu session => xác thực bằng session thất bại phải dùng cookie
            $arrAuth = $this->getAuthByCookie();
        }
         /*
         * Sau getAuthBySession() và getAuthByCookie(),
         * status chỉ còn:
         *
         * SERVER_UNAUTHENTICATED_STATUS:
         * - không có remember token
         * - token malformed
         * - token không tồn tại / hết hạn
         * - validator không khớp
         *
         * SERVER_AUTHENTICATED_STATUS:
         * - xác thực thành công bằng session hoặc remember cookie
         *
         * Lỗi DB hoặc dữ liệu DB sai contract:
         * - throw Exception, không chuyển thành unauthenticated.
         */
        if($arrAuth['data'] === null){
            //bổ sung các thông tin về guest user cho $auth["data"]
           $arrAuth['data'] = SessionInfo::create(UserInfo::createGuest());
        }
        //tới đây là trạng thái Response::SERVER_AUTHENTICATED_STATUS hoặc SERVER_UNAUTHENTICATED_STATUS
        $arrAuth['data'][SessionInfo::FIELD_LAST_ACTIVITY] = time(); 
        Session::set('auth', $arrAuth['data']);//cập nhật lại session
        return $arrAuth;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    /* return resp
     * resp["status"] === 
     * Response::SERVER_AUTHENTICATED_STATUS || 
     * Response::SERVER_UNAUTHENTICATED_STATUS
     */
    protected function getAuthBySession(): array {
        $sessionInfo = Session::get('auth');
        if ($sessionInfo === null) {
            //mục đích Session::reset(); là: Không có dữ liệu auth trong session hiện tại => sau này chương trình sẽ tạo data mới và ghi vào session
            //thì session đó phải có session_id mới không trùng với session_id hiện tại
            Session::reset();
            return [
                'status' => Response::SERVER_UNAUTHENTICATED_STATUS,
                'data'   => null,
                'extra'  => 'session missing'
            ];
        }
        if (!SessionInfo::isValid($sessionInfo)) {
            //mục đích Session::reset(); là: hiện tại session dữ liệu không hợp lệ 
            //=> 1) cần xóa dữ liệu không hợp lệ đó đi. 2)sau này chương trình sẽ tạo data mới và ghi vào session
            //thì session đó phải có session_id mới không trùng với session_id hiện tại
            Session::reset();
            return [
                'status' => Response::SERVER_UNAUTHENTICATED_STATUS,
                'data'   => null,
                'extra'  => 'invalid session data'
            ];
        }
        //$sessionInfo là chuẩn format
        $iNow = time();  
        $boolIdleExpired =
            $iNow - $sessionInfo[SessionInfo::FIELD_LAST_ACTIVITY]
            >= SESSION_IDLE_TIMEOUT;
        $boolAbsoluteExpired =
            $iNow - $sessionInfo[SessionInfo::FIELD_CREATED_AT]
            >= SESSION_ABSOLUTE_TIMEOUT;
        if ($boolIdleExpired || $boolAbsoluteExpired) {
            //Session::destroy();
            //mục đích Session::reset(); là: hiện tại session dữ liệu đã hết hạn
            //=> 1) cần xóa dữ liệu hết hạn đó đi. 2)sau này chương trình sẽ tạo data mới và ghi vào session
            //thì session đó phải có session_id mới không trùng với session_id hiện tại
            Session::reset();
            return ['status' => Response::SERVER_UNAUTHENTICATED_STATUS, 'data' => null, 'extra' => 'session expired' ]; //chưa authenticate by session
        }
        $status = $sessionInfo[UserInfo::FIELD_ID] === null ? Response::SERVER_UNAUTHENTICATED_STATUS : Response::SERVER_AUTHENTICATED_STATUS;
        return ['status' => $status, 'data' => $sessionInfo, 'extra' => null ]; 
    }
    /*---------------------------------------------------------------------------------------------------------------*/    
    /* return resp
     * resp["status"] === 
     *  Response::SERVER_AUTHENTICATED_STATUS || 
     *  Response::SERVER_UNAUTHENTICATED_STATUS || 
     */
    protected function getAuthByCookie(): array
    {
        $strToken = Cookie::get(['auth', 'token']);

        if ($strToken === null) {
            return [
                'status' => Response::SERVER_UNAUTHENTICATED_STATUS,
                'data'   => null,
                'extra'  => 'remember cookie is missing'
            ];
        }

        if (!is_string($strToken)) {
            Cookie::remove('auth');

            return [
                'status' => Response::SERVER_UNAUTHENTICATED_STATUS,
                'data'   => null,
                'extra'  => 'remember cookie is malformed'
            ];
        }

        $arrTokenPart = explode(':', $strToken);

        if (count($arrTokenPart) !== 2) {
            Cookie::remove('auth');

            return [
                'status' => Response::SERVER_UNAUTHENTICATED_STATUS,
                'data'   => null,
                'extra'  => 'remember cookie is malformed'
            ];
        }

        [$strLeftToken, $strRightToken] = $arrTokenPart;

        if (
            strlen($strLeftToken) !== AuthToken::LEFT_TOKEN_LENGTH
            || strlen($strRightToken) !== AuthToken::RIGHT_TOKEN_LENGTH
            || !ctype_xdigit($strLeftToken)
            || !ctype_xdigit($strRightToken)
        ) {
            Cookie::remove('auth');

            return [
                'status' => Response::SERVER_UNAUTHENTICATED_STATUS,
                'data'   => null,
                'extra'  => 'invalid token structure'
            ];
        }

        $arrAuth = $this->userService->getUserByToken(
            $strLeftToken,
            $strRightToken
        );

        if (Response::isResponseEmpty($arrAuth)) {
            Cookie::remove('auth');

            $arrAuth['status'] =
                Response::SERVER_UNAUTHENTICATED_STATUS;

            $arrAuth['extra'] =
                'remember token is invalid or expired';

            return $arrAuth;
        }

        $arrAuth['data'] =
            SessionInfo::create($arrAuth['data']);

        //Session::regenerateId(true);

        $arrAuth['status'] =
            Response::SERVER_AUTHENTICATED_STATUS;

        $arrAuth['extra'] =
            'auth by cookie';

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
