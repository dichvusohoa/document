<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Core\Foundation\ExtArray as E;
use Core\Normalization\AtomicNormalizer as A;
use Core\Normalization\JsonNormalizer as J;

function same(mixed $expected, mixed $actual): void
{
    if ($expected !== $actual) {
        throw new RuntimeException('Expected ' . var_export($expected, true)
            . ', got ' . var_export($actual, true));
    }
}

function raises(string $class, Closure $run): void
{
    try {
        $run();
    } catch (Throwable $e) {
        if ($e instanceof $class) {
            return;
        }
        throw $e;
    }
    throw new RuntimeException('Expected ' . $class);
}

/** Expected flat records; paths are explicitly logical in each test case. */
function errors(array ...$entries): array
{
    $errors = [];
    foreach ($entries as [$path, $code, $value]) {
        $errors[serialize($path)] = ['path' => $path, 'err_number' => $code, 'value' => $value];
    }
    return $errors;
}

$tests = [];
$tests['atomic, composite, binding, input isolation and cached response'] = static function (): void {
    $owner = new class {
        private int $increment = 1;
        public function processor(): Closure
        {
            return function (array $path, mixed $value): array {
                return [['path' => [], 'err_number' => 0, 'value' => ['n' => $value['n'] + $this->increment], 'error_value' => null]];
            };
        }
    };
    $input = ['n' => '12'];
    $normalizer = (new J($input))
        ->addStep(static fn(mixed $v): array|int => is_array($v) ? E::BRANCH_OR_PETIOLE : ['type' => 'int'])
        ->addStep(static fn(array $p, mixed $v): int => E::COMPOSITE_LEAF, $owner->processor());
    $result = $normalizer->normalize();
    same(['n' => 13], $result['data']);
    same(['n' => '12'], $input);
    $result['data']['n'] = 99;
    same(['n' => 13], $normalizer->normalize()['data']);
};
$tests['discard bypasses processor, preserves empty branches and numeric keys'] = static function (): void {
    $result = (new J(['rows' => [0 => 'drop', 1 => 'keep', 2 => 'drop'], 'empty' => ['x' => 'drop']]))
        ->addStep(static fn(mixed $v): int => is_array($v) ? E::BRANCH_OR_PETIOLE
            : ($v === 'drop' ? E::DISCARDED_LEAF : E::EXCLUDED),
            static function (array $p, mixed $v): array { throw new RuntimeException('Unexpected processor'); })
        ->addStep(static function (array $p, mixed $v): int {
            if ($v === 'drop') { throw new RuntimeException('Deleted node visited'); }
            return is_array($v) ? E::BRANCH_OR_PETIOLE : E::EXCLUDED;
        })->normalize();
    same(['rows' => [1 => 'keep'], 'empty' => []], $result['data']);
    same([], $result['extra']);
};
$tests['root and subtree discard'] = static function (): void {
    same([], (new J(['a' => 1]))->addStep(static fn(mixed $v): int => E::DISCARDED_LEAF)->normalize()['data']);
    same(['keep' => 2], (new J(['drop' => ['nested' => 1], 'keep' => 2]))
        ->addStep(static fn(array $p, mixed $v): int => $p === [] ? E::BRANCH_OR_PETIOLE
            : ($p === ['drop'] ? E::DISCARDED_LEAF : E::EXCLUDED))->normalize()['data']);
};
$tests['selected leaves and ancestors with errors skip processing while siblings run'] = static function (): void {
    $normalizer = (new J(['row' => ['age' => 'bad', 'keep' => 1], 'ok' => '2']))
        ->addStep(static fn(array $p, mixed $v): array|int => is_array($v) ? E::BRANCH_OR_PETIOLE
            : ($p === ['row', 'age'] ? ['type' => 'int'] : E::EXCLUDED))
        ->addStep(static fn(array $p, mixed $v): int => $p === [] ? E::BRANCH_OR_PETIOLE : E::COMPOSITE_LEAF,
            static function (array $p, mixed $v): array {
                same(['ok'], $p);
                return [['path' => [], 'err_number' => 0, 'value' => (int) $v, 'error_value' => null]];
            })
        ->addStep(static fn(mixed $v): int => E::COMPOSITE_LEAF,
            static function (array $p, mixed $v): array { throw new RuntimeException('Root with descendant error processed'); });
    $result = $normalizer->normalize();
    same(['row' => ['age' => 'bad', 'keep' => 1], 'ok' => 2], $result['data']);
    same(errors([['row', 'age'], A::ERR_FORMAT, 'bad']), $result['extra']);
    same(J::STATUS_ERROR, $result['status']);
    same($result, $normalizer->normalize());
};
$tests['petiole errors use logical paths and defines still visit failed leaves'] = static function (): void {
    $visited = [];
    $result = (new J(['item' => ['__petiole__' => 'bad', 'drop' => 1]]))
        ->addStep(static fn(array $p, mixed $v): array|int => is_array($v) ? E::BRANCH_OR_PETIOLE
            : (end($p) === '__petiole__' ? ['type' => 'int'] : E::DISCARDED_LEAF))
        ->addStep(static function (array $p, mixed $v) use (&$visited): int {
            $visited[] = $p;
            return is_array($v) ? E::BRANCH_OR_PETIOLE : E::COMPOSITE_LEAF;
        }, static function (array $p, mixed $v): array { throw new RuntimeException('Failed petiole leaf processed'); })
        ->normalize();
    same([[], ['item'], ['item', '__petiole__']], $visited);
    same(['item' => ['__petiole__' => 'bad']], $result['data']);
    same(errors([['item'], A::ERR_FORMAT, 'bad']), $result['extra']);
};
$tests['root scalar petiole and its error stay wrapped after discard'] = static function (): void {
    $result = (new J(['__petiole__' => 'bad', 'drop' => 1]))
        ->addStep(static fn(array $p, mixed $v): array|int => $p === [] ? E::BRANCH_OR_PETIOLE
            : ($p === ['__petiole__'] ? ['type' => 'int'] : E::DISCARDED_LEAF))
        ->addStep(static fn(mixed $v): int => E::EXCLUDED)->normalize();
    same(['__petiole__' => 'bad'], $result['data']);
    same(errors([[], A::ERR_FORMAT, 'bad']), $result['extra']);
};
$tests['EXCLUDED preserves descendant errors and custom petiole containers'] = static function (): void {
    E::setPetioleName('_leaf');
    try {
        $result = (new J(['item' => ['_leaf' => ['bad' => 1]]]))
            ->addStep(static fn(array $p, mixed $v): int => $p === [] || $p === ['item']
                ? E::BRANCH_OR_PETIOLE : E::COMPOSITE_LEAF,
                static fn(array $p, mixed $v): array => [['path' => [], 'err_number' => 1000, 'value' => $v, 'error_value' => $v]])
            ->addStep(static fn(mixed $v): int => E::EXCLUDED)->normalize();
        same(['item' => ['_leaf' => ['bad' => 1]]], $result['data']);
        same(errors([['item'], 1000, ['bad' => 1]]), $result['extra']);
    } finally {
        E::setPetioleName(E::PETIOLE_DEFAULT_NAME);
    }
};
$tests['root array petiole becomes a logical root error and skips root processing'] = static function (): void {
    $seen = [];
    $result = (new J(['__petiole__' => ['x' => 1], 'drop' => 2]))
        ->addStep(static fn(array $p, mixed $v): int => $p === [] ? E::BRANCH_OR_PETIOLE
            : ($p === ['drop'] ? E::DISCARDED_LEAF : E::COMPOSITE_LEAF),
            static fn(array $p, mixed $v): array => [['path' => [], 'err_number' => 1002, 'value' => $v, 'error_value' => $v]])
        ->addStep(static function (array $p, mixed $v) use (&$seen): int {
            $seen[] = $p;
            return E::COMPOSITE_LEAF;
        }, static function (array $p, mixed $v): array { throw new RuntimeException('Failed root processed'); })
        ->normalize();
    same(['__petiole__' => ['x' => 1]], $result['data']);
    same(errors([[], 1002, ['x' => 1]]), $result['extra']);
    same([[]], $seen);
};
$tests['solitary child petiole error skips selected ancestor processing'] = static function (): void {
    $result = (new J(['outer' => ['inner' => ['__petiole__' => 'bad', 'drop' => 1], 'drop' => 2]]))
        ->addStep(static fn(array $p, mixed $v): array|int => is_array($v) ? E::BRANCH_OR_PETIOLE
            : ($v === 'bad' ? ['type' => 'int'] : E::DISCARDED_LEAF))
        ->addStep(static fn(array $p, mixed $v): int => $p === [] ? E::BRANCH_OR_PETIOLE : E::COMPOSITE_LEAF,
            static function (array $p, mixed $v): array { throw new RuntimeException('Ancestor with petiole error processed'); })
        ->normalize();
    same(['outer' => ['inner' => ['__petiole__' => 'bad']]], $result['data']);
    same(errors([['outer', 'inner'], A::ERR_FORMAT, 'bad']), $result['extra']);
};
$tests['failed composites are defined again but processing and snapshots stay unchanged'] = static function (): void {
    $visited = [];
    $result = (new J(['item' => ['__petiole__' => ['x' => 1]]]))
        ->addStep(static fn(array $p, mixed $v): int => $p === [] ? E::BRANCH_OR_PETIOLE : E::COMPOSITE_LEAF,
            static fn(array $p, mixed $v): array => [['path' => [], 'err_number' => 1001, 'value' => $v, 'error_value' => $v]])
        ->addStep(static function (array $p, mixed $v) use (&$visited): int {
            $visited[] = $p;
            return $p === [] ? E::BRANCH_OR_PETIOLE : E::COMPOSITE_LEAF;
        }, static function (array $p, mixed $v): array { throw new RuntimeException('Failed item processed'); })
        ->normalize();
    same([[], ['item']], $visited);
    same(['item' => ['__petiole__' => ['x' => 1]]], $result['data']);
    same(errors([['item'], 1001, ['__petiole__' => ['x' => 1]]]), $result['extra']);
};
$tests['legacy string declarations and structural markers are rejected'] = static function (): void {
    raises(InvalidArgumentException::class, static fn() => (new J([]))
        ->addStep(static fn(mixed $v): array|int|string => E::EXCLUDED));
    foreach (['composite_leaf', E::BRANCH, E::LEAF, E::PETIOLE, true] as $marker) {
        raises(InvalidArgumentException::class, static fn() => (new J([]))
            ->addStep(static fn(mixed $v) => $marker)->normalize());
    }
};
$tests['candidate isolation, error range, root guard and lifecycle'] = static function (): void {
    $normalizer = (new J(['x' => 1]))->addStep(static fn(mixed $v): int => E::COMPOSITE_LEAF,
        static function (array $p, mixed $v): array {
            $v['x'] = 99;
            return [['path' => [], 'err_number' => 1000, 'value' => $v, 'error_value' => $v]];
        });
    same(['x' => 99], $normalizer->normalize()['data']);
    raises(LogicException::class, static fn() => $normalizer->addStep(static fn(mixed $v): int => E::EXCLUDED));
    foreach ([['err_number' => 1, 'value' => []], ['err_number' => 0, 'value' => 1]] as $bad) {
        $failed = (new J([]))->addStep(static fn(mixed $v): int => E::COMPOSITE_LEAF,
            static fn(array $p, mixed $v): array => $bad);
        raises(UnexpectedValueException::class, static fn() => $failed->normalize());
        raises(LogicException::class, static fn() => $failed->normalize());
    }
};

