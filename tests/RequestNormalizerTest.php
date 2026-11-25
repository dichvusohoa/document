<?php
declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';

use Core\Http\Request;
use Core\Normalization\BaseRequestSchema;
use Core\Normalization\RequestNormalizer;

function same(mixed $expected, mixed $actual): void {
    if ($expected !== $actual) { throw new RuntimeException(var_export([$expected, $actual], true)); }
}
function raises(string $type, Closure $fn): void {
    try { $fn(); } catch (Throwable $e) { if ($e instanceof $type) { return; } throw $e; }
    throw new RuntimeException('Expected ' . $type);
}
function fixture(array $query = [], array $post = []): Request {
    return new class($query, $post) extends Request {
        public function __construct(array $query, array $post) {
            $this->getData = $query;
            $this->postData = $post;
            $this->filesData = [];
            $this->serverData = ['CONTENT_TYPE' => 'multipart/form-data'];
        }
    };
}
function schema(?array $data): BaseRequestSchema {
    return new class($data) extends BaseRequestSchema {
        public function __construct(private ?array $data) {}
        protected function defineSchema(string $name): ?array {
            same('save', $name);
            return $this->data;
        }
    };
}
function run(Request $request, ?array $data): ?array {
    return (new RequestNormalizer($request))->normalize('save', schema($data));
}

$row = ['fields' => ['n' => ['type' => 'int']]];
$table = ['fields' => ['id' => ['type' => 'int']], 'keys' => ['id' => ['on_add' => 'required']]];
$bodySchema = ['post' => $row, 'post_json_fields' => ['items'], 'json' => ['items' => $table]];
$request = fixture(['extra' => 'x'], ['items' => '{']);
same(null, run($request, null));
same(null, run($request, ['path_params' => null, 'query' => null, 'post' => null]));
same(['items' => '{'], $request->post());
same(null, $request->json());

$pathSchema = ['fields' => [3 => ['type' => 'int']]];
$request = fixture(['n' => '2'], ['n' => '3', 'items' => '{']);
$mcao = ['module' => 'admin', 'controller' => 'product', 'action' => 'save', 'other_params' => [3 => 'bad']];
$request->setMCAO($mcao);
$errors = run($request, ['path_params' => $pathSchema, 'query' => $row] + $bodySchema);
same(['path_params'], array_keys($errors));
same($mcao, $request->mcao());
same(['n' => '2'], $request->query());
same(null, $request->json());

$mcao['other_params'] = [3 => '1'];
$request->setMCAO($mcao);
$request->set('query', ['n' => 'bad']);
$errors = run($request, ['path_params' => $pathSchema, 'query' => $row] + $bodySchema);
same(['query'], array_keys($errors));
$mcao['other_params'] = [3 => 1];
same($mcao, $request->mcao());
same(['n' => 'bad'], $request->query());
same(null, $request->json());

$request->set('query', ['n' => '2']);
$errors = run($request, ['query' => $row] + $bodySchema);
same(11001, $errors['post']['items']['err_number']);
same(['n' => 2], $request->query());
same(['n' => '3', 'items' => '{'], $request->post());

// Preparation succeeds; failed POST remains raw, JSON preparation is not rolled back.
$request = fixture([], ['n' => 'bad', 'items' => '{"add":[{"id":"4"}]}']);
$errors = run($request, $bodySchema);
same(['post'], array_keys($errors));
same(['n' => 'bad'], $request->post());
same(['items' => ['add' => [['id' => 4]]]], $request->json());
$request->set('post', ['n' => '3']);
same(null, run($request, $bodySchema));
same(['n' => 3], $request->post());

// POST partial row results must not be committed on failure.
$request = fixture([], ['a' => '1', 'b' => 'bad']);
run($request, ['post' => ['fields' => ['a' => ['type' => 'int'], 'b' => ['type' => 'int']]]]);
same(['a' => '1', 'b' => 'bad'], $request->post());

$calls = 0;
$callbackRow = $row + ['row_normalizer' => static function (array $row) use (&$calls): array {
    ++$calls;
    return ['err_number' => 1100, 'value' => ['n']];
}];
$request = fixture([], ['n' => '1']);
$expected = ['post' => ['err_number' => 1100, 'value' => ['n']]];
same($expected, run($request, ['post' => $callbackRow]));
same($expected, run($request, ['post' => $callbackRow]));
same(2, $calls);
raises(LogicException::class, fn() => run(fixture(), ['path_params' => $pathSchema]));
raises(InvalidArgumentException::class, fn() => run(fixture(['unknown' => 1]), ['query' => $row]));
raises(InvalidArgumentException::class, fn() => run(fixture(), []));

// Missing JSON source/tables are allowed; an empty source remains an array.
same(null, run(fixture(), ['json' => ['items' => $table]]));
$request = fixture();
$request->set('json', []);
same(null, run($request, ['json' => ['items' => $table]]));
same([], $request->json());
foreach ([123, null, [], ['unknown_action' => []]] as $invalid) {
    $request->set('json', ['items' => $invalid]);
    raises(InvalidArgumentException::class, fn() => run($request, ['json' => ['items' => $table]]));
}
$request->set('json', ['unknown' => ['add' => [['id' => 1]]]]);
raises(InvalidArgumentException::class, fn() => run($request, ['json' => ['items' => $table]]));

// Collect errors from both JSON tables and files even when POST fails.
$request = fixture([], ['n' => 'bad']);
$json = ['good' => ['add' => [['id' => '3']]],
    'bad1' => ['add' => [['id' => 'bad']]], 'bad2' => ['add' => [['id' => 'bad']]]];
$request->set('json', $json);
$errors = run($request, ['post' => $row,
    'json' => ['good' => $table, 'bad1' => $table, 'bad2' => $table],
    'files' => ['documents' => ['min_count' => '1']]]);
same(['post', 'json', 'files'], array_keys($errors));
same(['bad1', 'bad2'], array_keys($errors['json']));
same($json, $request->json()); // No partial commit of the successful table.
same(1604, $errors['files'][0]['err_number']);
same(['documents'], $errors['files'][0]['rule_path']);
same([], $request->file());

// Successful JSON is committed even when files fail; upload errors preserve files.
$file = ['name' => 'a.txt', 'full_path' => 'a.txt', 'type' => 'text/plain',
    'tmp_name' => '', 'size' => 0, 'error' => UPLOAD_ERR_PARTIAL];
$request = fixture();
$request->set('json', ['items' => ['add' => [['id' => '5']]]]);
$request->set('files', ['documents' => [3 => $file]]);
$errors = run($request, ['json' => ['items' => $table], 'files' => []]);
same(['files'], array_keys($errors));
same(1601, $errors['files'][0]['err_number']);
same(['documents', 3], $errors['files'][0]['path']);
same(['items' => ['add' => [['id' => 5]]]], $request->json());
same(['documents' => [3 => $file]], $request->file());
$file['error'] = UPLOAD_ERR_NO_FILE;
$request->set('files', ['documents' => [3 => $file]]);
same(null, run($request, ['files' => ['min_count' => 0]]));
same(['documents' => [3 => $file]], $request->file());

// A structural JSON exception prevents files from running.
$request->set('json', ['items' => []]);
raises(InvalidArgumentException::class, fn() => run($request,
    ['json' => ['items' => $table], 'files' => ['min_count' => 1]]));
echo "PASS: dispatch order, source commits, JSON tables, upload aggregation, schema normalization and exceptions.\n";
