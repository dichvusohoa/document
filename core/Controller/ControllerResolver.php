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
        if (is_subclass_of($strFQCN, BaseController::class)){
            return $this->container->get($strFQCN);
        }
        //BaseControllerFactory
        else if (is_subclass_of($strFQCN, BaseControllerFactory::class)){
            return $this->container->get($strFQCN)->create($requestAuthContext);
        } 
    }
}