$tests['partial composite results, root record anywhere, input isolation'] = static function (): void {
    $input = ['add' => [['a' => '123', 'b' => 'bad', 'omit' => null]]];
    foreach ([false, true] as $reverse) {
        $result = (new J($input))->addStep(
            static fn(array $p, mixed $v): int => count($p) === 2 ? E::COMPOSITE_LEAF : E::BRANCH_OR_PETIOLE,
            static function (array $p, mixed $v) use ($reverse): array {
                $records = [
                    ['path' => [], 'err_number' => 0, 'value' => ['a' => 123, 'b' => 'bad'], 'error_value' => null],
                    ['path' => ['b'], 'err_number' => A::ERR_FORMAT, 'value' => 'reported', 'error_value' => 'reported'],
                ];
                return $reverse ? array_reverse($records) : $records;
            })->normalize();
        same(['add' => [['a' => 123, 'b' => 'bad']]], $result['data']);
        same(errors([['add', 0, 'b'], A::ERR_FORMAT, 'reported']), $result['extra']);
        same(J::STATUS_ERROR, $result['status']);
    }
    same('123', $input['add'][0]['a']);
};
$tests['parent and child errors in either order, postprocessing value, blocked later step'] = static function (): void {
    foreach ([false, true] as $reverse) {
        $row = ['a' => 123, 'b' => 'bad'];
        $records = [
            ['path' => [], 'err_number' => 1001, 'value' => $row, 'error_value' => $row],
            ['path' => ['b'], 'err_number' => A::ERR_FORMAT, 'value' => 'bad', 'error_value' => 'bad'],
        ];
        if ($reverse) { $records = array_reverse($records); }
        $result = (new J(['a' => '123', 'b' => 'bad']))
            ->addStep(static fn(mixed $v): int => E::COMPOSITE_LEAF,
                static fn(array $p, mixed $v): array => $records)
            ->addStep(static fn(mixed $v): int => E::COMPOSITE_LEAF,
                static function (array $p, mixed $v): array { throw new RuntimeException('Failed root processed'); })
            ->normalize();
        same($row, $result['data']);
        same(['path' => [], 'err_number' => 1001, 'value' => $row], $result['extra'][serialize([])]);
        same(['path' => ['b'], 'err_number' => A::ERR_FORMAT, 'value' => 'bad'], $result['extra'][serialize(['b'])]);
    }
};
$tests['child-only composite errors allow valid siblings in later steps'] = static function (): void {
    $result = (new J(['a' => '12', 'b' => 'bad']))
        ->addStep(static fn(mixed $v): int => E::COMPOSITE_LEAF,
            static fn(array $p, mixed $v): array => [
                ['path' => [], 'err_number' => 0, 'value' => $v, 'error_value' => null],
                ['path' => ['b'], 'err_number' => A::ERR_FORMAT, 'value' => 'bad', 'error_value' => 'bad'],
            ])
        ->addStep(static fn(mixed $v): array|int => is_array($v) ? E::BRANCH_OR_PETIOLE : ['type' => 'int'])
        ->normalize();
    same(['a' => 12, 'b' => 'bad'], $result['data']);
};
$tests['malformed result contracts rejected and instance locked'] = static function (): void {
    $root = ['path' => [], 'err_number' => 0, 'value' => [], 'error_value' => null];
    $child = ['path' => ['x'], 'err_number' => 1000, 'value' => null, 'error_value' => null];
    $badResults = [
        [], ['err_number' => 0, 'value' => []], [$child], [$root, $root],
        [1 => $root], [$root, $child, $child],
        [$root, ['path' => ['x'], 'err_number' => 0, 'value' => null, 'error_value' => null]],
        [['path' => [], 'err_number' => 999, 'value' => [], 'error_value' => []]],
        [['path' => [], 'err_number' => -1, 'value' => [], 'error_value' => []]],
        [['path' => [], 'err_number' => 0, 'value' => 7, 'error_value' => null]],
        [$root, ['path' => [null], 'err_number' => 1000, 'value' => null, 'error_value' => null]],
        [$root, ['path' => [1 => 'x'], 'err_number' => 1000, 'value' => null, 'error_value' => null]],
        [$root, ['path' => ['1'], 'err_number' => 1000, 'value' => null, 'error_value' => null],
            ['path' => [1], 'err_number' => 1000, 'value' => null, 'error_value' => null]],
    ];
    foreach ($badResults as $bad) {
        $normalizer = (new J([]))->addStep(static fn(mixed $v): int => E::COMPOSITE_LEAF,
            static fn(array $p, mixed $v): array => $bad);
        raises(UnexpectedValueException::class, static fn() => $normalizer->normalize());
        raises(LogicException::class, static fn() => $normalizer->normalize());
    }
};
$tests['unsafe error values are rejected even on failed records'] = static function (): void {
    foreach ([new stdClass(), INF] as $unsafe) {
        raises(InvalidArgumentException::class, static fn() => (new J([]))
            ->addStep(static fn(mixed $v): int => E::COMPOSITE_LEAF,
                static fn(array $p, mixed $v): array => [
                    ['path' => [], 'err_number' => 1000, 'value' => ['bad' => $unsafe], 'error_value' => ['bad' => $unsafe]],
                ])->normalize());
    }
    $external = [];
    raises(UnexpectedValueException::class, static function () use (&$external): void {
        (new J([]))
        ->addStep(static fn(mixed $v): int => E::COMPOSITE_LEAF,
            static function (array $p, mixed $v) use (&$external): array {
                $record = ['path' => [], 'err_number' => 0, 'value' => &$external, 'error_value' => null];
                return [$record];
            })->normalize();
    });
};

