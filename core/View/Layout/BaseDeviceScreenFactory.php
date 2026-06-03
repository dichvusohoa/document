<?php
namespace Core\View\Layout;
//use Psr\Container\ContainerInterface;

use Core\Http\RequestAuthContext;
use Core\Http\Session;

abstract class BaseDeviceScreenFactory {
    protected  RequestAuthContext $requestAuthContext;
    //protected  ContainerInterface $container; chưa biết có dùng không
    public function __construct(RequestAuthContext $requestAuthContext){
        if(!$requestAuthContext->isSetRoutePath()){
            throw new InvalidArgumentException('requestAuthContext chưa có route path');
        }
        $this->requestAuthContext = $requestAuthContext;
        //$this->container = $container;
    }
    /*requiresScreenDetection quyết định trong ngữ cảnh nào thì phải tính ra thông tin chi tiết về screen*/
    abstract public function requiresScreenDetection(): bool;
    public function create(): ?array{
        if($this->requiresScreenDetection()){
            return Session::get('device_screen');
        }
        else{
            return null;
        }
        
    }
    
    /*---------------------------------------------------------------------------------------------------------------*/
    
}
