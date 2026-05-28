<?php
namespace Core\Controllers;
use Psr\Container\ContainerInterface;
use Core\Models\RequestAuthContext;
use Core\Controllers\HtmlPageControllers\BaseHtmlPageController;
use Core\Models\HtmlPageSchemas\BaseHtmlPageSchema;
class ControllerFactory {
    protected ContainerInterface $container;
    public function __construct(ContainerInterface $container) {
        $this->container = $container;
    }

    public function create(
        RequestAuthContext $ctx,
        array $routeInfo
    ): BaseController {

        $controllerFQCN = $routeInfo['fqcn'];

        // HTML controller
        if (is_subclass_of($controllerFQCN, BaseHtmlPageController::class)) {

            $schemaFQCN = $routeInfo['html_schema'];
            /*Dòng này rất quan trọng, khác với các class hay factory khác thì HtmlPageSchema chỉ được
            set vào container sau khi đã có kết quả định tuyến chứ không đặt ngay ở index.php.
            Lý do là thiết kế hiện nay chỉ có một class Layout nhưng có nhiều HtmlPageSchema. Dùng HtmlPageSchema
            nào thì chỉ được xác định sau khi đã định tuyến*/
            $this->container->set(BaseHtmlPageSchema::class, function($c) use ($schemaFQCN) {
                return $c->get($schemaFQCN);
            });

           // return new $controllerFQCN($schema);
            
        }

        // API controller
        /*if (is_subclass_of($controllerFQCN, BaseController::class)) {
            return new $controllerFQCN($ctx);
        }&*/
        return $this->container->get($controllerFQCN);

        throw new \RuntimeException("Invalid controller: $controllerFQCN");
    }
}
