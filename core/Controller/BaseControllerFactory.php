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
    /*Thông thường thì tùy theo thông tin về các loại action trong $requestAuthContext thì
    để quyết định chọn ra controller nào phù hợp */
    abstract public function create(RequestAuthContext $requestAuthContext): BaseController; 
}