$tests['nested parent and child errors with custom petiole'] = static function (): void {
    E::setPetioleName('_own');
    try {
        foreach ([false, true] as $reverse) {
            $records = [
                ['path' => ['group', 'bad'], 'err_number' => 1002, 'value' => 'bad', 'error_value' => 'bad'],
                ['path' => ['group'], 'err_number' => 1001, 'value' => ['bad' => 'bad'], 'error_value' => ['bad' => 'bad']],
                ['path' => [], 'err_number' => 0, 'value' => ['group' => ['bad' => 'bad'], 'ok' => '12'], 'error_value' => null],
            ];
            if ($reverse) { $records = array_reverse($records); }
            $result = (new J([]))->addStep(static fn(mixed $v): int => E::COMPOSITE_LEAF,
                static fn(array $p, mixed $v): array => $records)
                ->addStep(static fn(mixed $v): array|int => is_array($v) ? E::BRANCH_OR_PETIOLE : ['type' => 'int'])
                ->normalize();
            same(12, $result['data']['ok']);
            same(['path' => ['group'], 'err_number' => 1001, 'value' => ['bad' => 'bad']], $result['extra'][serialize(['group'])]);
            same(['path' => ['group', 'bad'], 'err_number' => 1002, 'value' => 'bad'], $result['extra'][serialize(['group', 'bad'])]);
        }
    } finally {
        E::setPetioleName(E::PETIOLE_DEFAULT_NAME);
    }
};
$tests['depth boundary allows flat error records and rejects excessive paths'] = static function (): void {
    $path = array_fill(0, J::MAX_DEPTH, 'x');
    $records = [
        ['path' => [], 'err_number' => 0, 'value' => [], 'error_value' => null],
        ['path' => $path, 'err_number' => 1000, 'value' => 'bad', 'error_value' => 'bad'],
    ];
    $result = (new J([]))->addStep(static fn(mixed $v): int => E::COMPOSITE_LEAF,
        static fn(array $p, mixed $v): array => $records)->normalize();
    same(J::STATUS_ERROR, $result['status']);
    $records[1]['path'][] = 'too-deep';
    raises(InvalidArgumentException::class, static fn() => (new J([]))
        ->addStep(static fn(mixed $v): int => E::COMPOSITE_LEAF,
            static fn(array $p, mixed $v): array => $records)->normalize());
};
$tests['error metadata names remain valid data paths, with parent errors in either order'] = static function (): void {
    $row = ['err_number' => 'bad', 'value' => null];
    foreach ([false, true] as $reverse) {
        $records = [
            ['path' => [], 'err_number' => 1000, 'value' => $row, 'error_value' => $row],
            ['path' => ['err_number'], 'err_number' => A::ERR_FORMAT, 'value' => 'bad', 'error_value' => 'bad'],
            ['path' => ['value'], 'err_number' => A::ERR_NULL_NOT_ALLOWED, 'value' => null, 'error_value' => null],
        ];
        if ($reverse) { $records = array_reverse($records); }
        $result = (new J($row))
            ->addStep(static fn(mixed $v): int => E::COMPOSITE_LEAF,
                static fn(array $p, mixed $v): array => $records)
            ->addStep(static fn(mixed $v): int => E::COMPOSITE_LEAF,
                static function (array $p, mixed $v): array { throw new RuntimeException('Failed root processed'); })
            ->normalize();
        same(['path' => [], 'err_number' => 1000, 'value' => $row], $result['extra'][serialize([])]);
        same(['path' => ['err_number'], 'err_number' => A::ERR_FORMAT, 'value' => 'bad'], $result['extra'][serialize(['err_number'])]);
        same(['path' => ['value'], 'err_number' => A::ERR_NULL_NOT_ALLOWED, 'value' => null], $result['extra'][serialize(['value'])]);
        same($row, $result['data']);
    }
};
$tests['atomic null error retains named value and protects later steps'] = static function (): void {
    $normalizer = (new J(['value' => null, 'ok' => '12']))
        ->addStep(static fn(mixed $v): array|int => is_array($v) ? E::BRANCH_OR_PETIOLE : ['type' => 'int'])
        ->addStep(static fn(mixed $v): array|int => is_array($v) ? E::BRANCH_OR_PETIOLE : ['type' => 'int']);
    $result = $normalizer->normalize();
    same(['value' => null, 'ok' => 12], $result['data']);
    same(errors([['value'], A::ERR_NULL_NOT_ALLOWED, null]), $result['extra']);
    same($result, $normalizer->normalize());
};
$tests['reserved petiole error aliases rejected before commit'] = static function (): void {
    raises(UnexpectedValueException::class, static fn() => (new J([]))
        ->addStep(static fn(mixed $v): int => E::COMPOSITE_LEAF,
            static fn(array $p, mixed $v): array => [
                ['path' => [], 'err_number' => 1000, 'value' => [], 'error_value' => []],
                ['path' => [E::PETIOLE_DEFAULT_NAME], 'err_number' => 1001, 'value' => null, 'error_value' => null],
            ])->normalize());
};

