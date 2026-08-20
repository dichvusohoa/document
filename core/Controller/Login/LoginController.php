<?php
namespace Core\Controller\Login;
use UnexpectedValueException;
use RuntimeException;
use Core\Http\Response;
use Core\Http\Session;
use Core\Http\Cookie;
use Core\User\UserInfo;
use Core\Auth\AuthToken;
use Core\Http\RequestAuthContext;
use Core\Controller\BaseController;
use Core\Auth\AuthService;
use Core\Routing\AuthRegistry;
use Core\Routing\MCAOInfo;
use Core\Controller\Login\LoginAttemptService;

class LoginController extends BaseController{
    protected AuthService $authService;
    protected array $authPolicy;
    /*---------------------------------------------------------------------------------------------------------------*/
    public function __construct(
            RequestAuthContext $requestAuthContext, 
            AuthRegistry $authRegistry,
            AuthService $authService,
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
            $iUserId = $arrResp['data'][UserInfo::FIELD_ID];
            $iRememberExpireSecond = $this->arrAuthPolicy[AuthRegistry::FIELD_REMEMBER_EXPIRE];
            $exec = $this->authService->tokenToDb($authToken, $iUserId, $iRememberExpireSecond);
            if (Response::isResponseError($exec)) {
                throw new RuntimeException('Could not store remember token');
            }
            Cookie::set(['auth', 'token'], 
                    $authToken->cookieToken(), 
                    $iRememberExpireSecond);
        }
        $authData = $arrResp['data'];
        unset($authData[UserInfo::FIELD_PASSWORD]);//lọc bỏ password không lưu vào auth
        $authData[UserInfo::FIELD_LAST_ACTIVITY] = time();
        Session::set('auth', $authData);
        return ['status'=> Response::SERVER_AUTHENTICATED_STATUS, 'data' => $authData , 'extra' => null];
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
    protected function needTurnstile(): bool{
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
