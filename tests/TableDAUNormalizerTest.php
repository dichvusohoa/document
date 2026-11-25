<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Core\Normalization\AtomicNormalizer as A;
use Core\Normalization\JsonNormalizer as J;
use Core\Normalization\TableDAUNormalizer as T;
use Core\Normalization\TableFieldSchema as S;

function same(mixed $expected, mixed $actual): void
{
    if ($expected !== $actual) {
        throw new RuntimeException('Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}
function raises(string $class, Closure $run): void
{
    try { $run(); } catch (Throwable $e) {
        if ($e instanceof $class) { return; }
        throw $e;
    }
    throw new RuntimeException('Expected ' . $class);
}
function schema(): array
{
    return ['fields' => [
        'id' => ['type' => 'int'],
        'tenant' => ['type' => 'int'],
        'generated' => ['type' => 'int', 'auto_value' => true],
        'a' => ['type' => 'int'],
        'b' => ['type' => 'int'],
        'auto' => ['type' => 'int', 'auto_value' => true],
    ], 'keys' => [
        'id' => ['on_add' => 'required'],
        'tenant' => ['on_add' => 'required'],
        'generated' => ['on_add' => 'forbidden'],
    ]];
}
function runData(array $data, ?array $schema = null): array
{
    return (new T($data, $schema ?? schema()))->normalize();
}
function errorAt(array $result, array $path): array
{
    $error = $result['extra'][serialize($path)];
    same($path, $error[J::FIELD_PATH]);
    return $error;
}
function rowCode(array $data, int $code, ?array $schema = null): void
{
    $result = runData($data, $schema);
    same(J::STATUS_ERROR, $result['status']);
    same($code, errorAt($result, [array_key_first($data), 0])[J::FIELD_ERR_NUMBER]);
}

$tests = [];
$tests['all actions, changed key, omissions, input isolation and cache'] = static function (): void {
    $data = [
        'delete' => [['id' => '1', 'tenant' => '2', 'generated' => '3']],
        'add' => [['id' => '4', 'tenant' => '2', 'a' => '12', 'b' => '13', 'auto' => null]],
        'update' => [['keys' => ['id' => '1', 'tenant' => '2', 'generated' => '3'],
            'values' => ['id' => '10', 'generated' => '20', 'a' => '14']]],
    ];
    $n = new T($data, schema());
    $result = $n->normalize();
    same(J::STATUS_OK, $result['status']);
    same([], $result['extra']);
    same(['id' => 4, 'tenant' => 2, 'a' => 12, 'b' => 13], $result['data']['add'][0]);
    same(['id' => 1, 'tenant' => 2, 'generated' => 3], $result['data']['delete'][0]);
    same(['id' => 10, 'generated' => 20, 'a' => 14], $result['data']['update'][0]['values']);
    same($result, $n->normalize());
    same('4', $data['add'][0]['id']);
};
$tests['field errors continue, bad arrays stay atomic errors, rows continue'] = static function (): void {
    $result = runData(['add' => [
        ['id' => '1', 'tenant' => '2', 'a' => 'bad', 'b' => []],
        ['id' => '3', 'tenant' => '2', 'a' => '4', 'b' => '5'],
    ]]);
    same(A::ERR_FORMAT, errorAt($result, ['add', 0, 'a'])[J::FIELD_ERR_NUMBER]);
    same(A::ERR_TYPE, errorAt($result, ['add', 0, 'b'])[J::FIELD_ERR_NUMBER]);
    same(['path' => ['add', 0, 'b'], 'err_number' => A::ERR_TYPE, 'value' => []],
        errorAt($result, ['add', 0, 'b']));
    same([], $result['data']['add'][0]['b']);
    same(5, $result['data']['add'][1]['b']);
};
$tests['key failure stops row but preserves earlier key normalization'] = static function (): void {
    $result = runData(['add' => [['a' => 'bad', 'id' => '1', 'tenant' => 'bad', 'b' => '5']]]);
    same(T::ERR_INVALID_KEY, errorAt($result, ['add', 0])[J::FIELD_ERR_NUMBER]);
    same(['a' => 'bad', 'id' => 1, 'tenant' => 'bad', 'b' => '5'], $result['data']['add'][0]);
    same($result['data']['add'][0], errorAt($result, ['add', 0])[J::FIELD_VALUE]);
};
$tests['presence policies required forbidden optional'] = static function (): void {
    rowCode(['add' => [['tenant' => 2, 'a' => 1, 'b' => 2]]], T::ERR_MISSING_KEY);
    foreach ([null, 8] as $value) {
        rowCode(['add' => [['id' => 1, 'tenant' => 2, 'generated' => $value, 'a' => 1, 'b' => 2]]], T::ERR_FORBIDDEN_KEY);
    }
    $s = schema();
    $s['keys']['id']['on_add'] = 'optional';
    $s['fields']['id']['nullable'] = true;
    $row = ['tenant' => 2, 'a' => 1, 'b' => 2];
    rowCode(['add' => [$row]], T::ERR_MISSING_KEY, $s);
    $s['fields']['id']['auto_value'] = true;
    same(J::STATUS_OK, runData(['add' => [$row]], $s)['status']);
    $s['fields']['id']['nullable'] = false;
    $row['id'] = null;
    rowCode(['add' => [$row]], T::ERR_INVALID_KEY, $s);
    $s['keys']['id']['on_add'] = 'required';
    unset($row['id']);
    rowCode(['add' => [$row]], T::ERR_MISSING_KEY, $s);
};
$tests['missing ordinary field requires auto even with nullable or default'] = static function (): void {
    foreach ([['nullable' => true], ['default_value' => 0]] as $options) {
        $s = schema();
        $s['fields']['a'] = array_merge(['type' => 'int'], $options);
        $data = ['add' => [['id' => 1, 'tenant' => 2, 'b' => 2]]];
        rowCode($data, T::ERR_MISSING_FIELD, $s);
        $s['fields']['a']['auto_value'] = true;
        same(J::STATUS_OK, runData($data, $s)['status']);
    }
};
$tests['null precedence default zero nullable and auto'] = static function (): void {
    $s = schema();
    $s['fields']['a']['default_value'] = 0;
    $s['fields']['b']['nullable'] = true;
    $s['fields']['auto']['default_value'] = 7;
    $r = runData(['add' => [['id' => 1, 'tenant' => 2, 'a' => null, 'b' => null, 'auto' => null]]], $s);
    same(['id' => 1, 'tenant' => 2, 'a' => 0, 'b' => null, 'auto' => 7], $r['data']['add'][0]);
    same(J::STATUS_OK, $r['status']);
};
$tests['update new keys are fields and auto does not discard'] = static function (): void {
    $data = ['update' => [['keys' => ['id' => '1', 'tenant' => '2', 'generated' => '3'],
        'values' => ['id' => 'bad', 'auto' => null, 'a' => '14']]]];
    $r = runData($data);
    same(['id' => 'bad', 'auto' => null, 'a' => 14], $r['data']['update'][0]['values']);
    same(A::ERR_FORMAT, errorAt($r, ['update', 0, 'values', 'id'])[J::FIELD_ERR_NUMBER]);
    same(A::ERR_NULL_NOT_ALLOWED, errorAt($r, ['update', 0, 'values', 'auto'])[J::FIELD_ERR_NUMBER]);
    same(['path' => ['update', 0, 'values', 'auto'], 'err_number' => A::ERR_NULL_NOT_ALLOWED, 'value' => null],
        errorAt($r, ['update', 0, 'values', 'auto']));
};
$tests['delete update require all old keys and preserve atomic null defaults'] = static function (): void {
    rowCode(['delete' => [['id' => 1, 'tenant' => 2]]], T::ERR_MISSING_KEY);
    rowCode(['update' => [['keys' => [], 'values' => ['a' => 1]]]], T::ERR_MISSING_KEY);
    $s = schema();
    $s['fields']['id']['default_value'] = 9;
    $s['fields']['tenant']['nullable'] = true;
    $r = runData(['delete' => [['id' => null, 'tenant' => null, 'generated' => 3]]], $s);
    same(['id' => 9, 'tenant' => null, 'generated' => 3], $r['data']['delete'][0]);
};
$tests['empty add row can be valid when all fields are generated'] = static function (): void {
    $s = ['fields' => ['id' => ['type' => 'int', 'auto_value' => true]],
        'keys' => ['id' => ['on_add' => 'forbidden']]];
    same(['status' => J::STATUS_OK, 'data' => ['add' => [[]]], 'extra' => []], runData(['add' => [[]]], $s));
    rowCode(['add' => [[]]], T::ERR_MISSING_KEY);
    rowCode(['delete' => [[]]], T::ERR_MISSING_KEY);
};
$tests['structural errors throw before data errors and lock instance'] = static function (): void {
    $bad = [[], ['add' => null], ['add' => []], ['other' => [[]]],
        ['add' => [1 => []]], ['add' => [0 => [], 2 => []]], ['add' => [null]],
        ['add' => [['unknown' => 1]]], ['delete' => [['a' => 1]]],
        ['update' => [['keys' => []]]], ['update' => [['keys' => [], 'values' => []]]],
        ['update' => [['keys' => null, 'values' => ['a' => 1]]]],
        ['update' => [['keys' => [], 'values' => ['unknown' => 1]]]],
        ['update' => [['keys' => [], 'values' => ['a' => 1], 'extra' => 1]]],
    ];
    foreach ($bad as $data) {
        $n = new T($data, schema());
        raises(InvalidArgumentException::class, static fn() => $n->normalize());
        raises(LogicException::class, static fn() => $n->normalize());
    }
    raises(InvalidArgumentException::class, static fn() => new T(['add' => [[]]], []));
};

$tests['schema accepts optional validator and rejects incompatible prototypes'] = static function (): void {
    $s = schema();
    same(true, S::isValid($s));
    $s['row_normalizer'] = null;
    same(true, S::isValid($s));
    same(J::STATUS_OK, runData(['delete' => [['id' => 1, 'tenant' => 2, 'generated' => 3]]], $s)['status']);
    foreach ([42, 'strlen',
        static fn(array $row): array => $row,
        static fn(string $action, array $row): bool => true,
        static fn(string $action, ?array $row): array => [],
        static fn(string $action, array &$row): array => [],
        static fn(string $action, array $row = []): array => [],
        static fn(string $action, array ...$row): array => [],
    ] as $bad) {
        $s['row_normalizer'] = $bad;
        same(false, S::isValid($s));
        raises(InvalidArgumentException::class, static fn() => new T([], $s));
    }
    foreach (['error_kind_resolver', 'field_groups', 'field_dependencies'] as $deferred) {
        $s = schema();
        $s[$deferred] = null;
        same(false, S::isValid($s));
    }
};
$tests['validator receives normalized DAU rows once and preserves closure binding'] = static function (): void {
    $owner = new class {
        public array $seen = [];
        public int $calls = 0;
        public function validator(): Closure {
            return function (string $action, array $row): array {
                $this->calls++;
                $this->seen[$action] = $row;
                return ['err_number' => 0, 'value' => $row];
            };
        }
    };
    $s = schema();
    $s['fields']['a']['default_value'] = 9;
    $s['row_normalizer'] = $owner->validator();
    $data = [
        'add' => [['id' => '1', 'tenant' => '2', 'a' => null, 'b' => '4', 'auto' => null]],
        'update' => [['keys' => ['id' => '1', 'tenant' => '2', 'generated' => '3'], 'values' => ['a' => '5']]],
        'delete' => [['id' => '1', 'tenant' => '2', 'generated' => '3']],
    ];
    $n = new T($data, $s);
    $result = $n->normalize();
    same(J::STATUS_OK, $result['status']);
    same(['id' => 1, 'tenant' => 2, 'a' => 9, 'b' => 4], $owner->seen['add']);
    foreach ($owner->seen as $action => $row) { same($row, $result['data'][$action][0]); }
    same($result, $n->normalize());
    same(3, $owner->calls);
    same('1', $data['add'][0]['id']);
};
$tests['validator skipped for presence key and atomic errors'] = static function (): void {
    $s = schema();
    $s['row_normalizer'] = static function (string $action, array $row): array {
        throw new RuntimeException('Validator must not run');
    };
    foreach ([
        ['add' => [['tenant' => 2, 'a' => 1, 'b' => 2]]],
        ['add' => [['id' => 1, 'tenant' => 2, 'b' => 2]]],
        ['delete' => [['id' => 'bad', 'tenant' => 2, 'generated' => 3]]],
        ['add' => [['id' => 1, 'tenant' => 2, 'a' => 'bad', 'b' => 2]]],
    ] as $data) { same(J::STATUS_ERROR, runData($data, $s)['status']); }
};
$tests['business errors preserve rows, report field groups and continue batch'] = static function (): void {
    $s = schema();
    $calls = 0;
    $s['row_normalizer'] = static function (string $action, array $row) use (&$calls): array {
        $calls++;
        $values = $action === 'update' ? $row['values'] : $row;
        if (!array_key_exists('a', $values) || !array_key_exists('b', $values)) {
            return ['err_number' => 1301, 'value' => $row];
        }
        if ($values['a'] > $values['b']) {
            return ['err_number' => 1300, 'value' => ['a', 'b']];
        }
        return ['err_number' => 0, 'value' => $row];
    };
    $data = [
        'add' => [
            ['id' => '1', 'tenant' => '2', 'a' => '9', 'b' => '4'],
            ['id' => '2', 'tenant' => '2', 'a' => '3', 'b' => '4'],
        ],
        'update' => [
            ['keys' => ['id' => '1', 'tenant' => '2', 'generated' => '3'], 'values' => ['a' => '9', 'b' => '4']],
            ['keys' => ['id' => '2', 'tenant' => '2', 'generated' => '3'], 'values' => ['a' => '9']],
        ],
    ];
    $r = runData($data, $s);
    same(4, $calls);
    same(['path' => ['add', 0], 'err_number' => 1300, 'value' => ['a', 'b']], errorAt($r, ['add', 0]));
    same(['path' => ['update', 0], 'err_number' => 1300, 'value' => ['a', 'b']], errorAt($r, ['update', 0]));
    same(['a' => 9, 'b' => 4], $r['data']['update'][0]['values']);
    same(['path' => ['update', 1], 'err_number' => 1301, 'value' => $r['data']['update'][1]],
        errorAt($r, ['update', 1]));
    same(false, isset($r['extra'][serialize(['add', 1])]));
    same(3, $r['data']['add'][1]['a']);
};
$tests['validator contract violations and exceptions lock instance'] = static function (): void {
    $data = ['delete' => [['id' => 1, 'tenant' => 2, 'generated' => 3]]];
    foreach ([1300, 1399] as $code) {
        $s = schema();
        $s['row_normalizer'] = static fn(string $action, array $row): array =>
            ['err_number' => $code, 'value' => $row];
        rowCode($data, $code, $s);
    }
    same(1200, T::ERR_MISSING_KEY);
    same(1201, T::ERR_FORBIDDEN_KEY);
    same(1202, T::ERR_MISSING_FIELD);
    same(1203, T::ERR_INVALID_KEY);
    foreach ([[], ['err_number' => 1300], ['err_number' => '1300', 'value' => []],
        ['err_number' => true, 'value' => []], ['err_number' => -1, 'value' => []],
        ['err_number' => 1, 'value' => []], ['err_number' => 1203, 'value' => []],
        ['err_number' => 1299, 'value' => []], ['err_number' => 0, 'value' => []],
        ['err_number' => 1400, 'value' => []], ['err_number' => 2000, 'value' => []],
        ['err_number' => 1300, 'value' => [], 'extra' => null],
    ] as $bad) {
        $s = schema();
        $s['row_normalizer'] = static fn(string $action, array $row): array => $bad;
        $n = new T($data, $s);
        raises(UnexpectedValueException::class, static fn() => $n->normalize());
        raises(LogicException::class, static fn() => $n->normalize());
    }
    $s = schema();
    $exception = new RuntimeException('UDA failure');
    $s['row_normalizer'] = static function (string $action, array $row) use ($exception): array { throw $exception; };
    $n = new T($data, $s);
    try { $n->normalize(); throw new LogicException('Expected callback exception'); }
    catch (RuntimeException $caught) { same($exception, $caught); }
    raises(LogicException::class, static fn() => $n->normalize());
};
$tests['delete validator row errors and incorrect return type'] = static function (): void {
    $data = ['delete' => [['id' => '1', 'tenant' => '2', 'generated' => '3']]];
    $s = schema();
    $s['row_normalizer'] = static fn(string $action, array $row): array => [
        'err_number' => 1302, 'value' => $row,
    ];
    $r = runData($data, $s);
    same(['id' => 1, 'tenant' => 2, 'generated' => 3], $r['data']['delete'][0]);
    same(['path' => ['delete', 0], 'err_number' => 1302, 'value' => $r['data']['delete'][0]],
        errorAt($r, ['delete', 0]));
    $s['row_normalizer'] = static function (string $action, array $row): array { return false; };
    $n = new T($data, $s);
    raises(TypeError::class, static fn() => $n->normalize());
    raises(LogicException::class, static fn() => $n->normalize());
};

$tests['normalizer corrects page size and dates in Add and Update'] = static function (): void {
    $s = ['fields' => [
        'id' => ['type' => 'int', 'auto_value' => true],
        'pageSize' => ['type' => 'int'],
        'start_date' => ['type' => 'date'], 'end_date' => ['type' => 'date'],
    ], 'keys' => ['id' => ['on_add' => 'forbidden']]];
    $s['row_normalizer'] = static function (string $action, array $row): array {
        $values = $action === 'update' ? $row['values'] : $row;
        $values['pageSize'] = min(100, $values['pageSize']);
        $values['end_date'] = max($values['start_date'], $values['end_date']);
        if ($action === 'update') { $row['values'] = $values; } else { $row = $values; }
        return ['err_number' => 0, 'value' => $row];
    };
    $values = ['pageSize' => '200', 'start_date' => '2026-11-02', 'end_date' => '2026-10-30'];
    $data = ['add' => [$values], 'update' => [['keys' => ['id' => '10'], 'values' => $values]]];
    $n = new T($data, $s);
    $r = $n->normalize();
    $expected = ['pageSize' => 100, 'start_date' => '2026-11-02', 'end_date' => '2026-11-02'];
    same(J::STATUS_OK, $r['status']);
    same($expected, $r['data']['add'][0]);
    same(['keys' => ['id' => 10], 'values' => $expected], $r['data']['update'][0]);
    same($r, $n->normalize());
    same('200', $data['add'][0]['pageSize']);
};
$tests['normalizer can add remove fields without repeating atomic and preserves data on failure'] = static function (): void {
    $s = schema();
    $s['row_normalizer'] = static function (string $action, array $row): array {
        if ($action === 'add') {
            unset($row['auto']);
            $row['a'] = '009'; // Deliberately not canonical: no second atomic pass.
        } else {
            unset($row['values']['a']);
            $row['values']['b'] = 99;
            $row['values']['id'] = 20; // New key value is not the old identity.
        }
        return ['err_number' => 0, 'value' => $row];
    };
    $data = ['add' => [['id' => 1, 'tenant' => 2, 'a' => 3, 'b' => 4, 'auto' => 5]],
        'update' => [['keys' => ['id' => 1, 'tenant' => 2, 'generated' => 3], 'values' => ['a' => 5]]]];
    $r = runData($data, $s);
    same('009', $r['data']['add'][0]['a']);
    same(false, array_key_exists('auto', $r['data']['add'][0]));
    same(['b' => 99, 'id' => 20], $r['data']['update'][0]['values']);
    $s['row_normalizer'] = static function (string $action, array $row): array {
        $row['a'] = 999;
        return ['err_number' => 1300, 'value' => ['a', 'b']];
    };
    $r = runData(['add' => [$data['add'][0]]], $s);
    same($data['add'][0], $r['data']['add'][0]);
    same(['path' => ['add', 0], 'err_number' => 1300, 'value' => ['a', 'b']], errorAt($r, ['add', 0]));
};
$tests['normalized row contract rejects shape presence and identity changes'] = static function (): void {
    $add = ['id' => 1, 'tenant' => 2, 'a' => 3, 'b' => 4];
    $keys = ['id' => 1, 'tenant' => 2, 'generated' => 3];
    $update = ['keys' => $keys, 'values' => ['a' => 5]];
    $cases = [
        ['add', $add, $add + ['unknown' => 1]],
        ['add', $add, $add + ['generated' => null]],
        ['add', $add, array_diff_key($add, ['id' => true])],
        ['add', $add, array_diff_key($add, ['a' => true])],
        ['update', $update, ['a' => 5]],
        ['update', $update, ['keys' => $keys, 'values' => []]],
        ['update', $update, ['keys' => $keys, 'values' => ['unknown' => 1]]],
        ['update', $update, ['keys' => array_merge($keys, ['id' => 9]), 'values' => ['a' => 5]]],
        ['update', $update, ['keys' => array_merge($keys, ['id' => '1']), 'values' => ['a' => 5]]],
        ['delete', $keys, array_merge($keys, ['id' => 9])],
        ['delete', $keys, array_diff_key($keys, ['id' => true])],
        ['delete', $keys, $keys + ['a' => 1]],
    ];
    foreach ($cases as [$action, $input, $output]) {
        $s = schema();
        $s['row_normalizer'] = static fn(string $action, array $row): array => ['err_number' => 0, 'value' => $output];
        $n = new T([$action => [$input]], $s);
        raises(UnexpectedValueException::class, static fn() => $n->normalize());
        raises(LogicException::class, static fn() => $n->normalize());
    }
    // optional without auto is also required by the current Add omission policy.
    $s = schema();
    $s['keys']['id']['on_add'] = 'optional';
    $s['row_normalizer'] = static function (string $action, array $row): array {
        unset($row['id']);
        return ['err_number' => 0, 'value' => $row];
    };
    raises(UnexpectedValueException::class, static fn() => runData(['add' => [$add]], $s));
    $s['fields']['id']['auto_value'] = true;
    same(J::STATUS_OK, runData(['add' => [$add]], $s)['status']);
};
$tests['identity ordering allowed but unsafe returned data and legacy schema rejected'] = static function (): void {
    $keys = ['id' => 1, 'tenant' => 2, 'generated' => 3];
    $s = schema();
    $s['row_normalizer'] = static function (string $action, array $row): array {
        if ($action === 'delete') { $row = array_reverse($row, true); }
        else { $row['keys'] = array_reverse($row['keys'], true); }
        return ['err_number' => 0, 'value' => $row];
    };
    same(J::STATUS_OK, runData(['delete' => [$keys],
        'update' => [['keys' => $keys, 'values' => ['a' => 1]]]], $s)['status']);
    foreach ([new stdClass(), INF] as $unsafe) {
        $s['row_normalizer'] = static function (string $action, array $row) use ($unsafe): array {
            $row['values']['a'] = $unsafe;
            return ['err_number' => 0, 'value' => $row];
        };
        raises(InvalidArgumentException::class, static fn() => runData(
            ['update' => [['keys' => $keys, 'values' => ['a' => 1]]]], $s));
    }
    $s = schema();
    $s['row_validator'] = null;
    same(false, S::isValid($s));
};

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
foreach ($tests as $name => $test) {
    $test();
    echo 'PASS: ', $name, PHP_EOL;
}
restore_error_handler();
echo count($tests), ' scenarios passed.', PHP_EOL;
