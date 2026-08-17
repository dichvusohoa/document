<?php
namespace Core\Auth;
use RuntimeException;
use LogicException;
use JsonException;
use UnexpectedValueException;
use Core\Http\Response;
use Core\Http\Session;
use Core\Http\Cookie;
use Core\Http\RequestAuthContext;
use Core\Routing\AuthRegistry;
use Core\Routing\MCAOInfo;
use Core\Auth\AuthToken;
use Core\User\UserInfo;
use Core\Database\DbService;
use Core\Controller\Login\LoginAttemptService;


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
    protected function rememberCookiePolicy(): bool{
        $arrMCAO = $this->requestAuthContext->mcao();
        $strControllerName = $arrMCAO[MCAOInfo::FIELD_CONTROLLER];
        $arrAuthPolicy = $this->authRegistry->getAuthPolicy($strControllerName);
        if($arrAuthPolicy === null){
            throw new UnexpectedValueException("Lỗi không tìm thấy entry {$strControllerName} trong dữ liệu AuthRegistry. Kiểm tra lại file config.login.php");
        }
        return $arrAuthPolicy[AuthRegistry::FIELD_REMEMBER_COOKIE];
    }
    public function login(
            string $strUser, 
            string $strPassword, 
            ?string $strToken = null): array{
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
            $strUserId = $arrResp['data'][UserInfo::FIELD_ID];
            $exec =  $this->tokenService->tokenToDB($authToken, $strUserId);
            if (Response::isResponseError($exec)) {
                throw new RuntimeException('Could not store remember token');
            }
            Cookie::set(['auth', 'token'], $authToken->cookieToken());
        }
        $authData = $arrResp['data'];
        unset($authData[UserInfo::FIELD_PASSWORD]);//lọc bỏ password không lưu vào auth
        $authData[UserInfo::FIELD_LAST_ACTIVITY] = time();
        Session::set('auth', $authData);
        //return ['status'=> Response::SERVER_AUTHENTICATED_STATUS, 'data' => null , 'extra' => null];
        return ['status'=> Response::SERVER_AUTHENTICATED_STATUS, 'data' => $authData , 'extra' => null];
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
    protected function verifyCredentials(string $strUser, string $strPassword): array {
        $arrContextAcceptedRole = $this->requestAuthContext->contextAcceptedRoles();
        $strContextAcceptedRolesJson = json_encode(
            $arrContextAcceptedRole,
            JSON_THROW_ON_ERROR
        );
        $arrResp = $this->dbService->fetchOne("lib_spGetUserByNameAndRoles",
            ["pName" => $strUser, "pRoles" => $strContextAcceptedRolesJson]);
        //1. chặn trước các lỗi DB - đây là bước đầu tiên
        if (Response::isResponseError($arrResp)) {
            throw new RuntimeException('Database error while authenticating user');
        }
        //2. lỗi dữ liệu empty - $arrResp['data'] có thể là null
        if(Response::isResponseEmpty($arrResp)){
            $this->loginAttemptService->increaseFailCount();
            return ['status' => Response::SERVER_UNAUTHENTICATED_STATUS, 'data' => 'Tên đăng nhập hoặc mật khẩu không đúng' , 'extra' => null];
        }
        //3. chuẩn hóa và kiểm tra format của $arrResp, đề phòng store procedure trả về sai format
        /*
         * roles bắt buộc phải tồn tại và khác null.
         */
        $strFieldRoles = UserInfo::FIELD_ROLES;

        if (!isset($arrResp['data'][$strFieldRoles])) {
            throw new LogicException(
                "lib_spGetUserByNameAndRoles trả về thiếu field {$strFieldRoles} "
                . 'của UserInfo hoặc giá trị bằng null'
            );
        }

        /*
         * registered_modules bắt buộc phải tồn tại,
         * nhưng giá trị null là hợp lệ trong bài toán no-module.
         */
        $strFieldRegisteredModules = UserInfo::FIELD_REGISTERED_MODULES;

        if (!array_key_exists(
            $strFieldRegisteredModules,
            $arrResp['data']
        )) {
            throw new LogicException(
                "lib_spGetUserByNameAndRoles trả về thiếu field "
                . "{$strFieldRegisteredModules} của UserInfo"
            );
        }
        try{
            $arrResp['data'][$strFieldRoles] = json_decode(
                $arrResp['data'][$strFieldRoles],
                true,
                512,
                JSON_THROW_ON_ERROR
            );
            if ($arrResp['data'][$strFieldRegisteredModules] !== null) {
                $arrResp['data'][$strFieldRegisteredModules] = json_decode(
                    $arrResp['data'][$strFieldRegisteredModules],
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );
            }
        }
        catch (JsonException $e) {
            throw new LogicException(
                'lib_spGetUserByNameAndRoles trả về dữ liệu JSON không hợp lệ.',
                0,
                $e
            );
        }
        if(!UserInfo::isValidWithPassword($arrResp['data'])){
            throw new LogicException('lib_spGetUserByNameAndRoles trả về dữ liệu không chuẩn format');
        }
        //4. verify password
        if (!password_verify($strPassword, $arrResp['data'][UserInfo::FIELD_PASSWORD])) {
            $this->loginAttemptService->increaseFailCount();
            return ['status' =>Response::SERVER_UNAUTHENTICATED_STATUS, 'data' => 'Tên đăng nhập hoặc mật khẩu không đúng' , 'extra' => null];
        }
        $arrResp['status'] = Response::SERVER_AUTHENTICATED_STATUS;
        return $arrResp;
    }
}