$tests['composite separates committed data from reported errors including null'] = static function (): void {
    $row = ['a' => 12, 'b' => 3];
    $result = (new J(['a' => '12', 'b' => '3']))
        ->addStep(static fn(mixed $v): int => E::COMPOSITE_LEAF,
            static fn(array $p, mixed $v): array => [
                ['path' => [], 'err_number' => 2000, 'value' => $row, 'error_value' => ['a', 'b']],
                ['path' => ['a'], 'err_number' => 2001, 'value' => 999, 'error_value' => null],
            ])->normalize();
    same($row, $result['data']);
    same(errors([[], 2000, ['a', 'b']], [['a'], 2001, null]), $result['extra']);
};
$tests['four keys required and successful error value ignored'] = static function (): void {
    raises(UnexpectedValueException::class, static fn() => (new J([]))
        ->addStep(static fn(mixed $v): int => E::COMPOSITE_LEAF,
            static fn(array $p, mixed $v): array => [
                ['path' => [], 'err_number' => 0, 'value' => []],
            ])->normalize());
    $result = (new J([]))->addStep(static fn(mixed $v): int => E::COMPOSITE_LEAF,
        static fn(array $p, mixed $v): array => [
            ['path' => [], 'err_number' => 0, 'value' => ['n' => 1], 'error_value' => 'ignored'],
        ])->normalize();
    same(['status' => J::STATUS_OK, 'data' => ['n' => 1], 'extra' => []], $result);
};
$tests['error_value safety checked independently of data'] = static function (): void {
    foreach ([new stdClass(), INF] as $unsafe) {
        raises(InvalidArgumentException::class, static fn() => (new J([]))
            ->addStep(static fn(mixed $v): int => E::COMPOSITE_LEAF,
                static fn(array $p, mixed $v): array => [
                    ['path' => [], 'err_number' => 2000, 'value' => [], 'error_value' => $unsafe],
                ])->normalize());
    }
    $external = 'bad';
    raises(UnexpectedValueException::class, static function () use (&$external): void {
        (new J([]))->addStep(static fn(mixed $v): int => E::COMPOSITE_LEAF,
            static function (array $p, mixed $v) use (&$external): array {
                return [['path' => [], 'err_number' => 2000, 'value' => [], 'error_value' => &$external]];
            })->normalize();
    });
    $tooDeep = null;
    for ($i = 0; $i <= J::MAX_DEPTH; $i++) { $tooDeep = [$tooDeep]; }
    raises(InvalidArgumentException::class, static fn() => (new J([]))
        ->addStep(static fn(mixed $v): int => E::COMPOSITE_LEAF,
            static fn(array $p, mixed $v): array => [
                ['path' => [], 'err_number' => 2000, 'value' => [], 'error_value' => $tooDeep],
            ])->normalize());
};

