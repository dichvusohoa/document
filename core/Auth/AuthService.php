<?php
namespace Core\Auth;
use Core\Http\Response;
use Core\Http\Session;
use Core\Http\Cookie;
use Core\Auth\AuthToken;
use Core\Database\DbService;
use \RuntimeException;

/*prefix authSrvc*/
class AuthService{
    protected DbService $dbService;
    protected AuthTokenService    $tokenService;
    function __construct(DbService $dbService, AuthTokenService $tokenService){
        $this->dbService    = $dbService;
        $this->tokenService = $tokenService;
    }
    public function login(string $strUser, string $strPassword, bool $isAdminLogin = false, $strToken = null){
        //$isAdminLogin = ($strRequiredRole === ADMIN_ROLE_NAME);
        if($this->needTurnstile($isAdminLogin)){
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
        $this->resetFailCount();
        Session::set('auth', $arrResp['data']);
        if(!$isAdminLogin){//ghi vào cookie
            $authToken = new AuthToken();
            Cookie::set(['auth', 'token'], $authToken->cookieToken());
            $strUserId = $arrResp['data']['id'];
            $this->tokenService->tokenToDB($authToken, $strUserId);
        }
        return [Response::SERVER_AUTHENTICATED_STATUS, 'data' => 'login success' , 'extra' => null];
    }
    
    protected function getFailCount(){
        //tạm thời dùng session
        return Session::get('login_fail_count')??0;
    }
    protected function increaseFailCount(){
        //tạm thời dùng session
        Session::set('login_fail_count',$this->getFailCount() + 1);
    }
    protected function resetFailCount(){
        //tạm thời dùng session
        Session::set('login_fail_count',0);
    }
    protected function needTurnstile(bool $isAdminLogin){
        $failCount = $this->getFailCount();
        if($isAdminLogin || $failCount >=3){
            return true;
        }
        return false;
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
            $this->increaseFailCount();
            return [Response::SERVER_UNAUTHENTICATED_STATUS, 'data' => 'login fail' , 'extra' => null];
        }
   
        if (password_verify($strPassword, $arrResp['data']['password'])) {
            return [Response::SERVER_AUTHENTICATED_STATUS, 'data' => 'login success' , 'extra' => null];
        }
        else{
            $this->increaseFailCount();
            return [Response::SERVER_UNAUTHENTICATED_STATUS, 'data' => 'login fail bởi pass hoặc id' , 'extra' => null];
        }
    }
}