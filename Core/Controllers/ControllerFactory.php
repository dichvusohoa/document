<?php
namespace Core\Controllers;
use Psr\Container\ContainerInterface;
use Core\Models\HtmlPageSchemas\SchemaFactory;
use Core\Models\RequestAuthContext;
use Core\Controllers\HtmlPageControllers\BaseHtmlPageController;

class ControllerFactory {
    protected ContainerInterface $container;
    protected SchemaFactory $schemaFactory;

    public function __construct(ContainerInterface $container, SchemaFactory $schemaFactory) {
        $this->container = $container;
        $this->schemaFactory = $schemaFactory;
    }

    public function create(
        RequestAuthContext $ctx,
        array $routeInfo
    ): BaseController {

        $controllerFQCN = $routeInfo['fqcn'];

        // HTML controller
        if (is_subclass_of($controllerFQCN, BaseHtmlPageController::class)) {

            $schemaFQCN = $routeInfo['schema'];

            $schema = $this->schemaFactory->create($schemaFQCN);

            return new $controllerFQCN($schema);
            
        }

        // API controller
        if (is_subclass_of($controllerFQCN, BaseController::class)) {
            return new $controllerFQCN($ctx);
        }

        throw new \RuntimeException("Invalid controller: $controllerFQCN");
    }
}
