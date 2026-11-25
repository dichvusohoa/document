<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Core\Http\Request;
use Core\Normalization\RequestNormalizer;

function same(mixed $expected, mixed $actual): void
{
    if ($expected !== $actual) {
        throw new RuntimeException('Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

// Inject already captured sources without reading php://input.
function requestFixture(array $post, string $contentType, ?array $json = null): Request
{
    return new class($post, $contentType, $json) extends Request {
        public function __construct(array $post, string $contentType, ?array $json)
        {
            $this->postData = $post;
            $this->serverData = ['CONTENT_TYPE' => $contentType];
            $this->jsonData = $json;
        }
    };
}

function preparer(Request $request): RequestNormalizer
{
    return new class($request) extends RequestNormalizer {
        public function prepare(array $schema): ?array
        {
            return $this->prepareJsonData($schema);
        }
    };
}

$tests = [];
$tests['forms move declared fields, preserve others and allow repeated calls'] = static function (): void {
    foreach ([' Multipart/Form-Data ; boundary=abc', 'application/x-www-form-urlencoded; charset=UTF-8'] as $type) {
        $post = ['object' => '{"add":[{"id":1}]}', 'list' => '[1,2]', 'empty' => '{}', 'keep' => 'raw'];
        $request = requestFixture($post, $type);
        $normalizer = preparer($request);
        $schema = ['post_json_fields' => ['object', 'missing', 'list', 'empty']];
        same(null, $normalizer->prepare($schema));
        same(['keep' => 'raw'], $request->post());
        same(['object' => ['add' => [['id' => 1]]], 'list' => [1, 2], 'empty' => []], $request->json());
        $json = $request->json();
        same(null, $normalizer->prepare($schema));
        same($json, $request->json());
        same(['keep' => 'raw'], $request->post());
    }
};
$tests['prepared JSON including empty array prevents conversion'] = static function (): void {
    foreach ([[], ['field' => ['original']]] as $json) {
        $request = requestFixture(['field' => '[2]'], 'multipart/form-data', $json);
        same(null, preparer($request)->prepare(['post_json_fields' => ['field']]));
        same($json, $request->json());
        same(['field' => '[2]'], $request->post());
    }
};
$tests['absent configuration and unsupported content types leave JSON unprepared'] = static function (): void {
    foreach (['', 'application/json', 'text/plain', 'application/json-patch+json'] as $type) {
        $request = requestFixture(['field' => '[2]'], $type);
        same(null, preparer($request)->prepare(['post_json_fields' => ['field']]));
        same(null, $request->json());
        same(['field' => '[2]'], $request->post());
    }
    foreach ([[], ['post_json_fields' => null]] as $schema) {
        $request = requestFixture(['field' => '[2]'], 'multipart/form-data');
        same(null, preparer($request)->prepare($schema));
        same(null, $request->json());
        same(['field' => '[2]'], $request->post());
    }
};
$tests['missing fields produce prepared empty JSON'] = static function (): void {
    $request = requestFixture(['keep' => 'raw'], 'multipart/form-data');
    same(null, preparer($request)->prepare(['post_json_fields' => ['missing']]));
    same([], $request->json());
    same(['keep' => 'raw'], $request->post());
};
$tests['all errors collected and neither source committed'] = static function (): void {
    $post = ['valid' => '[]', 'array' => [], 'null' => null, 'int' => 12,
        'syntax' => '{', 'utf8' => "\xFF", 'scalar' => '12', 'boolean' => 'false',
        'string' => '"text"', 'jsonNull' => 'null', 'lastValid' => '{}'];
    $request = requestFixture($post, 'multipart/form-data');
    $codes = ['array' => 11000, 'null' => 11000, 'int' => 11000,
        'syntax' => 11001, 'utf8' => 11001, 'scalar' => 11002, 'boolean' => 11002,
        'string' => 11002, 'jsonNull' => 11002];
    $expected = [];
    foreach ($codes as $field => $code) {
        $expected[$field] = ['err_number' => $code, 'value' => $post[$field]];
    }
    same(['post' => $expected], preparer($request)->prepare(['post_json_fields' => array_keys($post)]));
    same($post, $request->post());
    same(null, $request->json());
};

$globals = [$_GET, $_POST, $_FILES, $_SERVER];
foreach ($tests as $name => $test) {
    $test();
    echo "PASS: $name\n";
}
same($globals, [$_GET, $_POST, $_FILES, $_SERVER]);
echo count($tests) . " scenarios passed\n";