$tests['nested candidate mutation before exception does not commit'] = static function (): void {
    $input = ['row' => ['x' => '1']];
    $normalizer = (new J($input))
        ->addStep(static fn(mixed $v): array|int => is_array($v)
            ? E::BRANCH_OR_PETIOLE : ['type' => 'int'])
        ->addStep(static fn(mixed $v): int => E::COMPOSITE_LEAF,
            static function (array $p, mixed $v): array {
                $v['row']['x'] = 99;
                throw new RuntimeException('Stop before commit');
            });
    raises(RuntimeException::class, static fn() => $normalizer->normalize());
    $data = new ReflectionProperty(J::class, 'arrData');
    $data->setAccessible(true);
    same(['row' => ['x' => 1]], $data->getValue($normalizer));
    same(['row' => ['x' => '1']], $input);
};
$tests['array error snapshot, returned data and cached response remain independent'] = static function (): void {
    $input = ['bad' => ['nested' => ['x' => 1]], 'ok' => '2'];
    $normalizer = (new J($input))->addStep(
        static fn(array $p, mixed $v): array|int => $p === []
            ? E::BRANCH_OR_PETIOLE : ['type' => 'int']);
    $result = $normalizer->normalize();
    $expected = $result;
    same(A::ERR_TYPE, $result['extra'][serialize(['bad'])]['err_number']);
    same(2, $result['data']['ok']);
    $result['data']['bad']['nested']['x'] = 99;
    same(1, $result['extra'][serialize(['bad'])]['value']['nested']['x']);
    $result['extra'][serialize(['bad'])]['value']['nested']['x'] = 77;
    same(99, $result['data']['bad']['nested']['x']);
    same($expected, $normalizer->normalize());
    same(['bad' => ['nested' => ['x' => 1]], 'ok' => '2'], $input);
};

