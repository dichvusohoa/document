<?php
namespace Core\Auth;
class AuthToken {
    const LEFT_TOKEN_LENGTH  = 30;//Độ dài left token
    const RIGHT_TOKEN_LENGTH = 64;//Độ dài right token
    protected $sLeftToken;
    protected $sRightToken;
    function __construct(){
        $this->sLeftToken = bin2hex(random_bytes(self::LEFT_TOKEN_LENGTH >> 1));//lengh 30
        $this->sRightToken = bin2hex(random_bytes(self::RIGHT_TOKEN_LENGTH >> 1));//leng 66
    }
    //tạo ra token ghi vào cookie
    public function cookieToken(){
        return  $this->sLeftToken . ':' . $this->sRightToken;
    }
    public function leftToken(){
        return $this->sLeftToken;
    }
    public function rightToken(){
        return $this->sRightToken;
    }
    public function hashedRightToken(){
        return hash('sha256', $this->sRightToken);
    }
}