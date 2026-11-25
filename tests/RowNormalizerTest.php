<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Core\Http\Response;
use Core\Normalization\AtomicNormalizer as A;
use Core\Normalization\RowFieldSchema as S;
use Core\Normalization\RowNormalizer as R;

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
function normalizeRow(array $row, ?Closure $validator = null): array
{
    $schema = ['fields' => [
        'a' => ['type' => 'int', 'default_value' => 1],
        'b' => ['type' => 'int', 'nullable' => true],
        'c' => ['type' => 'int', 'auto_value' => true],
    ]];
    if ($validator !== null) { $schema['row_normalizer'] = $validator; }
    return (new R($row, $schema))->normalize();
}

$tests = [];
$tests['missing, null, default, auto metadata and input isolation'] = static function (): void {
    same(['status' => Response::SERVER_OK_STATUS, 'data' => [], 'extra' => null], normalizeRow([]));
    $input = ['a' => null, 'b' => null];
    same(['a' => 1, 'b' => null], normalizeRow($input)['data']);
    same(['a' => null, 'b' => null], $input);
    same(A::ERR_NULL_NOT_ALLOWED, normalizeRow(['c' => null])['extra']['c']['err_number']);
    $external = '12';
    $input = ['a' => &$external];
    same(['a' => 12], normalizeRow($input)['data']);
    same('12', $external);
};
$tests['collect all field errors and skip callback'] = static function (): void {
    $calls = 0;
    $result = normalizeRow(['a' => 'bad', 'b' => 'wrong', 'c' => '3'],
        static function (array $row) use (&$calls): array {
            ++$calls;
            return ['err_number' => 0, 'value' => $row];
        });
    same(0, $calls);
    same(Response::SERVER_LOGIC_ERR_STATUS, $result['status']);
    same(['a' => 'bad', 'b' => 'wrong', 'c' => 3], $result['data']);
    same(['a' => ['err_number' => A::ERR_FORMAT, 'value' => 'bad'],
        'b' => ['err_number' => A::ERR_FORMAT, 'value' => 'wrong']], $result['extra']);
};
$tests['callback receives normalized partial row and controls presence'] = static function (): void {
    $result = normalizeRow(['a' => '2'], static function (array $row): array {
        same(['a' => 2], $row);
        return ['err_number' => 1100, 'value' => ['b']];
    });
    same(['err_number' => 1100, 'value' => ['b']], $result['extra']);
    same(['a' => 2], $result['data']);
    same(null, normalizeRow(['a' => '2'], static fn(array $row): array =>
        ['err_number' => 0, 'value' => $row])['extra']);
    same(['err_number' => 1101, 'value' => null], normalizeRow([], static fn(array $row): array =>
        ['err_number' => 1101, 'value' => null])['extra']);
};
$tests['unknown names rejected before callback and petiole is ordinary field'] = static function (): void {
    raises(InvalidArgumentException::class, static fn() => normalizeRow(['unknown' => 1]));
    raises(InvalidArgumentException::class, static fn() => normalizeRow([0 => 1]));
    $schema = ['fields' => ['__petiole__' => ['type' => 'int']]];
    same(['__petiole__' => 4], (new R(['__petiole__' => '4'], $schema))->normalize()['data']);
};
$tests['schema validation including omitted fields and callback prototype'] = static function (): void {
    $base = ['fields' => ['a' => ['type' => 'int']]];
    foreach ([null, [], ['fields' => []], ['fields' => ['a' => ['type' => 'bad']]],
        ['fields' => ['' => ['type' => 'int']]], $base + ['extra' => true],
        $base + ['row_normalizer' => false]] as $schema) {
        same(false, S::isValid($schema));
    }
    foreach ([static fn($row): array => [], static fn(array $row) => [],
        static fn(array &$row): array => [], static fn(array $row = []): array => [],
        static fn(array ...$row): array => [], static fn(string $action, array $row): array => []]
        as $callback) {
        same(false, S::isValid($base + ['row_normalizer' => $callback]));
    }
    raises(InvalidArgumentException::class, static fn() => new R([], ['fields' => ['a' => ['type' => 'bad']]]));
};
$tests['optional callback accepts omission and null only'] = static function (): void {
    $base = ['fields' => ['a' => ['type' => 'int']]];
    foreach ([$base, $base + ['row_normalizer' => null]] as $schema) {
        same(true, S::isValid($schema));
        same(['status' => Response::SERVER_OK_STATUS, 'data' => ['a' => 12], 'extra' => null],
            (new R(['a' => '12'], $schema))->normalize());
        same(A::ERR_FORMAT, (new R(['a' => 'bad'], $schema))->normalize()['extra']['a']['err_number']);
    }
    foreach ([false, 0, '', [], 'strlen'] as $invalid) {
        same(false, S::isValid($base + ['row_normalizer' => $invalid]));
    }
};
$tests['malformed callback results and error boundaries'] = static function (): void {
    foreach ([1100, 1199] as $code) {
        same(['err_number' => $code, 'value' => []], normalizeRow([], static fn(array $row): array =>
            ['err_number' => $code, 'value' => []])['extra']);
    }
    foreach ([[], ['err_number' => 0], ['value' => []],
        ['err_number' => 0, 'value' => [], 'extra' => null],
        ['err_number' => '1100', 'value' => []], ['err_number' => true, 'value' => []],
        ['err_number' => -1, 'value' => []], ['err_number' => 999, 'value' => []],
        ['err_number' => 1000, 'value' => []], ['err_number' => 1099, 'value' => []],
        ['err_number' => 1200, 'value' => []], ['err_number' => 2000, 'value' => []],
        ['err_number' => 0, 'value' => 'abcd'], ['err_number' => 0, 'value' => null],
        ['err_number' => 0, 'value' => 1], ['err_number' => 0, 'value' => false]] as $result) {
        raises(UnexpectedValueException::class, static fn() => normalizeRow([], static fn(array $row): array => $result));
    }
    raises(TypeError::class, static fn() => normalizeRow([], static function (array $row): array { return 'bad'; }));
};
$tests['both direct result references rejected'] = static function (): void {
    foreach (['err_number', 'value'] as $key) {
        raises(UnexpectedValueException::class, static function () use ($key): void {
            $external = $key === 'err_number' ? 1100 : [];
            normalizeRow([], static function (array $row) use ($key, &$external): array {
                $result = ['err_number' => 1100, 'value' => []];
                $result[$key] =& $external;
                return $result;
            });
        });
    }
};
$tests['repeat calls rerun callback and exceptions propagate'] = static function (): void {
    $calls = 0;
    $normalizer = new R(['a' => '1'], ['fields' => ['a' => ['type' => 'int']],
        'row_normalizer' => static function (array $row) use (&$calls): array {
            ++$calls;
            return ['err_number' => 0, 'value' => $row];
        }]);
    same($normalizer->normalize(), $normalizer->normalize());
    same(2, $calls);
    raises(LogicException::class, static fn() => normalizeRow([], static function (array $row): array {
        throw new LogicException('UDA failure');
    }));
};
$tests['UDA clamps page size and corrects date relationship'] = static function (): void {
    $input = ['pageSize' => '200', 'start_date' => '2026-11-10', 'end_date' => '2026-11-01'];
    $result = (new R($input, ['fields' => [
        'pageSize' => ['type' => 'int'], 'start_date' => ['type' => 'date'], 'end_date' => ['type' => 'date'],
    ], 'row_normalizer' => static function (array $row): array {
        same(200, $row['pageSize']);
        $row['pageSize'] = min(100, $row['pageSize']);
        if ($row['end_date'] < $row['start_date']) {
            $row['end_date'] = $row['start_date'];
        }
        return ['err_number' => 0, 'value' => $row];
    }]))->normalize();
    same(['status' => Response::SERVER_OK_STATUS,
        'data' => ['pageSize' => 100, 'start_date' => '2026-11-10', 'end_date' => '2026-11-10'],
        'extra' => null], $result);
    same('200', $input['pageSize']);
    same('2026-11-01', $input['end_date']);
};
$tests['successful array replaces row without another normalization pass'] = static function (): void {
    foreach ([[], ['a' => '20', 'derived' => 'UDA output']] as $output) {
        same($output, normalizeRow(['a' => '1', 'b' => '2'], static fn(array $row): array =>
            ['err_number' => 0, 'value' => $output])['data']);
    }
};
$tests['failed callback keeps pre-callback data'] = static function (): void {
    $result = normalizeRow(['a' => '200'], static function (array $row): array {
        $row['a'] = 100;
        return ['err_number' => 1100, 'value' => ['a']];
    });
    same(['status' => Response::SERVER_LOGIC_ERR_STATUS, 'data' => ['a' => 200],
        'extra' => ['err_number' => 1100, 'value' => ['a']]], $result);
};
$tests['sparse mixed keys and whitespace survive normalization and callback'] = static function (): void {
    $schema = ['fields' => [0 => ['type' => 'int'], 5 => ['type' => 'int'],
        'name' => ['type' => 'string'], ' ' => ['type' => 'int'], '01' => ['type' => 'int']],
        'row_normalizer' => static function (array $row): array {
            same([5 => 50, 'name' => 'An', 0 => 10, ' ' => 3, '01' => 4], $row);
            return ['err_number' => 0, 'value' => $row];
        }];
    same(true, S::isValid($schema));
    $result = (new R([5 => '50', 'name' => 'An', 0 => '10', ' ' => '3', '01' => '4'], $schema))->normalize();
    same([5 => 50, 'name' => 'An', 0 => 10, ' ' => 3, '01' => 4], $result['data']);
    same(null, $result['extra']);
    $schema = ['fields' => ['0' => ['type' => 'int']]];
    same([0 => 2], (new R(['0' => '2'], $schema))->normalize()['data']);
};
$tests['numeric field errors preserve keys and skip callback'] = static function (): void {
    $schema = ['fields' => [0 => ['type' => 'int'], 5 => ['type' => 'int'], 'name' => ['type' => 'int']],
        'row_normalizer' => static function (array $row): array {
            throw new LogicException('Must not run on field errors');
        }];
    $result = (new R([5 => 'bad', 'name' => '7', 0 => 'wrong'], $schema))->normalize();
    same([5 => 'bad', 'name' => 7, 0 => 'wrong'], $result['data']);
    same([5 => ['err_number' => A::ERR_FORMAT, 'value' => 'bad'],
        0 => ['err_number' => A::ERR_FORMAT, 'value' => 'wrong']], $result['extra']);
    same(Response::SERVER_LOGIC_ERR_STATUS, $result['status']);
};
$tests['invalid and undeclared keys rejected while numeric omissions are allowed'] = static function (): void {
    foreach ([-1, '', '-1'] as $key) {
        $schema = ['fields' => [$key => ['type' => 'int']]];
        same(false, S::isValid($schema));
        raises(InvalidArgumentException::class, static fn() => new R([], $schema));
    }
    $schema = ['fields' => [0 => ['type' => 'int'], 5 => ['type' => 'int']]];
    foreach ([-1, '', 1] as $key) {
        raises(InvalidArgumentException::class, static fn() => new R([$key => '1'], $schema));
    }
    same([5 => 2], (new R([5 => '2'], $schema))->normalize()['data']);
};
foreach ($tests as $name => $test) {
    $test();
    echo "PASS: $name\n";
}
echo count($tests) . " scenarios passed\n";
