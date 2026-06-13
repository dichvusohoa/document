<?php
namespace Core\Controller\Login;
use Core\Http\Session;
class LoginAttemptService {
    protected string $key;
    public function __construct(){     
        $this->key = 'login_fail_count';
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

    public function needTurnstile(bool $isAdminLogin): bool{
        return $isAdminLogin || $this->getFailCount() >= 3;
    }
    //put your code here
}
