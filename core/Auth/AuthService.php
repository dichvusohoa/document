<?php
namespace Core\Auth;
use Core\Http\Response;
use Core\Http\Session;
use Core\Http\Cookie;
use Core\Auth\AuthToken;
use Core\Database\DbService;
use Core\Controller\Login\LoginAttemptService;
use \RuntimeException;
/*prefix authSrvc*/
class AuthService{
    protected DbService $dbService;
    protected AuthTokenService    $tokenService;
    protected LoginAttemptService $loginAttemptService;
    function __construct(DbService $dbService, AuthTokenService $tokenService, LoginAttemptService $loginAttemptService){
        $this->dbService    = $dbService;
        $this->tokenService = $tokenService;
        $this->loginAttemptService = $loginAttemptService;
    }
    public function login(string $strUser, string $strPassword, bool $isAdminLogin = false, $strToken = null){
        if($this->loginAttemptService->needTurnstile($isAdminLogin)){
            if(!self::verifyTurnstile($strToken)){
                return [
                    'status' => Response::SERVER_UNAUTHENTICATED_STATUS,
                    'data'   => 'Turnstile verification failed',
                    'extra' => null
                    ];
            }
        }

       
        $arrResp = $this->verifyCredentials($strUser, $strPassword, $isAdminLogin);
        if($arrResp['status'] !==Response::SERVER_AUTHENTICATED_STATUS){
            return $arrResp;
        } 
        $this->loginAttemptService->resetFailCount();
        session_regenerate_id(true);
        $authData = $arrResp['data'];
        unset($authData['password']);//lọc bỏ password không lưu vào auth
        Session::set('auth', $authData);
        if(!$isAdminLogin){//ghi vào cookie
            $authToken = new AuthToken();
            $strUserId = $arrResp['data']['id'];
            $exec =  $this->tokenService->tokenToDB($authToken, $strUserId);
            if (Response::isResponseError($exec)) {
                throw new RuntimeException('Could not store remember token');
            }
            Cookie::set(['auth', 'token'], $authToken->cookieToken());
            
        }
        return ['status'=> Response::SERVER_AUTHENTICATED_STATUS, 'data' => 'login success' , 'extra' => null];
    }
    
    protected static function verifyTurnstile(?string $token): bool{
        if ($token === null || $token === '') {
            return false;
        }
        $data = [
            'secret'   => TURNSTILE_SECRET_KEY,
            'response' => $token
        ];

        $ch = curl_init(
            'https://challenges.cloudflare.com/turnstile/v0/siteverify'
        );

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($data)
        ]);

        $result = curl_exec($ch);

        curl_close($ch);

        if ($result === false) {
            return false;
        }

        $response = json_decode($result, true);

        return !empty($response['success']);
    }
    protected function verifyCredentials(string $strUser, string $strPassword, $isAdminLogin = false) {
        $strRequiredRole = $isAdminLogin ? ADMIN_ROLE_NAME : null;
        $arrResp = $this->dbService->fetchOne("lib_spGetUserByNameAndRole",
            ["pName" => $strUser, "pRole" => $strRequiredRole]);
        if (Response::isResponseError($arrResp)) {
            throw new \RuntimeException('Database error while authenticating user');
        }
        
        if(Response::isResponseEmpty($arrResp)){
            $this->loginAttemptService->increaseFailCount();
            return ['status' => Response::SERVER_UNAUTHENTICATED_STATUS, 'data' => 'Tên đăng nhập hoặc mật khẩu không đúng' , 'extra' => null];
        }
   
        if (password_verify($strPassword, $arrResp['data']['password'])) {
            $arrResp['status'] = Response::SERVER_AUTHENTICATED_STATUS;
            return $arrResp;
            //return ['status' => Response::SERVER_AUTHENTICATED_STATUS, 'data' => 'login success' , 'extra' => null];
        }
        else{
            $this->loginAttemptService->increaseFailCount();
            return ['status' =>Response::SERVER_UNAUTHENTICATED_STATUS, 'data' => 'Tên đăng nhập hoặc mật khẩu không đúng' , 'extra' => null];
        }
    }
}