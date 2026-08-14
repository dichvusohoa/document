<?php
namespace Core\Controller\Login;
use Core\Http\Session;
use Core\Http\RequestAuthContext;
use Core\Routing\AuthRegistry;
use Core\Routing\MCAOInfo;
use UnexpectedValueException;
class LoginAttemptService {
    protected string $key;
    protected RequestAuthContext $requestAuthContext;
    protected AuthRegistry $authRegistry;        
    
    public function __construct(
            RequestAuthContext $requestAuthContext,
            AuthRegistry $authRegistry){     
        $this->key = 'login_fail_count';
        $this->requestAuthContext = $requestAuthContext;
        $this->authRegistry = $authRegistry;
    }

    public function getFailCount(): int{
        return Session::get($this->key) ?? 0;
    }

    public function increaseFailCount(): void{
        Session::set($this->key, $this->getFailCount() + 1);
    }

    public function resetFailCount(): void{
        Session::set($this->key, 0);
    }
    protected function turnstilePolicy(){
        $arrMCAO = $this->requestAuthContext->mcao();
        $strControllerName = $arrMCAO[MCAOInfo::FIELD_CONTROLLER];
        $arrAuthPolicy = $this->authRegistry->getAuthPolicy($strControllerName);
        if($arrAuthPolicy === null){
            throw new UnexpectedValueException('...');
        }
        return $arrAuthPolicy[AuthRegistry::FIELD_TURNSTILE];
    }
    public function needTurnstile(): bool{
        $mixTurnstile = $this->turnstilePolicy();
        if($mixTurnstile === AuthRegistry::TURNSTILE_ALWAYS){
            return true;
        }
        if($mixTurnstile === AuthRegistry::TURNSTILE_NEVER){
            return false;
        }
        return $this->getFailCount() >= $mixTurnstile;
    }
    //put your code here
}
