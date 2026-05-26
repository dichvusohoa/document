<?php
namespace Core\Middlewares;
use Psr\Container\ContainerInterface;
use Core\Models\Utility\ValidUtility;
class MiddlewareFactory {
    protected ContainerInterface $container;
    public function __construct(ContainerInterface $container) {
        $this->container = $container;
    }
    public function create(string $strFQCN): callable {
        $middleware = $this->container->get($strFQCN);
        return [$middleware, 'handle'];
    }

    public function createList(array $arrFQCN): array {
        if(!ValidUtility::isStringList($arrFQCN)){
            throw new UnexpectedValueException('Phải là một mảng string'); 
        }
        if(count($arrFQCN) === 0){
            return [];
        }
        return array_map(fn($strFQCN) => $this->create($strFQCN), $arrFQCN);
    }
}
