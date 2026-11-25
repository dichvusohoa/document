<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Core\Controller\BaseController;
use Core\Controller\ControllerResolver;
use Core\Controller\Login\LoginRequestSchema;
use Core\Foundation\App;
use Core\Http\Request;
use Core\Http\RequestAuthContext;
use Core\Http\Response;
use Core\Normalization\BaseRequestSchema;
use Core\Normalization\RequestNormalizer;

function same($expected, $actual): void {
    if ($expected !== $actual) {
        throw new RuntimeException('Unexpected value: ' . var_export($actual, true));
    }
}
function raises(string $class, Closure $run): void {
    try { $run(); } catch (Throwable $e) {
        if ($e instanceof $class) { return; }
        throw $e;
    }
    throw new RuntimeException('Expected ' . $class);
}
class ControllerTestSchema extends BaseRequestSchema {
    protected function defineSchema(string $strFunctionName): ?array { return null; }
}
class ControllerTestNormalizer extends RequestNormalizer {
    public int $calls = 0;
    public ?array $errors = null;
    public bool $fail = false;
    public ?BaseRequestSchema $received = null;
    public function normalize(string $strFunctionName, BaseRequestSchema $requestSchema): ?array {
        ++$this->calls;
        $this->received = $requestSchema;
        if ($this->fail) { throw new LogicException('test failure'); }
        if ($this->errors === null) { $this->request->set('json', ['value' => 42]); }
        return $this->errors;
    }
}
class ControllerTestSubject extends BaseController {
    public int $argumentCalls = 0;
    public int $actionCalls = 0;
    protected function argumentsForFunction(string $strFunctName): array {
        ++$this->argumentCalls;
        return [$this->requestAuthContext->request()->json('value')];
    }
    public function execute($value) { ++$this->actionCalls; return $value; }
}
class ControllerTestInjected extends ControllerTestSubject {
    public function __construct(RequestAuthContext $context, ControllerTestNormalizer $normalizer, ControllerTestSchema $schema) {
        parent::__construct($context, $normalizer, $schema);
    }
}

// Test điều phối controller trên nguồn JSON đã được chuẩn bị.
$request = new class extends Request {
    protected function initJson(): void { $this->jsonData = []; }
};
$context = new RequestAuthContext($request, [
    'status' => Response::SERVER_UNAUTHENTICATED_STATUS, 'data' => null, 'extra' => null,
]);
$schema = new ControllerTestSchema();
$normalizer = new ControllerTestNormalizer($request);
raises(InvalidArgumentException::class, fn() => new ControllerTestSubject($context, $normalizer));
raises(InvalidArgumentException::class, fn() => new ControllerTestSubject($context, null, $schema));
$plain = new ControllerTestSubject($context);
same(null, $plain->doFunction('execute'));
same(1, $plain->actionCalls);
$controller = new ControllerTestSubject($context, $normalizer, $schema);
same(42, $controller->doFunction('execute'));
same($schema, $normalizer->received);
same(1, $controller->argumentCalls);
$normalizer->errors = ['post' => ['value' => ['err_number' => 2, 'value' => 'bad']]];
same(['status' => Response::SERVER_LOGIC_ERR_STATUS, 'data' => null, 'extra' => $normalizer->errors],
    $controller->doFunction('execute'));
same(1, $controller->argumentCalls);
same(1, $controller->actionCalls);
same(42, $controller->doFunction('execute', false));
same(2, $normalizer->calls);
$normalizer->fail = true;
raises(LogicException::class, fn() => $controller->doFunction('execute'));
same(2, $controller->actionCalls);
raises(BadMethodCallException::class, fn() => $controller->doFunction('missing'));
same(3, $normalizer->calls);

$app = new App();
$app->set(RequestAuthContext::class, $context);
$app->set(Request::class, $request);
$resolver = new ControllerResolver($app);
$resolved = $resolver->create(ControllerTestInjected::class);
same($context, $resolved->getRequestAuthContext());
same(42, $resolved->doFunction('execute'));
raises(InvalidArgumentException::class, fn() => $resolver->create(stdClass::class));
$badApp = new App();
$badApp->set(ControllerTestSubject::class, new stdClass());
raises(UnexpectedValueException::class, fn() => (new ControllerResolver($badApp))->create(ControllerTestSubject::class));
same(true, $app->get(LoginRequestSchema::class) instanceof LoginRequestSchema);
raises(LogicException::class, fn() => (new LoginRequestSchema())->createSchema('login'));
same(null, (new RequestNormalizer($request))->normalize('login', $schema));
echo "PASS: dependency pairs, dispatch order, errors, bypass, exceptions, DI, resolver bindings and unfinished contracts.\n";