$tests['strict error-prefix index marks only ancestors and exact errors skip processing'] = static function (): void {
    $prefixes = null;
    $defined = [];
    $processed = [];
    $normalizer = (new J(['x1' => ['x2' => ['x3' => 'bad', 'sibling' => '2']]]))
        ->addStep(static fn(mixed $v): array|int => is_array($v) ? E::BRANCH_OR_PETIOLE : ['type' => 'int']);
    $normalizer->addStep(
        static function (array $p, mixed $v) use ($normalizer, &$prefixes, &$defined): int {
            $defined[] = $p;
            if ($p === []) {
                $index = new ReflectionProperty(J::class, 'arrHasDescendantError');
                $index->setAccessible(true);
                $prefixes = $index->getValue($normalizer);
            }
            return is_array($v) ? E::BRANCH_OR_PETIOLE : E::COMPOSITE_LEAF;
        },
        static function (array $p, mixed $v) use (&$processed): array {
            $processed[] = $p;
            return [['path' => [], 'err_number' => 0, 'value' => $v, 'error_value' => null]];
        });
    $result = $normalizer->normalize();
    same([serialize([]) => true, serialize(['x1']) => true, serialize(['x1', 'x2']) => true], $prefixes);
    same([[], ['x1'], ['x1', 'x2'], ['x1', 'x2', 'x3'], ['x1', 'x2', 'sibling']], $defined);
    same([['x1', 'x2', 'sibling']], $processed);
    same(errors([['x1', 'x2', 'x3'], A::ERR_FORMAT, 'bad']), $result['extra']);
};
$tests['logical error keys preserve PHP key types and root records'] = static function (): void {
    $result = (new J([]))->addStep(static fn(mixed $v): int => E::COMPOSITE_LEAF,
        static fn(array $p, mixed $v): array => [
            ['path' => [], 'err_number' => 1000, 'value' => [], 'error_value' => 'root'],
            ['path' => ['1'], 'err_number' => 1001, 'value' => null, 'error_value' => 'integer'],
            ['path' => ['01'], 'err_number' => 1002, 'value' => null, 'error_value' => 'string'],
        ])->normalize();
    same(errors([[], 1000, 'root'], [[1], 1001, 'integer'], [['01'], 1002, 'string']), $result['extra']);
};
$tests['three bottom-up steps process valid users but skip failed rows and root'] = static function (): void {
    $rowsProcessed = [];
    $result = (new J(['users' => [
        ['name' => ' tuan ', 'age' => 'abc'],
        ['name' => ' an ', 'age' => '25'],
    ]]))->addStep(static function (array $p, mixed $v): array|int {
        if (is_array($v)) { return E::BRANCH_OR_PETIOLE; }
        return end($p) === 'name' ? ['type' => 'string', 'trim' => true] : ['type' => 'int'];
    })->addStep(
        static fn(array $p, mixed $v): int => count($p) < 2 ? E::BRANCH_OR_PETIOLE : E::COMPOSITE_LEAF,
        static function (array $p, mixed $v) use (&$rowsProcessed): array {
            $rowsProcessed[] = $p;
            $v['checked'] = true;
            return [['path' => [], 'err_number' => 0, 'value' => $v, 'error_value' => null]];
        }
    )->addStep(static fn(mixed $v): int => E::COMPOSITE_LEAF,
        static function (array $p, mixed $v): array { throw new RuntimeException('Root with descendant error processed'); })
        ->normalize();
    same([['users', 1]], $rowsProcessed);
    same(['users' => [
        ['name' => 'tuan', 'age' => 'abc'],
        ['name' => 'an', 'age' => 25, 'checked' => true],
    ]], $result['data']);
    same(errors([['users', 0, 'age'], A::ERR_FORMAT, 'abc']), $result['extra']);
};

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
foreach ($tests as $name => $run) {
    $run();
    echo 'PASS: ', $name, PHP_EOL;
}
restore_error_handler();
echo count($tests), ' scenarios passed.', PHP_EOL;
