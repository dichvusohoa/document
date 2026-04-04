<?php
namespace Core\Models;
class MiddlewareChain {
    protected array $arrService = [];//cấu trúc một array các callable
    protected int $iIndex = 0;
    protected $handler; 
    public function __construct(array $arrService, ?callable $handler = null) {
        $this->arrService = $arrService; 
        $this->handler = $handler;
        $this->iIndex = 0;
    }
    
    public function handleChain(RequestAuthContext $requestAuthContext) {
        if (!isset($this->arrService[$this->iIndex])){ //đã xử lý hết các middleware
            if (is_callable($this->handler)) {
                return call_user_func($this->handler);
            }
            else{
                return;
            }
        }    
        $fn = $this->arrService[$this->iIndex]; //callable tại vị trí  hiện tại
        $this->iIndex++;
        return $fn( //run callable đó
            $requestAuthContext, 
            function($requestAuthContext) {
                return $this->handleChain($requestAuthContext);//đây là  handle của MiddlewareChain
            }
        );
    }
}
