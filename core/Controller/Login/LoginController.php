<?php
namespace Core\Controller\Login;
use UnexpectedValueException;
use Core\Http\Response;
use Core\Http\Session;
use Core\Http\Cookie;
use Core\User\BaseUserInfo;
use Core\Auth\SessionInfo;
use Core\Auth\AuthToken;
use Core\Http\RequestAuthContext;
use Core\Controller\BaseController;
use Core\Auth\AuthService;
use Core\Auth\AuthTokenService;
use Core\Routing\AuthRegistry;
use Core\Routing\MCAOInfo;
use Core\Controller\Login\LoginAttemptService;

class LoginController extends BaseController{
    protected AuthService $authService;
    protected AuthTokenService $authTokenService;
    protected LoginAttemptService $loginAttemptService;
    protected array $arrAuthPolicy;
    /*---------------------------------------------------------------------------------------------------------------*/
    public function __construct(
            RequestAuthContext $requestAuthContext, 
            AuthRegistry $authRegistry,
            AuthService $authService,
            AuthTokenService $authTokenService,
            LoginAttemptService $loginAttemptService){
        parent::__construct($requestAuthContext);
        $arrMCAO = $requestAuthContext->mcao();
        $strControllerName = $arrMCAO[MCAOInfo::FIELD_CONTROLLER];
        $arrAuthPolicy = $authRegistry->getAuthPolicy($strControllerName);
        if($arrAuthPolicy === null){
            throw new UnexpectedValueException("Lỗi không tìm thấy entry {$strControllerName} trong dữ liệu AuthRegistry. Kiểm tra lại file config.login.php");
        }
        $this->arrAuthPolicy = $arrAuthPolicy;
        $this->authService = $authService;
        $this->authTokenService = $authTokenService;
        $this->loginAttemptService = $loginAttemptService;
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    protected function argumentsForFunction(string $strFunctName): array{
        if($strFunctName === 'login'){
            $arrUser = $this->requestAuthContext->request()->post('user');
            //$token là public key
            $strToken = $this->requestAuthContext->request()->post('cf-turnstile-response');
            return [$arrUser['login'], $arrUser['password'], $strToken];
        }
    }
    /*---------------------------------------------------------------------------------------------------------------*/
    public function login(string $strUser, string $strPassword, ?string $strToken = null){
        if($this->needTurnstile()){
            if(!self::verifyTurnstile($strToken)){
                return [
                    'status' => Response::SERVER_UNAUTHENTICATED_STATUS,
                    'data'   => 'Turnstile verification failed',
                    'extra' => null
                    ];
            }
        }
        $arrResp = $this->authService->verifyCredentials($strUser, $strPassword);
        if($arrResp['status'] !== Response::SERVER_AUTHENTICATED_STATUS){
            $this->loginAttemptService->increaseFailCount();
            return $arrResp;
        } 
        $this->loginAttemptService->resetFailCount();
        session_regenerate_id(true);
        if($this->arrAuthPolicy[AuthRegistry::FIELD_REMEMBER_COOKIE]){//ghi vào cookie
            $authToken = new AuthToken();
            $iUserId = $arrResp['data'][BaseUserInfo::FIELD_ID];
            $iRememberExpireSecond = $this->arrAuthPolicy[AuthRegistry::FIELD_REMEMBER_EXPIRE];
            //nếu có DB Error thì $this->authTokenService->tokenToDb sẽ throw ra luôn
            //$exec = $this->authService->tokenToDb($authToken, $iUserId, $iRememberExpireSecond);
            $this->authTokenService->tokenToDB($authToken, $iUserId, $iRememberExpireSecond);
            Cookie::set(['auth', 'token'], 
                    $authToken->cookieToken(), 
                    $iRememberExpireSecond);
        }
        $arrSessionInfo = SessionInfo::create($arrResp['data']);
        Session::set('auth', $arrSessionInfo);
        return ['status'=> Response::SERVER_AUTHENTICATED_STATUS, 'data' => $arrSessionInfo , 'extra' => null];
    }
    /*---------------------------------------------------------------------------------------------------------------*/
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
    /*---------------------------------------------------------------------------------------------------------------*/
    public function needTurnstile(): bool{
        $mixTurnstile = $this->arrAuthPolicy[AuthRegistry::FIELD_TURNSTILE];
        if($mixTurnstile === AuthRegistry::TURNSTILE_ALWAYS){
            return true;
        }
        if($mixTurnstile === AuthRegistry::TURNSTILE_NEVER){
            return false;
        }
        return $this->loginAttemptService->getFailCount() >= $mixTurnstile;
    }
}
