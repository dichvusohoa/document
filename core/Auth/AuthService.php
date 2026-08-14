<?php
namespace Core\Auth;
use Core\Http\Response;
use Core\Http\Session;
use Core\Http\Cookie;
use Core\Http\RequestAuthContext;
use Core\Routing\AuthRegistry;
use Core\Routing\MCAOInfo;
use Core\Auth\AuthToken;
use Core\Database\DbService;
use Core\Controller\Login\LoginAttemptService;
use RuntimeException;

/*prefix authSrvc*/
class AuthService{
    protected RequestAuthContext $requestAuthContext;
    protected DbService $dbService;
    protected AuthTokenService    $tokenService;
    protected LoginAttemptService $loginAttemptService;
    protected AuthRegistry $authRegistry;
    function __construct(
        RequestAuthContext $requestAuthContext,
        DbService $dbService, 
        AuthTokenService $tokenService, 
        LoginAttemptService $loginAttemptService,
        AuthRegistry $authRegistry
    ){
        $this->requestAuthContext = $requestAuthContext;
        $this->dbService    = $dbService;
        $this->tokenService = $tokenService;
        $this->loginAttemptService = $loginAttemptService;
        $this->authRegistry = $authRegistry;
    }
    protected function rememberCookiePolicy(){
        $arrMCAO = $this->requestAuthContext->mcao();
        $strControllerName = $arrMCAO[MCAOInfo::FIELD_CONTROLLER];
        $arrAuthPolicy = $this->authRegistry->getAuthPolicy($strControllerName);
        if($arrAuthPolicy === null){
            throw new UnexpectedValueException('...');
        }
        return $arrAuthPolicy[AuthRegistry::FIELD_REMEMBER_COOKIE];
    }
    public function login(
            string $strUser, 
            string $strPassword, 
            string $strToken = null){
        if($this->loginAttemptService->needTurnstile()){
            if(!self::verifyTurnstile($strToken)){
                return [
                    'status' => Response::SERVER_UNAUTHENTICATED_STATUS,
                    'data'   => 'Turnstile verification failed',
                    'extra' => null
                    ];
            }
        }
       
        $arrResp = $this->verifyCredentials($strUser, $strPassword);
        if($arrResp['status'] !==Response::SERVER_AUTHENTICATED_STATUS){
            return $arrResp;
        } 
        $this->loginAttemptService->resetFailCount();
        session_regenerate_id(true);
       
        if($this->rememberCookiePolicy()){//ghi vào cookie
            $authToken = new AuthToken();
            $strUserId = $arrResp['data']['id'];
            $exec =  $this->tokenService->tokenToDB($authToken, $strUserId);
            if (Response::isResponseError($exec)) {
                throw new RuntimeException('Could not store remember token');
            }
            Cookie::set(['auth', 'token'], $authToken->cookieToken());
        }
        $authData = $arrResp['data'];
        unset($authData['password']);//lọc bỏ password không lưu vào auth
        Session::set('auth', $authData);
        return ['status'=> Response::SERVER_AUTHENTICATED_STATUS, 'data' => null , 'extra' => null];
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
    protected function verifyCredentials(string $strUser, string $strPassword) {
        //$strRequiredRole = $isAdminLogin ? ADMIN_ROLE_NAME : null;
        $arrContextAcceptedRole = $this->requestAuthContext->contextAcceptedRoles();
        $strContextAcceptedRoleJson = json_encode(
            $arrContextAcceptedRole,
            JSON_THROW_ON_ERROR
        );
        $arrResp = $this->dbService->fetchOne("lib_spGetUserByNameAndRoles",
            ["pName" => $strUser, "pRole" => $strContextAcceptedRoleJson]);
        if (Response::isResponseError($arrResp)) {
            throw new RuntimeException('Database error while authenticating user');
        }
        
        if(Response::isResponseEmpty($arrResp)){
            $this->loginAttemptService->increaseFailCount();
            return ['status' => Response::SERVER_UNAUTHENTICATED_STATUS, 'data' => 'Tên đăng nhập hoặc mật khẩu không đúng' , 'extra' => null];
        }
   
        if (password_verify($strPassword, $arrResp['data']['password'])) {
            $arrResp['status'] = Response::SERVER_AUTHENTICATED_STATUS;
            return $arrResp;
        }
        else{
            $this->loginAttemptService->increaseFailCount();
            return ['status' =>Response::SERVER_UNAUTHENTICATED_STATUS, 'data' => 'Tên đăng nhập hoặc mật khẩu không đúng' , 'extra' => null];
        }
    }
}