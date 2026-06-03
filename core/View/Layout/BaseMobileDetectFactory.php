<?php
namespace Core\View\Layout;
use Detection\MobileDetect;
use Core\Http\RequestAuthContext;
use Psr\Container\ContainerInterface;

abstract class BaseMobileDetectFactory {
    protected  RequestAuthContext $requestAuthContext;
    protected  ContainerInterface $container;
    public function __construct(RequestAuthContext $requestAuthContext, ContainerInterface $container){
        /*if(!$requestAuthContext->isSetRoutePath()){
            throw new InvalidArgumentException('requestAuthContext chưa có route path');
        }*/
        $this->requestAuthContext = $requestAuthContext;
        $this->container = $container;
    }
    /*requiresDeviceDetection quyết định trong ngữ cảnh nào thì phải tính ra loại thiết bị là gì*/
    abstract protected function requiresDeviceDetection(): bool;
    public function create(): ?MobileDetect{
        if($this->requiresDeviceDetection()){
            return $this->container->get(MobileDetect::class);
        }
        else{
            return null;
        }
        
    }
    
    /*---------------------------------------------------------------------------------------------------------------*/
    
}
