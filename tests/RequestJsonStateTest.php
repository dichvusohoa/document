<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Core\Http\Request;

function same($expected, $actual): void {
    if ($expected !== $actual) { throw new RuntimeException('Unexpected value: ' . var_export($actual, true)); }
}
function raises(string $class, Closure $run): void {
    try { $run(); } catch (Throwable $e) {
        if ($e instanceof $class) { return; }
        throw $e;
    }
    throw new RuntimeException('Expected ' . $class);
}

// Chỉ thay php://input trong process test; vẫn chạy initJson() thực tế.
class RequestInputStream {
    public $context;
    public static string $body = '';
    public static int $opens = 0;
    private int $offset = 0;
    public function stream_open($path, $mode, $options, &$openedPath): bool {
        if ($path !== 'php://input') { return false; }
        ++self::$opens;
        return true;
    }
    public function stream_read($count): string {
        $data = substr(self::$body, $this->offset, $count);
        $this->offset += strlen($data);
        return $data;
    }
    public function stream_eof(): bool { return $this->offset >= strlen(self::$body); }
    public function stream_stat(): array { return []; }
}

$saved = [$_GET, $_POST, $_SERVER, $_FILES];
stream_wrapper_unregister('php');
stream_wrapper_register('php', RequestInputStream::class);
try {
    $_GET = ['page' => '2', 'nil' => null];
    $_POST = ['name' => 'Alice'];
    $_FILES = [];
    foreach (['', 'multipart/form-data; boundary=x', 'application/x-www-form-urlencoded', 'text/application/json'] as $type) {
        $_SERVER = ['CONTENT_TYPE' => $type, 'HTTP_ACCEPT' => 'application/json'];
        $r = new Request();
        same(null, $r->json());
        same(null, $r->mcao());
        raises(LogicException::class, fn() => $r->json('missing', 'default'));
        raises(LogicException::class, fn() => $r->has('json', []));
        raises(LogicException::class, fn() => $r->has('json', 'missing'));
        raises(LogicException::class, fn() => $r->set('path_params', []));
        raises(LogicException::class, fn() => $r->pathParam());
        raises(LogicException::class, fn() => $r->has('path_params', []));
        raises(InvalidArgumentException::class, fn() => $r->set('unknown', []));
        same(null, $r->json());
        same('2', $r->query('page'));
        same(null, $r->query('nil', 10));
        same('Alice', $r->post('name'));
        same([], $r->file());
        $r->setMCAO(['module' => 'admin', 'controller' => 'product', 'action' => 'detail',
            'other_params' => [3 => '123', 4 => 'summary']]);
        same('123', $r->pathParam(3));
        same(true, $r->has('path_params', [3, 4]));
        $r->set('json', []);
        same([], $r->json());
        same(false, $r->has('json', 'missing'));
        $r->set('json', ['nil' => null, 'old' => 1]);
        same(null, $r->json('nil', 'fallback'));
        $r->set('json', ['new' => 2]);
        same(['new' => 2], $r->json());
        $r->set('post', ['remaining' => 'value']);
        same(['remaining' => 'value'], $r->post());
        same(['name' => 'Alice'], $_POST);
        $r->set('query', ['new' => '3']);
        same(['new' => '3'], $r->query());
        $r->set('get', []);
        same([], $r->query());
        same(['page' => '2', 'nil' => null], $_GET);
        $files = ['docs' => [4 => ['name' => 'a.pdf']]];
        $r->set('files', $files);
        same($files, $r->file());
        same([], $_FILES);
        $r->set('path_params', [3 => 123, 7 => 'kept']);
        same(['module' => 'admin', 'controller' => 'product', 'action' => 'detail',
            'other_params' => [3 => 123, 7 => 'kept']], $r->mcao());
        $r->set('path_params', []);
        same([], $r->pathParam());
    }
    same(0, RequestInputStream::$opens);
    $_SERVER = ['CONTENT_TYPE' => 'Application/JSON; charset=UTF-8'];
    foreach (['{}', '[]'] as $body) {
        RequestInputStream::$body = $body;
        $r = new Request();
        same([], $r->json());
        same(true, $r->has('json', []));
        same(false, $r->has('json', 'value'));
        same('default', $r->json('value', 'default'));
        $r->set('json', ['value' => null]);
        same(true, $r->has('json', 'value'));
        same(null, $r->json('value', 'default'));
        raises(InvalidArgumentException::class, fn() => $r->set('typo', ['bad' => 1]));
        same(['value' => null], $r->json());
        same(false, property_exists($r, 'rawInput'));
    }
    RequestInputStream::$body = '{"person":{"name":"Alice"},"nil":null}';
    $r = new Request();
    same(['name' => 'Alice'], $r->json('person'));
    same(true, $r->has('json', ['person', 'nil']));
    foreach (['', '{bad', '{"x":'] as $body) {
        RequestInputStream::$body = $body;
        raises(JsonException::class, fn() => new Request());
    }
    foreach (['null', 'true', '123', '"text"'] as $body) {
        RequestInputStream::$body = $body;
        raises(UnexpectedValueException::class, fn() => new Request());
    }
} finally {
    stream_wrapper_restore('php');
    [$_GET, $_POST, $_SERVER, $_FILES] = $saved;
}
echo "PASS: unavailable sources, empty/populated JSON, Content-Type, malformed/scalar JSON, null values and guarded writes.\n";
