<?php
namespace Core\Controller;
use Psr\Container\ContainerInterface;
use InvalidArgumentException;
use UnexpectedValueException;
/*ControllerResolver hiện nay hơi ít chức năng, có thể bỏ và chuyển chức năng sang HttpKernel
nhưng ta cứ tách riêng ra ControllerResolver đề phòng sự phát triển về sau
*/
class ControllerResolver {
    protected ContainerInterface $container;
    public function __construct(ContainerInterface $container) {
        $this->container = $container;
    }
    
    public function create(string $strFQCN): BaseController {
        if (!is_subclass_of($strFQCN, BaseController::class)) {
            throw new InvalidArgumentException(
                "'$strFQCN' phải kế thừa BaseController."
            );
        }
        $controller = $this->container->get($strFQCN);
        if (!($controller instanceof $strFQCN)) {
            throw new UnexpectedValueException(
                "Container phải trả instance của '$strFQCN'."
            );
        }
        return $controller;
    }
}
