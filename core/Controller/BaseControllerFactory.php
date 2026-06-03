<?php
namespace Core\Controller;
use Psr\Container\ContainerInterface;
use Core\Http\RequestAuthContext;
use Core\Controller\BaseController;

abstract class BaseControllerFactory {
    protected ContainerInterface $container;
    public function __construct(ContainerInterface $container) {
        $this->container = $container;
    }
    //tùy theo action mà tạo controller
    abstract public function create(
        RequestAuthContext $requestAuthContext
    ): BaseController; 
}
