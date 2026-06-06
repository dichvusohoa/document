<?php
namespace Core\Controller;
use Psr\Container\ContainerInterface;
use Core\Http\RequestAuthContext;
use Core\Controller\BaseController;

class ControllerResolver {
    protected ContainerInterface $container;
    public function __construct(ContainerInterface $container) {
        $this->container = $container;
    }
    
    public function create(string $strFQCN, RequestAuthContext $requestAuthContext): BaseController{
        /*module/controller trỏ trực tiếp controller*/
        if (is_subclass_of($strFQCN, BaseController::class)){
            return $this->container->get($strFQCN);
        }
        /*module/controller trỏ BaseControllerFactory, rồi thông qua ControllerFactory
        mà tạo ra controller cuối. Trường hợp này áp dụng cho các  */
        else if (is_subclass_of($strFQCN, BaseControllerFactory::class)){
            return $this->container->get($strFQCN)->create($requestAuthContext);
        } 
    }
}
